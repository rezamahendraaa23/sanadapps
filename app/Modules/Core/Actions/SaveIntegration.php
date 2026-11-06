<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Models\Integration;

class SaveIntegration
{
    /**
     * Kredensial yang dikosongkan berarti tidak diubah, sebab nilai aslinya tidak pernah dikirim ke layar.
     *
     * @param  array<string, string|null>  $credentials
     * @param  array<string, mixed>  $settings
     */
    public function handle(string $provider, ?string $mode, array $credentials, array $settings, bool $isActive): Integration
    {
        $integration = Integration::query()->firstOrNew(['provider' => $provider]);

        $merged = array_merge($integration->credentials ?? [], array_filter($credentials, fn ($value) => filled($value)));

        $integration->fill(['mode' => $mode, 'credentials' => $merged, 'settings' => $settings, 'is_active' => $isActive]);
        $integration->save();

        return $integration;
    }

    public static function mask(?string $secret): ?string
    {
        if (blank($secret)) {
            return null;
        }

        return '••••'.substr($secret, -4);
    }
}
