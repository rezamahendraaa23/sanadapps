<?php

namespace App\Modules\Wallet\Support;

use App\Modules\Core\Models\Module;

/**
 * Isi kartu tercetak yang bisa dipilih pesantren. Foto, nama, NIS, dan QR selalu tampil.
 */
final class CardFields
{
    public const OPTIONS = [
        'entry_year' => ['label' => 'Tahun masuk', 'changes' => false],
        'birth' => ['label' => 'Tempat dan tanggal lahir', 'changes' => false],
        'classroom' => ['label' => 'Kelas', 'changes' => true],
        'dormitory' => ['label' => 'Asrama', 'changes' => true],
        'academic_year' => ['label' => 'Tahun ajaran', 'changes' => true],
    ];

    public const DEFAULTS = ['entry_year'];

    /**
     * @return list<string>
     */
    public static function selected(): array
    {
        $saved = Module::query()->where('module_key', 'card')->value('settings')['card_fields'] ?? self::DEFAULTS;

        return array_values(array_intersect(array_keys(self::OPTIONS), $saved));
    }

    /**
     * @param  list<string>  $fields
     */
    public static function save(array $fields): void
    {
        $module = Module::query()->where('module_key', 'card')->firstOrFail();
        $module->update(['settings' => [...($module->settings ?? []), 'card_fields' => array_values(array_intersect(array_keys(self::OPTIONS), $fields))]]);
    }
}
