<?php

namespace App\Modules\Masjid\Http;

use App\Modules\Masjid\Models\DisplayScreen;
use App\Modules\Wallet\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateScreen
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) ($request->bearerToken() ?: $request->query('token'));
        $device = $token === '' ? null : Device::query()->where('type', 'tv_display')->where('api_key_hash', hash('sha256', $token))->where('is_active', true)->first();
        $screen = $device ? DisplayScreen::query()->where('device_id', $device->id)->where('is_active', true)->first() : null;

        if ($screen === null) {
            return response()->json(['ok' => false, 'message' => 'Token layar tidak sah.'], 401);
        }

        $request->attributes->set('screen', $screen);

        return $next($request);
    }
}
