<?php

namespace App\Modules\Leave\Actions;

use App\Modules\Core\Models\Student;
use App\Modules\Leave\Events\GateEvent;
use App\Modules\Leave\Events\LeaveEvent;
use App\Modules\Leave\Models\GateLog;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Support\LeaveException;
use App\Modules\Leave\Support\LeaveSettings;
use App\Modules\Wallet\Actions\RecordScan;
use App\Modules\Wallet\Support\CardResolver;
use App\Modules\Wallet\Support\QrToken;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GateScan
{
    private const LEAVE_QR_PREFIX = 'L1.';

    private const REPEAT_SECONDS = 5;

    public function __construct(private RecordScan $scan) {}

    public static function leaveQr(LeaveRequest $leave): string
    {
        return self::LEAVE_QR_PREFIX.$leave->qr_token;
    }

    /**
     * Pindai gerbang: arah ditentukan oleh status izin santri (PRD UI/UX 5.6b).
     *
     * @return array{outcome: string, message: string, student: Student|null, leave: LeaveRequest|null}
     *                                                                                                  outcome: out, in, denied, repeat, needs_pickup
     */
    public function handle(string $credential, ?int $userId, ?int $deviceId = null, ?string $pickupName = null, ?string $pickupRelation = null): array
    {
        $credential = trim($credential);
        [$student, $method, $problem, $card, $tokenLeave] = $this->identify($credential);

        if ($student === null || $problem !== null) {
            if ($card !== null || $student !== null || $credential !== '') {
                $this->log($student, null, 'out', $method, 'denied', $problem ?? 'Tidak dikenal.', $userId, $deviceId);
            }

            if ($card !== null || $student === null) {
                $this->scan->handle($card, $deviceId, 'gate', $card === null ? 'not_found' : 'denied', $problem);
            }

            return ['outcome' => 'denied', 'message' => (string) $problem, 'student' => $student, 'leave' => null];
        }

        // Tap dobel dalam 5 detik diabaikan agar tidak membuat catatan keluar lalu masuk.
        if ($pickupName === null && ! Cache::add('gate-repeat:'.$student->id, true, self::REPEAT_SECONDS)) {
            return ['outcome' => 'repeat', 'message' => 'Sudah terbaca.', 'student' => $student, 'leave' => null];
        }

        $result = DB::transaction(function () use ($student, $method, $tokenLeave, $userId, $deviceId, $pickupName, $pickupRelation): array {
            Student::query()->whereKey($student->id)->lockForUpdate()->first();

            $outside = LeaveRequest::query()->where('student_id', $student->id)->outside()->lockForUpdate()->first();

            if ($outside !== null) {
                return $this->recordIn($outside, $student, $method, $userId, $deviceId, null);
            }

            $leave = $this->usableLeave($student, $tokenLeave);

            if ($leave === null) {
                $reason = $this->denyReason($student);
                $this->log($student, null, 'out', $method, 'denied', $reason, $userId, $deviceId);

                return ['outcome' => 'denied', 'message' => $reason, 'student' => $student, 'leave' => null];
            }

            if ($leave->type === 'pulang' && blank($pickupName)) {
                return ['outcome' => 'needs_pickup', 'message' => 'Catat penjemput.', 'student' => $student, 'leave' => $leave];
            }

            return $this->recordOut($leave, $student, $method, $userId, $deviceId, $pickupName, $pickupRelation, null);
        });

        if ($card !== null) {
            $this->scan->handle($card, $deviceId, 'gate', in_array($result['outcome'], ['out', 'in'], true) ? 'ok' : 'denied', $result['outcome'] === 'denied' ? $result['message'] : null);
        }

        if (in_array($result['outcome'], ['out', 'in'], true)) {
            QrToken::consume($credential);
        }

        return $result;
    }

    /**
     * Kartu hilang atau reader rusak: wajib izin aktif dan alasan; masuk antrean tinjauan (GTE-03).
     *
     * @return array{outcome: string, message: string, student: Student|null, leave: LeaveRequest|null}
     */
    public function manual(LeaveRequest $leave, string $reason, int $userId, ?string $pickupName = null, ?string $pickupRelation = null): array
    {
        if (trim($reason) === '') {
            throw new LeaveException('Alasan wajib diisi.');
        }

        return DB::transaction(function () use ($leave, $reason, $userId, $pickupName, $pickupRelation): array {
            $locked = LeaveRequest::query()->whereKey($leave->id)->lockForUpdate()->firstOrFail();

            if ($locked->isOutside()) {
                return $this->recordIn($locked, $locked->student, 'manual', $userId, null, trim($reason));
            }

            if ($locked->status !== 'approved' || ! $this->inWindow($locked)) {
                throw new LeaveException('Izin ini tidak aktif sekarang.');
            }

            if ($locked->type === 'pulang' && blank($pickupName)) {
                throw new LeaveException('Izin pulang wajib mencatat penjemput.');
            }

            return $this->recordOut($locked, $locked->student, 'manual', $userId, null, $pickupName, $pickupRelation, trim($reason));
        });
    }

    /**
     * Keluar darurat tanpa izin, hanya musyrif atau admin. Dibuatkan izin agar santri muncul di papan (GTE-08).
     */
    public function override(Student $student, string $reason, CarbonImmutable $returnAt, int $userId): LeaveRequest
    {
        if (trim($reason) === '') {
            throw new LeaveException('Alasan wajib diisi.');
        }

        if ($returnAt->isPast()) {
            throw new LeaveException('Batas kembali harus setelah sekarang.');
        }

        return DB::transaction(function () use ($student, $reason, $returnAt, $userId): LeaveRequest {
            if (LeaveRequest::query()->where('student_id', $student->id)->outside()->lockForUpdate()->exists()) {
                throw new LeaveException('Santri ini tercatat sedang di luar.');
            }

            $leave = LeaveRequest::create([
                'student_id' => $student->id,
                'type' => 'keluar',
                'reason' => 'Darurat: '.trim($reason),
                'start_at' => now(),
                'end_at' => $returnAt,
                'status' => 'out',
                'requested_by' => $userId,
                'approved_by' => $userId,
                'approved_at' => now(),
                'out_at' => now(),
                'qr_token' => Str::random(40),
            ]);

            $log = $this->log($student, $leave->id, 'out', 'manual', 'override', null, $userId, null, null, null, trim($reason));
            GateEvent::dispatch($log);

            return $leave;
        });
    }

    /**
     * @return array{0: Student|null, 1: string, 2: string|null, 3: mixed, 4: LeaveRequest|null}
     */
    private function identify(string $credential): array
    {
        if (str_starts_with($credential, self::LEAVE_QR_PREFIX)) {
            $leave = LeaveRequest::query()->where('qr_token', substr($credential, strlen(self::LEAVE_QR_PREFIX)))->with('student')->first();

            return $leave === null
                ? [null, 'qr', 'QR izin tidak dikenali.', null, null]
                : [$leave->student, 'qr', null, null, $leave];
        }

        $resolved = CardResolver::resolve($credential, false);
        $card = $resolved['card'];

        if ($card === null || $resolved['problem'] !== null) {
            return [$card?->student, $resolved['method'], $resolved['problem'], $card, null];
        }

        if ($card->status !== 'active') {
            return [$card->student, $resolved['method'], 'Kartu diblokir.', $card, null];
        }

        return [$card->student, $resolved['method'], null, $card, null];
    }

    private function usableLeave(Student $student, ?LeaveRequest $tokenLeave): ?LeaveRequest
    {
        if ($tokenLeave !== null) {
            return $tokenLeave->status === 'approved' && $this->inWindow($tokenLeave) ? $tokenLeave : null;
        }

        return LeaveRequest::query()->where('student_id', $student->id)->where('status', 'approved')->orderBy('start_at')->get()
            ->first(fn (LeaveRequest $leave) => $this->inWindow($leave));
    }

    private function inWindow(LeaveRequest $leave): bool
    {
        return $leave->start_at->subMinutes(LeaveSettings::exitTolerance())->lessThanOrEqualTo(now()) && $leave->end_at->greaterThanOrEqualTo(now());
    }

    private function denyReason(Student $student): string
    {
        $latest = LeaveRequest::query()->where('student_id', $student->id)->latest('id')->first();

        return match (true) {
            $latest === null => 'Tidak ada izin.',
            $latest->status === 'pending' => 'Izin belum disetujui.',
            $latest->status === 'rejected' => 'Izin ditolak.',
            $latest->status === 'approved' && $latest->start_at->isFuture() => 'Izin belum berlaku. Mulai '.$latest->start_at->setTimezone(config('sanad.display_timezone'))->format('H.i').'.',
            $latest->status === 'approved' => 'Izin sudah kedaluwarsa.',
            default => 'Tidak ada izin aktif.',
        };
    }

    /**
     * @return array{outcome: string, message: string, student: Student, leave: LeaveRequest}
     */
    private function recordOut(LeaveRequest $leave, Student $student, string $method, ?int $userId, ?int $deviceId, ?string $pickupName, ?string $pickupRelation, ?string $note): array
    {
        $leave->update(['status' => 'out', 'out_at' => now()]);
        $log = $this->log($student, $leave->id, 'out', $method, 'ok', null, $userId, $deviceId, $pickupName, $pickupRelation, $note);
        GateEvent::dispatch($log);

        return ['outcome' => 'out', 'message' => 'Harus kembali '.$leave->end_at->setTimezone(config('sanad.display_timezone'))->translatedFormat('j M, H.i').'.', 'student' => $student, 'leave' => $leave];
    }

    /**
     * @return array{outcome: string, message: string, student: Student, leave: LeaveRequest}
     */
    private function recordIn(LeaveRequest $leave, Student $student, string $method, ?int $userId, ?int $deviceId, ?string $note): array
    {
        $late = now()->greaterThan($leave->end_at->addMinutes(LeaveSettings::returnTolerance()));
        $wasMarked = $leave->status === 'late';

        $leave->update(['status' => $late ? 'late' : 'returned', 'returned_at' => now()]);
        $log = $this->log($student, $leave->id, 'in', $method, 'ok', null, $userId, $deviceId, null, null, $note);
        GateEvent::dispatch($log);

        if ($late && ! $wasMarked) {
            LeaveEvent::dispatch($leave, 'late');
        }

        return ['outcome' => 'in', 'message' => $late ? 'Terlambat kembali.' : 'Tepat waktu.', 'student' => $student, 'leave' => $leave];
    }

    private function log(?Student $student, ?int $leaveId, string $direction, string $method, string $result, ?string $reason, ?int $userId, ?int $deviceId, ?string $pickupName = null, ?string $pickupRelation = null, ?string $note = null): GateLog
    {
        $log = GateLog::create([
            'student_id' => $student?->id,
            'leave_request_id' => $leaveId,
            'direction' => $direction,
            'method' => $method,
            'result' => $result,
            'deny_reason' => $reason,
            'recorded_by' => $userId,
            'device_id' => $deviceId,
            'occurred_at' => now(),
            'pickup_name' => filled($pickupName) ? trim((string) $pickupName) : null,
            'pickup_relation' => filled($pickupRelation) ? trim((string) $pickupRelation) : null,
            'note' => $note,
        ]);

        if ($result === 'denied' && $student !== null) {
            GateEvent::dispatch($log);
        }

        return $log;
    }
}
