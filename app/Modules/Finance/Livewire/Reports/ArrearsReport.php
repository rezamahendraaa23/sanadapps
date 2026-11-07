<?php

namespace App\Modules\Finance\Livewire\Reports;

use App\Modules\Core\Models\Classroom;
use App\Modules\Finance\Support\ArrearsQuery;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.staff', ['active' => 'arrears'])]
#[Title('Tunggakan')]
class ArrearsReport extends Component
{
    #[Url(as: 'kelas', except: '')]
    public string $classroom = '';

    public function render(): View
    {
        $rows = ArrearsQuery::rows($this->classroom === '' ? null : (int) $this->classroom);

        return view('finance.reports.arrears', [
            'rows' => $rows,
            'grandTotal' => $rows->sum('outstanding'),
            'classrooms' => Classroom::query()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }
}
