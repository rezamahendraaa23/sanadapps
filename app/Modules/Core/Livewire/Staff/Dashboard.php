<?php

namespace App\Modules\Core\Livewire\Staff;

use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Guardian;
use App\Modules\Core\Models\SchoolProfile;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Core\Support\SetupChecklist;
use App\Modules\Finance\Models\CashTransaction;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Support\ArrearsQuery;
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

    public function render(SetupChecklist $checklist, ModuleRegistry $modules): View
    {
        $user = auth()->user();
        $showSetup = $user->can('settings.manage') && SchoolProfile::current()->setup_dismissed_at === null;
        $canSeeStudents = $user->can('viewAny', Student::class);

        $finance = $user->can('finance.view') && $modules->enabled('finance');
        $monthStart = now(config('sanad.display_timezone'))->startOfMonth();

        return view('core.staff.dashboard', [
            'finance' => $finance ? [
                'paidThisMonth' => (int) CashTransaction::query()->where('type', 'in')->whereDate('transaction_date', '>=', $monthStart)->sum('amount'),
                'arrears' => ArrearsQuery::rows()->sum('outstanding'),
                'billed' => (int) Invoice::query()->where('status', '!=', 'void')->whereDate('period', $monthStart)->sum('total'),
            ] : null,
            'heroIsFinance' => $finance && $user->can('finance.verify'),
            'pendingTransfers' => $user->can('finance.verify') && $modules->enabled('payment_manual') ? Payment::query()->where('status', 'review')->count() : 0,
            'steps' => $showSetup ? $checklist->steps() : [],
            'activeStudents' => $canSeeStudents ? Student::query()->visibleTo($user)->where('status', 'active')->count() : null,
            'classrooms' => $user->can('masters.manage') ? Classroom::query()->count() : null,
            'guardians' => $user->can('viewAny', Guardian::class) ? Guardian::query()->count() : null,
        ]);
    }
}
