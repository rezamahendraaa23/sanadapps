<?php

namespace App\Modules\Pos\Livewire;

use App\Modules\Finance\Actions\FinanceException;
use App\Modules\Finance\Support\Rupiah;
use App\Modules\Pos\Actions\CheckoutOrder;
use App\Modules\Pos\Actions\ManageShift;
use App\Modules\Pos\Actions\VoidOrder;
use App\Modules\Pos\Models\Merchant;
use App\Modules\Pos\Models\MerchantProduct;
use App\Modules\Pos\Models\PosOrder;
use App\Modules\Pos\Models\PosPromotion;
use App\Modules\Pos\Models\ProductCategory;
use App\Modules\Wallet\Support\WalletException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.staff', ['active' => 'pos'])]
#[Title('Kasir')]
class Cashier extends Component
{
    #[Url(as: 'merchant', except: '')]
    public string $merchantUuid = '';

    public string $search = '';

    #[Url(as: 'kategori', except: '')]
    public string $category = '';

    public string $barcode = '';

    public string $scan = '';

    /** @var array<string, int> uuid produk ke jumlah */
    public array $cart = [];

    #[Locked]
    public string $stage = 'cart';

    /** Kunci yang sama selama satu keranjang, jadi tap ganda atau kirim ulang tidak memotong dua kali. */
    #[Locked]
    public ?string $checkoutKey = null;

    #[Locked]
    public ?string $credential = null;

    #[Locked]
    public ?string $pinMessage = null;

    /** @var array{ok: bool, message: string, name?: string, receipt?: string, change?: int}|null */
    #[Locked]
    public ?array $result = null;

    public string $voidReason = '';

    public string $openingCash = '';

    public string $countedCash = '';

    public string $shiftNote = '';

    public string $cashPaid = '';

    public string $customerName = '';

    #[Locked]
    public ?string $voidTarget = null;

    public function mount(): void
    {
        $this->merchantUuid = $this->merchants()->firstWhere('uuid', $this->merchantUuid)?->uuid ?? $this->merchants()->first()?->uuid ?? '';
    }

    private function merchants()
    {
        return Merchant::query()->accessibleBy(auth()->user())->where('is_active', true)->orderBy('name')->get();
    }

    private function merchant(): ?Merchant
    {
        return ! Str::isUuid($this->merchantUuid) ? null : Merchant::query()->accessibleBy(auth()->user())->where('uuid', $this->merchantUuid)->first();
    }

    public function updatedMerchantUuid(): void
    {
        $this->cartChanged();
        $this->cart = [];
        $this->category = '';
    }

    public function pickCategory(string $uuid): void
    {
        $this->category = $this->category === $uuid ? '' : $uuid;
    }

    private function cartChanged(): void
    {
        $this->checkoutKey = null;
    }

    public function add(string $uuid): void
    {
        $product = MerchantProduct::query()->where('merchant_id', $this->merchant()?->id)->where('is_active', true)->where('uuid', $uuid)->first();

        if ($product === null || $product->isOutOfStock()) {
            return;
        }

        if ($product->stock !== null && ($this->cart[$uuid] ?? 0) >= $product->stock) {
            $this->dispatch('toast', message: "Stok {$product->name} tinggal {$product->stock}.", tone: 'warning');

            return;
        }

        $this->cart[$uuid] = ($this->cart[$uuid] ?? 0) + 1;
        $this->cartChanged();
    }

    public function decrease(string $uuid): void
    {
        if (! isset($this->cart[$uuid])) {
            return;
        }

        if (--$this->cart[$uuid] < 1) {
            unset($this->cart[$uuid]);
        }

        $this->cartChanged();
    }

    public function removeAll(): void
    {
        $this->cart = [];
        $this->cartChanged();
    }

    public function addBarcode(): void
    {
        $this->resetErrorBag('barcode');
        $code = trim($this->barcode);
        $this->barcode = '';

        if ($code === '') {
            return;
        }

        $product = MerchantProduct::query()->where('merchant_id', $this->merchant()?->id)->where('is_active', true)->where('barcode', $code)->first();

        if ($product === null) {
            $this->addError('barcode', "Barcode {$code} belum terdaftar.");

            return;
        }

        $this->add($product->uuid);
    }

    public function startPayment(): void
    {
        if ($this->cart === []) {
            return;
        }

        $this->checkoutKey ??= (string) Str::uuid();
        $this->reset('scan', 'credential', 'pinMessage', 'result');
        $this->stage = 'tap';
    }

    public function submitScan(CheckoutOrder $checkout): void
    {
        $value = trim($this->scan);
        $this->scan = '';

        if ($value === '' || $this->stage !== 'tap') {
            return;
        }

        $this->credential = $value;
        $this->attempt($checkout, null);
    }

    public function submitPin(string $pin, CheckoutOrder $checkout): void
    {
        if ($this->stage !== 'pin' || $this->credential === null) {
            return;
        }

        $this->attempt($checkout, $pin);
    }

    private function attempt(CheckoutOrder $checkout, ?string $pin): void
    {
        $merchant = $this->merchant();
        abort_if($merchant === null, 403);

        $ids = MerchantProduct::query()->where('merchant_id', $merchant->id)->whereIn('uuid', array_keys($this->cart))->pluck('id', 'uuid');
        $lines = [];

        foreach ($this->cart as $uuid => $qty) {
            $lines[$ids[$uuid] ?? 0] = $qty;
        }

        $result = $checkout->handle($merchant, $lines, null, (string) $this->credential, $pin, (string) $this->checkoutKey, auth()->id());

        if ($result->needsPin()) {
            $this->stage = 'pin';
            $this->pinMessage = $result->code === 'pin_wrong' ? $result->message : null;

            return;
        }

        $this->stage = 'result';

        if ($result->ok) {
            $this->result = ['ok' => true, 'message' => 'Sisa saldo '.Rupiah::format((int) $result->balance), 'name' => $result->order->student->name, 'receipt' => $result->order->uuid];
            $this->cart = [];
            $this->reset('checkoutKey', 'credential', 'pinMessage');

            return;
        }

        $this->result = ['ok' => false, 'message' => $result->message];
        $this->reset('credential', 'pinMessage');
    }

    public function openShift(ManageShift $shifts): void
    {
        $merchant = $this->merchant();
        abort_if($merchant === null, 403);
        $this->resetErrorBag();

        try {
            $shifts->open($merchant, auth()->user(), (int) preg_replace('/\D/', '', $this->openingCash));
        } catch (WalletException $exception) {
            $this->addError('openingCash', $exception->getMessage());

            return;
        }

        $this->reset('openingCash');
        $this->dispatch('close-modal', 'buka-shift');
        $this->dispatch('toast', message: 'Shift dibuka.', tone: 'success');
    }

    public function closeShift(ManageShift $shifts): void
    {
        $merchant = $this->merchant();
        $shift = $merchant ? ManageShift::current($merchant, auth()->user()) : null;
        abort_if($shift === null, 404);
        $this->resetErrorBag();

        if (preg_replace('/\D/', '', $this->countedCash) === '') {
            $this->addError('countedCash', 'Isi jumlah uang di laci.');

            return;
        }

        $closed = $shifts->close($shift, auth()->user(), (int) preg_replace('/\D/', '', $this->countedCash), $this->shiftNote);
        $this->reset('countedCash', 'shiftNote');
        $this->dispatch('close-modal', 'tutup-shift');
        $difference = (int) $closed->difference();
        $this->dispatch('toast', message: $difference === 0 ? 'Shift ditutup. Uang cocok.' : 'Shift ditutup. Selisih '.($difference > 0 ? 'lebih ' : 'kurang ').Rupiah::format(abs($difference)).'.', tone: $difference === 0 ? 'success' : 'warning');
    }

    public function startCash(): void
    {
        if ($this->cart === [] || ! in_array('cash', $this->merchant()?->payment_modes ?? [], true)) {
            return;
        }

        $this->checkoutKey ??= (string) Str::uuid();
        $this->reset('cashPaid', 'customerName');
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'bayar-tunai');
    }

    public function payCash(CheckoutOrder $checkout): void
    {
        $merchant = $this->merchant();
        abort_if($merchant === null, 403);
        $this->resetErrorBag();
        $this->validate(['customerName' => 'nullable|string|max:100'], [], ['customerName' => 'nama pembeli']);

        $ids = MerchantProduct::query()->where('merchant_id', $merchant->id)->whereIn('uuid', array_keys($this->cart))->pluck('id', 'uuid');
        $lines = [];
        foreach ($this->cart as $uuid => $qty) {
            $lines[$ids[$uuid] ?? 0] = $qty;
        }

        try {
            $order = $checkout->cash($merchant, $lines, (int) preg_replace('/\D/', '', $this->cashPaid), $this->customerName, (string) ($this->checkoutKey ??= (string) Str::uuid()), auth()->id());
        } catch (WalletException $exception) {
            $this->addError('cashPaid', $exception->getMessage());

            return;
        }

        $this->dispatch('close-modal', 'bayar-tunai');
        $this->stage = 'result';
        $this->result = ['ok' => true, 'message' => 'Kembalian '.Rupiah::format((int) $order->change_amount), 'name' => $order->buyerName(), 'receipt' => $order->uuid, 'change' => (int) $order->change_amount];
        $this->cart = [];
        $this->reset('checkoutKey', 'credential', 'pinMessage', 'cashPaid', 'customerName');
    }

    public function backToTap(): void
    {
        $this->reset('result', 'credential', 'pinMessage', 'scan');
        $this->stage = 'tap';
    }

    public function backToCart(): void
    {
        $this->reset('result', 'credential', 'pinMessage', 'scan');
        $this->stage = 'cart';
    }

    public function askVoid(string $uuid): void
    {
        $this->voidTarget = $uuid;
        $this->reset('voidReason');
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'batal-transaksi');
    }

    public function confirmVoid(VoidOrder $action): void
    {
        $this->resetErrorBag();
        $order = PosOrder::query()->where('merchant_id', $this->merchant()?->id)->where('uuid', $this->voidTarget)->firstOrFail();

        try {
            $action->handle($order, $this->voidReason, auth()->user());
        } catch (FinanceException $exception) {
            $this->addError('voidReason', $exception->getMessage());

            return;
        }

        $this->dispatch('close-modal', 'batal-transaksi');
        $this->dispatch('toast', message: 'Transaksi dibatalkan, saldo sudah kembali.', tone: 'success');
    }

    public function render(): View
    {
        $merchant = $this->merchant();
        $term = trim($this->search);
        $inCart = $merchant && $this->cart !== [] ? MerchantProduct::query()->where('merchant_id', $merchant->id)->whereIn('uuid', array_keys($this->cart))->get()->keyBy('uuid') : collect();
        $promotions = $merchant ? PosPromotion::query()->where('merchant_id', $merchant->id)->runningOn(now(config('sanad.display_timezone'))->toDateString())->get() : collect();
        $lines = collect($this->cart)->map(function (int $qty, string $uuid) use ($inCart, $promotions) {
            if (! isset($inCart[$uuid])) {
                return null;
            }
            [$discount, $promotionId] = CheckoutOrder::bestPromotion($promotions, $inCart[$uuid], $qty);

            return ['product' => $inCart[$uuid], 'qty' => $qty, 'discount' => $discount, 'promo' => $promotionId ? $promotions->firstWhere('id', $promotionId)?->name : null, 'amount' => $inCart[$uuid]->price * $qty - $discount];
        })->filter();
        $shift = $merchant ? ManageShift::current($merchant, auth()->user()) : null;

        $categories = $merchant
            ? ProductCategory::query()->whereIn('id', MerchantProduct::query()->where('merchant_id', $merchant->id)->where('is_active', true)->whereNotNull('category_id')->select('category_id'))->orderBy('sort_order')->orderBy('name')->get()
            : collect();
        $categoryId = $categories->firstWhere('uuid', $this->category)?->id;
        $today = $merchant
            ? PosOrder::query()->where('merchant_id', $merchant->id)->where('status', '!=', 'void')->where('created_at', '>=', now(config('sanad.display_timezone'))->startOfDay()->utc())
            : null;

        return view('pos.cashier', [
            'merchants' => $this->merchants(),
            'merchant' => $merchant,
            'categories' => $categories,
            'products' => $merchant
                ? MerchantProduct::query()->where('merchant_id', $merchant->id)->where('is_active', true)
                    ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
                    ->when($term !== '', fn ($query) => $query->where('name', 'ilike', '%'.addcslashes($term, '%_').'%'))
                    ->orderBy('name')->limit(60)->get()
                : collect(),
            'lines' => $lines,
            'total' => $lines->sum('amount'),
            'discount' => $lines->sum('discount'),
            'promotions' => $promotions,
            'shift' => $shift,
            'shiftTotals' => $shift ? ManageShift::totals($shift) : null,
            'acceptsCash' => in_array('cash', $merchant?->payment_modes ?? [], true),
            'itemCount' => $lines->sum('qty'),
            'todayCount' => $today ? (clone $today)->count() : 0,
            'todayTotal' => $today ? (int) (clone $today)->sum('total') : 0,
            'recent' => $merchant
                ? PosOrder::query()->with('student:id,name')->where('merchant_id', $merchant->id)->where('created_at', '>=', now(config('sanad.display_timezone'))->startOfDay()->utc())->latest('id')->limit(8)->get()
                : collect(),
        ]);
    }
}
