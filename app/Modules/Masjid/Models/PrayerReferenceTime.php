<?php

namespace App\Modules\Masjid\Models;

use Illuminate\Database\Eloquent\Model;

class PrayerReferenceTime extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d'];
    }
}
