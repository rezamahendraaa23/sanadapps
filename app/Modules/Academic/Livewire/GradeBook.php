<?php

namespace App\Modules\Academic\Livewire;

use App\Modules\Academic\Actions\ManageReportCards;
use App\Modules\Academic\Actions\SaveGrades;
use App\Modules\Academic\Models\Assessment;
use App\Modules\Academic\Models\Grade;
use App\Modules\Academic\Models\GradeComponent;
use App\Modules\Academic\Models\ReportCard;
use App\Modules\Academic\Models\Subject;
use App\Modules\Academic\Models\TeachingAssignment;
use App\Modules\Academic\Models\TeachingScope;
use App\Modules\Academic\Support\AcademicException;
use App\Modules\Academic\Support\StudentSchedule;
use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Student;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Input nilai (U-04): ustadz hanya mapel dan kelas yang diajar; pengelola akademik semuanya.
 */
#[Title('Nilai')]
class GradeBook extends Component
{
    #[Url(as: 'penugasan', except: '')]
    public string $assignmentKey = '';

    #[Locked]
    public ?string $assessmentUuid = null;

    /** @var array<int, string> id santri ke nilai */
    public array $scores = [];

    public string $unlockReason = '';

    public string $title = '';

    public string $component_id = '';

    public string $max_score = '100';

    public string $assessed_on = '';

    /**
     * @return array<string, string> "mapel:kelas" ke label
     */
    private function options(): array
    {
        $user = auth()->user();

        if ($user->can('grades.manage')) {
            return TeachingAssignment::query()->with(['subject:id,name', 'classroom:id,name'])->whereHas('academicYear', fn ($query) => $query->where('is_active', true))->get()
                ->mapWithKeys(fn (TeachingAssignment $row) => [$row->subject_id.':'.$row->classroom_id => $row->subject->name.', '.$row->classroom->name])->all();
        }

        $teacher = TeachingScope::teacher($user);

        return $teacher === null ? [] : TeachingAssignment::query()->with(['subject:id,name', 'classroom:id,name'])->where('teacher_id', $teacher->id)->whereHas('academicYear', fn ($query) => $query->where('is_active', true))->get()
            ->mapWithKeys(fn (TeachingAssignment $row) => [$row->subject_id.':'.$row->classroom_id => $row->subject->name.', '.$row->classroom->name])->all();
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function selection(): ?array
    {
        if (! isset($this->options()[$this->assignmentKey])) {
            return null;
        }

        [$subject, $classroom] = explode(':', $this->assignmentKey);

        return [(int) $subject, (int) $classroom];
    }

    private function assessment(): ?Assessment
    {
        $selection = $this->selection();

        if ($selection === null || $this->assessmentUuid === null) {
            return null;
        }

        return Assessment::query()->where('uuid', $this->assessmentUuid)->where('subject_id', $selection[0])->where('classroom_id', $selection[1])->first();
    }

    public function mount(): void
    {
        if ($this->selection() === null) {
            $this->assignmentKey = (string) array_key_first($this->options());
        }
    }

    public function updatedAssignmentKey(): void
    {
        $this->reset('assessmentUuid', 'scores', 'unlockReason');
        $this->resetErrorBag();
    }

    public function openAssessment(string $uuid): void
    {
        $this->assessmentUuid = $uuid;
        $assessment = $this->assessment();
        abort_if($assessment === null, 403);
        $this->reset('unlockReason');
        $this->resetErrorBag();

        $existing = Grade::query()->where('assessment_id', $assessment->id)->pluck('score', 'student_id');
        $this->scores = [];

        foreach ($this->students($assessment->classroom_id) as $student) {
            $this->scores[$student->id] = isset($existing[$student->id]) ? rtrim(rtrim(number_format((float) $existing[$student->id], 2, '.', ''), '0'), '.') : '';
        }
    }

    private function students(int $classroomId)
    {
        return Student::query()->where('status', 'active')->where('classroom_id', $classroomId)->orderBy('name')->get(['id', 'name', 'nis']);
    }

    public function createAssessment(): void
    {
        abort_if($this->selection() === null, 403);
        $this->reset('title', 'component_id', 'max_score', 'assessed_on');
        $this->assessed_on = now(config('sanad.display_timezone'))->toDateString();
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-penilaian');
    }

    public function saveAssessment(): void
    {
        $selection = $this->selection();
        abort_if($selection === null, 403);
        $term = StudentSchedule::activeTerm();
        $this->resetErrorBag();

        if ($term === null) {
            $this->addError('title', 'Belum ada periode aktif.');

            return;
        }

        $this->validate([
            'title' => 'required|string|max:100',
            'component_id' => 'required|exists:grade_components,id',
            'max_score' => 'required|integer|min:1|max:1000',
            'assessed_on' => 'nullable|date',
        ], [], ['title' => 'judul', 'component_id' => 'komponen', 'max_score' => 'nilai maksimum', 'assessed_on' => 'tanggal']);

        $assessment = Assessment::create([
            'subject_id' => $selection[0],
            'classroom_id' => $selection[1],
            'term_id' => $term->id,
            'component_id' => (int) $this->component_id,
            'title' => $this->title,
            'max_score' => (int) $this->max_score,
            'assessed_on' => $this->assessed_on ?: null,
        ]);

        $this->dispatch('close-modal', 'form-penilaian');
        $this->openAssessment($assessment->uuid);
    }

    public function saveScores(SaveGrades $action): void
    {
        $assessment = $this->assessment();
        abort_if($assessment === null, 403);
        $this->resetErrorBag();
        $allowed = $this->students($assessment->classroom_id)->pluck('id')->all();
        $scores = array_intersect_key($this->scores, array_flip($allowed));

        foreach ($scores as $studentId => $value) {
            $value = trim(str_replace(',', '.', (string) $value));

            if ($value !== '' && (! is_numeric($value) || (float) $value < 0 || (float) $value > $assessment->max_score)) {
                $this->addError('scores.'.$studentId, "Harus 0 sampai {$assessment->max_score}.");
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        try {
            $changed = $action->handle($assessment, $scores, $this->unlockReason ?: null);
        } catch (AcademicException $exception) {
            $this->addError('unlockReason', $exception->getMessage());

            return;
        }

        $this->dispatch('toast', message: "{$changed} nilai tersimpan.", tone: 'success');
    }

    public function closeAssessment(): void
    {
        $this->reset('assessmentUuid', 'scores', 'unlockReason');
        $this->resetErrorBag();
    }

    public function render(ManageReportCards $cards): View
    {
        $selection = $this->selection();
        $term = StudentSchedule::activeTerm();
        $user = auth()->user();
        $portal = ! $user->can('grades.manage');
        $assessment = $this->assessment();
        $students = $selection ? $this->students($selection[1]) : collect();

        return view('academic.grade-book', [
            'options' => $this->options(),
            'subject' => $selection ? Subject::query()->find($selection[0]) : null,
            'classroom' => $selection ? Classroom::query()->find($selection[1]) : null,
            'assessments' => $selection && $term ? Assessment::query()->with('component:id,name')->withCount('grades')->where('subject_id', $selection[0])->where('classroom_id', $selection[1])->where('term_id', $term->id)->latest('id')->get() : collect(),
            'assessment' => $assessment,
            'students' => $students,
            'finals' => $selection && $term && ! $assessment ? $students->mapWithKeys(fn (Student $student) => [$student->id => $cards->finalScores($student, $term->id, $selection[1])[$selection[0]] ?? null]) : collect(),
            'lockedStudents' => $assessment ? ReportCard::query()->where('term_id', $assessment->term_id)->where('status', 'published')->whereIn('student_id', $students->pluck('id'))->pluck('student_id')->all() : [],
            'components' => GradeComponent::query()->where('is_active', true)->orderBy('id')->pluck('name', 'id')->all(),
            'hasTerm' => $term !== null,
        ])->layout($portal ? 'components.layouts.portal' : 'components.layouts.staff', $portal ? ['role' => 'ustadz', 'active' => 'grades', 'title' => 'Nilai'] : ['active' => 'grades']);
    }
}
