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

    /**
     * Ustadz dan musyrif bekerja dari HP di portalnya, kecuali punya peran panel lain.
     */
    private static function staffHome(User $user): string
    {
        $roles = $user->getRoleNames();

        if ($roles->diff(['ustadz', 'musyrif'])->isEmpty() && $roles->isNotEmpty()) {
            return route($roles->contains('ustadz') ? 'ustadz.home' : 'musyrif.home');
        }

        if ($roles->all() === ['penjaga_gerbang']) {
            return route('staff.gate.index');
        }

        return route('staff.dashboard');
    }

    public static function homeFor(User $user): string
    {
        return match ($user->type) {
            'guardian' => route('wali.home'),
            'student' => route('santri.home'),
            default => self::staffHome($user),
        };
    }
}
