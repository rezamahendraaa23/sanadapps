<?php

namespace App\Modules\Finance\Models;

use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashTransaction extends Model
{
    use Audited, HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'transaction_date' => 'date'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CashCategory::class);
    }
}
