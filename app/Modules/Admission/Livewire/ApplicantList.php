<?php

namespace App\Modules\Admission\Livewire;

use App\Modules\Admission\Actions\AdmissionFlow;
use App\Modules\Admission\Models\AdmissionPeriod;
use App\Modules\Admission\Models\Applicant;
use App\Modules\Admission\Support\AdmissionException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Daftar pendaftar (PD-02).
 */
#[Layout('components.layouts.staff', ['active' => 'ppdb-applicants'])]
#[Title('Pendaftar')]
class ApplicantList extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $status = '';

    #[Url(as: 'cari', except: '')]
    public string $search = '';

    #[Url(as: 'periode', except: '')]
    public string $period = '';

    /** @var list<string> */
    public array $selected = [];

    public function updating(string $name): void
    {
        if (in_array($name, ['status', 'search', 'period'], true)) {
            $this->resetPage();
            $this->selected = [];
        }
    }

    public function startSelection(AdmissionFlow $flow): void
    {
        abort_unless(auth()->user()->can('ppdb.manage'), 403);
        $moved = 0;
        $skipped = 0;

        foreach (Applicant::query()->whereIn('uuid', $this->selected)->get() as $applicant) {
            try {
                $flow->startSelection($applicant);
                $moved++;
            } catch (AdmissionException) {
                $skipped++;
            }
        }

        $this->selected = [];
        $this->dispatch('toast', message: "{$moved} masuk seleksi".($skipped ? ", {$skipped} dilewati karena dokumennya belum terverifikasi." : '.'), tone: $skipped ? 'warning' : 'success');
    }

    public function render(): View
    {
        $term = trim($this->search);

        return view('admission.applicants', [
            'applicants' => Applicant::query()->with('period:id,name')
                ->whereNot('status', 'draft')
                ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
                ->when($this->period !== '', fn ($query) => $query->where('period_id', (int) $this->period))
                ->when($term !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('name', 'ilike', '%'.addcslashes($term, '%_').'%')->orWhere('reg_no', 'ilike', '%'.addcslashes($term, '%_').'%')))
                ->latest('id')->paginate(25),
            'statuses' => array_diff_key(Applicant::STATUSES, ['draft' => true]),
            'periods' => AdmissionPeriod::query()->latest('id')->pluck('name', 'id')->all(),
            'canManage' => auth()->user()->can('ppdb.manage'),
        ]);
    }
}
