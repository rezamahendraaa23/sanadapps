<?php

namespace App\Modules\Academic\Livewire;

use App\Modules\Academic\Models\GradeComponent;
use App\Modules\Academic\Models\Subject;
use App\Modules\Academic\Models\TeachingAssignment;
use App\Modules\Academic\Support\AcademicSettings;
use App\Modules\Core\Models\AcademicYear;
use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Module;
use App\Modules\Core\Models\Teacher;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.staff', ['active' => 'subjects'])]
#[Title('Mapel & Penugasan')]
class AcademicMasters extends Component
{
    #[Url(except: 'mapel')]
    public string $tab = 'mapel';

    #[Locked]
    public ?int $editing = null;

    public string $code = '';

    public string $name = '';

    public string $type = 'umum';

    public string $kkm = '70';

    public bool $is_active = true;

    public string $teacher_id = '';

    public string $subject_id = '';

    public string $classroom_id = '';

    public string $component_name = '';

    public string $component_weight = '';

    public string $late_tolerance = '';

    /** @var list<string> */
    public array $verification_modes = [];

    public function mount(): void
    {
        $this->late_tolerance = (string) AcademicSettings::lateTolerance();
        $this->verification_modes = AcademicSettings::verificationModes();
    }

    public function selectTab(string $tab): void
    {
        $this->tab = in_array($tab, ['mapel', 'penugasan', 'komponen', 'aturan'], true) ? $tab : 'mapel';
        $this->resetErrorBag();
    }

    public function createSubject(): void
    {
        $this->reset('editing', 'code', 'name', 'type', 'kkm', 'is_active');
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-mapel');
    }

    public function editSubject(string $uuid): void
    {
        $subject = Subject::query()->where('uuid', $uuid)->firstOrFail();
        $this->editing = $subject->id;
        $this->fill(['code' => $subject->code, 'name' => $subject->name, 'type' => $subject->type, 'kkm' => (string) $subject->kkm, 'is_active' => $subject->is_active]);
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-mapel');
    }

    public function saveSubject(): void
    {
        $this->resetErrorBag();
        $data = $this->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('subjects', 'code')->ignore($this->editing)],
            'name' => 'required|string|max:100',
            'type' => 'required|in:diniyah,umum,tahfidz',
            'kkm' => 'required|integer|min:0|max:100',
            'is_active' => 'boolean',
        ], ['code.unique' => 'Kode ini sudah dipakai.'], ['code' => 'kode', 'name' => 'nama', 'kkm' => 'KKM']);

        $this->editing ? Subject::query()->findOrFail($this->editing)->update($data) : Subject::create($data);

        $this->dispatch('close-modal', 'form-mapel');
        $this->dispatch('toast', message: 'Mapel tersimpan.', tone: 'success');
    }

    public function saveAssignment(): void
    {
        $this->resetErrorBag();
        $year = AcademicYear::query()->where('is_active', true)->first();

        if ($year === null) {
            $this->addError('teacher_id', 'Aktifkan tahun ajaran dulu di Kelas & Asrama.');

            return;
        }

        $this->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'subject_id' => 'required|exists:subjects,id',
            'classroom_id' => 'required|exists:classrooms,id',
        ], [], ['teacher_id' => 'ustadz', 'subject_id' => 'mapel', 'classroom_id' => 'kelas']);

        TeachingAssignment::query()->updateOrCreate(
            ['subject_id' => $this->subject_id, 'classroom_id' => $this->classroom_id, 'academic_year_id' => $year->id],
            ['teacher_id' => $this->teacher_id],
        );

        $this->reset('teacher_id', 'subject_id', 'classroom_id');
        $this->dispatch('toast', message: 'Penugasan tersimpan.', tone: 'success');
    }

    public function removeAssignment(string $uuid): void
    {
        TeachingAssignment::query()->where('uuid', $uuid)->firstOrFail()->delete();
    }

    public function saveComponent(): void
    {
        $this->resetErrorBag();
        $this->validate(['component_name' => 'required|string|max:50', 'component_weight' => 'required|integer|min:1|max:100'], [], ['component_name' => 'nama', 'component_weight' => 'bobot']);

        GradeComponent::create(['name' => $this->component_name, 'weight' => (int) $this->component_weight]);
        $this->reset('component_name', 'component_weight');
    }

    public function toggleComponent(string $uuid): void
    {
        $component = GradeComponent::query()->where('uuid', $uuid)->firstOrFail();
        $component->update(['is_active' => ! $component->is_active]);
    }

    public function updateWeight(string $uuid, string $weight): void
    {
        abort_unless(ctype_digit($weight) && (int) $weight >= 0 && (int) $weight <= 100, 422);
        GradeComponent::query()->where('uuid', $uuid)->firstOrFail()->update(['weight' => (int) $weight]);
    }

    public function saveRules(): void
    {
        $this->resetErrorBag();
        $this->validate([
            'late_tolerance' => 'required|integer|min:0|max:120',
            'verification_modes' => 'required|array|min:1',
            'verification_modes.*' => 'in:manual,card,qr',
        ], ['verification_modes.required' => 'Pilih minimal satu cara absen.'], ['late_tolerance' => 'toleransi']);

        $module = Module::query()->where('module_key', 'attendance')->firstOrFail();
        $module->update(['settings' => [...($module->settings ?? []), 'late_tolerance' => (int) $this->late_tolerance, 'verification_modes' => array_values($this->verification_modes)]]);

        $this->dispatch('toast', message: 'Aturan absensi tersimpan.', tone: 'success');
    }

    public function render(): View
    {
        $components = GradeComponent::query()->orderBy('id')->get();

        return view('academic.masters', [
            'subjects' => Subject::query()->orderBy('type')->orderBy('name')->get(),
            'assignments' => TeachingAssignment::query()->with(['teacher:id,name', 'subject:id,name', 'classroom:id,name'])->whereHas('academicYear', fn ($query) => $query->where('is_active', true))->orderBy('classroom_id')->get(),
            'teachers' => Teacher::query()->orderBy('name')->pluck('name', 'id')->all(),
            'classrooms' => Classroom::query()->orderBy('name')->pluck('name', 'id')->all(),
            'subjectOptions' => Subject::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'components' => $components,
            'totalWeight' => (int) $components->where('is_active', true)->sum('weight'),
            'types' => Subject::TYPES,
        ]);
    }
}
