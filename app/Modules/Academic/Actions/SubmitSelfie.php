<?php

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Models\AttendanceRecord;
use App\Modules\Academic\Models\AttendanceSession;
use App\Modules\Academic\Support\AcademicException;
use App\Modules\Academic\Support\AcademicSettings;
use App\Modules\Academic\Support\AttendanceClock;
use App\Modules\Core\Models\Student;
use App\Modules\Finance\Actions\CompressProofImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Lottery;

class SubmitSelfie
{
    public const SAMPLE_PERCENT = 5;

    public function __construct(private CompressProofImage $images) {}

    /**
     * Kode ditetapkan server sekali per catatan, jadi membuka ulang halaman tidak mengganti kodenya.
     */
    public function challengeFor(AttendanceRecord $record): string
    {
        if ($record->challenge_code === null) {
            $record->update(['challenge_code' => str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT)]);
        }

        return $record->challenge_code;
    }

    /**
     * Tidak ada pencocokan otomatis. Waktu selalu dari server (PRD Sistem 8.9b).
     *
     * @param  array{lat?: float|string|null, lng?: float|string|null, delayed?: bool, from_file?: bool}  $context
     */
    public function handle(AttendanceSession $session, Student $student, UploadedFile $photo, array $context = []): AttendanceRecord
    {
        if (! in_array('selfie', $session->allowed_modes ?? [], true)) {
            throw new AcademicException('Sesi ini tidak memakai absen selfie.');
        }

        if ($session->isClosed()) {
            throw new AcademicException('Sesi ini sudah ditutup. Hubungi ustadz atau musyrif.');
        }

        $lat = filled($context['lat'] ?? null) ? (float) $context['lat'] : null;
        $lng = filled($context['lng'] ?? null) ? (float) $context['lng'] : null;
        $reasons = $this->flagReasons($lat, $lng);

        if ($context['delayed'] ?? false) {
            $reasons[] = 'Terkirim tertunda karena putus koneksi.';
        }

        if ($context['from_file'] ?? false) {
            $reasons[] = 'Foto dari pilihan berkas, tanpa kode acak.';
        }

        return DB::transaction(function () use ($session, $student, $photo, $lat, $lng, $reasons): AttendanceRecord {
            $record = AttendanceRecord::query()->where('session_id', $session->id)->where('student_id', $student->id)->lockForUpdate()->first();

            if ($record === null) {
                throw new AcademicException('Anda bukan peserta sesi ini.');
            }

            if ($record->scanned_at !== null && $record->review_status !== 'rejected') {
                return $record;
            }

            if ($record->evidence_path !== null) {
                Storage::disk('local')->delete($record->evidence_path);
            }

            $flagged = $reasons !== [];

            $record->update([
                'status' => AttendanceClock::status($session),
                'method' => 'selfie',
                'scanned_at' => now(),
                'evidence_path' => $this->images->store($photo, 'attendance-evidence'),
                'geo_lat' => $lat,
                'geo_lng' => $lng,
                'review_status' => $flagged ? 'flagged' : 'none',
                'sampled' => ! $flagged && Lottery::odds(self::SAMPLE_PERCENT, 100)->choose(),
                'reviewed_by' => null,
                'reviewed_at' => null,
                'note' => $flagged ? implode(' ', $reasons) : null,
            ]);

            return $record;
        });
    }

    /**
     * @return list<string>
     */
    private function flagReasons(?float $lat, ?float $lng): array
    {
        $fence = AcademicSettings::geofence();

        if (! $fence['enabled'] || $fence['lat'] === null || $fence['lng'] === null) {
            return [];
        }

        if ($lat === null || $lng === null) {
            return ['Lokasi tidak tersedia.'];
        }

        $distance = self::distanceInMeters($lat, $lng, $fence['lat'], $fence['lng']);

        return $distance > $fence['radius'] ? ['Di luar area, sekitar '.round($distance).' m dari pusat.'] : [];
    }

    public static function distanceInMeters(float $latA, float $lngA, float $latB, float $lngB): float
    {
        $latDelta = deg2rad($latB - $latA);
        $lngDelta = deg2rad($lngB - $lngA);
        $arc = sin($latDelta / 2) ** 2 + cos(deg2rad($latA)) * cos(deg2rad($latB)) * sin($lngDelta / 2) ** 2;

        return 6371000 * 2 * atan2(sqrt($arc), sqrt(1 - $arc));
    }
}
