<?php

namespace App\Modules\Letters\Models;

use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class LetterTemplate extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected $attributes = ['fields' => '[]', 'is_active' => true, 'needs_student' => false];

    protected function casts(): array
    {
        return ['fields' => 'array', 'needs_student' => 'boolean', 'is_active' => 'boolean'];
    }
}
