<?php

namespace App\Modules\Academic\Livewire;

use App\Modules\Academic\Actions\CopySchedule;
use App\Modules\Academic\Actions\SaveSchedule;
use App\Modules\Academic\Models\Schedule;
use App\Modules\Academic\Models\Subject;
use App\Modules\Academic\Support\AcademicException;
use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Teacher;
use App\Modules\Core\Models\Term;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.staff', ['active' => 'schedules'])]
#[Title('Jadwal')]
class ScheduleBuilder extends Component
{
    #[Url(as: 'term', except: '')]
    public string $termId = '';

    #[Url(as: 'kelas', except: '')]
    public string $classroomId = '';

    #[Locked]
    public ?int $editing = null;

    public string $day_of_week = '1';

    public string $start_time = '07:00';

    public string $end_time = '08:00';

    public string $activity_type = 'kbm';

    public string $title = '';

    public string $subject_id = '';

    public string $teacher_id = '';

    public string $room = '';

    public string $form_classroom = '';

    public string $copy_from = '';

    public function mount(): void
    {
        $this->termId = Term::query()->whereKey((int) $this->termId)->value('id') ?? Term::query()->where('is_active', true)->value('id') ?? Term::query()->latest('id')->value('id') ?? '';
        $this->termId = (string) $this->termId;
    }

    public function create(int $day = 1): void
    {
        $this->reset('editing', 'start_time', 'end_time', 'activity_type', 'title', 'subject_id', 'teacher_id', 'room');
        $this->day_of_week = (string) max(1, min(7, $day));
        $this->form_classroom = $this->classroomId;
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-jadwal');
    }

    public function edit(string $uuid): void
    {
        $schedule = Schedule::query()->where('uuid', $uuid)->firstOrFail();
        $this->editing = $schedule->id;
        $this->fill([
            'day_of_week' => (string) $schedule->day_of_week,
            'start_time' => substr((string) $schedule->start_time, 0, 5),
            'end_time' => substr((string) $schedule->end_time, 0, 5),
            'activity_type' => $schedule->activity_type,
            'title' => (string) $schedule->title,
            'subject_id' => (string) $schedule->subject_id,
            'teacher_id' => (string) $schedule->teacher_id,
            'room' => (string) $schedule->room,
            'form_classroom' => (string) $schedule->classroom_id,
        ]);
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-jadwal');
    }

    public function save(SaveSchedule $action): void
    {
        $this->resetErrorBag();

        $this->validate([
            'termId' => 'required|exists:terms,id',
            'day_of_week' => 'required|integer|between:1,7',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'activity_type' => 'required|in:'.implode(',', array_keys(Schedule::ACTIVITIES)),
            'subject_id' => 'nullable|required_if:activity_type,kbm|exists:subjects,id',
            'teacher_id' => 'nullable|exists:teachers,id',
            'form_classroom' => 'nullable|required_if:activity_type,kbm|exists:classrooms,id',
            'title' => 'nullable|string|max:100',
            'room' => 'nullable|string|max:50',
        ], ['end_time.after' => 'Jam selesai harus setelah jam mulai.', 'subject_id.required_if' => 'KBM wajib punya mapel.', 'form_classroom.required_if' => 'KBM wajib punya kelas.'], ['start_time' => 'jam mulai', 'end_time' => 'jam selesai', 'termId' => 'term']);

        $data = [
            'term_id' => (int) $this->termId,
            'classroom_id' => $this->form_classroom ?: null,
            'subject_id' => $this->subject_id ?: null,
            'teacher_id' => $this->teacher_id ?: null,
            'day_of_week' => (int) $this->day_of_week,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'room' => $this->room ?: null,
            'activity_type' => $this->activity_type,
            'title' => $this->title ?: null,
        ];

        try {
            $action->handle($data, $this->editing ? Schedule::query()->findOrFail($this->editing) : null);
        } catch (AcademicException $exception) {
            $this->addError('conflict', $exception->getMessage());

            return;
        }

        $this->dispatch('close-modal', 'form-jadwal');
        $this->dispatch('toast', message: 'Jadwal tersimpan.', tone: 'success');
    }

    public function remove(): void
    {
        abort_if($this->editing === null, 404);
        Schedule::query()->findOrFail($this->editing)->delete();
        $this->dispatch('close-modal', 'form-jadwal');
    }

    public function copy(CopySchedule $action): void
    {
        $this->resetErrorBag();

        try {
            $count = $action->handle((int) $this->copy_from, (int) $this->termId);
        } catch (AcademicException $exception) {
            $this->addError('copy_from', $exception->getMessage());

            return;
        }

        $this->dispatch('close-modal', 'salin-jadwal');
        $this->dispatch('toast', message: "{$count} jadwal disalin.", tone: 'success');
    }

    public function render(): View
    {
        $schedules = $this->termId === '' ? collect() : Schedule::query()
            ->with(['subject:id,name', 'teacher:id,name', 'classroom:id,name'])
            ->where('term_id', (int) $this->termId)
            ->when($this->classroomId !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('classroom_id', (int) $this->classroomId)->orWhereNull('classroom_id')))
            ->orderBy('start_time')->get()->groupBy('day_of_week');

        return view('academic.schedules', [
            'terms' => Term::query()->orderByDesc('start_date')->pluck('name', 'id')->all(),
            'classrooms' => Classroom::query()->orderBy('name')->pluck('name', 'id')->all(),
            'subjects' => Subject::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'teachers' => Teacher::query()->orderBy('name')->pluck('name', 'id')->all(),
            'schedules' => $schedules,
            'termActive' => Term::query()->whereKey((int) $this->termId)->where('is_active', true)->exists(),
            'days' => Schedule::DAYS,
            'activities' => Schedule::ACTIVITIES,
        ]);
    }
}
