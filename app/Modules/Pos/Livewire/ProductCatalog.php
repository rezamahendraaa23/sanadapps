<?php

namespace App\Modules\Pos\Livewire;

use App\Modules\Finance\Actions\CompressProofImage;
use App\Modules\Pos\Actions\StockLedger;
use App\Modules\Pos\Models\Merchant;
use App\Modules\Pos\Models\MerchantProduct;
use App\Modules\Pos\Models\ProductCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('components.layouts.staff', ['active' => 'merchants'])]
class ProductCatalog extends Component
{
    use WithFileUploads, WithPagination;

    public Merchant $merchant;

    #[Url(as: 'cari', except: '')]
    public string $search = '';

    #[Url(as: 'kategori', except: '')]
    public string $category = '';

    /** @var list<string> */
    public array $selected = [];

    #[Locked]
    public ?int $editing = null;

    public string $name = '';

    public string $price = '';

    public string $cost_price = '';

    public string $barcode = '';

    public string $category_id = '';

    public bool $track_stock = false;

    public string $initial_stock = '';

    public string $min_stock = '';

    public bool $is_active = true;

    public $photo = null;

    public string $bulk_mode = 'percent';

    public string $bulk_value = '';

    public string $category_name = '';

    public bool $category_restricted = false;

    public function mount(Merchant $merchant): void
    {
        abort_unless(Merchant::query()->accessibleBy(auth()->user())->whereKey($merchant->id)->exists(), 403);
        $this->merchant = $merchant;
    }

    private function assertManage(): void
    {
        abort_unless($this->merchant->canBeManagedBy(auth()->user()), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCategory(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->assertManage();
        $this->reset('editing', 'name', 'price', 'cost_price', 'barcode', 'category_id', 'track_stock', 'initial_stock', 'min_stock', 'is_active', 'photo');
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-produk');
    }

    public function edit(string $uuid): void
    {
        $this->assertManage();
        $product = $this->merchant->products()->where('uuid', $uuid)->firstOrFail();

        $this->editing = $product->id;
        $this->fill([
            'name' => $product->name,
            'price' => (string) $product->price,
            'cost_price' => (string) $product->cost_price,
            'barcode' => (string) $product->barcode,
            'category_id' => (string) $product->category_id,
            'track_stock' => $product->stock !== null,
            'initial_stock' => '',
            'min_stock' => (string) $product->min_stock,
            'is_active' => $product->is_active,
            'photo' => null,
        ]);
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-produk');
    }

    public function save(StockLedger $stock, CompressProofImage $images): void
    {
        $this->assertManage();
        $this->resetErrorBag();

        $this->price = (string) preg_replace('/\D/', '', $this->price);
        $this->cost_price = (string) preg_replace('/\D/', '', $this->cost_price);

        $this->validate([
            'name' => 'required|string|max:120',
            'price' => 'required|integer|min:1',
            'cost_price' => 'nullable|integer|min:0',
            'barcode' => ['nullable', 'string', 'max:50', Rule::unique('merchant_products', 'barcode')->where('merchant_id', $this->merchant->id)->ignore($this->editing)],
            'category_id' => ['nullable', Rule::exists('product_categories', 'id')->where(fn ($query) => $query->whereNull('merchant_id')->orWhere('merchant_id', $this->merchant->id))],
            'initial_stock' => 'nullable|integer|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'photo' => 'nullable|image|max:5120',
        ], ['barcode.unique' => 'Barcode ini sudah dipakai produk lain di merchant ini.'], ['name' => 'nama', 'price' => 'harga', 'cost_price' => 'harga modal', 'photo' => 'foto', 'min_stock' => 'stok minimum', 'initial_stock' => 'stok awal']);

        $data = [
            'name' => $this->name,
            'price' => (int) $this->price,
            'cost_price' => $this->cost_price === '' ? null : (int) $this->cost_price,
            'barcode' => $this->barcode ?: null,
            'category_id' => $this->category_id ?: null,
            'min_stock' => $this->track_stock && $this->min_stock !== '' ? (int) $this->min_stock : null,
            'is_active' => $this->is_active,
        ];

        DB::transaction(function () use ($data, $stock, $images): void {
            $product = $this->editing
                ? tap($this->merchant->products()->findOrFail($this->editing))->update($data)
                : MerchantProduct::create($data + ['merchant_id' => $this->merchant->id]);

            if ($this->photo) {
                $old = $product->photo_path;
                $product->update(['photo_path' => $images->store($this->photo, 'product-photos')]);

                if ($old) {
                    Storage::disk('local')->delete($old);
                }
            }

            $locked = MerchantProduct::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            if ($this->track_stock && $locked->stock === null) {
                MerchantProduct::query()->whereKey($locked->id)->update(['stock' => 0]);
                $locked->refresh();

                if ((int) $this->initial_stock > 0) {
                    $stock->move($locked, 'in', (int) $this->initial_stock, 'Stok awal', auth()->id());
                }
            } elseif (! $this->track_stock && $locked->stock !== null) {
                MerchantProduct::query()->whereKey($locked->id)->update(['stock' => null]);
            }
        });

        $this->dispatch('close-modal', 'form-produk');
        $this->dispatch('toast', message: 'Produk tersimpan.', tone: 'success');
    }

    public function applyBulkPrice(): void
    {
        $this->assertManage();
        $this->resetErrorBag();
        $value = (int) preg_replace('/[^\d-]/', '', $this->bulk_value);

        if ($this->selected === [] || $value === 0) {
            $this->addError('bulk_value', 'Pilih produk dan isi perubahan harganya.');

            return;
        }

        $products = $this->merchant->products()->whereIn('uuid', $this->selected)->get();
        $priced = $products->mapWithKeys(fn (MerchantProduct $product) => [
            $product->id => $this->bulk_mode === 'percent' ? (int) round($product->price * (100 + $value) / 100) : $product->price + $value,
        ]);

        if ($priced->contains(fn (int $price) => $price < 1)) {
            $this->addError('bulk_value', 'Ada harga yang menjadi nol atau minus. Kurangi perubahannya.');

            return;
        }

        DB::transaction(fn () => $products->each(fn (MerchantProduct $product) => $product->update(['price' => $priced[$product->id]])));

        $count = $products->count();
        $this->reset('selected', 'bulk_value');
        $this->dispatch('close-modal', 'ubah-harga');
        $this->dispatch('toast', message: "Harga {$count} produk diubah.", tone: 'success');
    }

    public function saveCategory(): void
    {
        $this->assertManage();
        $this->resetErrorBag();

        if (trim($this->category_name) === '') {
            $this->addError('category_name', 'Isi nama kategori.');

            return;
        }

        ProductCategory::create(['merchant_id' => $this->merchant->id, 'name' => trim($this->category_name), 'is_restricted' => $this->category_restricted]);

        $this->reset('category_name', 'category_restricted');
        $this->dispatch('toast', message: 'Kategori ditambahkan.', tone: 'success');
    }

    public function render(): View
    {
        $term = trim($this->search);

        return view('pos.catalog', [
            'products' => $this->merchant->products()->with('category:id,name,is_restricted')
                ->when($term !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('name', 'ilike', '%'.addcslashes($term, '%_').'%')->orWhere('barcode', $term)))
                ->when($this->category !== '', fn ($query) => $query->where('category_id', (int) $this->category))
                ->orderBy('name')->paginate(25),
            'categories' => ProductCategory::query()->where(fn ($query) => $query->whereNull('merchant_id')->orWhere('merchant_id', $this->merchant->id))->orderBy('sort_order')->orderBy('name')->get(),
            'canManage' => $this->merchant->canBeManagedBy(auth()->user()),
        ])->title('Katalog '.$this->merchant->name);
    }
}
