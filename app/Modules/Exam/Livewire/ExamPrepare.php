<?php

namespace App\Modules\Exam\Livewire;

use App\Modules\Exam\Actions\RunAttempt;
use App\Modules\Exam\Models\Exam;
use App\Modules\Exam\Models\ExamAttempt;
use App\Modules\Exam\Support\ExamException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.portal', ['role' => 'santri', 'active' => 'exams', 'title' => 'Persiapan Ujian'])]
#[Title('Persiapan Ujian')]
class ExamPrepare extends Component
{
    public Exam $exam;

    public string $code = '';

    public bool $honest = false;

    public function mount(Exam $exam): void
    {
        $student = auth()->user()->student;
        abort_unless($student && Exam::query()->whereKey($exam->id)->forStudent($student)->exists(), 403);
        $this->exam = $exam;
    }

    public function start(RunAttempt $attempts)
    {
        $this->resetErrorBag();

        if (! $this->honest) {
            $this->addError('honest', 'Centang pernyataan kejujuran dulu.');

            return null;
        }

        try {
            $attempt = $attempts->start($this->exam, auth()->user()->student, $this->code, request()->ip(), request()->userAgent());
        } catch (ExamException $exception) {
            $this->addError('code', $exception->getMessage());

            return null;
        }

        // Muat penuh: halaman ujian berdiri sendiri agar bisa dibuka lagi saat putus koneksi.
        return $this->redirect(route('exam.take', $attempt));
    }

    public function render(): View
    {
        return view('exam.prepare', [
            'continuing' => ExamAttempt::query()->where('exam_id', $this->exam->id)->where('student_id', auth()->user()->student->id)->where('status', 'in_progress')->exists(),
        ]);
    }
}
