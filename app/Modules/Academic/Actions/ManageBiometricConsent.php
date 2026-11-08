<?php

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Models\BiometricConsent;
use App\Modules\Academic\Models\FaceEnrollment;
use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Models\Guardian;
use App\Modules\Core\Models\InAppNotification;
use App\Modules\Core\Models\Student;
use App\Support\SchoolDate;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ManageBiometricConsent
{
    public function __construct(private RecordAudit $audit) {}

    public function grant(Student $student, ?Guardian $guardian, string $method, ?string $documentPath, ?string $ip, ?int $recordedBy, ?string $note = null): BiometricConsent
    {
        try {
            return DB::transaction(function () use ($student, $guardian, $method, $documentPath, $ip, $recordedBy, $note): BiometricConsent {
                $active = BiometricConsent::query()->active()->where('student_id', $student->id)->first();

                if ($active !== null) {
                    return $active;
                }

                $consent = BiometricConsent::create([
                    'student_id' => $student->id,
                    'guardian_id' => $guardian?->id,
                    'method' => $method,
                    'document_path' => $documentPath,
                    'granted_at' => now(),
                    'ip' => $ip,
                    'recorded_by' => $recordedBy,
                    'note' => $note,
                ]);

                $this->audit->handle('biometric.consent_granted', 'biometric_consent', $consent->id, null, ['student_id' => $student->id, 'method' => $method]);

                return $consent;
            });
        } catch (UniqueConstraintViolationException) {
            return BiometricConsent::query()->active()->where('student_id', $student->id)->firstOrFail();
        }
    }

    /**
     * Data wajah dihapus saat itu juga; catatan absensi lama tetap (PRD Sistem 8.9b).
     */
    public function revoke(Student $student): void
    {
        DB::transaction(function () use ($student): void {
            foreach (BiometricConsent::query()->active()->where('student_id', $student->id)->lockForUpdate()->get() as $consent) {
                $consent->update(['revoked_at' => now()]);
                $this->audit->handle('biometric.consent_revoked', 'biometric_consent', $consent->id);
            }

            $this->eraseEnrollment($student);
        });
    }

    public function eraseEnrollment(Student $student): void
    {
        foreach (FaceEnrollment::query()->where('student_id', $student->id)->where('status', 'active')->get() as $enrollment) {
            $enrollment->update(['status' => 'revoked', 'embedding' => null, 'revoked_at' => now()]);
            $this->audit->handle('biometric.face_erased', 'face_enrollment', $enrollment->id);
        }
    }

    /**
     * Job harian: santri yang keluar atau lulus, atau persetujuannya tidak aktif lagi.
     */
    public function sweep(): int
    {
        $count = 0;

        FaceEnrollment::query()->where('status', 'active')->with(['student', 'consent'])->each(function (FaceEnrollment $enrollment) use (&$count): void {
            if ($enrollment->student->status !== 'active' || $enrollment->consent->revoked_at !== null) {
                $this->eraseEnrollment($enrollment->student);
                $count++;
            }
        });

        return $count;
    }

    public function requestFromGuardians(Student $student): int
    {
        $count = 0;

        foreach ($student->guardians()->whereNotNull('user_id')->get() as $guardian) {
            InAppNotification::create([
                'user_id' => $guardian->user_id,
                'title' => 'Mohon keputusan data wajah untuk '.$student->name,
                'body' => 'Absensi wajah butuh persetujuan Anda. Tanpa persetujuan, santri tetap absen dengan kartu atau manual.',
                'link' => route('wali.face-consent'),
                'dedupe_key' => 'face-consent:'.$student->id.':'.$guardian->user_id.':'.SchoolDate::today()->toDateString(),
            ]);
            $count++;
        }

        return $count;
    }
}
