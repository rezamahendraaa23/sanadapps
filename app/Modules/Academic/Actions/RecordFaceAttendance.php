<?php

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Face\FaceInput;
use App\Modules\Academic\Face\FaceMatcher;
use App\Modules\Academic\Face\FaceQualityException;
use App\Modules\Academic\Models\AttendanceRecord;
use App\Modules\Academic\Models\AttendanceSession;
use App\Modules\Academic\Models\FaceEnrollment;
use App\Modules\Academic\Support\AcademicSettings;
use App\Modules\Academic\Support\AttendanceClock;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Finance\Actions\CompressProofImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordFaceAttendance
{
    public const MAX_LIVENESS_FAILURES = 3;

    private const CHALLENGES = ['Kedipkan mata', 'Tengok ke kiri', 'Tengok ke kanan'];

    public function __construct(private FaceMatcher $matcher, private ModuleRegistry $modules, private CompressProofImage $images) {}

    /**
     * @return array{token: string, challenge: string}
     */
    public function challenge(string $subject): array
    {
        $token = Str::random(40);
        $challenge = Arr::random(self::CHALLENGES);
        Cache::put('face-liveness:'.$token, ['challenge' => $challenge, 'subject' => $subject], now()->addMinutes(2));

        return ['token' => $token, 'challenge' => $challenge];
    }

    /**
     * Skor di atas ambang atas hadir otomatis, di antara dua ambang menunggu musyrif, di bawahnya ditolak
     * dan ditawari kartu atau manual (PRD Sistem 8.9b).
     *
     * @return array{ok: bool, outcome: string, message: string, name: string|null, fallback: bool}
     */
    public function scan(AttendanceSession $session, FaceInput $input, ?UploadedFile $evidence, string $token, string $subject): array
    {
        if (! $this->modules->enabled('face_attendance') || ! $this->matcher->available() || ! in_array('face', $session->modes(), true)) {
            return $this->result(false, 'unavailable', 'Absen wajah tidak tersedia untuk sesi ini. Pakai kartu atau manual.', fallback: true);
        }

        if ($session->isClosed()) {
            return $this->result(false, 'closed', 'Sesi ini sudah ditutup.');
        }

        $challenge = Cache::pull('face-liveness:'.$token);

        if (! is_array($challenge) || $challenge['subject'] !== $subject) {
            return $this->result(false, 'expired', 'Tantangan kedaluwarsa. Coba lagi.');
        }

        if (! $this->matcher->isLive($challenge['challenge'], $input)) {
            $failures = Cache::increment($this->failureKey($session, $subject));
            Cache::put($this->failureKey($session, $subject), $failures, now()->addMinutes(15));

            return $failures >= self::MAX_LIVENESS_FAILURES
                ? $this->result(false, 'liveness', 'Pemeriksaan gagal 3 kali. Pakai kartu atau manual.', fallback: true)
                : $this->result(false, 'liveness', 'Pemeriksaan gerakan gagal. Coba lagi.');
        }

        Cache::forget($this->failureKey($session, $subject));

        $candidates = FaceEnrollment::query()->where('status', 'active')
            ->whereHas('consent', fn ($query) => $query->whereNull('revoked_at'))
            ->whereHas('student', fn ($query) => $query->where('status', 'active'))
            ->whereIn('student_id', AttendanceRecord::query()->where('session_id', $session->id)->select('student_id'))
            ->with('student:id,name')->get();

        if ($candidates->isEmpty()) {
            return $this->result(false, 'no_candidates', 'Belum ada wajah terdaftar untuk sesi ini. Pakai kartu atau manual.', fallback: true);
        }

        try {
            $match = $this->matcher->identify($input, $candidates->mapWithKeys(fn (FaceEnrollment $candidate) => [$candidate->student_id => $candidate->embedding])->all());
        } catch (FaceQualityException $exception) {
            return $this->result(false, 'quality', $exception->getMessage());
        }

        $bestScore = $match?->score ?? 0.0;
        $best = $match === null ? null : $candidates->firstWhere('student_id', $match->studentId);

        $thresholds = AcademicSettings::faceThresholds();

        if ($best === null || $bestScore < $thresholds['lower']) {
            return $this->result(false, 'rejected', 'Wajah tidak dikenali. Pakai kartu atau manual.', fallback: true);
        }

        // Dua santri hampir sama mirip, misalnya kakak beradik: manusia yang memutuskan.
        $confident = $bestScore >= $thresholds['upper'] && ! $match->ambiguous;

        return DB::transaction(function () use ($session, $best, $bestScore, $confident, $evidence): array {
            $record = AttendanceRecord::query()->where('session_id', $session->id)->where('student_id', $best->student_id)->lockForUpdate()->firstOrFail();

            if ($record->scanned_at !== null && $record->review_status !== 'rejected') {
                return $this->result(true, 'already', 'Sudah tercatat.', $best->student->name);
            }

            $record->update($confident
                ? ['status' => AttendanceClock::status($session), 'method' => 'face', 'scanned_at' => now(), 'match_score' => $bestScore, 'review_status' => 'none', 'note' => null]
                // Foto hanya disimpan bila manusia perlu melihatnya.
                : ['method' => 'face', 'scanned_at' => now(), 'match_score' => $bestScore, 'review_status' => 'pending', 'evidence_path' => $evidence ? $this->images->store($evidence, 'attendance-evidence') : null, 'note' => null]);

            return $confident
                ? $this->result(true, 'present', 'Hadir tercatat.', $best->student->name)
                : $this->result(true, 'pending', 'Menunggu konfirmasi musyrif.', $best->student->name);
        });
    }

    private function failureKey(AttendanceSession $session, string $subject): string
    {
        return 'face-liveness-fail:'.$session->id.':'.$subject;
    }

    /**
     * @return array{ok: bool, outcome: string, message: string, name: string|null, fallback: bool}
     */
    private function result(bool $ok, string $outcome, string $message, ?string $name = null, bool $fallback = false): array
    {
        return compact('ok', 'outcome', 'message', 'name', 'fallback');
    }
}
