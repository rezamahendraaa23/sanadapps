<?php

namespace App\Modules\Core\Models;

use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class Import extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected $attributes = ['errors' => '[]'];

    protected function casts(): array
    {
        return ['errors' => 'array'];
    }
}
