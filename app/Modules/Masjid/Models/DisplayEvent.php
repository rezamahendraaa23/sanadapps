<?php

namespace App\Modules\Masjid\Models;

use Illuminate\Database\Eloquent\Model;

class DisplayEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'occurred_at' => 'immutable_datetime'];
    }
}
