<?php

namespace App\Modules\Wallet\Http;

use App\Modules\Wallet\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->bearerToken();
        $device = $key === '' ? null : Device::query()->where('api_key_hash', hash('sha256', $key))->where('is_active', true)->first();

        if ($device === null) {
            return response()->json(['ok' => false, 'message' => 'Kunci perangkat tidak sah.'], 401);
        }

        // Cukup sekali per menit, agar pemindaian ramai tidak menulis ke tabel ini terus-menerus.
        if ($device->last_seen_at === null || $device->last_seen_at->diffInSeconds(now(), true) >= 60) {
            Device::query()->whereKey($device->id)->update(['last_seen_at' => now()]);
        }

        $request->attributes->set('device', $device);

        return $next($request);
    }
}
