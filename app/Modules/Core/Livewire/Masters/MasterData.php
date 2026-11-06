<?php

namespace App\Modules\Core\Livewire\Masters;

use App\Modules\Core\Actions\SetActivePeriod;
use App\Modules\Core\Models\AcademicYear;
use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Dormitory;
use App\Modules\Core\Models\Teacher;
use App\Modules\Core\Models\Term;
use App\Modules\Core\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.staff', ['active' => 'classrooms'])]
#[Title('Kelas & Asrama')]
class MasterData extends Component
{
    private const MODELS = ['years' => AcademicYear::class, 'terms' => Term::class, 'classrooms' => Classroom::class, 'dormitories' => Dormitory::class];

    #[Url(as: 'tab', except: 'classrooms')]
    public string $tab = 'classrooms';

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?int $deletingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function selectTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, self::MODELS) ? $tab : 'classrooms';
        $this->resetErrorBag();
    }

    public function create(): void
    {
        $this->editingId = null;
        $this->form = [];
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-master');
    }

    public function edit(int $id): void
    {
        $record = $this->model()::query()->findOrFail($id);
        $this->editingId = $id;
        $this->form = array_map(
            fn ($value) => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value,
            array_intersect_key($record->attributesToArray(), array_flip($this->fields())),
        );

        foreach (['start_date', 'end_date'] as $date) {
            if (isset($record->{$date}) && in_array($date, $this->fields(), true)) {
                $this->form[$date] = $record->{$date}->format('Y-m-d');
            }
        }

        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-master');
    }

    public function save(): void
    {
        $data = array_map(fn ($value) => $value === '' ? null : $value, $this->validate($this->rules())['form']);

        if ($this->editingId) {
            $this->model()::query()->findOrFail($this->editingId)->update($data);
        } else {
            $this->model()::query()->create($data);
        }

        $this->dispatch('close-modal', 'form-master');
        $this->dispatch('toast', message: 'Data tersimpan.', tone: 'success');
        $this->reset('editingId', 'form');
    }

    public function activate(int $id, SetActivePeriod $action): void
    {
        match ($this->tab) {
            'years' => $action->year(AcademicYear::query()->findOrFail($id)),
            'terms' => $action->term(Term::query()->findOrFail($id)),
        };

        $this->dispatch('toast', message: 'Periode aktif diganti.', tone: 'success');
    }

    public function askDelete(int $id): void
    {
        $this->deletingId = $id;
        $this->dispatch('open-modal', 'hapus-master');
    }

    public function confirmDelete(): void
    {
        $record = $this->model()::query()->findOrFail($this->deletingId);
        $this->deletingId = null;
        $this->dispatch('close-modal', 'hapus-master');

        if ($reason = $this->deleteBlocker($record)) {
            $this->dispatch('toast', message: $reason, tone: 'danger');

            return;
        }

        $record->delete();
        $this->dispatch('toast', message: 'Data dihapus.', tone: 'success');
    }

    private function deleteBlocker(Model $record): ?string
    {
        return match (true) {
            $record instanceof Classroom && $record->students()->exists() => 'Kelas ini masih punya santri. Pindahkan santrinya dulu.',
            $record instanceof Dormitory && $record->students()->exists() => 'Asrama ini masih punya santri. Pindahkan santrinya dulu.',
            $record instanceof AcademicYear && $record->is_active => 'Tahun ajaran aktif tidak bisa dihapus.',
            $record instanceof Term && $record->is_active => 'Term aktif tidak bisa dihapus.',
            default => null,
        };
    }

    /**
     * @return class-string<Model>
     */
    private function model(): string
    {
        return self::MODELS[$this->tab];
    }

    /**
     * @return list<string>
     */
    private function fields(): array
    {
        return array_map(fn (string $key) => substr($key, 5), array_keys($this->rules()));
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $unique = fn (string $table) => Rule::unique($table, 'name')->ignore($this->editingId);

        return match ($this->tab) {
            'years' => [
                'form.name' => ['required', 'string', 'max:50', $unique('academic_years')],
                'form.start_date' => 'required|date',
                'form.end_date' => 'required|date|after:form.start_date',
            ],
            'terms' => [
                'form.academic_year_id' => 'required|exists:academic_years,id',
                'form.name' => 'required|string|max:50',
                'form.start_date' => 'required|date',
                'form.end_date' => 'required|date|after:form.start_date',
            ],
            'dormitories' => [
                'form.name' => ['required', 'string', 'max:100', $unique('dormitories')],
                'form.gender' => 'required|in:L,P',
                'form.capacity' => 'nullable|integer|min:1|max:5000',
                'form.musyrif_user_id' => 'nullable|exists:users,id',
            ],
            default => [
                'form.name' => ['required', 'string', 'max:100', $unique('classrooms')],
                'form.level' => 'nullable|string|max:50',
                'form.homeroom_teacher_id' => 'nullable|exists:teachers,id',
                'form.capacity' => 'nullable|integer|min:1|max:5000',
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'form.name' => 'nama',
            'form.start_date' => 'tanggal mulai',
            'form.end_date' => 'tanggal selesai',
            'form.academic_year_id' => 'tahun ajaran',
            'form.gender' => 'jenis kelamin',
            'form.capacity' => 'kapasitas',
            'form.level' => 'jenjang',
        ];
    }

    public function render(): View
    {
        return view('core.masters.index', [
            'rows' => match ($this->tab) {
                'years' => AcademicYear::query()->orderByDesc('start_date')->get(),
                'terms' => Term::query()->with('academicYear')->orderByDesc('start_date')->get(),
                'dormitories' => Dormitory::query()->with('musyrif')->withCount('students')->orderBy('name')->get(),
                default => Classroom::query()->with('homeroomTeacher')->withCount('students')->orderBy('name')->get(),
            },
            'years' => AcademicYear::query()->orderByDesc('start_date')->pluck('name', 'id')->all(),
            'teachers' => Teacher::query()->orderBy('name')->pluck('name', 'id')->all(),
            'musyrifs' => User::role('musyrif')->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }
}
