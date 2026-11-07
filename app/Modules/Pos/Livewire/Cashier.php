<?php

namespace App\Modules\Pos\Livewire;

use App\Modules\Finance\Actions\FinanceException;
use App\Modules\Finance\Support\Rupiah;
use App\Modules\Pos\Actions\CheckoutOrder;
use App\Modules\Pos\Actions\VoidOrder;
use App\Modules\Pos\Models\Merchant;
use App\Modules\Pos\Models\MerchantProduct;
use App\Modules\Pos\Models\PosOrder;
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

    /** @var array{ok: bool, message: string, name?: string}|null */
    #[Locked]
    public ?array $result = null;

    public string $voidReason = '';

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
            $this->result = ['ok' => true, 'message' => 'Sisa saldo '.Rupiah::format((int) $result->balance), 'name' => $result->order->student->name];
            $this->cart = [];
            $this->reset('checkoutKey', 'credential', 'pinMessage');

            return;
        }

        $this->result = ['ok' => false, 'message' => $result->message];
        $this->reset('credential', 'pinMessage');
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
        $lines = collect($this->cart)->map(fn (int $qty, string $uuid) => isset($inCart[$uuid]) ? ['product' => $inCart[$uuid], 'qty' => $qty, 'amount' => $inCart[$uuid]->price * $qty] : null)->filter();

        return view('pos.cashier', [
            'merchants' => $this->merchants(),
            'merchant' => $merchant,
            'products' => $merchant
                ? MerchantProduct::query()->where('merchant_id', $merchant->id)->where('is_active', true)
                    ->when($term !== '', fn ($query) => $query->where('name', 'ilike', '%'.addcslashes($term, '%_').'%'))
                    ->orderBy('name')->limit(60)->get()
                : collect(),
            'lines' => $lines,
            'total' => $lines->sum('amount'),
            'recent' => $merchant
                ? PosOrder::query()->with('student:id,name')->where('merchant_id', $merchant->id)->where('created_at', '>=', now(config('sanad.display_timezone'))->startOfDay()->utc())->latest('id')->limit(8)->get()
                : collect(),
        ]);
    }
}
