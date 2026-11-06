<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Support\Audited;
use App\Support\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use Audited, HasPublicUuid, SoftDeletes;

    protected $guarded = ['id', 'uuid'];

    protected $attributes = ['extra' => '{}'];

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'entry_date' => 'date', 'extra' => 'array'];
    }

    /**
     * Hanya santri yang boleh dilihat pengguna ini; dipakai semua daftar agar id tidak bisa ditebak.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('students.view-all')) {
            return $query;
        }

        return match ($user->type) {
            'guardian' => $query->whereHas('guardians', fn (Builder $guardians) => $guardians->where('guardians.user_id', $user->id)),
            'student' => $query->where('students.user_id', $user->id),
            default => $query->where(function (Builder $scoped) use ($user): void {
                $scoped->whereRaw('1 = 0');

                if ($user->can('students.view-own-class')) {
                    $scoped->orWhereHas('classroom.homeroomTeacher', fn (Builder $teacher) => $teacher->where('user_id', $user->id));
                }

                if ($user->can('students.view-own-dormitory')) {
                    $scoped->orWhereHas('dormitory', fn (Builder $dormitory) => $dormitory->where('musyrif_user_id', $user->id));
                }
            }),
        };
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function dormitory(): BelongsTo
    {
        return $this->belongsTo(Dormitory::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class)
            ->using(GuardianStudent::class)
            ->withPivot(['relation', 'is_primary', 'is_payer', 'can_receive_wa'])
            ->withTimestamps();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }
}
