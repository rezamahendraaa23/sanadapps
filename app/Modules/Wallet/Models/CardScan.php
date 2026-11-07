<?php

namespace App\Modules\Wallet\Models;

use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardScan extends Model
{
    use HasPublicUuid;

    public $timestamps = false;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['scanned_at' => 'immutable_datetime'];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(StudentCard::class, 'card_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
