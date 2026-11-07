<?php

namespace App\Modules\Pos\Models;

use App\Modules\Core\Models\User;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasPublicUuid;

    public const UPDATED_AT = null;

    public const LABELS = ['in' => 'Stok masuk', 'out' => 'Stok keluar', 'sale' => 'Terjual', 'void' => 'Batal jual', 'adjust' => 'Penyesuaian'];

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'stock_after' => 'integer', 'created_at' => 'immutable_datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MerchantProduct::class, 'product_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
