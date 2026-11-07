<?php

namespace App\Modules\Admission\Actions;

use App\Modules\Admission\Models\AdmissionResult;
use App\Modules\Admission\Models\AdmissionStep;
use App\Modules\Admission\Models\Applicant;
use App\Modules\Admission\Support\AdmissionException;
use App\Modules\Exam\Models\ExamAttempt;
use Illuminate\Support\Facades\DB;

class ScoreApplicant
{
    public function save(Applicant $applicant, AdmissionStep $step, float $score, ?string $notes, ?int $userId): void
    {
        if ($step->period_id !== $applicant->period_id) {
            throw new AdmissionException('Tahap ini bukan milik periode pendaftar.');
        }

        if ($score < 0 || $score > 100) {
            throw new AdmissionException('Nilai harus 0 sampai 100.');
        }

        if ($applicant->status !== 'in_selection') {
            throw new AdmissionException('Nilai hanya bisa diisi saat dalam seleksi.');
        }

        DB::transaction(function () use ($applicant, $step, $score, $notes, $userId): void {
            AdmissionResult::query()->updateOrCreate(['applicant_id' => $applicant->id, 'step_id' => $step->id], ['score' => $score, 'notes' => $notes, 'evaluated_by' => $userId]);
            $this->recalculate($applicant);
        });
    }

    /**
     * Skor akhir = jumlah (skor x bobot) / jumlah bobot, hanya bila semua tahap sudah bernilai (PRD Sistem 8.13).
     */
    public function recalculate(Applicant $applicant): void
    {
        $steps = $applicant->period->steps()->get();
        $results = $applicant->results()->pluck('score', 'step_id');
        $weight = (int) $steps->sum('weight');
        $complete = $steps->isNotEmpty() && $weight > 0 && $steps->every(fn (AdmissionStep $step) => isset($results[$step->id]));

        $applicant->update([
            'total_score' => $complete ? round($steps->sum(fn (AdmissionStep $step) => (float) $results[$step->id] * $step->weight) / $weight, 2) : null,
        ]);
    }

    /**
     * Nilai ujian seleksi masuk sendiri ke tahapnya saat selesai dinilai (EXM-12).
     */
    public function syncFromExam(ExamAttempt $attempt): void
    {
        if ($attempt->applicant_id === null || $attempt->status !== 'graded') {
            return;
        }

        $applicant = Applicant::query()->find($attempt->applicant_id);
        $step = $applicant?->period->steps()->where('exam_id', $attempt->exam_id)->first();

        if ($step === null || $applicant->status !== 'in_selection') {
            return;
        }

        AdmissionResult::query()->updateOrCreate(['applicant_id' => $applicant->id, 'step_id' => $step->id], ['score' => (float) $attempt->score, 'notes' => 'Dari ujian online']);
        $this->recalculate($applicant);
    }
}
