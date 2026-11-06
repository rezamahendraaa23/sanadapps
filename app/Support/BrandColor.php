<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Warna utama pesantren hanya boleh dipakai bila teks putih di atasnya lolos 4,5:1 (PRD UI/UX 2.2).
 */
final class BrandColor
{
    public const DEFAULT = '#0B5D45';

    public const MINIMUM_CONTRAST = 4.5;

    public static function contrastWithWhite(string $hex): float
    {
        return 1.05 / (self::luminance($hex) + 0.05);
    }

    public static function isAccessible(string $hex): bool
    {
        return self::contrastWithWhite($hex) >= self::MINIMUM_CONTRAST;
    }

    /**
     * Versi terang pertama yang lolos kontras, untuk disarankan saat warna pilihan admin ditolak.
     */
    public static function suggestAccessible(string $hex): string
    {
        $candidate = self::normalize($hex);

        while (! self::isAccessible($candidate)) {
            $candidate = self::darken($candidate, 0.97);
        }

        return $candidate;
    }

    /**
     * CSS penimpa token primer, atau null bila warna bawaan dipakai atau warna gagal uji kontras.
     */
    public static function cssOverrides(?string $hex): ?string
    {
        if ($hex === null || ! self::isValid($hex)) {
            return null;
        }

        $color = self::normalize($hex);

        if ($color === self::DEFAULT || ! self::isAccessible($color)) {
            return null;
        }

        return sprintf(
            ':root{--color-primary:%s;--color-primary-hover:%s;--color-primary-active:%s;'
            .'--color-primary-soft:color-mix(in srgb,%1$s 9%%,white);'
            .'--color-primary-border:color-mix(in srgb,%1$s 28%%,white);--color-chart-1:%1$s}',
            $color,
            self::darken($color, 0.84),
            self::darken($color, 0.65),
        );
    }

    public static function isValid(string $hex): bool
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $hex) === 1;
    }

    private static function normalize(string $hex): string
    {
        if (! self::isValid($hex)) {
            throw new InvalidArgumentException("Warna tidak valid: {$hex}");
        }

        return strtoupper($hex);
    }

    private static function darken(string $hex, float $factor): string
    {
        return '#'.implode('', array_map(
            fn (int $channel): string => sprintf('%02X', (int) floor($channel * $factor)),
            self::channels($hex),
        ));
    }

    /**
     * @return array{int, int, int}
     */
    private static function channels(string $hex): array
    {
        $hex = self::normalize($hex);

        return [
            (int) hexdec(substr($hex, 1, 2)),
            (int) hexdec(substr($hex, 3, 2)),
            (int) hexdec(substr($hex, 5, 2)),
        ];
    }

    private static function luminance(string $hex): float
    {
        [$red, $green, $blue] = array_map(function (int $channel): float {
            $value = $channel / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, self::channels($hex));

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    }
}
