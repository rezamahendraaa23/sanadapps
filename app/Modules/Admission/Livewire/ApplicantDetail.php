<?php

namespace App\Modules\Admission\Livewire;

use App\Modules\Admission\Actions\AdmissionFlow;
use App\Modules\Admission\Actions\EnrollApplicant;
use App\Modules\Admission\Actions\ScoreApplicant;
use App\Modules\Admission\Models\Applicant;
use App\Modules\Admission\Models\ApplicantDocument;
use App\Modules\Admission\Support\AdmissionException;
use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Dormitory;
use App\Modules\Finance\Actions\FinanceException;
use App\Modules\Finance\Actions\RecordCashPayment;
use App\Modules\Finance\Models\FeeType;
use App\Modules\Finance\Support\Rupiah;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Detail pendaftar (PD-03): data, dokumen, nilai, keputusan, daftar ulang.
 */
#[Layout('components.layouts.staff', ['active' => 'ppdb-applicants'])]
#[Title('Detail Pendaftar')]
class ApplicantDetail extends Component
{
    public Applicant $applicant;

    /** @var array<string, string> */
    public array $scores = [];

    /** @var array<string, string> */
    public array $documentNotes = [];

    public string $classroom_id = '';

    public string $dormitory_id = '';

    #[Locked]
    public ?string $issuedPassword = null;

    #[Locked]
    public ?string $studentUrl = null;

    public function mount(Applicant $applicant): void
    {
        $this->applicant = $applicant;
        $this->loadScores();
    }

    private function loadScores(): void
    {
        $results = $this->applicant->results()->pluck('score', 'step_id');

        foreach ($this->applicant->period->steps()->get() as $step) {
            $this->scores[$step->uuid] = isset($results[$step->id]) ? rtrim(rtrim(number_format((float) $results[$step->id], 2, ',', ''), '0'), ',') : '';
        }
    }

    private function manage(): void
    {
        abort_unless(auth()->user()->can('ppdb.manage'), 403);
    }

    private function fail(AdmissionException|FinanceException $exception): void
    {
        $this->dispatch('toast', message: $exception->getMessage(), tone: 'danger');
    }

    public function reviewDocument(string $uuid, string $status, AdmissionFlow $flow): void
    {
        $this->manage();
        $document = ApplicantDocument::query()->where('applicant_id', $this->applicant->id)->where('uuid', $uuid)->firstOrFail();

        try {
            $flow->reviewDocument($document, $status, $this->documentNotes[$uuid] ?? null);
        } catch (AdmissionException $exception) {
            $this->fail($exception);
        }
    }

    public function verify(AdmissionFlow $flow): void
    {
        $this->manage();

        try {
            $flow->verify($this->applicant);
            $this->dispatch('toast', message: 'Dokumen terverifikasi.', tone: 'success');
        } catch (AdmissionException $exception) {
            $this->fail($exception);
        }
    }

    public function startSelection(AdmissionFlow $flow): void
    {
        $this->manage();

        try {
            $flow->startSelection($this->applicant);
        } catch (AdmissionException $exception) {
            $this->fail($exception);
        }
    }

    public function saveScores(ScoreApplicant $scoring): void
    {
        $this->manage();

        try {
            foreach ($this->applicant->period->steps()->get() as $step) {
                $value = str_replace(',', '.', trim($this->scores[$step->uuid] ?? ''));

                if ($value === '') {
                    continue;
                }

                if (! is_numeric($value)) {
                    throw new AdmissionException('Nilai '.$step->name.' harus angka.');
                }

                $scoring->save($this->applicant, $step, (float) $value, null, auth()->id());
            }

            $this->dispatch('toast', message: 'Nilai tersimpan.', tone: 'success');
        } catch (AdmissionException $exception) {
            $this->fail($exception);
        }

        $this->applicant->refresh();
    }

    public function decide(string $decision, AdmissionFlow $flow): void
    {
        $this->manage();

        try {
            $flow->decide($this->applicant, $decision);
        } catch (AdmissionException $exception) {
            $this->fail($exception);
        }

        $this->applicant->refresh();
    }

    public function recordCash(RecordCashPayment $payments): void
    {
        abort_unless(auth()->user()->can('finance.manage'), 403);
        $invoice = $this->applicant->invoice;
        abort_if($invoice === null, 404);

        try {
            $payments->handle([$invoice->id], $invoice->outstanding(), auth()->id());
            $this->dispatch('toast', message: 'Pembayaran tunai tercatat.', tone: 'success');
        } catch (FinanceException $exception) {
            $this->fail($exception);
        }

        $this->applicant->refresh();
    }

    public function enroll(EnrollApplicant $enroll): void
    {
        $this->manage();

        try {
            $result = $enroll->handle($this->applicant, $this->classroom_id === '' ? null : (int) $this->classroom_id, $this->dormitory_id === '' ? null : (int) $this->dormitory_id, auth()->id());
        } catch (AdmissionException $exception) {
            $this->fail($exception);

            return;
        }

        $this->issuedPassword = $result['password'];
        $this->studentUrl = route('staff.students.show', $result['student']);
        $this->applicant->refresh();
        $this->dispatch('close-modal', 'daftar-ulang');
        $this->dispatch('toast', message: 'Daftar ulang selesai.', tone: 'success');
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('admission.detail', [
            'documents' => $this->applicant->documents()->get(),
            'steps' => $this->applicant->period->steps()->get(),
            'invoice' => $this->applicant->invoice,
            'canManage' => $user->can('ppdb.manage'),
            'canCash' => $user->can('finance.manage'),
            'classrooms' => Classroom::query()->ordered()->pluck('name', 'id')->all(),
            'dormitories' => Dormitory::query()->orderBy('name')->pluck('name', 'id')->all(),
            'feeNames' => FeeType::query()->where('category', 'one_time')->where('is_active', true)->where('is_mandatory', true)->pluck('name')->all(),
            'money' => fn (int $value) => Rupiah::format($value),
        ])->title($this->applicant->name ?: 'Detail Pendaftar');
    }
}
