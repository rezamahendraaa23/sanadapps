<?php

namespace App\Modules\Academic\Support;

use App\Modules\Core\Models\Module;

/**
 * Aturan yang bisa diubah pesantren; nilai bawaan dari PRD Sistem 8.9, 8.9b, dan 8.10.
 */
final class AcademicSettings
{
    public const PREDICATES = [90 => 'A', 80 => 'B', 70 => 'C', 60 => 'D', 0 => 'E'];

    public static function lateTolerance(): int
    {
        return (int) (self::attendance()['late_tolerance'] ?? 10);
    }

    /**
     * @return list<string>
     */
    public static function verificationModes(): array
    {
        return self::attendance()['verification_modes'] ?? ['manual', 'card', 'qr'];
    }

    public static function predicate(float $score): string
    {
        foreach (self::PREDICATES as $minimum => $predicate) {
            if ($score >= $minimum) {
                return $predicate;
            }
        }

        return 'E';
    }

    /**
     * @return array<string, mixed>
     */
    private static function attendance(): array
    {
        return Module::query()->where('module_key', 'attendance')->value('settings') ?? [];
    }
}
