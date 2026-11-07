<?php

namespace App\Modules\Academic\Support;

use App\Modules\Core\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AttendanceStats
{
    /**
     * Rekap per santri; persentase kehadiran menghitung hadir dan terlambat dari sesi yang sudah ditutup.
     *
     * @return Collection<int, array{student: Student, hadir: int, izin: int, sakit: int, alpa: int, terlambat: int, total: int, percent: int}>
     */
    public static function perStudent(?int $classroomId, ?int $dormitoryId, string $from, string $to, ?array $studentIds = null): Collection
    {
        $rows = DB::table('attendance_records')
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.session_id')
            ->whereNotNull('attendance_sessions.closed_at')
            ->whereBetween('attendance_sessions.session_date', [$from, $to])
            ->selectRaw('attendance_records.student_id, attendance_records.status, COUNT(*) AS total')
            ->groupBy('attendance_records.student_id', 'attendance_records.status')
            ->get()
            ->groupBy('student_id');

        return Student::query()
            ->when($classroomId, fn ($query) => $query->where('classroom_id', $classroomId))
            ->when($dormitoryId, fn ($query) => $query->where('dormitory_id', $dormitoryId))
            ->when($studentIds !== null, fn ($query) => $query->whereIn('id', $studentIds))
            ->whereIn('id', $rows->keys())
            ->orderBy('name')
            ->get()
            ->map(function (Student $student) use ($rows): array {
                $counts = $rows[$student->id]->pluck('total', 'status');
                $row = ['student' => $student];

                foreach (['hadir', 'izin', 'sakit', 'alpa', 'terlambat'] as $status) {
                    $row[$status] = (int) ($counts[$status] ?? 0);
                }

                $row['total'] = (int) $counts->sum();
                $row['percent'] = $row['total'] === 0 ? 0 : (int) round(($row['hadir'] + $row['terlambat']) / $row['total'] * 100);

                return $row;
            });
    }
}
