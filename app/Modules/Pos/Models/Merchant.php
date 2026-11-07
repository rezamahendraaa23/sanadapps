<?php

namespace App\Modules\Pos\Models;

use App\Modules\Core\Models\User;
use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Merchant extends Model
{
    use Audited, HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected $attributes = ['type' => 'warung', 'is_active' => true, 'commission_percent' => 0];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'commission_percent' => 'decimal:2',
            'pin_threshold' => 'integer',
            'payment_modes' => 'array',
            'opening_hours' => 'array',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(MerchantProduct::class);
    }

    public function cashiers(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    /**
     * Kasir dan pengelola hanya melihat merchant miliknya (POS-01).
     *
     * @param  Builder<Merchant>  $query
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->can('merchants.manage')) {
            return $query;
        }

        return $query->where(fn (Builder $scoped) => $scoped
            ->where('pic_user_id', $user->id)
            ->orWhereHas('cashiers', fn (Builder $cashiers) => $cashiers->whereKey($user->id)));
    }

    public function balance(): int
    {
        return (int) (MerchantLedgerEntry::query()->where('merchant_id', $this->id)->latest('id')->value('balance_after') ?? 0);
    }

    public function commissionFor(int $total): int
    {
        return (int) floor($total * (float) $this->commission_percent / 100);
    }
}
