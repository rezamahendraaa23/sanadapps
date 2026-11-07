<?php

namespace App\Modules\Leave\Actions;

use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\User;
use App\Modules\Leave\Events\LeaveEvent;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Support\LeaveException;
use App\Modules\Leave\Support\LeaveSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ManageLeave
{
    public function request(Student $student, string $type, string $reason, CarbonImmutable $start, CarbonImmutable $end, int $userId): LeaveRequest
    {
        if (! isset(LeaveRequest::TYPES[$type])) {
            throw new LeaveException('Jenis izin tidak dikenal.');
        }

        if (trim($reason) === '') {
            throw new LeaveException('Alasan wajib diisi.');
        }

        if ($end->lessThanOrEqualTo($start)) {
            throw new LeaveException('Waktu kembali harus setelah waktu keluar.');
        }

        if ($end->isPast()) {
            throw new LeaveException('Waktu kembali sudah lewat.');
        }

        $overlap = LeaveRequest::query()->where('student_id', $student->id)
            ->whereIn('status', ['pending', 'approved', 'out'])
            ->where('start_at', '<', $end)->where('end_at', '>', $start)
            ->exists();

        if ($overlap) {
            throw new LeaveException('Sudah ada izin lain pada rentang waktu ini.');
        }

        return DB::transaction(function () use ($student, $type, $reason, $start, $end, $userId): LeaveRequest {
            $leave = LeaveRequest::create([
                'student_id' => $student->id,
                'type' => $type,
                'reason' => trim($reason),
                'start_at' => $start,
                'end_at' => $end,
                'requested_by' => $userId,
                'qr_token' => Str::random(40),
            ]);

            LeaveEvent::dispatch($leave, 'requested');

            return $leave;
        });
    }

    /**
     * Bila dua tingkat aktif, persetujuan musyrif diteruskan ke pengelola (LEV-02).
     */
    public function approve(LeaveRequest $leave, User $user): void
    {
        $this->change($leave, function (LeaveRequest $locked) use ($user): void {
            if ($locked->status !== 'pending') {
                throw new LeaveException('Izin ini sudah diputuskan.');
            }

            if (LeaveSettings::twoLevel() && ! $user->can('leave.manage')) {
                $locked->update(['first_approved_by' => $user->id]);

                return;
            }

            $locked->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()]);
            LeaveEvent::dispatch($locked, 'decided');
        });
    }

    public function reject(LeaveRequest $leave, string $reason, User $user): void
    {
        if (trim($reason) === '') {
            throw new LeaveException('Alasan penolakan wajib diisi.');
        }

        $this->change($leave, function (LeaveRequest $locked) use ($reason, $user): void {
            if ($locked->status !== 'pending') {
                throw new LeaveException('Izin ini sudah diputuskan.');
            }

            $locked->update(['status' => 'rejected', 'reject_reason' => trim($reason), 'approved_by' => $user->id, 'approved_at' => now()]);
            LeaveEvent::dispatch($locked, 'decided');
        });
    }

    public function cancel(LeaveRequest $leave): void
    {
        $this->change($leave, function (LeaveRequest $locked): void {
            if ($locked->status !== 'pending') {
                throw new LeaveException('Hanya izin yang belum disetujui yang bisa dibatalkan.');
            }

            $locked->update(['status' => 'cancelled']);
        });
    }

    /**
     * Job berkala: izin lewat batas ditandai terlambat dan diberitahukan sekali (LEV-04).
     *
     * @return int Jumlah yang baru ditandai.
     */
    public function markLate(): int
    {
        $limit = now()->subMinutes(LeaveSettings::returnTolerance());
        $count = 0;

        LeaveRequest::query()->where('status', 'out')->whereNull('returned_at')->where('end_at', '<', $limit)->each(function (LeaveRequest $leave) use (&$count): void {
            $this->change($leave, function (LeaveRequest $locked) use (&$count): void {
                if ($locked->status === 'out' && $locked->returned_at === null) {
                    $locked->update(['status' => 'late', 'late_notified_at' => now()]);
                    LeaveEvent::dispatch($locked, 'late');
                    $count++;
                }
            });
        });

        return $count;
    }

    private function change(LeaveRequest $leave, callable $callback): void
    {
        DB::transaction(function () use ($leave, $callback): void {
            $callback(LeaveRequest::query()->whereKey($leave->id)->lockForUpdate()->firstOrFail());
        });

        $leave->refresh();
    }
}
