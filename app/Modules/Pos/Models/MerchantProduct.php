<?php

namespace App\Modules\Pos\Models;

use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantProduct extends Model
{
    use Audited, HasPublicUuid;

    /** @var list<string> */
    protected array $auditExcept = ['stock'];

    protected $guarded = ['id', 'uuid'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['price' => 'integer', 'cost_price' => 'integer', 'stock' => 'integer', 'min_stock' => 'integer', 'is_active' => 'boolean'];
    }

    public function isLowStock(): bool
    {
        return $this->stock !== null && $this->min_stock !== null && $this->stock <= $this->min_stock;
    }

    public function isOutOfStock(): bool
    {
        return $this->stock !== null && $this->stock <= 0;
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }
}
