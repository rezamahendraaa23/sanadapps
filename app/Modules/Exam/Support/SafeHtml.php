<?php

namespace App\Modules\Exam\Support;

/**
 * Teks soal: HTML terbatas tanpa atribut, jadi tidak ada skrip atau penangan peristiwa yang lolos.
 */
final class SafeHtml
{
    private const ALLOWED = '<b><strong><i><em><u><br><p><ul><ol><li><sub><sup>';

    public static function clean(string $html): string
    {
        $clean = strip_tags($html, self::ALLOWED);

        return trim((string) preg_replace('/<(\/?)([a-z0-9]+)(\s[^>]*)?>/i', '<$1$2>', $clean));
    }
}
