<?php

namespace App\Modules\Masjid\Models;

use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class KajianSchedule extends Model
{
    use Audited, HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'day_of_week' => 'integer'];
    }
}
