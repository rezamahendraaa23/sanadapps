<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'consumed_at' => 'immutable_datetime'];
    }
}
