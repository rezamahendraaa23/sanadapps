<?php

namespace App\Modules\Core\Livewire\Masters;

use App\Modules\Core\Actions\CreateAcademicYear;
use App\Modules\Core\Actions\SetActivePeriod;
use App\Modules\Core\Models\AcademicYear;
use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Dormitory;
use App\Modules\Core\Models\Teacher;
use App\Modules\Core\Models\Term;
use App\Modules\Core\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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

    /** Di tab Tahun ajaran, form bisa untuk tahun ajaran atau term di dalamnya. */
    #[Locked]
    public ?string $formFor = null;

    /** Pembagian tahun ajaran baru: setahun penuh (sistem fase) atau dua semester. */
    public string $split = 'year';

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        $this->tab = $this->normalTab($this->tab);
    }

    public function selectTab(string $tab): void
    {
        $this->tab = $this->normalTab($tab);
        $this->resetErrorBag();
    }

    /**
     * Term tidak punya tab sendiri lagi: ia tampil di dalam tahun ajarannya. Tautan lama ?tab=terms tetap sampai.
     */
    private function normalTab(string $tab): string
    {
        return match (true) {
            $tab === 'terms' => 'years',
            array_key_exists($tab, self::MODELS) => $tab,
            default => 'classrooms',
        };
    }

    public function create(): void
    {
        $this->editingId = null;
        $this->formFor = $this->tab === 'years' ? 'years' : null;
        $this->form = $this->tab === 'years' ? CreateAcademicYear::suggestion() : [];
        $this->split = 'year';
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-master');
    }

    /**
     * Mulai cepat: tahun ajaran yang disarankan, langsung dengan term dan aktif.
     */
    public function quickStart(CreateAcademicYear $action): void
    {
        abort_if(AcademicYear::query()->exists(), 409);
        $result = $action->handle(CreateAcademicYear::suggestion(), array_key_exists($this->split, CreateAcademicYear::SPLITS) ? $this->split : 'year');
        $this->dispatch('toast', message: 'Tahun ajaran '.$result['year']->name.' dibuat dan langsung aktif.', tone: 'success');
    }

    /**
     * Term baru di tahun ajaran tertentu; tanggalnya disarankan menyambung term terakhir.
     */
    public function createTerm(int $yearId): void
    {
        $year = AcademicYear::query()->findOrFail($yearId);
        $last = $year->terms()->orderByDesc('end_date')->first();
        $start = $last ? $last->end_date->addDay() : $year->start_date;

        $this->editingId = null;
        $this->formFor = 'terms';
        $this->form = ['academic_year_id' => $year->id, 'name' => '', 'start_date' => $start->format('Y-m-d'), 'end_date' => $year->end_date->format('Y-m-d')];
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-master');
    }

    public function edit(int $id, ?string $kind = null): void
    {
        $this->formFor = $this->tab === 'years' ? ($kind === 'terms' ? 'terms' : 'years') : null;
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

        $message = 'Data tersimpan.';
        if ($this->editingId) {
            $this->model()::query()->findOrFail($this->editingId)->update($data);
        } elseif ($this->target() === 'years') {
            $result = app(CreateAcademicYear::class)->handle($data, array_key_exists($this->split, CreateAcademicYear::SPLITS) ? $this->split : 'year');
            $message = $result['activated'] ? 'Tahun ajaran dibuat dan langsung aktif.' : 'Tahun ajaran dibuat. Aktifkan saat periodenya tiba.';
        } else {
            $record = $this->model()::query()->create($data);
            // Term pertama di tahun ajaran yang sedang aktif langsung dipakai.
            if ($record instanceof Term && $record->academicYear->is_active && ! Term::query()->where('is_active', true)->exists()) {
                app(SetActivePeriod::class)->term($record);
                $message = 'Term dibuat dan langsung aktif.';
            }
        }

        $this->dispatch('close-modal', 'form-master');
        $this->dispatch('toast', message: $message, tone: 'success');
        $this->reset('editingId', 'form', 'formFor');
    }

    public function activate(int $id, SetActivePeriod $action, string $kind = 'years'): void
    {
        match ($kind) {
            'terms' => $action->term(Term::query()->findOrFail($id)),
            default => $action->year(AcademicYear::query()->findOrFail($id)),
        };

        $this->dispatch('toast', message: 'Periode aktif diganti.', tone: 'success');
    }

    public function askDelete(int $id, ?string $kind = null): void
    {
        $this->formFor = $this->tab === 'years' ? ($kind === 'terms' ? 'terms' : 'years') : null;
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
            // Menghapus periode ikut menghapus jadwal dan target tahfidz, dan ditolak database bila sudah ada nilai atau rapor.
            $record instanceof AcademicYear && $this->periodInUse($record->terms()->pluck('id')->all(), $record->id) => 'Tahun ajaran ini sudah dipakai jadwal, penugasan, nilai, atau rapor, jadi tidak bisa dihapus.',
            $record instanceof Term && $this->periodInUse([$record->id], null) => 'Term ini sudah dipakai jadwal, nilai, atau rapor, jadi tidak bisa dihapus.',
            default => null,
        };
    }

    /**
     * @param  list<int>  $termIds
     */
    private function periodInUse(array $termIds, ?int $yearId): bool
    {
        foreach (['schedules', 'tahfidz_targets', 'assessments', 'report_cards'] as $table) {
            if ($termIds !== [] && DB::table($table)->whereIn('term_id', $termIds)->exists()) {
                return true;
            }
        }

        return $yearId !== null && DB::table('teaching_assignments')->where('academic_year_id', $yearId)->exists();
    }

    /**
     * @return class-string<Model>
     */
    private function model(): string
    {
        return self::MODELS[$this->target()];
    }

    private function target(): string
    {
        return $this->tab === 'years' ? ($this->formFor ?? 'years') : $this->tab;
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

        return match ($this->target()) {
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
                'years' => AcademicYear::query()->with(['terms' => fn ($query) => $query->orderBy('start_date')])->orderByDesc('is_active')->orderByDesc('start_date')->get(),
                'dormitories' => Dormitory::query()->with('musyrif')->withCount('students')->orderBy('name')->get(),
                default => Classroom::query()->with('homeroomTeacher')->withCount('students')->orderBy('name')->get(),
            },
            'years' => AcademicYear::query()->orderByDesc('start_date')->pluck('name', 'id')->all(),
            'formTarget' => $this->target(),
            'splits' => CreateAcademicYear::SPLITS,
            'suggestion' => $this->tab === 'years' ? CreateAcademicYear::suggestion() : null,
            'teachers' => Teacher::query()->orderBy('name')->pluck('name', 'id')->all(),
            'musyrifs' => User::role('musyrif')->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }
}
