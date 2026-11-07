<?php

namespace App\Modules\Academic\Support;

use App\Modules\Core\Models\Module;
use App\Modules\Core\Support\ModuleRegistry;

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

    /**
     * Cara absen yang bisa dipilih saat membuka sesi. Wajah hanya bila modulnya aktif.
     *
     * @return array<string, string>
     */
    public static function modeOptions(): array
    {
        $labels = ['manual' => 'Manual', 'card' => 'Tap kartu', 'qr' => 'Pindai QR', 'selfie' => 'Selfie dari HP santri'];

        if (app(ModuleRegistry::class)->enabled('face_attendance')) {
            $labels['face'] = 'Wajah di pos';
        }

        return array_intersect_key($labels, array_flip(self::verificationModes()));
    }

    /**
     * @return array{enabled: bool, lat: float|null, lng: float|null, radius: int}
     */
    public static function geofence(): array
    {
        $rules = self::attendance();

        return [
            'enabled' => (bool) ($rules['geofence_enabled'] ?? false),
            'lat' => isset($rules['geofence_lat']) ? (float) $rules['geofence_lat'] : null,
            'lng' => isset($rules['geofence_lng']) ? (float) $rules['geofence_lng'] : null,
            'radius' => (int) ($rules['geofence_radius'] ?? 100),
        ];
    }

    public static function selfieRetentionDays(): int
    {
        return (int) (self::attendance()['selfie_retention'] ?? 30);
    }

    /**
     * Titik mulai dari PRD; wajib dikalibrasi lewat PoC pada perangkat nyata.
     *
     * @return array{upper: float, lower: float}
     */
    public static function faceThresholds(): array
    {
        $rules = self::attendance();

        return ['upper' => (float) ($rules['face_upper'] ?? 0.80), 'lower' => (float) ($rules['face_lower'] ?? 0.60)];
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
