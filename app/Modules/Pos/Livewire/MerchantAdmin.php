<?php

namespace App\Modules\Pos\Livewire;

use App\Modules\Core\Models\User;
use App\Modules\Pos\Models\Merchant;
use App\Modules\Pos\Models\MerchantProduct;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.staff', ['active' => 'merchants'])]
#[Title('Merchant')]
class MerchantAdmin extends Component
{
    use WithPagination;

    #[Url(as: 'merchant', except: '')]
    public string $selected = '';

    #[Locked]
    public ?int $editingMerchant = null;

    public string $name = '';

    public string $type = 'warung';

    public string $location = '';

    public string $commission_percent = '0';

    public string $pin_threshold = '';

    public string $pic_user_id = '';

    /** @var list<string> */
    public array $cashiers = [];

    public bool $is_active = true;

    #[Locked]
    public ?int $editingProduct = null;

    public string $product_name = '';

    public string $product_price = '';

    public string $product_barcode = '';

    public bool $product_active = true;

    public function createMerchant(): void
    {
        $this->reset('editingMerchant', 'name', 'type', 'location', 'commission_percent', 'pin_threshold', 'pic_user_id', 'cashiers', 'is_active');
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-merchant');
    }

    public function editMerchant(string $uuid): void
    {
        $merchant = Merchant::query()->with('cashiers:id')->where('uuid', $uuid)->firstOrFail();

        $this->editingMerchant = $merchant->id;
        $this->fill([
            'name' => $merchant->name,
            'type' => $merchant->type,
            'location' => (string) $merchant->location,
            'commission_percent' => (string) (float) $merchant->commission_percent,
            'pin_threshold' => (string) $merchant->pin_threshold,
            'pic_user_id' => (string) $merchant->pic_user_id,
            'cashiers' => $merchant->cashiers->pluck('id')->map(fn ($id) => (string) $id)->all(),
            'is_active' => $merchant->is_active,
        ]);
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-merchant');
    }

    public function saveMerchant(): void
    {
        $this->resetErrorBag();
        $this->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:mart,warung,kantin',
            'location' => 'nullable|string|max:150',
            'commission_percent' => 'required|numeric|min:0|max:50',
            'pic_user_id' => 'nullable|integer|exists:users,id',
            'cashiers.*' => 'integer|exists:users,id',
        ], [], ['name' => 'nama', 'commission_percent' => 'komisi']);

        $threshold = preg_replace('/\D/', '', $this->pin_threshold);
        $data = [
            'name' => $this->name,
            'type' => $this->type,
            'location' => $this->location ?: null,
            'commission_percent' => $this->commission_percent,
            'pin_threshold' => $threshold === '' ? null : (int) $threshold,
            'pic_user_id' => $this->pic_user_id ?: null,
            'is_active' => $this->is_active,
        ];

        $merchant = $this->editingMerchant
            ? tap(Merchant::query()->findOrFail($this->editingMerchant))->update($data)
            : Merchant::create($data + ['payment_modes' => ['card', 'qr']]);

        $merchant->cashiers()->sync($this->cashiers);
        $this->selected = $merchant->uuid;

        $this->dispatch('close-modal', 'form-merchant');
        $this->dispatch('toast', message: 'Merchant tersimpan.', tone: 'success');
    }

    private function selectedMerchant(): ?Merchant
    {
        return ! Str::isUuid($this->selected) ? null : Merchant::query()->where('uuid', $this->selected)->first();
    }

    public function createProduct(): void
    {
        $this->reset('editingProduct', 'product_name', 'product_price', 'product_barcode', 'product_active');
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-produk');
    }

    public function editProduct(string $uuid): void
    {
        $product = MerchantProduct::query()->where('merchant_id', $this->selectedMerchant()?->id)->where('uuid', $uuid)->firstOrFail();

        $this->editingProduct = $product->id;
        $this->fill(['product_name' => $product->name, 'product_price' => (string) $product->price, 'product_barcode' => (string) $product->barcode, 'product_active' => $product->is_active]);
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-produk');
    }

    public function saveProduct(): void
    {
        $merchant = $this->selectedMerchant();
        abort_if($merchant === null, 404);
        $this->resetErrorBag();

        $this->product_price = (string) preg_replace('/\D/', '', $this->product_price);

        $this->validate([
            'product_name' => 'required|string|max:120',
            'product_price' => 'required|integer|min:1',
            'product_barcode' => ['nullable', 'string', 'max:50', Rule::unique('merchant_products', 'barcode')->where('merchant_id', $merchant->id)->ignore($this->editingProduct)],
        ], ['product_barcode.unique' => 'Barcode ini sudah dipakai produk lain di merchant ini.'], ['product_name' => 'nama', 'product_price' => 'harga', 'product_barcode' => 'barcode']);

        $data = ['name' => $this->product_name, 'price' => (int) $this->product_price, 'barcode' => $this->product_barcode ?: null, 'is_active' => $this->product_active];

        if ($this->editingProduct) {
            MerchantProduct::query()->where('merchant_id', $merchant->id)->findOrFail($this->editingProduct)->update($data);
        } else {
            MerchantProduct::create($data + ['merchant_id' => $merchant->id]);
        }

        $this->dispatch('close-modal', 'form-produk');
        $this->dispatch('toast', message: 'Produk tersimpan.', tone: 'success');
    }

    public function render(): View
    {
        $selected = $this->selectedMerchant();

        return view('pos.merchants', [
            'merchants' => Merchant::query()->withCount('products')->orderBy('name')->get(),
            'selectedMerchant' => $selected,
            'balance' => $selected?->balance() ?? 0,
            'products' => $selected ? MerchantProduct::query()->where('merchant_id', $selected->id)->orderBy('name')->paginate(25) : null,
            'staffUsers' => User::query()->where('type', 'staff')->where('is_active', true)->whereHas('roles', fn ($roles) => $roles->whereIn('name', ['kasir', 'pengelola_merchant']))->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
