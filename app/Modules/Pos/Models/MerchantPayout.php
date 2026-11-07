<?php

namespace App\Modules\Pos\Models;

use App\Modules\Core\Models\User;
use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantPayout extends Model
{
    use Audited, HasPublicUuid;

    public const LABELS = ['requested' => 'Menunggu persetujuan', 'approved' => 'Disetujui, belum dibayar', 'paid' => 'Sudah dibayar', 'rejected' => 'Ditolak'];

    protected $guarded = ['id', 'uuid'];

    protected $attributes = ['status' => 'requested', 'method' => 'cash'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'paid_at' => 'immutable_datetime'];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
