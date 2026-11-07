<?php

namespace App\Modules\Exam\Livewire;

use App\Modules\Exam\Models\Exam;
use App\Modules\Exam\Models\ExamAttempt;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.portal', ['role' => 'santri', 'active' => 'exams', 'title' => 'Ujian'])]
#[Title('Ujian')]
class StudentExams extends Component
{
    #[Url(except: 'tersedia')]
    public string $tab = 'tersedia';

    public function render(): View
    {
        $student = auth()->user()->student;
        $exams = $student ? Exam::query()->forStudent($student)->with('subject:id,name')->whereIn('status', ['published', 'closed'])->orderBy('start_at')->get() : collect();
        $attempts = $student ? ExamAttempt::query()->where('student_id', $student->id)->latest('attempt_no')->get()->groupBy('exam_id') : collect();

        $used = fn (Exam $exam) => count($attempts[$exam->id] ?? []);
        $active = fn (Exam $exam) => collect($attempts[$exam->id] ?? [])->contains('status', 'in_progress');

        return view('exam.student-list', [
            'available' => $exams->filter(fn (Exam $exam) => $exam->isOpen() && ($active($exam) || $used($exam) < $exam->max_attempts)),
            'upcoming' => $exams->filter(fn (Exam $exam) => $exam->status === 'published' && $exam->start_at?->isFuture()),
            'finished' => $exams->filter(fn (Exam $exam) => $used($exam) > 0 && ! $active($exam) && ! ($exam->isOpen() && $used($exam) < $exam->max_attempts)),
            'attempts' => $attempts,
        ]);
    }
}
