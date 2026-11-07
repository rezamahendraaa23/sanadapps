<?php

namespace App\Modules\Exam\Livewire;

use App\Modules\Academic\Models\Subject;
use App\Modules\Academic\Support\StudentSchedule;
use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Student;
use App\Modules\Exam\Actions\ManageExam;
use App\Modules\Exam\Models\Exam;
use App\Modules\Exam\Models\ExamTarget;
use App\Modules\Exam\Models\QuestionBank;
use App\Modules\Exam\Support\ExamException;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pembuat ujian 4 langkah (U-07); setiap langkah langsung tersimpan sebagai draf.
 */
#[Title('Susun Ujian')]
class ExamWizard extends Component
{
    use StaffPortalLayout;

    public Exam $exam;

    #[Url(as: 'langkah', except: 1)]
    public int $step = 1;

    public string $title = '';

    public string $subject_id = '';

    public string $start_at = '';

    public string $end_at = '';

    public string $duration = '60';

    public string $bank_id = '';

    public string $random_tag = '';

    public string $random_count = '';

    public bool $shuffle_questions = true;

    public bool $shuffle_options = true;

    public string $max_attempts = '1';

    public string $access_code = '';

    public string $show_result_mode = 'after_close';

    public string $pass_score = '';

    /** @var list<string> */
    public array $classrooms = [];

    public string $student_search = '';

    public string $audience = 'student';

    public function mount(Exam $exam): void
    {
        abort_unless($exam->canBeManagedBy(auth()->user()), 403);
        abort_unless($exam->status === 'draft', 404);
        $this->exam = $exam;
        $zone = config('sanad.display_timezone');
        $this->fill([
            'title' => $exam->title,
            'subject_id' => (string) $exam->subject_id,
            'start_at' => $exam->start_at?->setTimezone($zone)->format('Y-m-d\TH:i') ?? '',
            'end_at' => $exam->end_at?->setTimezone($zone)->format('Y-m-d\TH:i') ?? '',
            'duration' => (string) $exam->duration_minutes,
            'shuffle_questions' => $exam->shuffle_questions || $exam->wasRecentlyCreated || ! $exam->start_at,
            'shuffle_options' => $exam->shuffle_options || ! $exam->start_at,
            'max_attempts' => (string) $exam->max_attempts,
            'access_code' => (string) $exam->access_code,
            'show_result_mode' => $exam->show_result_mode,
            'pass_score' => $exam->pass_score === null ? '' : (string) $exam->pass_score,
            'audience' => $exam->audience,
            'classrooms' => $exam->targets()->whereNotNull('classroom_id')->pluck('classroom_id')->map(fn ($id) => (string) $id)->all(),
        ]);
        $this->step = max(1, min(4, $this->step));
    }

    public function saveInfo(): bool
    {
        $this->resetErrorBag();
        $this->validate([
            'title' => 'required|string|max:150',
            'subject_id' => 'required|exists:subjects,id',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'duration' => 'required|integer|min:1|max:600',
        ], ['end_at.after' => 'Waktu tutup harus setelah waktu buka.'], ['title' => 'judul', 'subject_id' => 'mapel', 'start_at' => 'waktu buka', 'end_at' => 'waktu tutup', 'duration' => 'durasi']);

        $zone = config('sanad.display_timezone');

        $this->exam->update([
            'title' => $this->title,
            'subject_id' => (int) $this->subject_id,
            'term_id' => StudentSchedule::activeTerm()?->id,
            'start_at' => CarbonImmutable::parse($this->start_at, $zone)->utc(),
            'end_at' => CarbonImmutable::parse($this->end_at, $zone)->utc(),
            'duration_minutes' => (int) $this->duration,
        ]);

        return true;
    }

    public function saveSettings(): bool
    {
        $this->resetErrorBag();
        $this->validate([
            'max_attempts' => 'required|integer|min:1|max:10',
            'show_result_mode' => 'required|in:immediately,after_close,never',
            'pass_score' => 'nullable|numeric|min:0|max:100',
            'access_code' => 'nullable|string|max:30',
        ], [], ['max_attempts' => 'batas percobaan', 'pass_score' => 'nilai lulus', 'access_code' => 'kode akses']);

        $this->exam->update([
            'shuffle_questions' => $this->shuffle_questions,
            'shuffle_options' => $this->shuffle_options,
            'max_attempts' => (int) $this->max_attempts,
            'access_code' => trim($this->access_code) ?: null,
            'show_result_mode' => $this->show_result_mode,
            'pass_score' => $this->pass_score === '' ? null : (float) $this->pass_score,
        ]);

        return true;
    }

    private function saveTargets(): void
    {
        DB::transaction(function (): void {
            $this->exam->targets()->whereNotNull('classroom_id')->delete();

            foreach (Classroom::query()->whereIn('id', array_map('intval', $this->classrooms))->pluck('id') as $id) {
                ExamTarget::create(['exam_id' => $this->exam->id, 'classroom_id' => $id]);
            }
        });
    }

    public function next(): void
    {
        $ok = match ($this->step) {
            1 => $this->saveInfo(),
            3 => $this->saveSettings(),
            default => true,
        };

        if ($ok) {
            $this->step = min(4, $this->step + 1);
        }
    }

    public function back(): void
    {
        $this->resetErrorBag();
        $this->step = max(1, $this->step - 1);
    }

    private function banks()
    {
        return QuestionBank::query()->accessibleBy(auth()->user())->withCount('questions')->orderBy('name')->get();
    }

    private function nextOrder(): int
    {
        return (int) DB::table('exam_questions')->where('exam_id', $this->exam->id)->max('sort_order') + 1;
    }

    public function toggleQuestion(string $uuid): void
    {
        $bank = $this->banks()->firstWhere('id', (int) $this->bank_id);
        abort_if($bank === null, 403);
        $question = $bank->questions()->where('uuid', $uuid)->firstOrFail();

        if ($this->exam->questions()->whereKey($question->id)->exists()) {
            $this->exam->questions()->detach($question->id);

            return;
        }

        $this->exam->questions()->attach($question->id, ['sort_order' => $this->nextOrder()]);
    }

    public function addRandom(): void
    {
        $this->resetErrorBag();
        $bank = $this->banks()->firstWhere('id', (int) $this->bank_id);

        if ($bank === null) {
            $this->addError('bank_id', 'Pilih bank soal dulu.');

            return;
        }

        $count = (int) $this->random_count;

        if ($count < 1) {
            $this->addError('random_count', 'Isi jumlah soal.');

            return;
        }

        $tag = trim($this->random_tag);
        $picked = $bank->questions()
            ->whereNotIn('id', $this->exam->questions()->pluck('questions.id'))
            ->when($tag !== '', fn ($query) => $query->whereJsonContains('tags', $tag))
            ->inRandomOrder()->limit($count)->get();

        if ($picked->count() < $count) {
            $this->addError('random_count', "Hanya tersedia {$picked->count()} soal yang cocok.");
        }

        $order = $this->nextOrder();

        foreach ($picked as $question) {
            $this->exam->questions()->attach($question->id, ['sort_order' => $order++]);
        }
    }

    public function addStudent(string $uuid): void
    {
        $student = Student::query()->visibleTo(auth()->user())->where('uuid', $uuid)->firstOrFail();
        ExamTarget::query()->firstOrCreate(['exam_id' => $this->exam->id, 'student_id' => $student->id]);
        $this->reset('student_search');
    }

    public function removeStudent(int $studentId): void
    {
        $this->exam->targets()->where('student_id', $studentId)->delete();
    }

    public function publish(ManageExam $exams)
    {
        $this->resetErrorBag();
        $this->saveTargets();

        try {
            $exams->publish($this->exam->refresh());
        } catch (ExamException $exception) {
            $this->addError('publish', $exception->getMessage());

            return null;
        }

        session()->flash('toast', 'Ujian terbit.');

        return $this->redirect($this->examRoute('index'), navigate: true);
    }

    public function updatedAudience(): void
    {
        $this->exam->update(['audience' => $this->audience === 'applicant' ? 'applicant' : 'student']);
    }

    public function updatedClassrooms(): void
    {
        $this->saveTargets();
    }

    public function render(ManageExam $exams): View
    {
        [$layout, $params] = $this->examLayout('Susun Ujian');
        $bank = $this->banks()->firstWhere('id', (int) $this->bank_id);
        $chosen = $this->exam->questions()->get();
        $term = trim($this->student_search);

        return view('exam.wizard', [
            'subjects' => Subject::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'bankOptions' => $this->banks()->mapWithKeys(fn (QuestionBank $item) => [$item->id => $item->name.' ('.$item->questions_count.' soal)'])->all(),
            'bankQuestions' => $bank?->questions()->latest('id')->get() ?? collect(),
            'chosen' => $chosen,
            'chosenIds' => $chosen->pluck('id')->all(),
            'totalPoints' => (float) $chosen->sum(fn ($question) => (float) ($question->pivot->points_override ?? $question->points)),
            'classroomOptions' => Classroom::query()->orderBy('name')->get(),
            'studentResults' => mb_strlen($term) >= 2 ? Student::query()->visibleTo(auth()->user())->where(fn ($query) => $query->where('name', 'ilike', '%'.addcslashes($term, '%_').'%')->orWhere('nis', 'like', addcslashes($term, '%_').'%'))->orderBy('name')->limit(6)->get() : collect(),
            'targetStudents' => Student::query()->whereIn('id', $this->exam->targets()->whereNotNull('student_id')->pluck('student_id'))->orderBy('name')->get(),
            'problems' => $this->step === 4 ? $exams->problems($this->exam->refresh()) : [],
            'participantCount' => $this->step === 4 ? $exams->targetStudents($this->exam)->count() : 0,
        ])->layout($layout, $params);
    }
}
