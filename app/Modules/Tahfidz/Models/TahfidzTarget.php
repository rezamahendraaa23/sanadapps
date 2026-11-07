<?php

namespace App\Modules\Tahfidz\Models;

use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class TahfidzTarget extends Model
{
    use Audited, HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['target_pages' => 'float', 'target_juz' => 'float'];
    }
}
