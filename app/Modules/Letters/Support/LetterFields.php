<?php

namespace App\Modules\Letters\Support;

use App\Modules\Core\Models\SchoolProfile;
use App\Modules\Core\Models\Student;
use App\Support\SchoolIdentity;
use Carbon\CarbonInterface;

/**
 * Isian otomatis dalam template: {nama_santri}, {nomor_surat}, dan seterusnya. Hasilnya teks biasa,
 * jadi tidak ada HTML dari isian yang ikut tercetak.
 */
final class LetterFields
{
    /** Isian bawaan untuk bantuan di layar template. */
    public const BUILT_IN = [
        'nomor_surat' => 'Nomor surat',
        'tanggal_surat' => 'Tanggal surat, misalnya 8 November 2026',
        'kota' => 'Kota surat dibuat',
        'kepada' => 'Penerima surat',
        'nama_lembaga' => 'Nama pesantren',
        'alamat_lembaga' => 'Alamat pesantren',
        'telepon_lembaga' => 'Telepon pesantren',
        'penandatangan' => 'Nama penandatangan',
        'jabatan' => 'Jabatan penandatangan',
        'nama_santri' => 'Nama santri',
        'nis' => 'NIS',
        'kelas' => 'Kelas santri',
        'asrama' => 'Asrama santri',
        'jenis_kelamin' => 'Laki-laki atau Perempuan',
        'tempat_tanggal_lahir' => 'Tempat dan tanggal lahir santri',
        'alamat_santri' => 'Alamat santri',
        'nama_wali' => 'Nama wali utama',
    ];

    /** Isian yang harus diketik petugas; isian lain diambil dari data dan bila kosong ditulis tanda hubung. */
    public const TYPED = ['kepada', 'penandatangan', 'jabatan'];

    /**
     * @param  array<string, string>  $extra  Isian tambahan template dan isian yang diketik.
     * @return array<string, string>
     */
    public static function values(?Student $student, CarbonInterface $date, string $number, ?string $recipient, string $signerName, string $signerTitle, string $city, array $extra = []): array
    {
        $profile = SchoolProfile::query()->first();
        $guardian = $student?->guardians()->orderByPivot('is_primary', 'desc')->first();

        $values = [
            'nomor_surat' => $number,
            'tanggal_surat' => $date->locale('id')->translatedFormat('j F Y'),
            'kota' => $city,
            'kepada' => (string) $recipient,
            'nama_lembaga' => app(SchoolIdentity::class)->displayName(),
            'alamat_lembaga' => (string) $profile?->address,
            'telepon_lembaga' => (string) $profile?->phone,
            'penandatangan' => $signerName,
            'jabatan' => $signerTitle,
            'nama_santri' => (string) $student?->name,
            'nis' => (string) $student?->nis,
            'kelas' => (string) $student?->classroom?->name,
            'asrama' => (string) $student?->dormitory?->name,
            'jenis_kelamin' => $student ? ($student->gender === 'P' ? 'Perempuan' : 'Laki-laki') : '',
            'tempat_tanggal_lahir' => $student ? collect([$student->birth_place, $student->birth_date?->locale('id')->translatedFormat('j F Y')])->filter()->implode(', ') : '',
            'alamat_santri' => (string) $student?->address,
            'nama_wali' => (string) $guardian?->name,
        ];

        foreach ($values as $key => $value) {
            if (trim($value) === '' && ! in_array($key, self::TYPED, true)) {
                $values[$key] = '-';
            }
        }

        return [...$extra, ...$values];
    }

    /**
     * @param  array<string, string>  $values
     */
    public static function fill(string $text, array $values): string
    {
        return (string) preg_replace_callback('/\{([a-z0-9_]+)\}/', fn (array $match) => array_key_exists($match[1], $values) ? $values[$match[1]] : $match[0], $text);
    }

    /**
     * Isian yang tertulis di template tetapi belum punya nilai, supaya surat tidak terbit dengan kurung kurawal.
     *
     * @param  array<string, string>  $values
     * @return list<string>
     */
    public static function missing(string $text, array $values): array
    {
        preg_match_all('/\{([a-z0-9_]+)\}/', $text, $matches);

        return array_values(array_unique(array_filter($matches[1], fn (string $key) => trim((string) ($values[$key] ?? '')) === '')));
    }
}
