<?php

namespace App\Modules\Core\Livewire\Auth;

use App\Modules\Core\Actions\StartSession;
use App\Modules\Core\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.auth')]
#[Title('Verifikasi dua langkah')]
class TwoFactorChallenge extends Component
{
    public string $code = '';

    public function mount()
    {
        return session('two_factor_user') ? null : $this->redirectRoute('login');
    }

    public function verify(TwoFactorAuthenticationProvider $provider)
    {
        $this->resetErrorBag();

        $user = User::find(session('two_factor_user'));

        if ($user === null) {
            return $this->redirectRoute('login');
        }

        $key = 'two-factor:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('code', 'Terlalu banyak percobaan. Coba lagi beberapa menit lagi.');

            return null;
        }

        if (! $this->codeIsValid($user, $provider)) {
            RateLimiter::hit($key, 300);
            $this->reset('code');
            $this->addError('code', 'Kode salah. Periksa aplikasi autentikator Anda.');

            return null;
        }

        RateLimiter::clear($key);
        $remember = (bool) session('two_factor_remember');
        session()->forget(['two_factor_user', 'two_factor_remember']);

        return $this->redirect(app(StartSession::class)->handle($user, $remember));
    }

    private function codeIsValid(User $user, TwoFactorAuthenticationProvider $provider): bool
    {
        $code = trim($this->code);

        if (preg_match('/^\d{6}$/', $code) === 1) {
            return $provider->verify(decrypt($user->two_factor_secret), $code);
        }

        $recovery = collect($user->recoveryCodes())->first(fn (string $stored) => hash_equals($stored, $code));

        if ($recovery === null) {
            return false;
        }

        $user->replaceRecoveryCode($recovery);

        return true;
    }

    public function render(): View
    {
        return view('core.auth.two-factor');
    }
}
