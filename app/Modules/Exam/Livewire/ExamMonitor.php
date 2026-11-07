<?php

namespace App\Modules\Exam\Livewire;

use App\Modules\Exam\Actions\ManageExam;
use App\Modules\Exam\Models\Exam;
use App\Modules\Exam\Models\ExamAttempt;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Monitor live (U-08); terputus = belum ada sinyal 90 detik.
 */
#[Title('Monitor Ujian')]
class ExamMonitor extends Component
{
    use StaffPortalLayout;

    private const DISCONNECTED_SECONDS = 90;

    public Exam $exam;

    public function mount(Exam $exam): void
    {
        abort_unless($exam->canBeManagedBy(auth()->user()), 403);
        $this->exam = $exam;
    }

    public function closeExam(ManageExam $exams): void
    {
        abort_unless($this->exam->canBeManagedBy(auth()->user()), 403);
        $exams->close($this->exam);
        $this->dispatch('close-modal', 'tutup-ujian');
        $this->dispatch('toast', message: 'Ujian ditutup. Peserta yang masih mengerjakan terkumpul otomatis.', tone: 'success');
    }

    public function render(ManageExam $exams): View
    {
        [$layout, $params] = $this->examLayout('Monitor Ujian');
        $students = $exams->targetStudents($this->exam);
        $attempts = ExamAttempt::query()->withCount('answers')->where('exam_id', $this->exam->id)->orderByDesc('attempt_no')->get()->unique('student_id')->keyBy('student_id');
        $total = $this->exam->questions()->count();
        $cutoff = now()->subSeconds(self::DISCONNECTED_SECONDS);

        $rows = $students->map(function ($student) use ($attempts, $cutoff) {
            $attempt = $attempts[$student->id] ?? null;
            $state = match (true) {
                $attempt === null => 'belum',
                $attempt->isOpen() && ($attempt->last_seen_at === null || $attempt->last_seen_at->lessThan($cutoff)) => 'terputus',
                $attempt->isOpen() => 'mengerjakan',
                default => 'selesai',
            };

            return ['student' => $student, 'attempt' => $attempt, 'state' => $state];
        })->sortBy(fn (array $row) => ['terputus' => 0, 'mengerjakan' => 1, 'belum' => 2, 'selesai' => 3][$row['state']])->values();

        return view('exam.monitor', [
            'rows' => $rows,
            'counts' => $rows->countBy('state'),
            'total' => $total,
            'updatedAt' => now(config('sanad.display_timezone'))->format('H.i.s'),
            'resultsUrl' => $this->examRoute('results', $this->exam),
        ])->layout($layout, $params)->title('Monitor: '.$this->exam->title);
    }
}
