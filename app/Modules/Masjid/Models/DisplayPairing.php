<?php

namespace App\Modules\Masjid\Models;

use Illuminate\Database\Eloquent\Model;

class DisplayPairing extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['pairing_token_hash', 'issued_token'];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'issued_token' => 'encrypted'];
    }
}
