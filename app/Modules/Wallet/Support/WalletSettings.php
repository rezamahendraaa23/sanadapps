<?php

namespace App\Modules\Wallet\Support;

use App\Modules\Core\Models\Module;

/**
 * Aturan dompet yang bisa diubah pesantren; nilai bawaan dari PRD Sistem 8.7.
 */
final class WalletSettings
{
    public const DEFAULTS = ['pin_threshold' => 20000, 'low_balance' => 10000, 'topup_min' => 10000, 'topup_max' => 5000000];

    /**
     * @return array{pin_threshold: int, low_balance: int, topup_min: int, topup_max: int}
     */
    public static function all(): array
    {
        $saved = Module::query()->where('module_key', 'wallet')->value('settings') ?? [];

        return array_map('intval', [...self::DEFAULTS, ...array_intersect_key($saved, self::DEFAULTS)]);
    }

    public static function get(string $key): int
    {
        return self::all()[$key];
    }
}
