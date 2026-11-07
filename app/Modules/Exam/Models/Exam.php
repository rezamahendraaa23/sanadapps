<?php

namespace App\Modules\Exam\Models;

use App\Modules\Academic\Models\Assessment;
use App\Modules\Academic\Models\Subject;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\Term;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    use Audited, HasPublicUuid;

    /** @var list<string> */
    protected array $auditExcept = ['access_code'];

    protected $guarded = ['id', 'uuid'];

    protected $hidden = ['access_code'];

    protected $attributes = ['status' => 'draft', 'audience' => 'student', 'duration_minutes' => 60, 'max_attempts' => 1, 'show_result_mode' => 'after_close'];

    protected function casts(): array
    {
        return ['start_at' => 'immutable_datetime', 'end_at' => 'immutable_datetime', 'shuffle_questions' => 'boolean', 'shuffle_options' => 'boolean', 'duration_minutes' => 'integer', 'max_attempts' => 'integer', 'pass_score' => 'float'];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'exam_questions')->withPivot(['sort_order', 'points_override'])->orderByPivot('sort_order');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(ExamTarget::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function canBeManagedBy(User $user): bool
    {
        return $user->can('exams.manage') || ($user->can('exams.own') && $this->created_by === $user->id);
    }

    /**
     * @param  Builder<Exam>  $query
     */
    public function scopeManagedBy(Builder $query, User $user): Builder
    {
        return $user->can('exams.manage') ? $query : $query->where('created_by', $user->id);
    }

    /**
     * @param  Builder<Exam>  $query
     */
    public function scopeForStudent(Builder $query, Student $student): Builder
    {
        return $query->where('audience', 'student')->whereHas('targets', fn (Builder $targets) => $targets
            ->where('student_id', $student->id)
            ->when($student->classroom_id, fn (Builder $inner) => $inner->orWhere('classroom_id', $student->classroom_id)));
    }

    public function isOpen(): bool
    {
        return $this->status === 'published' && $this->start_at !== null && $this->end_at !== null && $this->start_at->lessThanOrEqualTo(now()) && $this->end_at->greaterThan(now());
    }

    public function maxPoints(): float
    {
        return (float) $this->questions()->withTrashed()->get()->sum(fn (Question $question) => (float) ($question->pivot->points_override ?? $question->points));
    }
}
