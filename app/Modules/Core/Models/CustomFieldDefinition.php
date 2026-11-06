<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class CustomFieldDefinition extends Model
{
    use Audited, HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected $attributes = ['options' => '[]'];

    protected function casts(): array
    {
        return ['options' => 'array', 'is_required' => 'boolean', 'is_active' => 'boolean'];
    }
}
