<?php

namespace App\Modules\Core\Livewire\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.auth')]
#[Title('Lupa sandi')]
class ForgotPassword extends Component
{
    public string $email = '';

    public bool $sent = false;

    public function send(): void
    {
        $this->validate(['email' => 'required|email'], [
            'email.required' => 'Isi email akun Anda.',
            'email.email' => 'Format email belum benar.',
        ]);

        $key = 'password-reset:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('email', 'Terlalu banyak permintaan. Coba lagi beberapa menit lagi.');

            return;
        }

        RateLimiter::hit($key, 600);
        Password::sendResetLink(['email' => $this->email]);

        // Jawaban sama untuk email terdaftar atau tidak, agar akun tidak bisa ditebak.
        $this->sent = true;
    }

    public function render(): View
    {
        return view('core.auth.forgot-password');
    }
}
