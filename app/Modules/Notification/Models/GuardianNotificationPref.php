<?php

namespace App\Modules\Notification\Models;

use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class GuardianNotificationPref extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }
}
