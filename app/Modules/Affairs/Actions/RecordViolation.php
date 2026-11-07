<?php

namespace App\Modules\Affairs\Actions;

use App\Modules\Affairs\Models\DisciplineRecord;
use App\Modules\Affairs\Models\DisciplineRule;
use App\Modules\Affairs\Models\DisciplineSanction;
use App\Modules\Core\Models\AcademicYear;
use App\Modules\Core\Models\InAppNotification;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\User;
use App\Modules\Notification\Actions\QueueNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class RecordViolation
{
    public function __construct(private QueueNotification $notifications) {}

    /**
     * Poin dihitung per tahun ajaran (DSC-02); melewati ambang memicu sanksi sekali saja (DSC-03).
     *
     * @param  array{notes?: string|null, evidence_path?: string|null, leave_request_id?: int|null}  $extra
     */
    public function handle(Student $student, DisciplineRule $rule, string $occurredOn, array $extra, ?int $userId): DisciplineRecord
    {
        $issued = [];

        $record = DB::transaction(function () use ($student, $rule, $occurredOn, $extra, $userId, &$issued): DisciplineRecord {
            DB::table('students')->where('id', $student->id)->lockForUpdate()->first();

            $record = DisciplineRecord::create([
                'student_id' => $student->id,
                'rule_id' => $rule->id,
                'occurred_on' => $occurredOn,
                'points' => $rule->points,
                'notes' => $extra['notes'] ?? null,
                'evidence_path' => $extra['evidence_path'] ?? null,
                'leave_request_id' => $extra['leave_request_id'] ?? null,
                'recorded_by' => $userId,
            ]);

            $year = self::yearFor($occurredOn);
            $total = self::points($student, $year);

            foreach (DisciplineSanction::query()->where('min_points', '<=', $total)->orderBy('min_points')->get() as $sanction) {
                try {
                    DB::transaction(fn () => DB::table('discipline_sanction_events')->insert([
                        'student_id' => $student->id,
                        'sanction_id' => $sanction->id,
                        'academic_year_id' => $year?->id,
                        'points_at' => $total,
                        'issued_at' => now(),
                    ]));
                    $issued[] = [$sanction, $total, $year?->id];
                } catch (UniqueConstraintViolationException) {
                    continue;
                }
            }

            return $record;
        });

        foreach ($issued as [$sanction, $total, $yearId]) {
            $this->announce($student, $sanction, $total, (int) $yearId);
        }

        return $record;
    }

    public static function yearFor(string $date): ?AcademicYear
    {
        return AcademicYear::query()->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->first()
            ?? AcademicYear::query()->where('is_active', true)->first();
    }

    public static function points(Student $student, ?AcademicYear $year = null): int
    {
        $year ??= AcademicYear::query()->where('is_active', true)->first();

        return (int) DisciplineRecord::query()->where('student_id', $student->id)
            ->when($year, fn ($query) => $query->whereBetween('occurred_on', [$year->start_date->toDateString(), $year->end_date->toDateString()]))
            ->sum('points');
    }

    private function announce(Student $student, DisciplineSanction $sanction, int $total, int $yearId): void
    {
        if ($sanction->notify_guardian) {
            $this->notifications->toGuardians('discipline_sanction', [$student], fn ($guardian) => [
                'nama_wali' => $guardian->name,
                'nama_santri' => $student->name,
                'sanksi' => $sanction->name,
                'poin' => $total,
            ], 'Pemberitahuan '.$sanction->name, null, 'sanction:'.$sanction->id.':'.$student->id.':'.$yearId);
        }

        foreach (User::role('admin')->pluck('id') as $adminId) {
            InAppNotification::create(['user_id' => $adminId, 'title' => $sanction->name.': '.$student->name, 'body' => "Poin pelanggaran mencapai {$total}.", 'link' => route('staff.discipline.index'), 'dedupe_key' => 'sanction-admin:'.$sanction->id.':'.$student->id.':'.$yearId.':'.$adminId]);
        }
    }
}
