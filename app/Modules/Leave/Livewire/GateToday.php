<?php

namespace App\Modules\Leave\Livewire;

use App\Modules\Leave\Models\LeaveRequest;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.staff', ['active' => 'gate'])]
#[Title('Izin Hari Ini')]
class GateToday extends Component
{
    public function render(): View
    {
        $zone = config('sanad.display_timezone');
        $start = now($zone)->startOfDay()->utc();
        $end = now($zone)->endOfDay()->utc();

        return view('leave.gate-today', [
            'leaves' => LeaveRequest::query()->with('student.classroom:id,name')->whereIn('status', ['approved', 'out', 'returned', 'late'])->where('start_at', '<=', $end)->where('end_at', '>=', $start)->orderBy('start_at')->get(),
            'types' => LeaveRequest::TYPES,
        ]);
    }
}
