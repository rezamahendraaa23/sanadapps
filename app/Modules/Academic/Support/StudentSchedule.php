<?php

namespace App\Modules\Academic\Support;

use App\Modules\Academic\Models\Schedule;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\Teacher;
use App\Modules\Core\Models\Term;
use Illuminate\Support\Collection;

final class StudentSchedule
{
    public static function activeTerm(): ?Term
    {
        return Term::query()->where('is_active', true)->first();
    }

    public static function todayIso(): int
    {
        return (int) now(config('sanad.display_timezone'))->dayOfWeekIso;
    }

    /**
     * Jadwal kelasnya ditambah kegiatan umum tanpa kelas.
     *
     * @return Collection<int, Schedule>
     */
    public static function forStudent(Student $student, int $day): Collection
    {
        $term = self::activeTerm();

        if ($term === null) {
            return collect();
        }

        return Schedule::query()->with(['subject:id,name', 'teacher:id,name'])
            ->where('term_id', $term->id)->where('day_of_week', $day)
            ->where(fn ($query) => $query->whereNull('classroom_id')->orWhere('classroom_id', $student->classroom_id))
            ->orderBy('start_time')->get();
    }

    /**
     * @return Collection<int, Schedule>
     */
    public static function forTeacher(Teacher $teacher, int $day): Collection
    {
        $term = self::activeTerm();

        if ($term === null) {
            return collect();
        }

        return Schedule::query()->with(['subject:id,name', 'classroom:id,name'])
            ->where('term_id', $term->id)->where('teacher_id', $teacher->id)->where('day_of_week', $day)
            ->orderBy('start_time')->get();
    }
}
