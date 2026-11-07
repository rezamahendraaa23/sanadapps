<?php

namespace App\Modules\Admission\Livewire;

use App\Modules\Admission\Actions\AdmissionOtp;
use App\Modules\Admission\Models\AdmissionStep;
use App\Modules\Admission\Models\Applicant;
use App\Modules\Core\Actions\OtpResult;
use App\Modules\Exam\Actions\RunAttempt;
use App\Modules\Exam\Models\ExamAttempt;
use App\Modules\Exam\Support\ExamException;
use App\Modules\Finance\Actions\FinanceException;
use App\Modules\Finance\Models\Payment;
use App\Modules\Payment\Actions\CreateOnlinePayment;
use App\Modules\Payment\Support\MidtransConfig;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Cek status publik (P-03): nomor pendaftaran + OTP ke WhatsApp wali yang terdaftar.
 */
#[Layout('components.layouts.auth')]
#[Title('Cek Status Pendaftaran')]
class AdmissionStatus extends Component
{
    public string $reg_no = '';

    public string $code = '';

    public bool $otpSent = false;

    public string $examCode = '';

    public function sendOtp(AdmissionOtp $otp): void
    {
        $this->resetErrorBag();
        $this->validate(['reg_no' => 'required|string|max:40'], [], ['reg_no' => 'nomor pendaftaran']);

        if (RateLimiter::tooManyAttempts('ppdb-status:'.request()->ip(), 10)) {
            $this->addError('reg_no', 'Terlalu banyak percobaan. Coba lagi nanti.');

            return;
        }

        RateLimiter::hit('ppdb-status:'.request()->ip(), 600);

        // Jawaban sama walau nomor tidak ada, agar nomor pendaftaran tidak bisa ditebak.
        $applicant = Applicant::query()->where('reg_no', trim($this->reg_no))->first();

        if ($applicant !== null && ! $otp->send($applicant->guardian_phone)) {
            $this->addError('reg_no', 'Terlalu sering meminta kode. Coba lagi 10 menit lagi.');

            return;
        }

        $this->otpSent = true;
    }

    public function verifyOtp(AdmissionOtp $otp): void
    {
        $this->resetErrorBag();
        $applicant = Applicant::query()->where('reg_no', trim($this->reg_no))->first();

        if ($applicant === null || $otp->verify($applicant->guardian_phone, trim($this->code)) !== OtpResult::Valid) {
            $this->addError('code', 'Kode salah atau sudah kedaluwarsa.');

            return;
        }

        session()->regenerate();
        session([Applicant::SESSION_KEY => $applicant->uuid]);
        $this->reset('code', 'otpSent');
    }

    public function pay(CreateOnlinePayment $payments)
    {
        $applicant = Applicant::current();
        abort_if($applicant === null, 403);
        $invoice = $applicant->invoice;
        abort_if($invoice === null, 404);

        $pending = Payment::query()->where('status', 'pending')->where('method', 'midtrans')->whereHas('invoices', fn ($query) => $query->whereKey($invoice->id))->latest('id')->first();
        $url = $pending?->gateway_payload['redirect_url'] ?? null;

        if ($url === null) {
            try {
                $payment = $payments->handle(collect([$invoice]), null, null, route('ppdb.status'));
                $payment->update(['purpose' => 'admission_fee']);
                $url = $payment->gateway_payload['redirect_url'];
            } catch (FinanceException $exception) {
                $this->addError('pay', $exception->getMessage());

                return null;
            }
        }

        return $this->redirect($url);
    }

    public function startExam(string $stepUuid, RunAttempt $attempts)
    {
        $applicant = Applicant::current();
        abort_if($applicant === null, 403);
        $step = AdmissionStep::query()->where('period_id', $applicant->period_id)->where('uuid', $stepUuid)->whereNotNull('exam_id')->firstOrFail();
        $this->resetErrorBag();

        try {
            $attempt = $attempts->startForApplicant($step->exam, $applicant, $this->examCode, request()->ip(), request()->userAgent());
        } catch (ExamException $exception) {
            $this->addError('exam', $exception->getMessage());

            return null;
        }

        return $this->redirect(route('ppdb.exam.take', $attempt));
    }

    public function logout(): void
    {
        session()->forget(Applicant::SESSION_KEY);
        $this->reset();
    }

    public function render(MidtransConfig $midtrans): View
    {
        $applicant = Applicant::current();
        $exams = collect();

        if ($applicant?->status === 'in_selection') {
            $exams = $applicant->period->steps()->whereNotNull('exam_id')->with('exam')->get()->filter(fn (AdmissionStep $step) => $step->exam?->isOpen()
                && ExamAttempt::query()->where('exam_id', $step->exam_id)->where('applicant_id', $applicant->id)->whereNot('status', 'in_progress')->count() < $step->exam->max_attempts);
        }

        return view('admission.status', [
            'applicant' => $applicant,
            'publicStatus' => $applicant?->publicStatus(),
            'invoice' => $applicant?->invoice,
            'exams' => $exams,
            'onlineReady' => $midtrans->isReady(),
            'rejectedDocuments' => $applicant?->documents()->where('status', 'rejected')->get() ?? collect(),
        ]);
    }
}
