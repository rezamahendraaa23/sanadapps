<?php

namespace App\Modules\Pos\Livewire;

use App\Modules\Pos\Actions\StockLedger;
use App\Modules\Pos\Models\Merchant;
use App\Modules\Pos\Models\MerchantProduct;
use App\Modules\Pos\Models\ProductCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

#[Layout('components.layouts.staff', ['active' => 'merchants'])]
class CatalogImport extends Component
{
    use WithFileUploads;

    public const HEADINGS = ['nama', 'harga', 'harga_modal', 'barcode', 'kategori', 'stok', 'stok_minimum'];

    public Merchant $merchant;

    public $file = null;

    /** @var list<array<string, mixed>>|null */
    #[Locked]
    public ?array $rows = null;

    /** @var array{created: int, updated: int}|null */
    #[Locked]
    public ?array $imported = null;

    public function mount(Merchant $merchant): void
    {
        abort_unless($merchant->canBeManagedBy(auth()->user()), 403);
        $this->merchant = $merchant;
    }

    public function check(): void
    {
        $this->resetErrorBag();
        $this->validate(['file' => 'required|file|mimes:xlsx|max:10240'], ['file.required' => 'Pilih berkas Excel dulu.', 'file.mimes' => 'Berkas harus berformat .xlsx.', 'file.max' => 'Berkas terlalu besar. Maksimal 10 MB.']);

        try {
            $sheet = Excel::toArray(new class implements ToArray, WithHeadingRow
            {
                public function array(array $array): void {}
            }, $this->file)[0] ?? [];
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('file', 'Berkas tidak bisa dibaca. Gunakan template yang tersedia.');

            return;
        }

        $seen = [];
        $this->rows = [];

        foreach ($sheet as $index => $raw) {
            if (collect($raw)->filter(fn ($value) => filled($value))->isEmpty()) {
                continue;
            }

            $this->rows[] = $this->parse($raw, $index + 2, $seen);
        }

        if ($this->rows === []) {
            $this->rows = null;
            $this->addError('file', 'Berkas tidak berisi data.');
        }
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, true>  $seen
     * @return array<string, mixed>
     */
    private function parse(array $raw, int $line, array &$seen): array
    {
        $errors = [];
        $name = trim((string) ($raw['nama'] ?? ''));
        $price = (int) preg_replace('/\D/', '', (string) ($raw['harga'] ?? ''));
        $barcode = trim((string) ($raw['barcode'] ?? ''));
        $stock = trim((string) ($raw['stok'] ?? ''));

        if ($name === '') {
            $errors[] = 'Nama wajib diisi.';
        }

        if ($price <= 0) {
            $errors[] = 'Harga harus lebih dari nol.';
        }

        if ($barcode !== '') {
            if (isset($seen[$barcode])) {
                $errors[] = 'Barcode ganda di berkas ini.';
            }

            $seen[$barcode] = true;
        }

        if ($stock !== '' && ! ctype_digit($stock)) {
            $errors[] = 'Stok harus angka.';
        }

        return [
            'row' => $line,
            'name' => $name,
            'price' => $price,
            'cost_price' => filled($raw['harga_modal'] ?? null) ? (int) preg_replace('/\D/', '', (string) $raw['harga_modal']) : null,
            'barcode' => $barcode ?: null,
            'category' => trim((string) ($raw['kategori'] ?? '')) ?: null,
            'stock' => $stock === '' ? null : (int) $stock,
            'min_stock' => filled($raw['stok_minimum'] ?? null) ? (int) preg_replace('/\D/', '', (string) $raw['stok_minimum']) : null,
            'exists' => $barcode !== '' && $this->merchant->products()->where('barcode', $barcode)->exists(),
            'errors' => $errors,
        ];
    }

    /**
     * Barcode yang sudah ada memperbarui produknya, jadi berkas yang sama aman diimpor ulang.
     */
    public function import(StockLedger $stock): void
    {
        abort_if($this->rows === null, 422);
        abort_unless($this->merchant->canBeManagedBy(auth()->user()), 403);
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($stock, &$created, &$updated): void {
            foreach ($this->rows as $row) {
                if ($row['errors'] !== []) {
                    continue;
                }

                $category = $row['category']
                    ? ProductCategory::query()->firstOrCreate(['merchant_id' => $this->merchant->id, 'name' => $row['category']])
                    : null;
                $existing = $row['barcode'] ? $this->merchant->products()->where('barcode', $row['barcode'])->lockForUpdate()->first() : null;
                $data = ['name' => $row['name'], 'price' => $row['price'], 'cost_price' => $row['cost_price'], 'category_id' => $category?->id, 'min_stock' => $row['min_stock']];

                $product = $existing ? tap($existing)->update($data) : MerchantProduct::create($data + ['merchant_id' => $this->merchant->id, 'barcode' => $row['barcode']]);
                $existing ? $updated++ : $created++;

                // Stok di berkas adalah hasil hitung: selisihnya menjadi mutasi, tidak menimpa diam-diam.
                if ($row['stock'] !== null) {
                    if ($product->stock === null) {
                        MerchantProduct::query()->whereKey($product->id)->update(['stock' => 0]);
                        $product->refresh();
                    }

                    $difference = $row['stock'] - (int) $product->stock;

                    if ($difference !== 0) {
                        $stock->move($product, 'adjust', $difference, 'Impor katalog', auth()->id());
                    }
                }
            }
        });

        $this->imported = ['created' => $created, 'updated' => $updated];
        $this->rows = null;
    }

    public function startOver(): void
    {
        $this->reset('file', 'rows', 'imported');
        $this->resetErrorBag();
    }

    public function render(): View
    {
        return view('pos.catalog-import')->title('Impor Katalog '.$this->merchant->name);
    }
}
