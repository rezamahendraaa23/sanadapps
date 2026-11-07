<?php

namespace App\Modules\Masjid\Models;

use Illuminate\Database\Eloquent\Model;

class HijriMonth extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_on' => 'date:Y-m-d'];
    }
}
