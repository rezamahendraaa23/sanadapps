<?php

namespace App\Modules\Core\Livewire\Auth;

use App\Modules\Core\Actions\OtpResult;
use App\Modules\Core\Actions\RequestLoginOtp;
use App\Modules\Core\Actions\StartSession;
use App\Modules\Core\Actions\VerifyLoginOtp;
use App\Modules\Core\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.auth')]
#[Title('Verifikasi OTP')]
class VerifyOtp extends Component
{
    public string $code = '';

    public string $maskedPhone = '';

    public function mount()
    {
        $phone = session('otp_phone');

        if ($phone === null) {
            return $this->redirectRoute('login');
        }

        $this->maskedPhone = Str::mask($phone, '•', 4, max(strlen($phone) - 7, 1));

        return null;
    }

    public function verify(VerifyLoginOtp $action)
    {
        $this->resetErrorBag();

        $phone = session('otp_phone');

        if ($phone === null) {
            return $this->redirectRoute('login');
        }

        if (preg_match('/^\d{6}$/', $this->code) !== 1) {
            $this->addError('code', 'Kode terdiri dari 6 angka.');

            return null;
        }

        [$result, $left] = $action->handle($phone, $this->code);
        $user = $result === OtpResult::Valid
            ? User::query()->where('type', 'guardian')->where('phone', $phone)->where('is_active', true)->first()
            : null;

        if ($user === null) {
            $this->reset('code');
            $this->addError('code', match ($result) {
                OtpResult::Wrong => "OTP salah, sisa {$left} percobaan.",
                OtpResult::Locked => 'Percobaan habis. Minta kode baru.',
                default => 'Kode sudah tidak berlaku. Minta kode baru.',
            });

            return null;
        }

        session()->forget('otp_phone');

        return $this->redirect(app(StartSession::class)->handle($user, true));
    }

    public function resend(RequestLoginOtp $action): void
    {
        $phone = session('otp_phone');

        if ($phone === null) {
            return;
        }

        $this->reset('code');
        $this->resetErrorBag();

        if ($action->handle($phone)) {
            $this->dispatch('otp-sent');
            $this->dispatch('toast', message: 'Kode baru sudah dikirim.', tone: 'success');
        } else {
            $this->addError('code', 'Kode sudah diminta 3 kali. Coba lagi dalam 10 menit.');
        }
    }

    public function render(): View
    {
        return view('core.auth.verify-otp');
    }
}
