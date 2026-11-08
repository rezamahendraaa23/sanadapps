<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan (PRD Sistem §11). Semua aset dilayani dari domain sendiri, jadi CSP membatasi
 * sumber ke 'self'. Livewire dan Alpine butuh skrip inline dan eval, lihat docs/ASSUMPTIONS.md.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(self), payment=()');
        $headers->set('Content-Security-Policy', $this->policy());

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function policy(): string
    {
        // Server Vite saat pengembangan (berkas public/hot) ikut diizinkan.
        $dev = is_file(public_path('hot')) ? trim((string) file_get_contents(public_path('hot'))) : '';
        $devWs = $dev !== '' ? preg_replace('#^http#', 'ws', $dev) : '';
        $midtrans = 'https://app.midtrans.com https://app.sandbox.midtrans.com';

        return implode('; ', [
            "default-src 'self'",
            trim("script-src 'self' 'unsafe-inline' 'unsafe-eval' {$dev}"),
            trim("style-src 'self' 'unsafe-inline' {$dev}"),
            "img-src 'self' data: blob:",
            "media-src 'self' blob:",
            "font-src 'self' data:",
            trim("connect-src 'self' {$dev} {$devWs}"),
            "worker-src 'self' blob:",
            "frame-src 'self' https://www.youtube-nocookie.com https://www.youtube.com",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self' {$midtrans}",
            "frame-ancestors 'self'",
        ]);
    }
}
