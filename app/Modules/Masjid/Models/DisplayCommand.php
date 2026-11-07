<?php

namespace App\Modules\Masjid\Models;

use Illuminate\Database\Eloquent\Model;

class DisplayCommand extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['acked_at' => 'immutable_datetime'];
    }
}
