<?php

namespace App\Modules\Core\Livewire\Auth;

use App\Modules\Core\Actions\RequestLoginOtp;
use App\Modules\Core\Actions\StartSession;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Core\Support\PhoneNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

#[Layout('components.layouts.auth')]
#[Title('Masuk')]
class Login extends Component
{
    private const MAX_ATTEMPTS = 5;

    private const LOCK_SECONDS = 300;

    #[Url(as: 'sebagai', except: 'wali')]
    public string $tab = 'wali';

    public string $phone = '';

    public string $identifier = '';

    public string $password = '';

    public bool $remember = false;

    public function selectTab(string $tab): void
    {
        $this->tab = in_array($tab, ['wali', 'santri', 'staf'], true) ? $tab : 'wali';
        $this->resetErrorBag();
        $this->reset('password');
    }

    public function requestOtp(RequestLoginOtp $action)
    {
        $this->resetErrorBag();

        $phone = PhoneNumber::normalize($this->phone);

        if ($phone === null) {
            $this->addError('phone', 'Nomor WhatsApp belum benar. Contoh: 081234567890.');

            return null;
        }

        try {
            $accepted = $action->handle($phone);
        } catch (RuntimeException $exception) {
            report($exception);
            $this->addError('phone', 'Kode belum bisa dikirim. Hubungi admin pesantren.');

            return null;
        }

        if (! $accepted) {
            $this->addError('phone', 'Kode sudah diminta 3 kali. Coba lagi dalam 10 menit.');

            return null;
        }

        session()->put('otp_phone', $phone);

        return $this->redirectRoute('login.otp', navigate: false);
    }

    public function loginWithPassword()
    {
        $this->resetErrorBag();

        $field = $this->tab === 'staf' ? 'email' : 'username';
        $type = $this->tab === 'staf' ? 'staff' : 'student';
        $key = 'login:'.$type.':'.mb_strtolower($this->identifier).'|'.request()->ip();

        if (trim($this->identifier) === '' || $this->password === '') {
            $this->addError('identifier', $this->tab === 'staf' ? 'Isi email dan sandi.' : 'Isi NIS dan sandi.');

            return null;
        }

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);
            $this->addError('identifier', "Terlalu banyak percobaan. Coba lagi dalam {$minutes} menit.");

            return null;
        }

        $user = User::query()->where('type', $type)->where($field, trim($this->identifier))->first();

        if ($user === null || $user->password === null || ! Hash::check($this->password, $user->password)) {
            RateLimiter::hit($key, self::LOCK_SECONDS);
            $this->addError('identifier', $this->tab === 'staf' ? 'Email atau sandi salah.' : 'NIS atau sandi salah.');

            return null;
        }

        if (! $user->is_active) {
            $this->addError('identifier', 'Akun ini sedang dinonaktifkan. Hubungi admin pesantren.');

            return null;
        }

        RateLimiter::clear($key);

        if ($user->hasEnabledTwoFactorAuthentication()) {
            session()->put('two_factor_user', $user->id);
            session()->put('two_factor_remember', $this->remember);

            return $this->redirectRoute('login.two-factor');
        }

        return $this->redirect(app(StartSession::class)->handle($user, $this->remember));
    }

    public function render(ModuleRegistry $modules): View
    {
        return view('core.auth.login', [
            'tabs' => array_filter([
                'wali' => $modules->enabled('portal_wali') ? 'Wali' : null,
                'santri' => $modules->enabled('portal_santri') ? 'Santri' : null,
                'staf' => 'Staf',
            ]),
        ]);
    }
}
