<?php

namespace App\Modules\Pos\Livewire;

use App\Modules\Finance\Support\Rupiah;
use App\Modules\Pos\Actions\ReturnItem;
use App\Modules\Pos\Models\Merchant;
use App\Modules\Pos\Models\PosOrder;
use App\Modules\Pos\Models\PosShift;
use App\Modules\Wallet\Support\WalletException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Riwayat transaksi dengan retur per barang, dan rekap shift kasir.
 */
#[Layout('components.layouts.staff', ['active' => 'merchants'])]
#[Title('Transaksi')]
class OrderHistory extends Component
{
    use WithPagination;

    public Merchant $merchant;

    #[Url(as: 'tab', except: 'transaksi')]
    public string $tab = 'transaksi';

    #[Url(as: 'cari', except: '')]
    public string $search = '';

    #[Url(as: 'tanggal', except: '')]
    public string $date = '';

    #[Locked]
    public ?string $detail = null;

    #[Locked]
    public ?int $returnItem = null;

    public string $returnQty = '1';

    public bool $restock = true;

    public string $returnReason = '';

    public function mount(Merchant $merchant): void
    {
        abort_unless(Merchant::query()->accessibleBy(auth()->user())->whereKey($merchant->id)->exists(), 403);
        abort_unless(in_array($this->tab, ['transaksi', 'shift'], true), 404);
        $this->merchant = $merchant;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingDate(): void
    {
        $this->resetPage();
    }

    public function selectTab(string $tab): void
    {
        abort_unless(in_array($tab, ['transaksi', 'shift'], true), 404);
        $this->tab = $tab;
        $this->resetPage();
    }

    public function open(string $uuid): void
    {
        $this->detail = PosOrder::query()->where('merchant_id', $this->merchant->id)->where('uuid', $uuid)->firstOrFail()->uuid;
        $this->reset('returnItem');
        $this->dispatch('open-modal', 'detail-transaksi');
    }

    public function startReturn(int $itemId): void
    {
        $this->returnItem = $itemId;
        $this->returnQty = '1';
        $this->restock = true;
        $this->returnReason = '';
        $this->resetErrorBag();
    }

    public function cancelReturn(): void
    {
        $this->reset('returnItem');
    }

    public function confirmReturn(ReturnItem $returns): void
    {
        $order = PosOrder::query()->where('merchant_id', $this->merchant->id)->where('uuid', $this->detail)->firstOrFail();
        $item = $order->items()->whereKey($this->returnItem)->firstOrFail();
        $this->validate(['returnQty' => 'required|integer|min:1', 'returnReason' => 'required|string|max:200'], [], ['returnQty' => 'jumlah', 'returnReason' => 'alasan']);

        try {
            $return = $returns->handle($order, $item, (int) $this->returnQty, $this->restock, $this->returnReason, auth()->user());
        } catch (WalletException $exception) {
            $this->addError('returnQty', $exception->getMessage());

            return;
        }

        $this->reset('returnItem');
        $this->dispatch('toast', message: $order->isCash() ? 'Retur tercatat. Kembalikan uang tunai '.Rupiah::format($return->amount).' ke pembeli.' : 'Retur tercatat. Saldo santri sudah kembali.', tone: 'success');
    }

    public function render(): View
    {
        $zone = config('sanad.display_timezone');
        $term = trim($this->search);
        $orders = null;
        $shifts = null;

        if ($this->tab === 'transaksi') {
            $orders = PosOrder::query()->with(['student:id,name', 'cashier:id,name'])->where('merchant_id', $this->merchant->id)
                ->when($this->date !== '' && strtotime($this->date) !== false, function ($query) use ($zone) {
                    $start = now($zone)->parse($this->date, $zone)->startOfDay()->utc();
                    $query->where('created_at', '>=', $start)->where('created_at', '<', $start->copy()->addDay());
                })
                ->when($term !== '', fn ($query) => $query->where(fn ($q) => $q->where('receipt_no', 'ilike', '%'.addcslashes($term, '%_').'%')
                    ->orWhere('customer_name', 'ilike', '%'.addcslashes($term, '%_').'%')
                    ->orWhereHas('student', fn ($s) => $s->where('name', 'ilike', '%'.addcslashes($term, '%_').'%'))))
                ->latest('id')->paginate(25);
        } else {
            $shifts = PosShift::query()->with('cashier:id,name')->where('merchant_id', $this->merchant->id)->latest('opened_at')->paginate(25);
        }

        return view('pos.orders', [
            'orders' => $orders,
            'shifts' => $shifts,
            'canReturn' => auth()->user()->can('pos.void'),
            'order' => $this->detail ? PosOrder::query()->with(['items', 'returns.item:id,name', 'student:id,name', 'cashier:id,name'])->where('merchant_id', $this->merchant->id)->where('uuid', $this->detail)->first() : null,
        ]);
    }
}
