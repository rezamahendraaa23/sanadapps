<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\Auth;

class StartSession
{
    public function __construct(private RecordAudit $audit) {}

    public function handle(User $user, bool $remember = false): string
    {
        Auth::login($user, $remember);
        session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        $this->audit->handle('login', 'User', $user->id);

        $home = self::homeFor($user);
        $intended = session()->pull('url.intended');

        return is_string($intended) && str_starts_with($intended, $home) ? $intended : $home;
    }

    public static function homeFor(User $user): string
    {
        return match ($user->type) {
            'guardian' => route('wali.home'),
            'student' => route('santri.home'),
            default => route('staff.dashboard'),
        };
    }
}
