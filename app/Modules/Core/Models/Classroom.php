<?php

namespace App\Modules\Core\Models;

use App\Modules\Academic\Models\CurriculumPhase;
use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
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
    /**
     * Urutan alami: Kelas 7, 8, 9, 10, bukan 10, 11, 12, 7. Angka diambil dari jenjang, atau dari nama bila jenjang kosong.
     *
     * @param  Builder<Classroom>  $query
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderByRaw("COALESCE(NULLIF(regexp_replace(COALESCE(level, ''), '[^0-9]', '', 'g'), '')::numeric, NULLIF(substring(name from '[0-9]+'), '')::numeric) NULLS LAST")
            ->orderBy('name');
    }

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
