<?php

namespace App\Modules\Tahfidz\Support;

use App\Modules\Core\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class TahfidzRecap
{
    public static function date(?string $value): ?string
    {
        return is_string($value) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $value : null;
    }

    /**
     * @return Collection<int, array{student: Student, ziyadah_pages: float, murojaah: int, tahsin: int, mutabaah: int}>
     */
    public static function rows(?int $classroomId, string $from, string $to): Collection
    {
        $records = DB::table('tahfidz_records')->whereBetween('record_date', [$from, $to])
            ->selectRaw('student_id, type, COUNT(*) AS total, SUM(pages) AS pages')->groupBy('student_id', 'type')->get()->groupBy('student_id');
        $mutabaah = DB::table('mutabaah_logs')->whereBetween('log_date', [$from, $to])->where('status', 'done')
            ->selectRaw('student_id, COUNT(*) AS total')->groupBy('student_id')->pluck('total', 'student_id');

        return Student::query()
            ->when($classroomId, fn ($query) => $query->where('classroom_id', $classroomId))
            ->whereIn('id', $records->keys()->merge($mutabaah->keys())->unique())
            ->orderBy('name')->get()
            ->map(function (Student $student) use ($records, $mutabaah): array {
                $own = collect($records[$student->id] ?? [])->keyBy('type');

                return [
                    'student' => $student,
                    'ziyadah_pages' => (float) ($own['ziyadah']->pages ?? 0),
                    'murojaah' => (int) ($own['murojaah']->total ?? 0),
                    'tahsin' => (int) ($own['tahsin']->total ?? 0),
                    'mutabaah' => (int) ($mutabaah[$student->id] ?? 0),
                ];
            });
    }
}
