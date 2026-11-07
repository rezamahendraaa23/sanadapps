<?php

namespace App\Modules\Affairs\Livewire;

use App\Modules\Core\Models\Classroom;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Ekspor untuk Dapodik atau EMIS (EX-01). Kolomnya umum, karena template resmi belum diverifikasi (D5).
 */
#[Layout('components.layouts.staff', ['active' => 'export'])]
#[Title('Ekspor Dapodik/EMIS')]
class GovernmentExport extends Component
{
    public string $kind = 'dapodik';

    public string $classroom = '';

    public string $status = 'active';

    public function render(): View
    {
        return view('affairs.export', ['classrooms' => Classroom::query()->orderBy('name')->pluck('name', 'id')->all()]);
    }
}
