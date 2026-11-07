<?php

namespace App\Modules\Affairs\Models;

use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class DisciplineSanction extends Model
{
    use Audited, HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['min_points' => 'integer', 'notify_guardian' => 'boolean'];
    }
}
