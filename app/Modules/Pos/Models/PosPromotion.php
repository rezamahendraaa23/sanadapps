<?php

namespace App\Modules\Pos\Models;

use App\Modules\Finance\Support\Rupiah;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosPromotion extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected $attributes = ['is_active' => true, 'min_qty' => 1];

    protected function casts(): array
    {
        return ['value' => 'integer', 'min_qty' => 'integer', 'starts_on' => 'date', 'ends_on' => 'date', 'is_active' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MerchantProduct::class, 'product_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /**
     * Promo yang berlaku pada tanggal pesantren tertentu.
     */
    public function scopeRunningOn(Builder $query, string $date): void
    {
        $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_on')->orWhere('starts_on', '<=', $date))
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $date));
    }

    public function appliesTo(MerchantProduct $product): bool
    {
        return ($this->product_id === null && $this->category_id === null)
            || $this->product_id === $product->id
            || ($this->category_id !== null && $this->category_id === $product->category_id);
    }

    /**
     * Potongan untuk satu baris; nol bila jumlah belum mencapai syarat.
     */
    public function discountFor(int $unitPrice, int $qty): int
    {
        if ($qty < $this->min_qty) {
            return 0;
        }

        $line = $unitPrice * $qty;
        $discount = $this->kind === 'percent' ? intdiv($line * $this->value, 100) : $this->value * $qty;

        return min($line, $discount);
    }

    public function label(): string
    {
        $value = $this->kind === 'percent' ? $this->value.'%' : Rupiah::format($this->value).' per barang';

        return 'Potongan '.$value.($this->min_qty > 1 ? ', minimal '.$this->min_qty.' barang' : '');
    }
}
