<?php

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Models\Assessment;
use App\Modules\Academic\Models\Grade;
use App\Modules\Academic\Models\ReportCard;
use App\Modules\Academic\Support\AcademicException;
use App\Modules\Core\Actions\RecordAudit;
use Illuminate\Support\Facades\DB;

class SaveGrades
{
    public function __construct(private RecordAudit $audit) {}

    /**
     * Nilai santri yang rapornya sudah terbit terkunci; mengubahnya wajib alasan dan tercatat (GRD-04).
     *
     * @param  array<int, string|null>  $scores  id santri ke nilai; kosong berarti belum dinilai
     * @return int Jumlah nilai yang berubah.
     */
    public function handle(Assessment $assessment, array $scores, ?string $unlockReason = null): int
    {
        $locked = ReportCard::query()->where('term_id', $assessment->term_id)->where('status', 'published')->whereIn('student_id', array_keys($scores))->pluck('student_id')->all();

        return DB::transaction(function () use ($assessment, $scores, $locked, $unlockReason): int {
            $existing = Grade::query()->where('assessment_id', $assessment->id)->lockForUpdate()->get()->keyBy('student_id');
            $changed = 0;

            foreach ($scores as $studentId => $raw) {
                $current = $existing[$studentId] ?? null;
                $value = is_string($raw) ? trim(str_replace(',', '.', $raw)) : $raw;

                if ($value === null || $value === '') {
                    $same = $current === null;
                } else {
                    if (! is_numeric($value) || (float) $value < 0 || (float) $value > $assessment->max_score) {
                        throw new AcademicException("Nilai harus antara 0 dan {$assessment->max_score}.");
                    }

                    $same = $current !== null && abs($current->score - (float) $value) < 0.001;
                }

                if ($same) {
                    continue;
                }

                if (in_array($studentId, $locked, true)) {
                    if (blank($unlockReason)) {
                        throw new AcademicException('Rapor sudah terbit, nilai terkunci. Isi alasan untuk mengubahnya.');
                    }

                    $this->audit->handle('grade_unlock', 'Grade', $current?->id, ['score' => $current?->score], ['score' => $value, 'student_id' => $studentId, 'assessment_id' => $assessment->id, 'reason' => trim((string) $unlockReason)]);
                }

                if ($value === null || $value === '') {
                    $current->delete();
                } elseif ($current !== null) {
                    $current->update(['score' => (float) $value]);
                } else {
                    Grade::create(['assessment_id' => $assessment->id, 'student_id' => $studentId, 'score' => (float) $value]);
                }

                $changed++;
            }

            return $changed;
        });
    }
}
