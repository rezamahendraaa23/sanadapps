<?php

namespace App\Modules\Academic\Support;

use App\Modules\Core\Models\Module;
use App\Modules\Core\Models\SchoolProfile;
use Illuminate\Support\Facades\Storage;

/**
 * Sepuluh desain sertifikat. Judul dan nama pesantren boleh berbahasa Arab dengan font kaligrafi;
 * isi dari "diberikan kepada" sampai tanda tangan tetap bahasa Indonesia. Semua font berlisensi
 * SIL Open Font License (Google Fonts) dan disimpan lokal agar PDF tidak butuh internet.
 */
final class CertificateDesigns
{
    public const DEFAULT = 'mihrab';

    /** @var array<string, array{name: string, note: string, fonts: list<string>}> */
    public const ALL = [
        'mihrab' => ['name' => 'Mihrab Emas', 'note' => 'Bingkai lengkung mihrab, judul khat Ruq\'ah.', 'fonts' => ['aref-ruqaa-700', 'amiri-400', 'amiri-latin-400', 'amiri-latin-700']],
        'khatam' => ['name' => 'Bingkai Khatam', 'note' => 'Pita bintang delapan mengelilingi kertas, khat Naskh.', 'fonts' => ['amiri-700', 'amiri-400', 'amiri-latin-400', 'amiri-latin-700']],
        'kufi' => ['name' => 'Kufi Modern', 'note' => 'Bersih dan muda, blok warna di kiri, khat Kufi.', 'fonts' => ['reem-kufi-400-700', 'noto-kufi-arabic-400-900']],
        'naskh' => ['name' => 'Naskh Klasik', 'note' => 'Kertas gading, garis emas ganda, mawar di sudut.', 'fonts' => ['scheherazade-new-700', 'scheherazade-new-400', 'scheherazade-new-latin-400', 'scheherazade-new-latin-700']],
        'tinta' => ['name' => 'Tinta Malam', 'note' => 'Latar hijau tua penuh, tulisan emas, khat Ruq\'ah.', 'fonts' => ['aref-ruqaa-700', 'aref-ruqaa-400', 'amiri-latin-400', 'amiri-latin-700']],
        'arabesk' => ['name' => 'Arabesk', 'note' => 'Sulur arabesk di empat sudut, judul khat hias.', 'fonts' => ['katibeh-400', 'amiri-400', 'amiri-latin-400', 'amiri-latin-700']],
        'kubah' => ['name' => 'Siluet Kubah', 'note' => 'Deret kubah dan menara di bawah, judul tegas.', 'fonts' => ['rakkas-400', 'rakkas-latin-400', 'amiri-400']],
        'girih' => ['name' => 'Pita Girih', 'note' => 'Pita pola girih di sisi kiri, gaya kontemporer.', 'fonts' => ['el-messiri-400-700', 'el-messiri-latin-400-700']],
        'nastaliq' => ['name' => 'Nasta\'liq Lembut', 'note' => 'Warna lembut dan sudut membulat, khat Nasta\'liq.', 'fonts' => ['gulzar-400', 'lateef-400']],
        'tegas' => ['name' => 'Tegas Kontemporer', 'note' => 'Huruf Arab besar sebagai latar, untuk santri muda.', 'fonts' => ['lalezar-400', 'marhey-300-700']],
    ];

    private const RANGES = [
        'arabic' => 'U+0600-06FF, U+0750-077F, U+0870-088E, U+0890-0891, U+0897-08E1, U+08E3-08FF, U+200C-200E, U+2010-2011, U+204F, U+2E41, U+FB50-FDFF, U+FE70-FE74, U+FE76-FEFC',
        'latin' => 'U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD',
    ];

    /**
     * Keluarga huruf Arab utama desain, dipakai juga di halaman belakang.
     */
    public static function titleFont(string $key): string
    {
        $first = self::ALL[self::key($key)]['fonts'][0];

        return ucwords(str_replace('-', ' ', (string) preg_replace('/(-latin)?-\d{3}(-\d{3})?$/', '', $first)));
    }

    public static function key(?string $key): string
    {
        return isset(self::ALL[$key]) ? (string) $key : self::DEFAULT;
    }

    /**
     * @-font-face untuk desain ini. Data disematkan untuk PDF; pratinjau di browser memakai URL biasa.
     */
    public static function fontFaces(string $key, bool $embed = true): string
    {
        $css = '';

        foreach (self::ALL[self::key($key)]['fonts'] as $file) {
            preg_match('/^(.*?)(-latin)?-(\d{3})(?:-(\d{3}))?$/', $file, $match);
            $family = ucwords(str_replace('-', ' ', $match[1]));
            $weight = $match[3].(isset($match[4]) && $match[4] !== '' ? ' '.$match[4] : '');
            $path = public_path('fonts/arabic/'.$file.'.woff2');
            $src = $embed ? 'data:font/woff2;base64,'.base64_encode((string) file_get_contents($path)) : asset('fonts/arabic/'.$file.'.woff2');
            $range = self::RANGES[$match[2] !== '' ? 'latin' : 'arabic'];
            $css .= "@font-face{font-family:'{$family}';font-weight:{$weight};font-style:normal;src:url({$src}) format('woff2');unicode-range:{$range}}";
        }

        return $css;
    }

    /**
     * @return array{school_name_ar: string, default_design: string, logos: list<string>}
     */
    public static function settings(): array
    {
        $saved = Module::query()->where('module_key', 'curriculum')->value('settings') ?? [];

        return ['school_name_ar' => (string) ($saved['school_name_ar'] ?? ''), 'default_design' => self::key($saved['default_design'] ?? null), 'logos' => array_values($saved['logos'] ?? [])];
    }

    /**
     * Logo sertifikat sebagai data agar ikut tercetak; tanpa logo khusus dipakai logo pesantren.
     *
     * @return list<string>
     */
    public static function logoData(): array
    {
        $local = array_filter(self::settings()['logos'], fn (string $path) => Storage::disk('local')->exists($path));

        if ($local !== []) {
            return array_values(array_map(fn (string $path) => 'data:'.Storage::disk('local')->mimeType($path).';base64,'.base64_encode(Storage::disk('local')->get($path)), $local));
        }

        $profile = SchoolProfile::query()->value('logo_path');

        return $profile && Storage::disk('public')->exists($profile)
            ? ['data:'.Storage::disk('public')->mimeType($profile).';base64,'.base64_encode(Storage::disk('public')->get($profile))]
            : [];
    }

    /**
     * @param  array{school_name_ar?: string, default_design?: string, logos?: list<string>}  $values
     */
    public static function save(array $values): void
    {
        $module = Module::query()->where('module_key', 'curriculum')->firstOrFail();
        $module->update(['settings' => [...($module->settings ?? []), ...array_intersect_key($values, ['school_name_ar' => 1, 'default_design' => 1, 'logos' => 1])]]);
    }
}
