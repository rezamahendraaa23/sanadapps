<?php

namespace App\Modules\Core\Models;

use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class InAppNotification extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['read_at' => 'immutable_datetime'];
    }
}
