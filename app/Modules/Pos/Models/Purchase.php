<?php

namespace App\Modules\Pos\Models;

use App\Modules\Core\Models\User;
use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    use Audited, HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['purchased_on' => 'date', 'due_on' => 'date', 'total' => 'integer', 'paid_amount' => 'integer'];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function outstanding(): int
    {
        return $this->total - $this->paid_amount;
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->outstanding() === 0 => 'Lunas',
            $this->paid_amount > 0 => 'Dibayar sebagian',
            default => 'Belum dibayar',
        };
    }
}
