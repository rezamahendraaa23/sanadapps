<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Keputusan pemilik: setelah adzan hitung mundur iqamah 5 menit, lalu layar khusyuk 5 menit.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('iqamah_settings')->update(['minutes_after_adhan' => 5, 'prayer_duration_minutes' => 5, 'updated_at' => now()]);
    }

    public function down(): void
    {
        foreach (['fajr' => 15, 'dhuhr' => 10, 'asr' => 10, 'maghrib' => 8, 'isha' => 10] as $prayer => $minutes) {
            DB::table('iqamah_settings')->where('prayer', $prayer)->update(['minutes_after_adhan' => $minutes, 'prayer_duration_minutes' => $prayer === 'fajr' ? 15 : 12]);
        }
    }
};
