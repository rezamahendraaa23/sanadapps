<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class Integration extends Model
{
    use Audited, HasPublicUuid;

    /** @var list<string> */
    protected array $auditExcept = ['credentials'];

    protected $guarded = ['id', 'uuid'];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return ['credentials' => 'encrypted:array', 'settings' => 'array', 'is_active' => 'boolean'];
    }
}
