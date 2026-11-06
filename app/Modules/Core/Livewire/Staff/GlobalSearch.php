<?php

namespace App\Modules\Core\Livewire\Staff;

use App\Modules\Core\Models\Student;
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

        return view('core.staff.global-search', ['results' => $results, 'searched' => mb_strlen($term) >= 2]);
    }
}
