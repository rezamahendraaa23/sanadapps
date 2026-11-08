<?php

namespace App\Modules\Core\Livewire\Staff;

use App\Modules\Core\Models\SchoolProfile;
use App\Modules\Core\Support\DashboardMetrics;
use App\Modules\Core\Support\SetupChecklist;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.staff', ['active' => 'home'])]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public function mark(string $key, SetupChecklist $checklist): void
    {
        abort_unless(auth()->user()->can('settings.manage'), 403);
        $checklist->mark($key);
    }

    public function dismissChecklist(SetupChecklist $checklist): void
    {
        abort_unless(auth()->user()->can('settings.manage'), 403);
        abort_unless(collect($checklist->steps())->every('done'), 422);

        SchoolProfile::current()->update(['setup_dismissed_at' => now()]);
    }

    public function render(SetupChecklist $checklist, DashboardMetrics $metrics): View
    {
        $user = auth()->user();
        $showSetup = $user->can('settings.manage') && SchoolProfile::current()->setup_dismissed_at === null;

        return view('core.staff.dashboard', [
            'steps' => $showSetup ? $checklist->steps() : [],
            'm' => $metrics->for($user),
        ]);
    }
}
