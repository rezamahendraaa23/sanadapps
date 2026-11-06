<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Term extends Model
{
    use Audited, HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'is_active' => 'boolean'];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
