<?php

namespace App\Modules\Tahfidz\Livewire;

use App\Modules\Core\Models\Student;
use App\Modules\Tahfidz\Models\MutabaahLog;
use App\Modules\Tahfidz\Support\TahfidzRecap;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Verifikasi mutabaah (U-12): musyrif untuk santri asramanya, ustadz untuk kelasnya.
 */
#[Title('Verifikasi Mutabaah')]
class MutabaahVerify extends Component
{
    #[Url(as: 'tanggal', except: '')]
    public string $date = '';

    /** @var list<string> */
    public array $selected = [];

    public function mount(): void
    {
        $this->date = TahfidzRecap::date($this->date) ?? now(config('sanad.display_timezone'))->toDateString();
    }

    private function logs()
    {
        return MutabaahLog::query()
            ->with(['student:id,name', 'item:id,name'])
            ->whereIn('student_id', Student::query()->visibleTo(auth()->user())->select('students.id'))
            ->whereDate('log_date', TahfidzRecap::date($this->date) ?? now()->toDateString())
            ->where('status', 'done')
            ->whereNull('verified_by');
    }

    public function verifySelected(): void
    {
        $count = (clone $this->logs())->whereIn('uuid', $this->selected)->update(['verified_by' => auth()->id(), 'verified_at' => now()]);
        $this->reset('selected');
        $this->dispatch('toast', message: "{$count} amalan diverifikasi.", tone: 'success');
    }

    public function verifyAll(): void
    {
        $count = (clone $this->logs())->update(['verified_by' => auth()->id(), 'verified_at' => now()]);
        $this->reset('selected');
        $this->dispatch('toast', message: "{$count} amalan diverifikasi.", tone: 'success');
    }

    public function render(): View
    {
        $user = auth()->user();
        $role = $user->hasRole('ustadz') ? 'ustadz' : 'musyrif';
        $portal = ! $user->can('tahfidz.manage');

        return view('tahfidz.verify', ['logs' => $this->logs()->get()->groupBy('student_id')])
            ->layout($portal ? 'components.layouts.portal' : 'components.layouts.staff', $portal ? ['role' => $role, 'active' => $role === 'ustadz' ? 'tahfidz' : 'home', 'title' => 'Verifikasi Mutabaah'] : ['active' => 'tahfidz']);
    }
}
