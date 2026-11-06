<?php

namespace App\Http\Middleware;

use App\Modules\Core\Actions\StartSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserType
{
    public function handle(Request $request, Closure $next, string $type): Response
    {
        $user = $request->user();

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login')->with('status', 'Akun ini sedang dinonaktifkan. Hubungi admin pesantren.');
        }

        if ($user->type !== $type) {
            return redirect()->to(StartSession::homeFor($user));
        }

        return $next($request);
    }
}
