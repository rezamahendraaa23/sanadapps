<?php

namespace App\Modules\Notification\Models;

use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use Audited, HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected $attributes = ['channel' => 'whatsapp', 'is_enabled' => true];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }
}
