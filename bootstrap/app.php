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
        // Daftar proxy tepercaya dibaca dari config('sanad.trusted_proxies') di AppServiceProvider, agar tetap berlaku setelah config:cache.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => StartSession::homeFor($request->user()));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
