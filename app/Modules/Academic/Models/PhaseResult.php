<?php

namespace App\Modules\Academic\Models;

use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhaseResult extends Model
{
    use Audited, HasPublicUuid;

    public const LABELS = ['pending' => 'Belum ditetapkan', 'passed' => 'Lulus', 'failed' => 'Belum lulus'];

    protected $guarded = ['id', 'uuid'];

    protected $attributes = ['status' => 'pending', 'scores' => '{}'];

    protected function casts(): array
    {
        return [
            'scores' => 'array', 'final_score' => 'float', 'started_on' => 'date', 'ended_on' => 'date',
            'certified_at' => 'immutable_datetime', 'decided_at' => 'immutable_datetime', 'promoted_at' => 'immutable_datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(CurriculumPhase::class, 'phase_id');
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
}
