<?php

namespace App\Modules\Core\Livewire\Staff;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.staff', ['active' => null])]
#[Title('Keamanan Akun')]
class AccountSecurity extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $code = '';

    public bool $showRecovery = false;

    public function changePassword(): void
    {
        $this->resetErrorBag();
        $this->validate(['current_password' => 'required', 'password' => ['required', 'confirmed', Password::min(8)]], [], ['current_password' => 'sandi sekarang', 'password' => 'sandi baru']);

        $user = auth()->user();

        if ($user->password === null || ! Hash::check($this->current_password, $user->password)) {
            $this->addError('current_password', 'Sandi sekarang salah.');

            return;
        }

        $user->update(['password' => $this->password]);
        $this->reset('current_password', 'password', 'password_confirmation');
        $this->dispatch('toast', message: 'Sandi berhasil diganti.', tone: 'success');
    }

    public function enable(EnableTwoFactorAuthentication $action): void
    {
        $action(auth()->user());
        $this->reset('code');
    }

    public function confirm(ConfirmTwoFactorAuthentication $action): void
    {
        $this->resetErrorBag();

        try {
            $action(auth()->user(), $this->code);
        } catch (ValidationException) {
            $this->addError('code', 'Kode salah. Periksa jam di HP lalu coba lagi.');

            return;
        }

        $this->reset('code');
        $this->showRecovery = true;
        $this->dispatch('toast', message: 'Verifikasi dua langkah aktif.', tone: 'success');
    }

    public function disable(DisableTwoFactorAuthentication $action): void
    {
        $action(auth()->user());
        $this->showRecovery = false;
        $this->dispatch('toast', message: 'Verifikasi dua langkah dimatikan.', tone: 'success');
    }

    public function render(): View
    {
        $user = auth()->user()->fresh();

        return view('core.staff.account-security', [
            'pending' => $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null,
            'enabled' => $user->two_factor_confirmed_at !== null,
            'qr' => $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null ? $user->twoFactorQrCodeSvg() : null,
            'recovery' => $this->showRecovery ? $user->recoveryCodes() : [],
        ]);
    }
}
