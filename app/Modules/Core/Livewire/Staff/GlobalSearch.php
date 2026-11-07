<?php

namespace App\Modules\Core\Livewire\Staff;

use App\Modules\Core\Models\Student;
use App\Modules\Finance\Models\Invoice;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class GlobalSearch extends Component
{
    public string $query = '';

    public function render(): View
    {
        $user = auth()->user();
        $term = trim($this->query);

        $results = mb_strlen($term) >= 2 && $user->can('viewAny', Student::class)
            ? Student::query()
                ->visibleTo($user)
                ->with('classroom:id,name')
                ->where(fn ($inner) => $inner
                    ->where('name', 'ilike', '%'.addcslashes($term, '%_').'%')
                    ->orWhere('nis', 'like', addcslashes($term, '%_').'%'))
                ->orderBy('name')
                ->limit(8)
                ->get(['id', 'uuid', 'name', 'nis', 'classroom_id'])
            : collect();

        $invoices = mb_strlen($term) >= 2 && $user->can('finance.view')
            ? Invoice::query()->with('student:id,name')->where('invoice_no', 'ilike', '%'.addcslashes($term, '%_').'%')->latest('id')->limit(5)->get()
            : collect();

        return view('core.staff.global-search', ['results' => $results, 'invoices' => $invoices, 'searched' => mb_strlen($term) >= 2]);
    }
}
