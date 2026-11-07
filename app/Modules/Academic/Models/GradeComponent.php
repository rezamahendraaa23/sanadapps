<?php

namespace App\Modules\Academic\Models;

use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class GradeComponent extends Model
{
    use Audited, HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['weight' => 'integer', 'is_active' => 'boolean'];
    }
}
