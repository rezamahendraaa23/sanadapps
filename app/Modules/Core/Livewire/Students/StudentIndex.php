<?php

namespace App\Modules\Core\Livewire\Students;

use App\Modules\Core\Actions\SaveStudent;
use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Dormitory;
use App\Modules\Core\Models\Student;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.staff', ['active' => 'students'])]
#[Title('Santri')]
class StudentIndex extends Component
{
    use WithPagination;

    #[Url(as: 'cari', except: '')]
    public string $search = '';

    #[Url(as: 'kelas', except: '')]
    public string $classroom = '';

    #[Url(as: 'asrama', except: '')]
    public string $dormitory = '';

    #[Url(except: 'active')]
    public string $status = 'active';

    #[Url(except: 'name')]
    public string $sort = 'name';

    #[Url(except: 'asc')]
    public string $direction = 'asc';

    /** @var list<string> */
    public array $selected = [];

    public string $bulkClassroom = '';

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'classroom', 'dormitory', 'status'], true)) {
            $this->resetPage();
            $this->selected = [];
        }
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, ['name', 'nis'], true)) {
            return;
        }

        $this->direction = $this->sort === $column && $this->direction === 'asc' ? 'desc' : 'asc';
        $this->sort = $column;
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'classroom', 'dormitory', 'status');
        $this->resetPage();
    }

    public function moveSelected(SaveStudent $action): void
    {
        $this->authorize('create', Student::class);
        $this->validate(['bulkClassroom' => 'required|exists:classrooms,id', 'selected' => 'required|array|min:1'], ['bulkClassroom.required' => 'Pilih kelas tujuan.']);

        $students = Student::query()->visibleTo(auth()->user())->whereIn('uuid', $this->selected)->get();

        foreach ($students as $student) {
            $action->handle(['classroom_id' => (int) $this->bulkClassroom], $student);
        }

        $this->reset('selected', 'bulkClassroom');
        $this->dispatch('toast', message: "{$students->count()} santri dipindahkan.", tone: 'success');
    }

    private function query(): Builder
    {
        $search = trim($this->search);

        return Student::query()
            ->visibleTo(auth()->user())
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('name', 'ilike', '%'.addcslashes($search, '%_').'%')
                ->orWhere('nis', 'like', addcslashes($search, '%_').'%')))
            ->when($this->classroom !== '', fn (Builder $query) => $query->where('classroom_id', (int) $this->classroom))
            ->when($this->dormitory !== '', fn (Builder $query) => $query->where('dormitory_id', (int) $this->dormitory))
            ->when($this->status !== 'all', fn (Builder $query) => $query->where('status', $this->status));
    }

    public function render(): View
    {
        $sort = in_array($this->sort, ['name', 'nis'], true) ? $this->sort : 'name';

        return view('core.students.index', [
            'students' => $this->query()->with(['classroom:id,name', 'dormitory:id,name'])->orderBy($sort, $this->direction === 'desc' ? 'desc' : 'asc')->paginate(25),
            'classrooms' => Classroom::query()->ordered()->pluck('name', 'id')->all(),
            'dormitories' => Dormitory::query()->orderBy('name')->pluck('name', 'id')->all(),
            'hasAnyStudent' => Student::query()->visibleTo(auth()->user())->exists(),
        ]);
    }
}
