<?php

namespace App\Modules\Wallet\Support;

use App\Modules\Wallet\Models\StudentCard;
use Illuminate\Support\Facades\Cache;

/**
 * Token QR: HMAC dengan qr_secret per kartu (PRD Sistem 8.7). Yang dinamis berganti tiap 30 detik dan
 * sekali pakai, untuk belanja. Yang tercetak di kartu tetap, hanya untuk scope bukan belanja (A53).
 */
final class QrToken
{
    public const WINDOW_SECONDS = 30;

    private const DYNAMIC = 'D1';

    private const STATIC = 'S1';

    public static function dynamic(StudentCard $card): string
    {
        return self::sign(self::DYNAMIC, $card, (string) self::window());
    }

    public static function printed(StudentCard $card): string
    {
        return self::sign(self::STATIC, $card, '0');
    }

    public static function secondsLeft(): int
    {
        return self::WINDOW_SECONDS - (now()->getTimestamp() % self::WINDOW_SECONDS);
    }

    public static function isToken(string $value): bool
    {
        return str_starts_with($value, self::DYNAMIC.'.') || str_starts_with($value, self::STATIC.'.');
    }

    public static function isDynamic(string $value): bool
    {
        return str_starts_with($value, self::DYNAMIC.'.');
    }

    public static function cardUuid(string $value): ?string
    {
        return explode('.', $value)[1] ?? null;
    }

    /**
     * Jendela sebelumnya masih diterima agar token yang baru berganti saat dipindai tidak gagal.
     *
     * @return string|null Alasan penolakan, null bila sah.
     */
    public static function problem(string $value, StudentCard $card): ?string
    {
        $parts = explode('.', $value);

        if (count($parts) !== 4) {
            return 'QR tidak dikenali.';
        }

        [$kind, , $window] = $parts;

        if (! hash_equals(self::sign($kind, $card, $window), $value)) {
            return 'QR tidak dikenali.';
        }

        if ($kind === self::STATIC) {
            return null;
        }

        $age = self::window() - (int) $window;

        if ($age < 0 || $age > 1) {
            return 'QR sudah kedaluwarsa. Minta santri membuka kartu lagi.';
        }

        return Cache::has(self::usedKey($value)) ? 'QR ini sudah dipakai. Tunggu QR berikutnya.' : null;
    }

    /**
     * @return bool false bila ada proses lain yang lebih dulu memakainya.
     */
    public static function consume(string $value): bool
    {
        return ! self::isDynamic($value) || Cache::add(self::usedKey($value), true, self::WINDOW_SECONDS * 3);
    }

    private static function window(): int
    {
        return (int) floor(now()->getTimestamp() / self::WINDOW_SECONDS);
    }

    private static function sign(string $kind, StudentCard $card, string $window): string
    {
        $payload = implode('.', [$kind, $card->uuid, $window]);

        return $payload.'.'.substr(hash_hmac('sha256', $payload, $card->qr_secret), 0, 32);
    }

    private static function usedKey(string $value): string
    {
        return 'qr-used:'.hash('sha256', $value);
    }
}
