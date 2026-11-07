<?php

namespace App\Modules\Pos\Models;

use App\Modules\Core\Models\User;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosShift extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'opened_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime',
            'opening_cash' => 'integer', 'expected_cash' => 'integer', 'counted_cash' => 'integer',
            'cash_sales' => 'integer', 'card_sales' => 'integer', 'refunds' => 'integer', 'order_count' => 'integer',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_user_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(PosOrder::class, 'shift_id');
    }

    public function difference(): ?int
    {
        return $this->counted_cash === null ? null : $this->counted_cash - (int) $this->expected_cash;
    }
}
