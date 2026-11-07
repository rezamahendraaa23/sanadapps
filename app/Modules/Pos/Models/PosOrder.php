<?php

namespace App\Modules\Pos\Models;

use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\User;
use App\Modules\Wallet\Models\WalletTransaction;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosOrder extends Model
{
    use HasPublicUuid;

    /** Kasir boleh membatalkan transaksinya sendiri dalam batas ini (POS-12). */
    public const CASHIER_VOID_MINUTES = 10;

    protected $guarded = ['id', 'uuid'];

    protected $attributes = ['status' => 'paid'];

    protected function casts(): array
    {
        return ['total' => 'integer', 'commission' => 'integer', 'voided_at' => 'immutable_datetime'];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosOrderItem::class);
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class);
    }

    public function canBeVoidedBy(User $user): bool
    {
        if ($this->status !== 'paid') {
            return false;
        }

        if ($user->can('pos.void')) {
            return Merchant::query()->accessibleBy($user)->whereKey($this->merchant_id)->exists();
        }

        return $user->can('pos.operate')
            && $this->cashier_user_id === $user->id
            && $this->created_at->diffInMinutes(now(), true) < self::CASHIER_VOID_MINUTES;
    }
}
