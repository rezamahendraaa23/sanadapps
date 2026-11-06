<?php

namespace App\Http\Middleware;

use App\Modules\Core\Support\ModuleCatalog;
use App\Modules\Core\Support\ModuleRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    public function __construct(private ModuleRegistry $modules) {}

    public function handle(Request $request, Closure $next, string $module): Response
    {
        if ($this->modules->enabled($module)) {
            return $next($request);
        }

        $name = ModuleCatalog::MODULES[$module]['name'] ?? $module;
        $message = "Modul {$name} sedang tidak aktif. Admin bisa mengaktifkannya di Pengaturan, lalu Modul.";

        if ($request->expectsJson()) {
            return response()->json(['error' => ['code' => 'MODULE_DISABLED', 'message' => $message]], 403);
        }

        return response()->view('errors.module-disabled', ['message' => $message], 403);
    }
}
