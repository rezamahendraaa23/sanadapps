<?php

namespace App\Modules\Exam\Models;

use App\Modules\Academic\Models\Subject;
use App\Modules\Academic\Models\TeachingScope;
use App\Modules\Core\Models\Teacher;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionBank extends Model
{
    use Audited, HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'owner_teacher_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'bank_id');
    }

    /**
     * Ustadz hanya bank miliknya; pengelola semuanya.
     *
     * @param  Builder<QuestionBank>  $query
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        return $user->can('exams.manage') ? $query : $query->where('owner_teacher_id', TeachingScope::teacher($user)?->id ?? 0);
    }
}
