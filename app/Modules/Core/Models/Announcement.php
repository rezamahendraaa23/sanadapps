<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use Audited, HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['publish_at' => 'immutable_datetime', 'send_wa' => 'boolean'];
    }
}
