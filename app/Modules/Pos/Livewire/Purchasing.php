<?php

namespace App\Modules\Pos\Livewire;

use App\Modules\Pos\Actions\ManagePurchase;
use App\Modules\Pos\Models\Merchant;
use App\Modules\Pos\Models\MerchantProduct;
use App\Modules\Pos\Models\Purchase;
use App\Modules\Pos\Models\PurchaseItem;
use App\Modules\Pos\Models\Supplier;
use App\Modules\Wallet\Support\WalletException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Pembelian dari pemasok, utang, daftar perlu belanja, dan barang yang segera kedaluwarsa.
 */
#[Layout('components.layouts.staff', ['active' => 'merchants'])]
#[Title('Pembelian')]
class Purchasing extends Component
{
    use WithPagination;

    public const EXPIRY_DAYS = 30;

    public Merchant $merchant;

    #[Url(as: 'tab', except: 'pembelian')]
    public string $tab = 'pembelian';

    public string $supplier_id = '';

    public string $purchased_on = '';

    public string $due_on = '';

    public string $paid_now = '';

    public string $note = '';

    /** @var list<array{product: string, qty: string, unit_cost: string, expires_on: string}> */
    public array $rows = [];

    public string $supplier_name = '';

    public string $supplier_phone = '';

    #[Locked]
    public ?string $paying = null;

    public string $pay_amount = '';

    public string $pay_method = 'cash';

    public function mount(Merchant $merchant): void
    {
        abort_unless(Merchant::query()->accessibleBy(auth()->user())->whereKey($merchant->id)->exists(), 403);
        $this->merchant = $merchant;
        abort_unless(in_array($this->tab, ['pembelian', 'pemasok', 'belanja', 'kedaluwarsa'], true), 404);
    }

    private function manage(): void
    {
        abort_unless($this->merchant->canBeManagedBy(auth()->user()), 403);
    }

    public function selectTab(string $tab): void
    {
        abort_unless(in_array($tab, ['pembelian', 'pemasok', 'belanja', 'kedaluwarsa'], true), 404);
        $this->tab = $tab;
        $this->resetPage();
    }

    /**
     * @param  list<string>  $productUuids  Isi awal, misalnya dari daftar perlu belanja.
     */
    public function newPurchase(array $productUuids = []): void
    {
        $this->manage();
        $this->reset('supplier_id', 'due_on', 'paid_now', 'note');
        $this->purchased_on = now(config('sanad.display_timezone'))->toDateString();
        $products = MerchantProduct::query()->where('merchant_id', $this->merchant->id)->whereIn('uuid', $productUuids)->get();
        $this->rows = $products->isEmpty()
            ? [['product' => '', 'qty' => '', 'unit_cost' => '', 'expires_on' => '']]
            : $products->map(fn (MerchantProduct $product) => [
                'product' => $product->uuid,
                'qty' => (string) max(1, (int) $product->min_stock * 2 - (int) $product->stock),
                'unit_cost' => (string) ($product->cost_price ?? ''),
                'expires_on' => '',
            ])->values()->all();
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-pembelian');
    }

    public function restockAll(): void
    {
        $this->newPurchase($this->lowStock()->pluck('uuid')->all());
    }

    public function addRow(): void
    {
        $this->rows[] = ['product' => '', 'qty' => '', 'unit_cost' => '', 'expires_on' => ''];
    }

    public function removeRow(int $index): void
    {
        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
    }

    public function savePurchase(ManagePurchase $purchases): void
    {
        $this->manage();
        $this->resetErrorBag();
        $this->validate([
            'purchased_on' => 'required|date|before_or_equal:'.now(config('sanad.display_timezone'))->toDateString(),
            'due_on' => 'nullable|date|after_or_equal:purchased_on',
            'rows' => 'required|array|min:1',
            'rows.*.product' => 'required|uuid',
            'rows.*.qty' => 'required|integer|min:1|max:100000',
            'rows.*.unit_cost' => 'required',
            'rows.*.expires_on' => 'nullable|date',
        ], [], ['purchased_on' => 'tanggal beli', 'due_on' => 'jatuh tempo', 'rows.*.product' => 'barang', 'rows.*.qty' => 'jumlah', 'rows.*.unit_cost' => 'harga beli']);

        $ids = MerchantProduct::query()->where('merchant_id', $this->merchant->id)->whereIn('uuid', array_column($this->rows, 'product'))->pluck('id', 'uuid');
        $lines = array_map(fn (array $row) => [
            'product_id' => (int) ($ids[$row['product']] ?? 0),
            'qty' => (int) $row['qty'],
            'unit_cost' => (int) preg_replace('/\D/', '', (string) $row['unit_cost']),
            'expires_on' => $row['expires_on'] ?: null,
        ], $this->rows);
        $supplierId = $this->supplier_id !== '' ? Supplier::query()->where('merchant_id', $this->merchant->id)->where('uuid', $this->supplier_id)->value('id') : null;

        try {
            $purchase = $purchases->create($this->merchant, $supplierId, $this->purchased_on, $lines, (int) preg_replace('/\D/', '', $this->paid_now), $this->due_on ?: null, $this->note, auth()->user());
        } catch (WalletException $exception) {
            $this->addError('rows', $exception->getMessage());

            return;
        }

        $this->dispatch('close-modal', 'form-pembelian');
        $this->dispatch('toast', message: 'Pembelian '.$purchase->purchase_no.' tersimpan. Stok sudah bertambah.', tone: 'success');
        $this->tab = 'pembelian';
    }

    public function askPay(string $uuid): void
    {
        $this->manage();
        $purchase = Purchase::query()->where('merchant_id', $this->merchant->id)->where('uuid', $uuid)->firstOrFail();
        $this->paying = $purchase->uuid;
        $this->pay_amount = (string) $purchase->outstanding();
        $this->pay_method = 'cash';
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'bayar-pemasok');
    }

    public function pay(ManagePurchase $purchases): void
    {
        $this->manage();
        $this->validate(['pay_method' => 'required|in:cash,transfer']);
        $purchase = Purchase::query()->where('merchant_id', $this->merchant->id)->where('uuid', $this->paying)->firstOrFail();

        try {
            $purchases->pay($purchase, (int) preg_replace('/\D/', '', $this->pay_amount), $this->pay_method, now(config('sanad.display_timezone'))->toDateString(), null, auth()->user());
        } catch (WalletException $exception) {
            $this->addError('pay_amount', $exception->getMessage());

            return;
        }

        $this->dispatch('close-modal', 'bayar-pemasok');
        $this->dispatch('toast', message: $purchase->outstanding() === 0 ? 'Utang pembelian ini lunas.' : 'Pembayaran tercatat.', tone: 'success');
    }

    public function saveSupplier(): void
    {
        $this->manage();
        $this->validate(['supplier_name' => 'required|string|max:100', 'supplier_phone' => 'nullable|string|max:30'], [], ['supplier_name' => 'nama pemasok', 'supplier_phone' => 'nomor telepon']);
        Supplier::create(['merchant_id' => $this->merchant->id, 'name' => trim($this->supplier_name), 'phone' => $this->supplier_phone ?: null]);
        $this->reset('supplier_name', 'supplier_phone');
        $this->dispatch('toast', message: 'Pemasok tersimpan.', tone: 'success');
    }

    private function lowStock()
    {
        return MerchantProduct::query()->where('merchant_id', $this->merchant->id)->where('is_active', true)->whereNotNull('min_stock')->whereColumn('stock', '<=', 'min_stock')->orderBy('stock')->get();
    }

    /**
     * Perkiraan: kiriman dengan tanggal kedaluwarsa dekat, selama produknya masih punya stok.
     * Stok tidak dilacak per kiriman, jadi sisa ditampilkan sebagai paling banyak stok saat ini.
     */
    private function expiring()
    {
        $today = now(config('sanad.display_timezone'))->toDateString();

        return PurchaseItem::query()
            ->with('product:id,uuid,name,stock')
            ->whereHas('purchase', fn ($query) => $query->where('merchant_id', $this->merchant->id))
            ->whereHas('product', fn ($query) => $query->where('stock', '>', 0))
            ->whereNotNull('expires_on')
            ->where('expires_on', '<=', now(config('sanad.display_timezone'))->addDays(self::EXPIRY_DAYS)->toDateString())
            ->orderBy('expires_on')
            ->limit(100)
            ->get()
            ->map(fn (PurchaseItem $item) => [
                'item' => $item,
                'left' => min($item->qty, (int) $item->product->stock),
                'expired' => $item->expires_on->toDateString() < $today,
            ]);
    }

    public function render(): View
    {
        return view('pos.purchasing', [
            'canManage' => $this->merchant->canBeManagedBy(auth()->user()),
            'purchases' => $this->tab === 'pembelian'
                ? Purchase::query()->with('supplier:id,name')->where('merchant_id', $this->merchant->id)->latest('purchased_on')->latest('id')->paginate(20)
                : null,
            'debt' => (int) Purchase::query()->where('merchant_id', $this->merchant->id)->sum(DB::raw('total - paid_amount')),
            'suppliers' => Supplier::query()->where('merchant_id', $this->merchant->id)->where('is_active', true)->withSum('purchases as debt', DB::raw('total - paid_amount'))->orderBy('name')->get(),
            'lowStock' => $this->lowStock(),
            'expiring' => $this->tab === 'kedaluwarsa' ? $this->expiring() : collect(),
            'products' => MerchantProduct::query()->where('merchant_id', $this->merchant->id)->where('is_active', true)->orderBy('name')->pluck('name', 'uuid')->all(),
        ]);
    }
}
