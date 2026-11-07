<?php

namespace App\Modules\Masjid\Models;

use App\Modules\Core\Support\Audited;
use Illuminate\Database\Eloquent\Model;

class PrayerAdjustment extends Model
{
    use Audited;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['effective_from' => 'date:Y-m-d', 'minutes' => 'integer'];
    }
}
