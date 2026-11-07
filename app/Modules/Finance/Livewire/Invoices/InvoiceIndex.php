<?php

namespace App\Modules\Finance\Livewire\Invoices;

use App\Modules\Core\Models\Classroom;
use App\Modules\Finance\Models\Invoice;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.staff', ['active' => 'invoices'])]
#[Title('Tagihan')]
class InvoiceIndex extends Component
{
    use WithPagination;

    #[Url(as: 'cari', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(as: 'kelas', except: '')]
    public string $classroom = '';

    #[Url(as: 'periode', except: '')]
    public string $period = '';

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'status', 'classroom', 'period'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status', 'classroom', 'period');
        $this->resetPage();
    }

    public function render(): View
    {
        $term = trim($this->search);

        return view('finance.invoices.index', [
            'invoices' => Invoice::query()
                ->visibleTo(auth()->user())
                ->with('student:id,name,nis,classroom_id')
                ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                    ->where('invoice_no', 'ilike', '%'.addcslashes($term, '%_').'%')
                    ->orWhereHas('student', fn (Builder $student) => $student
                        ->where('name', 'ilike', '%'.addcslashes($term, '%_').'%')
                        ->orWhere('nis', 'like', addcslashes($term, '%_').'%'))))
                ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
                ->when($this->classroom !== '', fn (Builder $query) => $query->whereHas('student', fn (Builder $student) => $student->where('classroom_id', (int) $this->classroom)))
                ->when(preg_match('/^\d{4}-\d{2}$/', $this->period) === 1, fn (Builder $query) => $query->whereDate('period', $this->period.'-01'))
                ->latest('id')
                ->paginate(25),
            'classrooms' => Classroom::query()->orderBy('name')->pluck('name', 'id')->all(),
            'hasAny' => Invoice::query()->exists(),
        ]);
    }
}
