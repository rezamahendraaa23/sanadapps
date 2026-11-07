<?php

namespace App\Modules\Wallet\Models;

use App\Modules\Pos\Models\Merchant;
use App\Modules\Pos\Models\PosOrder;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    use HasPublicUuid;

    public const UPDATED_AT = null;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'balance_after' => 'integer', 'created_at' => 'immutable_datetime'];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'ref_id');
    }

    public function signedAmount(): int
    {
        return $this->direction === 'credit' ? $this->amount : -$this->amount;
    }

    public function label(): string
    {
        return match ($this->type) {
            'topup' => 'Top-up',
            'spend' => 'Belanja',
            'refund' => $this->direction === 'credit' ? 'Pengembalian belanja' : 'Penarikan saldo',
            'adjustment' => 'Penyesuaian',
            'payout' => 'Penyaluran',
        };
    }
}
