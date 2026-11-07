<?php

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Models\AttendanceRecord;
use App\Modules\Academic\Support\AcademicException;
use App\Modules\Academic\Support\AttendanceClock;
use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReviewAttendance
{
    public function __construct(private RecordAudit $audit) {}

    /**
     * @return Collection<int, AttendanceRecord>
     */
    public function queueFor(User $user, int $limit = 50): Collection
    {
        return AttendanceRecord::query()->awaitingReview()
            ->with(['student:id,name,nis', 'session.dormitory', 'session.schedule.teacher', 'session.classroom.homeroomTeacher'])
            ->orderBy('scanned_at')->limit(500)->get()
            ->filter(fn (AttendanceRecord $record) => $record->session->canBeTakenBy($user))
            ->take($limit)->values();
    }

    public function decide(AttendanceRecord $record, string $decision, ?string $note, User $user): void
    {
        if (! $record->session->canBeTakenBy($user)) {
            throw new AcademicException('Anda tidak berhak meninjau absensi ini.');
        }

        if (! in_array($decision, ['approve', 'reject'], true)) {
            throw new AcademicException('Keputusan tidak dikenal.');
        }

        if ($decision === 'reject' && blank($note)) {
            throw new AcademicException('Isi alasan penolakan.');
        }

        DB::transaction(function () use ($record, $decision, $note, $user): void {
            $locked = AttendanceRecord::query()->awaitingReview()->whereKey($record->id)->lockForUpdate()->first();

            if ($locked === null) {
                throw new AcademicException('Absensi ini sudah ditinjau.');
            }

            $before = ['status' => $locked->status, 'review_status' => $locked->review_status];
            $locked->update($decision === 'approve'
                ? [
                    // Zona abu-abu belum dihitung hadir sebelum dikonfirmasi; waktunya tetap saat dipindai.
                    'status' => $locked->review_status === 'pending' ? AttendanceClock::status($record->session, $locked->scanned_at) : $locked->status,
                    'review_status' => 'approved',
                    'sampled' => false,
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                ]
                : [
                    'status' => 'alpa',
                    'review_status' => 'rejected',
                    'sampled' => false,
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                    'note' => trim((string) $note),
                ]);

            $this->audit->handle('attendance.reviewed', 'attendance_record', $locked->id, $before, ['status' => $locked->status, 'review_status' => $locked->review_status]);
        });
    }
}
