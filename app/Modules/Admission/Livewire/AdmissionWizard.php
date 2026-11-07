<?php

namespace App\Modules\Admission\Livewire;

use App\Modules\Admission\Actions\AdmissionOtp;
use App\Modules\Admission\Actions\RegisterApplicant;
use App\Modules\Admission\Models\AdmissionPeriod;
use App\Modules\Admission\Models\Applicant;
use App\Modules\Admission\Support\AdmissionException;
use App\Modules\Core\Actions\OtpResult;
use App\Modules\Core\Support\PhoneNumber;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Wizard pendaftaran publik (P-02): tanpa akun, cukup OTP WhatsApp. Tiap langkah tersimpan sebagai draf.
 */
#[Layout('components.layouts.auth')]
#[Title('Pendaftaran Santri Baru')]
class AdmissionWizard extends Component
{
    use WithFileUploads;

    public int $step = 0;

    public string $phone = '';

    public string $code = '';

    public bool $otpSent = false;

    public string $name = '';

    public string $gender = '';

    public string $birth_place = '';

    public string $birth_date = '';

    public string $address = '';

    public string $previous_school = '';

    public string $guardian_name = '';

    public string $guardian_email = '';

    public string $guardian_relation = 'ayah';

    /** @var array<string, mixed> */
    public array $uploads = [];

    public bool $consent = false;

    public function mount(): void
    {
        $applicant = Applicant::current();

        if ($applicant === null) {
            return;
        }

        if ($applicant->status !== 'draft') {
            $this->redirectRoute('ppdb.status');

            return;
        }

        $this->fillFrom($applicant);
        $this->step = 1;
    }

    private function applicant(): Applicant
    {
        $applicant = Applicant::current();
        abort_if($applicant === null, 403);

        return $applicant;
    }

    private function fillFrom(Applicant $applicant): void
    {
        $this->fill([
            'name' => (string) $applicant->name,
            'gender' => (string) $applicant->gender,
            'birth_place' => (string) $applicant->birth_place,
            'birth_date' => (string) $applicant->birth_date?->format('Y-m-d'),
            'address' => (string) $applicant->address,
            'previous_school' => (string) $applicant->previous_school,
            'guardian_name' => (string) $applicant->guardian_name,
            'guardian_email' => (string) $applicant->guardian_email,
            'guardian_relation' => $applicant->guardian_relation,
        ]);
    }

    public function sendOtp(AdmissionOtp $otp): void
    {
        $this->resetErrorBag();
        $phone = PhoneNumber::normalize($this->phone);

        if ($phone === null) {
            $this->addError('phone', 'Nomor WhatsApp belum benar. Contoh: 081234567890.');

            return;
        }

        if (! $otp->send($phone)) {
            $this->addError('phone', 'Terlalu sering meminta kode. Coba lagi 10 menit lagi.');

            return;
        }

        $this->otpSent = true;
    }

    public function verifyOtp(AdmissionOtp $otp, RegisterApplicant $register): void
    {
        $this->resetErrorBag();
        $period = AdmissionPeriod::accepting();

        if ($period === null) {
            $this->addError('code', 'Pendaftaran sedang ditutup.');

            return;
        }

        $phone = (string) PhoneNumber::normalize($this->phone);

        if ($otp->verify($phone, trim($this->code)) !== OtpResult::Valid) {
            $this->addError('code', 'Kode salah atau sudah kedaluwarsa.');

            return;
        }

        $applicant = $register->start($period, $phone);
        session()->regenerate();
        session([Applicant::SESSION_KEY => $applicant->uuid]);
        $this->fillFrom($applicant);
        $this->step = 1;
    }

    public function saveChild(): void
    {
        $this->resetErrorBag();
        $data = $this->validate([
            'name' => 'required|string|max:150',
            'gender' => 'required|in:L,P',
            'birth_place' => 'nullable|string|max:100',
            'birth_date' => 'required|date|before:today',
            'address' => 'required|string|max:500',
            'previous_school' => 'nullable|string|max:150',
        ], [], ['name' => 'nama', 'gender' => 'jenis kelamin', 'birth_date' => 'tanggal lahir', 'address' => 'alamat']);

        $this->applicant()->update($data);
        $this->step = 2;
    }

    public function saveGuardian(): void
    {
        $this->resetErrorBag();
        $data = $this->validate([
            'guardian_name' => 'required|string|max:150',
            'guardian_email' => 'nullable|email|max:150',
            'guardian_relation' => 'required|in:ayah,ibu,wali',
        ], [], ['guardian_name' => 'nama wali', 'guardian_email' => 'email']);

        $this->applicant()->update($data);
        $this->step = 3;
    }

    public function updatedUploads(mixed $file, string $type, RegisterApplicant $register): void
    {
        $this->resetErrorBag();
        $this->validate(['uploads.'.$type => 'file|mimes:jpg,jpeg,png,pdf|max:5120'], ['uploads.'.$type.'.mimes' => 'Berkas harus JPG, PNG, atau PDF.', 'uploads.'.$type.'.max' => 'Berkas maksimal 5 MB.']);

        try {
            $register->storeDocument($this->applicant(), $type, $file);
        } catch (AdmissionException $exception) {
            $this->addError('uploads.'.$type, $exception->getMessage());
        }

        unset($this->uploads[$type]);
    }

    public function toReview(RegisterApplicant $register): void
    {
        $this->resetErrorBag();
        $missing = array_diff(Applicant::REQUIRED_DOCUMENTS, $this->applicant()->documents()->pluck('type')->all());

        if ($missing !== []) {
            $this->addError('documents', 'Unggah dulu: '.implode(', ', array_map(fn ($type) => Applicant::DOCUMENTS[$type], $missing)).'.');

            return;
        }

        $this->step = 4;
    }

    public function back(): void
    {
        $this->resetErrorBag();
        $this->step = max(1, $this->step - 1);
    }

    public function submit(RegisterApplicant $register)
    {
        $this->resetErrorBag();

        try {
            $register->submit($this->applicant(), $this->consent);
        } catch (AdmissionException $exception) {
            $this->addError('consent', $exception->getMessage());

            return null;
        }

        return $this->redirectRoute('ppdb.status');
    }

    public function render(): View
    {
        $applicant = Applicant::current();

        return view('admission.wizard', [
            'period' => AdmissionPeriod::accepting() ?? $applicant?->period,
            'accepting' => AdmissionPeriod::accepting() !== null,
            'applicant' => $applicant,
            'documents' => $applicant?->documents()->get()->keyBy('type') ?? collect(),
        ]);
    }
}
