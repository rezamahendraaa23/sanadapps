<?php

namespace App\Modules\Letters\Support;

use App\Modules\Core\Models\Module;

/**
 * Pengaturan surat tersimpan di pengaturan modul `letters`.
 */
final class LetterSettings
{
    public const DEFAULTS = [
        'number_format' => '{nomor}/{kode}/{bulan_romawi}/{tahun}',
        'number_digits' => 3,
        'signer_name' => '',
        'signer_title' => 'Pimpinan Pondok',
        'city' => '',
    ];

    /**
     * @return array{number_format: string, number_digits: int, signer_name: string, signer_title: string, city: string}
     */
    public static function all(): array
    {
        return [...self::DEFAULTS, ...(Module::query()->where('module_key', 'letters')->value('settings') ?? [])];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function save(array $values): void
    {
        $module = Module::query()->where('module_key', 'letters')->firstOrFail();
        $module->update(['settings' => [...($module->settings ?? []), ...array_intersect_key($values, self::DEFAULTS)]]);
    }

    private const ROMAN = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    /**
     * Contoh: 007/SKS/XI/2026.
     */
    public static function formatNumber(string $format, int $sequence, int $digits, string $code, int $month, int $year): string
    {
        return strtr($format, [
            '{nomor}' => str_pad((string) $sequence, max(1, min(6, $digits)), '0', STR_PAD_LEFT),
            '{kode}' => $code,
            '{bulan_romawi}' => self::ROMAN[$month],
            '{bulan}' => str_pad((string) $month, 2, '0', STR_PAD_LEFT),
            '{tahun}' => (string) $year,
        ]);
    }
}
