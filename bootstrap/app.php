<?php

use App\Http\Middleware\ApplySchoolTimezone;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\EnsureUserType;
use App\Http\Middleware\SecurityHeaders;
use App\Modules\Core\Actions\StartSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'module' => EnsureModuleEnabled::class,
            'user.type' => EnsureUserType::class,
            'role' => RoleMiddleware::class,
        ]);
        $middleware->web(append: [ApplySchoolTimezone::class]);
        $middleware->append(SecurityHeaders::class);
        // Proxy di mesin yang sama (Cloudflare Tunnel, Nginx) memberi tahu bahwa pengunjung datang lewat HTTPS.
        // Hanya alamat lokal yang dipercaya, kecuali TRUSTED_PROXIES diisi untuk proxy di mesin lain.
        $middleware->trustProxies(at: array_filter(explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1,::1'))));
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => StartSession::homeFor($request->user()));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
