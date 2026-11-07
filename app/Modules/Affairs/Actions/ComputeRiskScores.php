<?php

namespace App\Modules\Affairs\Actions;

use App\Modules\Affairs\Models\DisciplineSanction;
use App\Modules\Affairs\Models\StudentRiskScore;
use App\Modules\Core\Models\Module;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\Term;
use App\Modules\Core\Support\ModuleRegistry;
use Illuminate\Support\Facades\DB;

/**
 * Skor risiko harian (PRD Sistem 8.12). Hanya sinyal dari modul yang aktif, dan alasannya selalu tersimpan.
 */
class ComputeRiskScores
{
    public const WEIGHTS = ['arrears_two' => 3, 'arrears_one' => 1, 'attendance' => 2, 'grades' => 2, 'discipline' => 2, 'late_leave' => 1];

    public function __construct(private ModuleRegistry $modules) {}

    /**
     * @return array<string, int>
     */
    public static function weights(): array
    {
        return [...self::WEIGHTS, ...(Module::query()->where('module_key', 'analytics')->value('settings')['weights'] ?? [])];
    }

    public function handle(): int
    {
        $zone = config('sanad.display_timezone');
        $today = now($zone)->toDateString();
        $weights = self::weights();
        $count = 0;

        Student::query()->where('status', 'active')->chunkById(200, function ($students) use ($today, $weights, &$count): void {
            foreach ($students as $student) {
                $reasons = array_values(array_filter([
                    $this->modules->enabled('finance') ? $this->arrears($student, $today, $weights) : null,
                    $this->modules->enabled('attendance') ? $this->attendance($student, $today, $weights) : null,
                    $this->modules->enabled('grading') ? $this->grades($student, $weights) : null,
                    $this->modules->enabled('discipline') ? $this->discipline($student, $weights) : null,
                    $this->modules->enabled('leave') ? $this->lateLeave($student, $weights) : null,
                ]));
                $score = (int) array_sum(array_column($reasons, 'points'));

                StudentRiskScore::query()->updateOrCreate(['student_id' => $student->id, 'computed_on' => $today], [
                    'score' => $score,
                    'level' => $score >= 5 ? 'high' : ($score >= 3 ? 'medium' : 'low'),
                    'reasons' => $reasons,
                ]);
                $count++;
            }
        });

        return $count;
    }

    /**
     * @param  array<string, int>  $weights
     * @return array{text: string, points: int}|null
     */
    private function arrears(Student $student, string $today, array $weights): ?array
    {
        $months = DB::table('invoices')->where('student_id', $student->id)->whereIn('status', ['unpaid', 'partial', 'overdue'])->whereNull('deleted_at')->where('due_date', '<', $today)
            ->selectRaw("COUNT(DISTINCT to_char(due_date, 'YYYY-MM')) AS months")->value('months');

        if ((int) $months === 0) {
            return null;
        }

        return ['text' => "Menunggak {$months} bulan", 'points' => (int) $months >= 2 ? $weights['arrears_two'] : $weights['arrears_one']];
    }

    /**
     * @param  array<string, int>  $weights
     * @return array{text: string, points: int}|null
     */
    private function attendance(Student $student, string $today, array $weights): ?array
    {
        $since = now(config('sanad.display_timezone'))->subDays(30)->toDateString();
        $row = DB::table('attendance_records')->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.session_id')
            ->where('attendance_records.student_id', $student->id)->whereBetween('attendance_sessions.session_date', [$since, $today])
            ->selectRaw("COUNT(*) AS total, COUNT(*) FILTER (WHERE attendance_records.status IN ('hadir', 'terlambat', 'izin', 'sakit')) AS present")->first();

        if ((int) $row->total === 0) {
            return null;
        }

        $rate = (int) $row->present / (int) $row->total * 100;

        return $rate < 85 ? ['text' => 'Kehadiran '.round($rate).'% dalam 30 hari', 'points' => $weights['attendance']] : null;
    }

    /**
     * @param  array<string, int>  $weights
     * @return array{text: string, points: int}|null
     */
    private function grades(Student $student, array $weights): ?array
    {
        $terms = Term::query()->where('start_date', '<=', now())->orderByDesc('start_date')->limit(2)->pluck('id');

        if ($terms->count() < 2) {
            return null;
        }

        $average = fn (int $termId) => DB::table('grades')->join('assessments', 'assessments.id', '=', 'grades.assessment_id')
            ->where('grades.student_id', $student->id)->where('assessments.term_id', $termId)
            ->selectRaw('AVG(grades.score * 100.0 / assessments.max_score) AS average')->value('average');

        $current = $average($terms[0]);
        $previous = $average($terms[1]);

        if ($current === null || $previous === null || (float) $previous - (float) $current <= 10) {
            return null;
        }

        return ['text' => 'Rata-rata nilai turun '.round((float) $previous - (float) $current).' poin', 'points' => $weights['grades']];
    }

    /**
     * @param  array<string, int>  $weights
     * @return array{text: string, points: int}|null
     */
    private function discipline(Student $student, array $weights): ?array
    {
        $threshold = DisciplineSanction::query()->min('min_points');
        $points = RecordViolation::points($student);

        return $threshold !== null && $points >= $threshold ? ['text' => "Poin pelanggaran {$points}", 'points' => $weights['discipline']] : null;
    }

    /**
     * @param  array<string, int>  $weights
     * @return array{text: string, points: int}|null
     */
    private function lateLeave(Student $student, array $weights): ?array
    {
        $late = DB::table('leave_requests')->where('student_id', $student->id)->whereNotNull('late_notified_at')->where('late_notified_at', '>=', now()->subDays(60))->count();

        return $late >= 2 ? ['text' => "Terlambat kembali {$late} kali dalam 60 hari", 'points' => $weights['late_leave']] : null;
    }
}
