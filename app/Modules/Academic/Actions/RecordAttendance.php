<?php

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Events\AttendanceClosed;
use App\Modules\Academic\Models\AttendanceRecord;
use App\Modules\Academic\Models\AttendanceSession;
use App\Modules\Academic\Support\AcademicException;
use App\Modules\Academic\Support\AcademicSettings;
use App\Modules\Wallet\Actions\RecordScan;
use App\Modules\Wallet\Support\CardResolver;
use App\Modules\Wallet\Support\QrToken;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RecordAttendance
{
    public function __construct(private RecordScan $scan) {}

    /**
     * Menyimpan status batch dan menutup sesi. Boleh disimpan ulang untuk koreksi pada hari yang sama.
     *
     * @param  array<string, string>  $statuses  uuid catatan ke status
     */
    public function save(AttendanceSession $session, array $statuses, int $userId): void
    {
        $allowed = array_keys(AttendanceRecord::STATUSES);

        DB::transaction(function () use ($session, $statuses, $userId, $allowed): void {
            $locked = AttendanceSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            foreach ($locked->records()->get() as $record) {
                $status = $statuses[$record->uuid] ?? $record->status;

                if (! in_array($status, $allowed, true)) {
                    throw new AcademicException('Status absensi tidak dikenal.');
                }

                if ($status !== $record->status) {
                    $record->update(['status' => $status]);
                }
            }

            $locked->update(['closed_at' => $locked->closed_at ?? now(), 'taken_by' => $userId]);

            AttendanceClosed::dispatch($locked);
        });

        $session->refresh();
    }

    /**
     * Terlambat dihitung otomatis terhadap jam mulai; pindai ganda tidak mengubah waktu pertama (ATT-03).
     *
     * @return array{ok: bool, message: string, name: string|null, status: string|null}
     */
    public function scan(AttendanceSession $session, string $credential, ?int $deviceId = null): array
    {
        $resolved = CardResolver::resolve($credential, false);
        $card = $resolved['card'];

        if ($card === null || $resolved['problem'] !== null) {
            $this->scan->handle($card, $deviceId, 'attendance', $card === null ? 'not_found' : 'denied', $resolved['problem']);

            return ['ok' => false, 'message' => (string) $resolved['problem'], 'name' => null, 'status' => null];
        }

        if ($card->status !== 'active') {
            $this->scan->handle($card, $deviceId, 'attendance', 'blocked', 'Kartu diblokir.');

            return ['ok' => false, 'message' => 'Kartu diblokir.', 'name' => $card->student->name, 'status' => null];
        }

        if ($session->isClosed()) {
            return ['ok' => false, 'message' => 'Sesi ini sudah ditutup.', 'name' => $card->student->name, 'status' => null];
        }

        return DB::transaction(function () use ($session, $card, $resolved, $credential, $deviceId): array {
            $record = AttendanceRecord::query()->where('session_id', $session->id)->where('student_id', $card->student_id)->lockForUpdate()->first();

            if ($record === null) {
                $this->scan->handle($card, $deviceId, 'attendance', 'denied', 'Bukan peserta sesi ini.');

                return ['ok' => false, 'message' => $card->student->name.' bukan peserta sesi ini.', 'name' => $card->student->name, 'status' => null];
            }

            if ($record->scanned_at !== null) {
                return ['ok' => true, 'message' => 'Sudah tercatat.', 'name' => $card->student->name, 'status' => $record->status];
            }

            QrToken::consume($credential);

            $record->update(['status' => $this->statusNow($session), 'method' => $resolved['method'], 'scanned_at' => now()]);
            $this->scan->handle($card, $deviceId, 'attendance', 'ok', null, 'attendance_session', $session->id);

            return ['ok' => true, 'message' => AttendanceRecord::STATUSES[$record->status], 'name' => $card->student->name, 'status' => $record->status];
        });
    }

    private function statusNow(AttendanceSession $session): string
    {
        $zone = config('sanad.display_timezone');
        $start = CarbonImmutable::parse($session->session_date->toDateString().' '.$session->start_time, $zone);

        return now($zone)->greaterThan($start->addMinutes(AcademicSettings::lateTolerance())) ? 'terlambat' : 'hadir';
    }
}
