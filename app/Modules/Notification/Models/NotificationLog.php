<?php

namespace App\Modules\Notification\Models;

use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected $attributes = ['status' => 'queued', 'channel' => 'whatsapp', 'attempts' => 0];

    protected function casts(): array
    {
        return ['payload' => 'array', 'sent_at' => 'immutable_datetime'];
    }
}
