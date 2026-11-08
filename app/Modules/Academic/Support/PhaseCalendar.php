<?php

namespace App\Modules\Academic\Support;

use App\Modules\Academic\Models\AcademicBreak;
use App\Modules\Masjid\Models\MasjidProfile;
use App\Modules\Masjid\Support\HijriDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Tanggal selesai fase dihitung dari hari pelajaran reguler: masa jeda (program khusus atau libur) tidak ikut terhitung.
 */
final class PhaseCalendar
{
    /**
     * @return array{ends_on: CarbonImmutable, break_days: int}
     */
    public static function end(CarbonImmutable $start, int $weeks, ?Collection $breaks = null): array
    {
        $breaks ??= AcademicBreak::query()->orderBy('starts_on')->get();
        $study = $weeks * 7;
        $day = $start;
        $counted = 0;
        $skipped = 0;

        // Maju per hari sampai hari belajar terpenuhi; batas aman 5 tahun agar libur yang salah isi tidak membuat putaran tanpa akhir.
        for ($guard = 0; $guard < 1830; $guard++) {
            $inBreak = $breaks->contains(fn (AcademicBreak $break) => $day->betweenIncluded($break->starts_on, $break->ends_on));
            $inBreak ? $skipped++ : $counted++;
            if ($counted >= $study) {
                break;
            }
            $day = $day->addDay();
        }

        return ['ends_on' => $day, 'break_days' => $skipped];
    }

    /**
     * Saran tanggal Program Ramadan: dari pengaturan Masjid bila diisi, atau perkiraan kalender hijriah.
     * Admin tetap bisa mengubahnya sebelum menyimpan, misalnya menambah libur Idul Fitri sebagai jeda terpisah.
     *
     * @return array{name: string, kind: string, starts_on: string, ends_on: string, source: string}|null
     */
    public static function ramadanSuggestion(CarbonImmutable $from): ?array
    {
        $profile = Schema::hasTable('masjid_profile') ? MasjidProfile::query()->first() : null;
        if ($profile?->ramadan_start && $profile->ramadan_end && $profile->ramadan_end->toDateString() >= $from->toDateString()) {
            $start = CarbonImmutable::parse($profile->ramadan_start);
            $end = CarbonImmutable::parse($profile->ramadan_end);
            $source = 'pengaturan Masjid';
        } else {
            [$start, $end] = self::estimateRamadan($from);
            if ($start === null) {
                return null;
            }
            $source = 'perkiraan kalender hijriah';
        }

        return [
            'name' => 'Program Ramadan '.$start->year,
            'kind' => 'special',
            'starts_on' => $start->toDateString(),
            'ends_on' => $end->toDateString(),
            'source' => $source,
        ];
    }

    /**
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}
     */
    private static function estimateRamadan(CarbonImmutable $from): array
    {
        $start = null;
        for ($i = 0; $i < 400; $i++) {
            $day = $from->addDays($i);
            $month = HijriDate::for($day->toDateString())['month'];
            if ($month === 9 && $start === null) {
                $start = $day;
            } elseif ($month !== 9 && $start !== null) {
                return [$start, $day->subDay()];
            }
        }

        return [null, null];
    }
}
