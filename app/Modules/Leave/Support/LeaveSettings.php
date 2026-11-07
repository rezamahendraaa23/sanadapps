<?php

namespace App\Modules\Leave\Support;

use App\Modules\Core\Models\Module;

final class LeaveSettings
{
    public static function twoLevel(): bool
    {
        return (bool) (self::all()['two_level'] ?? false);
    }

    public static function returnTolerance(): int
    {
        return (int) (self::all()['return_tolerance'] ?? 15);
    }

    public static function exitTolerance(): int
    {
        return (int) (self::all()['exit_tolerance'] ?? 30);
    }

    /**
     * @return array<string, mixed>
     */
    private static function all(): array
    {
        return Module::query()->where('module_key', 'leave')->value('settings') ?? [];
    }
}
