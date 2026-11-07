<?php

namespace App\Modules\Academic\Models;

use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use Audited, HasPublicUuid;

    public const TYPES = ['diniyah' => 'Diniyah', 'umum' => 'Umum', 'tahfidz' => 'Tahfidz'];

    protected $guarded = ['id', 'uuid'];

    protected $attributes = ['is_active' => true, 'type' => 'umum', 'kkm' => 70];

    protected function casts(): array
    {
        return ['kkm' => 'integer', 'is_active' => 'boolean'];
    }
}
