<?php

namespace App\Modules\Core\Support;

final class PhoneNumber
{
    /**
     * Mengubah 0812..., +62812..., atau 62812... menjadi format E.164 tanpa plus (PRD Sistem 6.2).
     */
    public static function normalize(string $input): ?string
    {
        $digits = preg_replace('/\D/', '', $input) ?? '';

        $digits = match (true) {
            str_starts_with($digits, '62') => $digits,
            str_starts_with($digits, '0') => '62'.substr($digits, 1),
            str_starts_with($digits, '8') => '62'.$digits,
            default => '',
        };

        return preg_match('/^628\d{7,12}$/', $digits) === 1 ? $digits : null;
    }
}
