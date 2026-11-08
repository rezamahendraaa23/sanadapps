<?php

namespace App\Modules\Exam\Livewire;

use App\Modules\Academic\Actions\SaveGrades;
use App\Modules\Academic\Models\GradeComponent;
use App\Modules\Academic\Support\AcademicException;
use App\Modules\Core\Models\Classroom;
use App\Modules\Exam\Actions\GradeAttempt;
use App\Modules\Exam\Actions\ManageExam;
use App\Modules\Exam\Models\Exam;
use App\Modules\Exam\Models\ExamAnswer;
use App\Modules\Exam\Models\ExamAttempt;
use App\Modules\Exam\Support\ExamException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Koreksi esai dan rekap (U-09, AC-04). Skor total diperbarui otomatis (EXM-10).
 */
#[Title('Hasil Ujian')]
class ExamResults extends Component
{
    use StaffPortalLayout;

    public Exam $exam;

    public string $essayScore = '';

    public string $push_classroom = '';

    public string $push_component = '';

    public function mount(Exam $exam): void
    {
        abort_unless($exam->canBeManagedBy(auth()->user()), 403);
        $this->exam = $exam;
    }

    private function nextEssay(): ?ExamAnswer
    {
        return ExamAnswer::query()->with(['question', 'attempt.student:id,name', 'attempt.applicant:id,name'])
            ->whereHas('attempt', fn ($query) => $query->where('exam_id', $this->exam->id)->whereIn('status', ['submitted', 'auto_submitted', 'graded']))
            ->whereHas('question', fn ($query) => $query->where('type', 'essay'))
            ->whereNull('graded_by')->whereNotNull('answer')
            ->whereRaw("COALESCE(answer->>'value', '') <> ''")
            ->orderBy('id')->first();
    }

    public function gradeEssay(string $uuid, GradeAttempt $grader): void
    {
        abort_unless($this->exam->canBeManagedBy(auth()->user()), 403);
        $this->resetErrorBag();
        $answer = ExamAnswer::query()->with(['question', 'attempt'])->where('uuid', $uuid)->whereHas('attempt', fn ($query) => $query->where('exam_id', $this->exam->id))->firstOrFail();
        $maximum = (float) ($this->exam->questions()->withTrashed()->whereKey($answer->question_id)->first()?->pivot->points_override ?? $answer->question->points);
        $score = str_replace(',', '.', $this->essayScore);

        if (! is_numeric($score) || (float) $score < 0 || (float) $score > $maximum) {
            $this->addError('essayScore', "Poin harus 0 sampai {$maximum}.");

            return;
        }

        DB::transaction(function () use ($answer, $score, $grader): void {
            $answer->update(['score' => (float) $score, 'graded_by' => auth()->id(), 'is_correct' => null]);
            $grader->handle($answer->attempt);
        });

        $this->reset('essayScore');
    }

    public function push(ManageExam $exams, SaveGrades $grades): void
    {
        abort_unless($this->exam->canBeManagedBy(auth()->user()), 403);
        $this->resetErrorBag();

        if ($this->push_classroom === '' || $this->push_component === '') {
            $this->addError('push_classroom', 'Pilih kelas dan komponen nilai.');

            return;
        }

        try {
            $count = $exams->pushToGrades($this->exam, (int) $this->push_classroom, (int) $this->push_component, $grades);
        } catch (ExamException|AcademicException $exception) {
            $this->addError('push_classroom', $exception->getMessage());

            return;
        }

        $this->dispatch('close-modal', 'kirim-nilai');
        $this->dispatch('toast', message: "{$count} nilai masuk ke buku nilai.", tone: 'success');
    }

    public function render(ManageExam $exams): View
    {
        [$layout, $params] = $this->examLayout('Hasil Ujian');
        $attempts = ExamAttempt::query()->with(['student:id,name,nis,classroom_id', 'applicant:id,name'])->where('exam_id', $this->exam->id)->whereNot('status', 'in_progress')->orderByDesc('score')->get();
        $graded = $attempts->where('status', 'graded');
        $questions = $this->exam->questions()->withTrashed()->get();

        $analysis = DB::table('exam_answers')
            ->join('exam_attempts', 'exam_attempts.id', '=', 'exam_answers.attempt_id')
            ->where('exam_attempts.exam_id', $this->exam->id)->whereNot('exam_attempts.status', 'in_progress')
            ->selectRaw('exam_answers.question_id, COUNT(*) FILTER (WHERE exam_answers.is_correct) AS correct, COUNT(*) AS answered')
            ->groupBy('exam_answers.question_id')->get()->keyBy('question_id');

        $items = $questions->filter(fn ($question) => $question->type !== 'essay')->map(function ($question) use ($analysis, $attempts) {
            $row = $analysis[$question->id] ?? null;
            $total = max(1, $attempts->count());

            return ['question' => $question, 'percent' => (int) round(((int) ($row->correct ?? 0)) / $total * 100)];
        })->sortBy('percent')->values();

        $buckets = collect(range(0, 9))->mapWithKeys(fn (int $bucket) => [$bucket => 0]);

        foreach ($graded as $attempt) {
            $bucket = min(9, (int) floor($attempt->score / 10));
            $buckets[$bucket] = $buckets[$bucket] + 1;
        }

        return view('exam.results', [
            'attempts' => $attempts,
            'average' => $graded->isEmpty() ? null : round($graded->avg('score'), 1),
            'essay' => $this->nextEssay(),
            'essayPoints' => null,
            'items' => $items,
            'buckets' => $buckets,
            'maxBucket' => max(1, $buckets->max()),
            'classrooms' => Classroom::query()->whereIn('id', $attempts->pluck('student.classroom_id')->filter()->unique())->ordered()->pluck('name', 'id')->all(),
            'components' => GradeComponent::query()->where('is_active', true)->orderBy('id')->pluck('name', 'id')->all(),
            'monitorUrl' => $this->examRoute('monitor', $this->exam),
        ])->layout($layout, $params)->title('Hasil: '.$this->exam->title);
    }
}
