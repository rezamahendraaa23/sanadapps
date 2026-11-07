<?php

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Events\ReportCardPublished;
use App\Modules\Academic\Models\Assessment;
use App\Modules\Academic\Models\GradeComponent;
use App\Modules\Academic\Models\ReportCard;
use App\Modules\Academic\Models\ReportCardItem;
use App\Modules\Academic\Models\Subject;
use App\Modules\Academic\Support\AcademicException;
use App\Modules\Academic\Support\AcademicSettings;
use App\Modules\Core\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ManageReportCards
{
    /**
     * Nilai akhir mapel = jumlah (rata-rata komponen x bobot) dibagi jumlah bobot komponen yang ada
     * penilaiannya (PRD Sistem 8.10). Nilai dinormalkan ke skala 100 sesuai nilai maksimum penilaian.
     *
     * @return array<int, float> id mapel ke nilai akhir
     */
    public function finalScores(Student $student, int $termId, int $classroomId): array
    {
        $rows = DB::table('grades')
            ->join('assessments', 'assessments.id', '=', 'grades.assessment_id')
            ->where('grades.student_id', $student->id)->where('assessments.term_id', $termId)->where('assessments.classroom_id', $classroomId)
            ->selectRaw('assessments.subject_id, assessments.component_id, AVG(grades.score * 100.0 / assessments.max_score) AS average')
            ->groupBy('assessments.subject_id', 'assessments.component_id')
            ->get()->groupBy('subject_id');

        $weights = GradeComponent::query()->pluck('weight', 'id');
        $scores = [];

        foreach ($rows as $subjectId => $components) {
            $sum = 0.0;
            $weightSum = 0;

            foreach ($components as $component) {
                $weight = (int) ($weights[$component->component_id] ?? 0);
                $sum += (float) $component->average * $weight;
                $weightSum += $weight;
            }

            if ($weightSum > 0) {
                $scores[(int) $subjectId] = round($sum / $weightSum, 2);
            }
        }

        return $scores;
    }

    /**
     * Membuat atau menyegarkan draf; rapor yang sudah terbit tidak disentuh.
     *
     * @return int Jumlah rapor draf.
     */
    public function generate(int $termId, int $classroomId): int
    {
        $total = (int) GradeComponent::query()->where('is_active', true)->sum('weight');

        if ($total !== 100) {
            throw new AcademicException("Total bobot komponen nilai harus 100%, sekarang {$total}%.");
        }

        $students = Student::query()->where('status', 'active')->where('classroom_id', $classroomId)->get();

        if ($students->isEmpty()) {
            throw new AcademicException('Kelas ini belum punya santri aktif.');
        }

        if (! Assessment::query()->where('term_id', $termId)->where('classroom_id', $classroomId)->exists()) {
            throw new AcademicException('Belum ada penilaian untuk kelas ini.');
        }

        $subjects = Subject::query()->pluck('kkm', 'id');
        $count = 0;

        DB::transaction(function () use ($students, $termId, $classroomId, $subjects, &$count): void {
            foreach ($students as $student) {
                $card = ReportCard::query()->firstOrCreate(['student_id' => $student->id, 'term_id' => $termId], ['classroom_id' => $classroomId]);

                if ($card->status === 'published') {
                    continue;
                }

                $card->items()->delete();

                foreach ($this->finalScores($student, $termId, $classroomId) as $subjectId => $score) {
                    ReportCardItem::create([
                        'report_card_id' => $card->id,
                        'subject_id' => $subjectId,
                        'final_score' => $score,
                        'predicate' => AcademicSettings::predicate($score),
                        'remark' => $score < ($subjects[$subjectId] ?? 0) ? 'Di bawah KKM' : null,
                    ]);
                }

                $count++;
            }
        });

        return $count;
    }

    /**
     * @param  Collection<int, ReportCard>  $cards
     * @return int Jumlah yang baru terbit.
     */
    public function publish(Collection $cards): int
    {
        $count = 0;

        DB::transaction(function () use ($cards, &$count): void {
            foreach ($cards as $card) {
                $locked = ReportCard::query()->whereKey($card->id)->lockForUpdate()->firstOrFail();

                if ($locked->status === 'published' || ! $locked->items()->exists()) {
                    continue;
                }

                $locked->update(['status' => 'published', 'published_at' => now()]);
                ReportCardPublished::dispatch($locked);
                $count++;
            }
        });

        return $count;
    }
}
