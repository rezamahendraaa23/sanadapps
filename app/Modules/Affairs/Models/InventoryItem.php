<?php

namespace App\Modules\Affairs\Models;

use App\Modules\Core\Models\Dormitory;
use App\Modules\Core\Models\User;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    use HasPublicUuid;

    public const MOVEMENTS = ['in' => 'Masuk', 'out' => 'Keluar', 'damage' => 'Rusak', 'repair' => 'Diperbaiki', 'adjust' => 'Penyesuaian'];

    protected $guarded = ['id', 'uuid'];

    protected $attributes = ['quantity' => 0, 'condition' => 'baik'];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function dormitory(): BelongsTo
    {
        return $this->belongsTo(Dormitory::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'item_id');
    }

    /**
     * Musyrif hanya barang asramanya.
     *
     * @param  Builder<InventoryItem>  $query
     */
    public function scopeManagedBy(Builder $query, User $user): Builder
    {
        return $user->hasRole(['admin', 'admin_akademik'])
            ? $query
            : $query->whereIn('dormitory_id', Dormitory::query()->where('musyrif_user_id', $user->id)->select('id'));
    }
}
