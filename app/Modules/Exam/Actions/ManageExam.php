<?php

namespace App\Modules\Exam\Actions;

use App\Modules\Academic\Actions\SaveGrades;
use App\Modules\Academic\Models\Assessment;
use App\Modules\Core\Models\InAppNotification;
use App\Modules\Core\Models\Student;
use App\Modules\Exam\Models\Exam;
use App\Modules\Exam\Models\ExamAttempt;
use App\Modules\Exam\Support\ExamException;
use Illuminate\Support\Facades\DB;

class ManageExam
{
    public function __construct(private RunAttempt $attempts) {}

    /**
     * @return list<string> Masalah yang menghalangi penerbitan (EXM-03).
     */
    public function problems(Exam $exam): array
    {
        $problems = [];

        if (blank($exam->title)) {
            $problems[] = 'Judul belum diisi.';
        }

        if ($exam->start_at === null || $exam->end_at === null) {
            $problems[] = 'Jendela waktu belum diisi.';
        } elseif ($exam->end_at->lessThanOrEqualTo($exam->start_at)) {
            $problems[] = 'Waktu tutup harus setelah waktu buka.';
        } elseif ($exam->end_at->isPast()) {
            $problems[] = 'Waktu tutup sudah lewat.';
        }

        if ($exam->duration_minutes < 1) {
            $problems[] = 'Durasi belum diisi.';
        }

        if ($exam->questions()->count() === 0) {
            $problems[] = 'Belum ada soal.';
        }

        if ($exam->targets()->count() === 0) {
            $problems[] = 'Belum ada sasaran kelas atau santri.';
        }

        return $problems;
    }

    public function publish(Exam $exam): void
    {
        if (($problems = $this->problems($exam)) !== []) {
            throw new ExamException(implode(' ', $problems));
        }

        DB::transaction(function () use ($exam): void {
            $exam->update(['status' => 'published']);

            foreach ($this->targetStudents($exam)->whereNotNull('user_id') as $student) {
                InAppNotification::create([
                    'user_id' => $student->user_id,
                    'title' => 'Ujian baru: '.$exam->title,
                    'body' => 'Dibuka '.$exam->start_at->setTimezone(config('sanad.display_timezone'))->translatedFormat('j M, H.i').'.',
                    'link' => route('santri.exams'),
                    'dedupe_key' => 'exam-published:'.$exam->id.':'.$student->user_id,
                ]);
            }
        });
    }

    public function targetStudents(Exam $exam)
    {
        $targets = $exam->targets()->get();

        return Student::query()->where('status', 'active')->where(fn ($query) => $query
            ->whereIn('classroom_id', $targets->pluck('classroom_id')->filter())
            ->orWhereIn('id', $targets->pluck('student_id')->filter()))->orderBy('name')->get();
    }

    public function close(Exam $exam): void
    {
        DB::transaction(function () use ($exam): void {
            $exam->update(['status' => 'closed']);

            foreach (ExamAttempt::query()->where('exam_id', $exam->id)->where('status', 'in_progress')->get() as $attempt) {
                $attempt->update(['deadline_at' => now()]);
            }

            $this->attempts->autoSubmitExpired();
        });
    }

    /**
     * Mengirim nilai ke buku nilai sebagai penilaian (EXM-11); kirim ulang memperbarui, tidak menggandakan.
     *
     * @return int Jumlah nilai yang berubah.
     */
    public function pushToGrades(Exam $exam, int $classroomId, int $componentId, SaveGrades $grades): int
    {
        if ($exam->subject_id === null || $exam->term_id === null) {
            throw new ExamException('Ujian ini belum punya mapel dan term.');
        }

        $attempts = ExamAttempt::query()->with('student')->where('exam_id', $exam->id)->where('status', 'graded')->get()
            ->filter(fn (ExamAttempt $attempt) => $attempt->student?->classroom_id === $classroomId)
            ->sortBy('attempt_no')->keyBy('student_id');

        if ($attempts->isEmpty()) {
            throw new ExamException('Belum ada hasil yang selesai dinilai untuk kelas ini.');
        }

        return DB::transaction(function () use ($exam, $classroomId, $componentId, $attempts, $grades): int {
            $assessment = Assessment::query()->firstOrCreate(
                ['exam_id' => $exam->id, 'classroom_id' => $classroomId],
                ['subject_id' => $exam->subject_id, 'term_id' => $exam->term_id, 'component_id' => $componentId, 'title' => $exam->title, 'max_score' => 100, 'assessed_on' => now(config('sanad.display_timezone'))->toDateString()],
            );

            $exam->update(['assessment_id' => $assessment->id]);

            return $grades->handle($assessment, $attempts->mapWithKeys(fn (ExamAttempt $attempt) => [$attempt->student_id => (string) $attempt->score])->all());
        });
    }
}
