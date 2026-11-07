<?php

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Face\FaceMatcher;
use App\Modules\Academic\Face\FaceQualityException;
use App\Modules\Academic\Models\BiometricConsent;
use App\Modules\Academic\Models\FaceEnrollment;
use App\Modules\Academic\Support\AcademicException;
use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Models\Student;
use Illuminate\Support\Facades\DB;

class EnrollFace
{
    public const MIN_PHOTOS = 3;

    public function __construct(private FaceMatcher $matcher, private ManageBiometricConsent $consents, private RecordAudit $audit) {}

    /**
     * Foto hanya dibaca untuk menghasilkan embedding; tidak ada yang disimpan (ATT-08).
     *
     * @param  list<string>  $images  Isi berkas gambar.
     */
    public function handle(Student $student, array $images, int $enrolledBy): FaceEnrollment
    {
        if (! $this->matcher->available()) {
            throw new AcademicException('Pencocokan wajah belum tersedia di server ini.');
        }

        $consent = BiometricConsent::query()->active()->where('student_id', $student->id)->first();

        if ($consent === null) {
            throw new AcademicException('Belum ada persetujuan wali yang aktif.');
        }

        if (count($images) < self::MIN_PHOTOS) {
            throw new AcademicException('Ambil minimal '.self::MIN_PHOTOS.' foto dari sudut berbeda.');
        }

        try {
            $template = $this->matcher->enroll($images);
        } catch (FaceQualityException $exception) {
            throw new AcademicException($exception->getMessage());
        }

        return DB::transaction(function () use ($student, $consent, $template, $enrolledBy): FaceEnrollment {
            $this->consents->eraseEnrollment($student);

            $enrollment = FaceEnrollment::create([
                'student_id' => $student->id,
                'consent_id' => $consent->id,
                'embedding' => $template->data,
                'model_version' => $this->matcher->modelVersion(),
                'quality_score' => $template->quality,
                'status' => 'active',
                'enrolled_by' => $enrolledBy,
                'enrolled_at' => now(),
            ]);

            $this->audit->handle('biometric.face_enrolled', 'face_enrollment', $enrollment->id, null, ['student_id' => $student->id]);

            return $enrollment;
        });
    }
}
