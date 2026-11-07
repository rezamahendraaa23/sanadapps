<?php

namespace App\Modules\Finance\Support;

use Illuminate\Support\Facades\DB;

final class DocumentNumber
{
    /**
     * Nomor berurutan tanpa lubang karena ikut transaksi pemanggil; baris penghitung terkunci
     * sampai transaksi selesai, jadi dua proses bersamaan tidak mendapat nomor yang sama.
     */
    public static function next(string $prefix): string
    {
        $month = now(config('sanad.display_timezone'))->format('Ym');
        $key = $prefix.'-'.$month;

        $value = DB::selectOne(
            'INSERT INTO document_counters (key, value) VALUES (?, 1)
             ON CONFLICT (key) DO UPDATE SET value = document_counters.value + 1
             RETURNING value',
            [$key],
        )->value;

        return sprintf('%s-%s-%05d', $prefix, $month, $value);
    }
}
