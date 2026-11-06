<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\StudentEnrollment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PromoteStudents
{
    public const UNDO_HOURS = 24;

    public function __construct(private RecordAudit $audit, private SaveStudent $students) {}

    /**
     * Pratinjau memakai query yang sama dengan eksekusi agar jumlahnya pasti cocok (COR-10).
     *
     * @return Collection<int, Student>
     */
    public function preview(int $fromClassroomId): Collection
    {
        return Student::query()->where('classroom_id', $fromClassroomId)->where('status', 'active')->orderBy('name')->get();
    }

    /**
     * @param  int|null  $toClassroomId  null berarti lulus.
     * @return string Id batch untuk pembatalan.
     */
    public function execute(int $fromClassroomId, ?int $toClassroomId): string
    {
        return DB::transaction(function () use ($fromClassroomId, $toClassroomId): string {
            $batch = (string) Str::uuid7();
            $students = Student::query()->where('classroom_id', $fromClassroomId)->where('status', 'active')->lockForUpdate()->get();

            foreach ($students as $student) {
                $student->update(['classroom_id' => $toClassroomId, 'status' => $toClassroomId ? 'active' : 'graduated']);
                $this->students->moveEnrollment($student, $toClassroomId ? 'promoted' : 'graduated', $batch);
            }

            $this->audit->handle($toClassroomId ? 'promote' : 'graduate', 'Classroom', $fromClassroomId, null, ['batch' => $batch, 'count' => $students->count(), 'to' => $toClassroomId]);

            return $batch;
        });
    }

    public function undo(string $batch): int
    {
        return DB::transaction(function () use ($batch): int {
            $closed = StudentEnrollment::query()->where('batch_id', $batch)->whereIn('status', ['promoted', 'graduated'])->lockForUpdate()->get();

            if ($closed->isEmpty()) {
                throw new RuntimeException('Proses ini sudah dibatalkan.');
            }

            if ($closed->first()->ended_at->addHours(self::UNDO_HOURS)->isPast()) {
                throw new RuntimeException('Batas pembatalan 24 jam sudah lewat.');
            }

            StudentEnrollment::query()->where('batch_id', $batch)->where('status', 'active')->update(['ended_at' => now(), 'status' => 'reverted']);

            foreach ($closed as $enrollment) {
                Student::query()->whereKey($enrollment->student_id)->first()?->update(['classroom_id' => $enrollment->classroom_id, 'status' => 'active']);
                $enrollment->update(['ended_at' => null, 'status' => 'active', 'batch_id' => null]);
            }

            $this->audit->handle('promote_undo', 'StudentEnrollment', null, null, ['batch' => $batch, 'count' => $closed->count()]);

            return $closed->count();
        });
    }
}
