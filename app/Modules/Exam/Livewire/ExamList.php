<?php

namespace App\Modules\Exam\Livewire;

use App\Modules\Exam\Models\Exam;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Ujian')]
class ExamList extends Component
{
    use StaffPortalLayout, WithPagination;

    public function createExam()
    {
        $exam = Exam::create(['title' => 'Ujian baru', 'created_by' => auth()->id()]);

        return $this->redirect($this->examRoute('edit', $exam), navigate: true);
    }

    public function render(): View
    {
        [$layout, $params] = $this->examLayout('Ujian');

        return view('exam.list', [
            'exams' => Exam::query()->managedBy(auth()->user())->with('subject:id,name')->withCount(['attempts', 'questions'])->latest('id')->paginate(20),
            'banksUrl' => $this->examRoute('banks'),
            'routePrefix' => auth()->user()->can('exams.manage') ? 'staff.exams.' : 'ustadz.exams.',
        ])->layout($layout, $params);
    }
}
