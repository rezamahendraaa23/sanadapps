<?php

namespace App\Support\Pdf;

final class PrintStyles
{
    private static ?string $cached = null;

    /**
     * CSS hasil build ditanam langsung, dan font disematkan sebagai data karena Chromium menolak
     * memuat font dari file://. Hanya subset latin dan arab yang disertakan agar PDF tetap kecil.
     */
    public static function inline(): string
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true);
        $css = (string) file_get_contents(public_path('build/'.$manifest['resources/css/app.css']['file']));

        return self::$cached = (string) preg_replace_callback('/@font-face\s*\{[^}]*\}/', function (array $match): string {
            if (preg_match('#url\("?(/build/assets/([^")]+\.woff2))"?\)#', $match[0], $font) !== 1
                || preg_match('/(-latin-|-arabic-)/', $font[2]) !== 1) {
                return '';
            }

            $data = base64_encode((string) file_get_contents(public_path(ltrim($font[1], '/'))));

            return (string) preg_replace('/src\s*:[^;}]+/', 'src:url(data:font/woff2;base64,'.$data.') format("woff2")', $match[0]);
        }, $css);
    }
}
