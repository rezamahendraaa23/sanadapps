<?php

namespace App\Modules\Core\Models;

use App\Modules\Academic\Models\CurriculumPhase;
use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classroom extends Model
{
    use Audited, HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['phase_started_on' => 'date', 'phase_ends_on' => 'date'];
    }

    /** Fase kurikulum yang sedang dijalani rombongan ini, bila pesantren memakai kurikulum fase. */
    public function phase(): BelongsTo
    {
        return $this->belongsTo(CurriculumPhase::class, 'phase_id');
    }

    public function homeroomTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'homeroom_teacher_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
