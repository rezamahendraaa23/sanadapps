<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Models\SchoolProfile;
use App\Modules\Core\Models\Student;
use Illuminate\Support\Facades\DB;

class GenerateNis
{
    private const LOCK_KEY = 73001;

    /**
     * Pola {tahun}{urut:4} menjadi 20260001. Dipanggil di dalam transaksi; kunci menjaga dua
     * pendaftaran bersamaan tidak mendapat nomor yang sama.
     */
    public function handle(?int $year = null): string
    {
        DB::statement('SELECT pg_advisory_xact_lock(?)', [self::LOCK_KEY]);

        $pattern = SchoolProfile::current()->nis_pattern;
        $prefix = str_replace('{tahun}', (string) ($year ?? now()->year), $pattern);
        preg_match('/\{urut:(\d)\}/', $prefix, $match);
        $digits = (int) ($match[1] ?? 4);
        [$before, $after] = explode($match[0] ?? '{urut:4}', $prefix) + [1 => ''];

        $last = Student::withTrashed()
            ->where('nis', 'like', addcslashes($before, '%_').'%'.addcslashes($after, '%_'))
            ->pluck('nis')
            ->map(fn (string $nis) => (int) substr($nis, strlen($before), strlen($nis) - strlen($before) - strlen($after)))
            ->max() ?? 0;

        return $before.str_pad((string) ($last + 1), $digits, '0', STR_PAD_LEFT).$after;
    }
}
