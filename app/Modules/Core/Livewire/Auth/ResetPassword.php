<?php

namespace App\Modules\Core\Livewire\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.auth')]
#[Title('Buat sandi baru')]
class ResetPassword extends Component
{
    #[Locked]
    public string $token = '';

    #[Url]
    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
    }

    public function save()
    {
        $this->validate(['email' => 'required|email', 'password' => ['required', 'confirmed', PasswordRule::min(8)]], [
            'password.required' => 'Isi sandi baru.',
            'password.confirmed' => 'Ulangi sandi belum sama.',
            'password.min' => 'Sandi minimal 8 karakter.',
        ]);

        $status = Password::reset(
            ['email' => $this->email, 'token' => $this->token, 'password' => $this->password, 'password_confirmation' => $this->password_confirmation],
            fn ($user, string $password) => $user->forceFill(['password' => $password])->save(),
        );

        if ($status !== Password::PASSWORD_RESET) {
            $this->addError('password', 'Tautan ini sudah tidak berlaku. Minta tautan baru dari halaman lupa sandi.');

            return null;
        }

        session()->flash('status', 'Sandi baru tersimpan. Silakan masuk.');

        return $this->redirectRoute('login', ['sebagai' => 'staf']);
    }

    public function render(): View
    {
        return view('core.auth.reset-password');
    }
}
