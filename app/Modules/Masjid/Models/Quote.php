<?php

namespace App\Modules\Masjid\Models;

use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class Quote extends Model
{
    use Audited, HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['verified_at' => 'immutable_datetime', 'is_active' => 'boolean'];
    }
}
