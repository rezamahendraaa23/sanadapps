<?php

namespace App\Modules\Pos\Models;

use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class SupplierPayment extends Model
{
    use HasPublicUuid;

    public const UPDATED_AT = null;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'paid_on' => 'date'];
    }
}
