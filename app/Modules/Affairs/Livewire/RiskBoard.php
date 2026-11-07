<?php

namespace App\Modules\Affairs\Livewire;

use App\Modules\Affairs\Actions\ComputeRiskScores;
use App\Modules\Affairs\Models\StudentRiskScore;
use App\Modules\Core\Models\Module;
use App\Modules\Core\Models\Student;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Daftar santri berisiko beserta alasannya (ANL-01).
 */
#[Layout('components.layouts.staff', ['active' => 'analytics'])]
#[Title('Analitik Risiko')]
class RiskBoard extends Component
{
    use WithPagination;

    #[Url(except: 'high')]
    public string $level = 'high';

    /** @var array<string, string> */
    public array $weights = [];

    public function mount(): void
    {
        $this->weights = array_map('strval', ComputeRiskScores::weights());
    }

    public function compute(ComputeRiskScores $scores): void
    {
        $count = $scores->handle();
        $this->dispatch('toast', message: "{$count} santri dihitung.", tone: 'success');
    }

    public function saveWeights(): void
    {
        abort_unless(auth()->user()->can('settings.manage'), 403);
        $this->resetErrorBag();
        $this->validate(['weights' => 'array', 'weights.*' => 'required|integer|min:0|max:20'], ['weights.*' => 'Bobot harus angka 0 sampai 20.']);
        $module = Module::query()->where('module_key', 'analytics')->firstOrFail();
        $module->update(['settings' => [...($module->settings ?? []), 'weights' => array_map('intval', array_intersect_key($this->weights, ComputeRiskScores::WEIGHTS))]]);
        $this->dispatch('toast', message: 'Bobot tersimpan. Berlaku saat hitung berikutnya.', tone: 'success');
    }

    public function render(): View
    {
        $latest = StudentRiskScore::query()->max('computed_on');
        $base = StudentRiskScore::query()->when($latest, fn ($query) => $query->whereDate('computed_on', $latest), fn ($query) => $query->whereRaw('1 = 0'))
            ->whereIn('student_id', Student::query()->visibleTo(auth()->user())->select('students.id'));

        return view('affairs.risk', [
            'scores' => (clone $base)->with('student:id,uuid,name,nis')->where('level', $this->level)->orderByDesc('score')->paginate(25),
            'counts' => (clone $base)->selectRaw('level, COUNT(*) AS total')->groupBy('level')->pluck('total', 'level'),
            'latest' => $latest,
            'labels' => ['arrears_two' => 'Menunggak 2 bulan atau lebih', 'arrears_one' => 'Menunggak 1 bulan', 'attendance' => 'Kehadiran di bawah 85%', 'grades' => 'Nilai turun lebih dari 10', 'discipline' => 'Poin melewati ambang pertama', 'late_leave' => 'Terlambat kembali 2 kali'],
            'canConfigure' => auth()->user()->can('settings.manage'),
        ]);
    }
}
