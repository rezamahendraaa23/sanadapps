<?php

namespace App\Modules\Pos\Livewire;

use App\Modules\Pos\Models\Merchant;
use App\Modules\Pos\Models\MerchantProduct;
use App\Modules\Pos\Models\PosPromotion;
use App\Modules\Pos\Models\ProductCategory;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Promo sederhana: potongan persen atau rupiah per barang, untuk satu produk, satu kategori, atau
 * semua produk, dengan syarat jumlah minimal dan rentang tanggal. Promo tidak ditumpuk.
 */
#[Layout('components.layouts.staff', ['active' => 'merchants'])]
#[Title('Promo')]
class Promotions extends Component
{
    public Merchant $merchant;

    #[Locked]
    public ?string $editing = null;

    public string $name = '';

    public string $target = 'all';

    public string $product = '';

    public string $category = '';

    public string $kind = 'percent';

    public string $value = '';

    public string $min_qty = '1';

    public string $starts_on = '';

    public string $ends_on = '';

    public function mount(Merchant $merchant): void
    {
        abort_unless(Merchant::query()->accessibleBy(auth()->user())->whereKey($merchant->id)->exists(), 403);
        $this->merchant = $merchant;
    }

    public function create(): void
    {
        abort_unless($this->merchant->canBeManagedBy(auth()->user()), 403);
        $this->reset('editing', 'name', 'target', 'product', 'category', 'kind', 'value', 'min_qty', 'ends_on');
        $this->starts_on = now(config('sanad.display_timezone'))->toDateString();
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-promo');
    }

    public function edit(string $uuid): void
    {
        abort_unless($this->merchant->canBeManagedBy(auth()->user()), 403);
        $promo = PosPromotion::query()->where('merchant_id', $this->merchant->id)->where('uuid', $uuid)->with(['product:id,uuid', 'category:id,uuid'])->firstOrFail();
        $this->editing = $promo->uuid;
        $this->fill([
            'name' => $promo->name,
            'target' => $promo->product_id ? 'product' : ($promo->category_id ? 'category' : 'all'),
            'product' => (string) $promo->product?->uuid,
            'category' => (string) $promo->category?->uuid,
            'kind' => $promo->kind,
            'value' => (string) $promo->value,
            'min_qty' => (string) $promo->min_qty,
            'starts_on' => (string) $promo->starts_on?->toDateString(),
            'ends_on' => (string) $promo->ends_on?->toDateString(),
        ]);
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-promo');
    }

    public function save(): void
    {
        abort_unless($this->merchant->canBeManagedBy(auth()->user()), 403);
        $this->value = (string) preg_replace('/\D/', '', $this->value);
        $this->validate([
            'name' => 'required|string|max:80',
            'target' => 'required|in:all,product,category',
            'product' => 'required_if:target,product|nullable|uuid',
            'category' => 'required_if:target,category|nullable|uuid',
            'kind' => 'required|in:percent,amount',
            'value' => ['required', 'integer', 'min:1', $this->kind === 'percent' ? 'max:100' : 'max:100000000'],
            'min_qty' => 'required|integer|min:1|max:1000',
            'starts_on' => 'nullable|date',
            'ends_on' => 'nullable|date|after_or_equal:starts_on',
        ], ['product.required_if' => 'Pilih produknya.', 'category.required_if' => 'Pilih kategorinya.'], ['name' => 'nama promo', 'value' => 'besar potongan', 'min_qty' => 'jumlah minimal', 'ends_on' => 'tanggal selesai']);

        $data = [
            'name' => trim($this->name),
            'product_id' => $this->target === 'product' ? MerchantProduct::query()->where('merchant_id', $this->merchant->id)->where('uuid', $this->product)->value('id') : null,
            'category_id' => $this->target === 'category' ? ProductCategory::query()->where(fn ($q) => $q->where('merchant_id', $this->merchant->id)->orWhereNull('merchant_id'))->where('uuid', $this->category)->value('id') : null,
            'kind' => $this->kind,
            'value' => (int) $this->value,
            'min_qty' => (int) $this->min_qty,
            'starts_on' => $this->starts_on ?: null,
            'ends_on' => $this->ends_on ?: null,
        ];

        if (($this->target === 'product' && ! $data['product_id']) || ($this->target === 'category' && ! $data['category_id'])) {
            $this->addError($this->target, 'Pilihan tidak ditemukan.');

            return;
        }

        $this->editing
            ? PosPromotion::query()->where('merchant_id', $this->merchant->id)->where('uuid', $this->editing)->firstOrFail()->update($data)
            : PosPromotion::create($data + ['merchant_id' => $this->merchant->id]);

        $this->dispatch('close-modal', 'form-promo');
        $this->dispatch('toast', message: 'Promo tersimpan dan langsung dipakai di kasir.', tone: 'success');
    }

    public function toggle(string $uuid): void
    {
        abort_unless($this->merchant->canBeManagedBy(auth()->user()), 403);
        $promo = PosPromotion::query()->where('merchant_id', $this->merchant->id)->where('uuid', $uuid)->firstOrFail();
        $promo->update(['is_active' => ! $promo->is_active]);
    }

    public function render(): View
    {
        $today = now(config('sanad.display_timezone'))->toDateString();

        return view('pos.promotions', [
            'canManage' => $this->merchant->canBeManagedBy(auth()->user()),
            'promotions' => PosPromotion::query()->where('merchant_id', $this->merchant->id)->with(['product:id,name', 'category:id,name'])->latest('id')->get(),
            'running' => PosPromotion::query()->where('merchant_id', $this->merchant->id)->runningOn($today)->pluck('id')->all(),
            'products' => MerchantProduct::query()->where('merchant_id', $this->merchant->id)->where('is_active', true)->orderBy('name')->pluck('name', 'uuid')->all(),
            'categories' => ProductCategory::query()->where(fn ($q) => $q->where('merchant_id', $this->merchant->id)->orWhereNull('merchant_id'))->orderBy('name')->pluck('name', 'uuid')->all(),
        ]);
    }
}
