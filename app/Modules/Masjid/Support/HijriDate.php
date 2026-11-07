<?php

namespace App\Modules\Masjid\Support;

use App\Modules\Masjid\Models\HijriMonth;
use App\Modules\Masjid\Models\MasjidProfile;
use Carbon\CarbonImmutable;

/**
 * Tanggal hijriah tabular dengan koreksi. Awal bulan resmi diisi admin, sistem tidak menetapkannya (PRD Masjid 8.2).
 */
final class HijriDate
{
    public const MONTHS = ['Muharram', 'Safar', 'Rabiul Awal', 'Rabiul Akhir', 'Jumadil Awal', 'Jumadil Akhir', 'Rajab', 'Syakban', 'Ramadan', 'Syawal', 'Zulkaidah', 'Zulhijah'];

    /**
     * @return array{day: int, month: int, year: int, label: string, approximate: bool}
     */
    public static function for(string $date, ?MasjidProfile $profile = null): array
    {
        $set = HijriMonth::query()->whereDate('starts_on', '<=', $date)->orderByDesc('starts_on')->first();

        if ($set !== null) {
            $day = CarbonImmutable::parse($set->starts_on)->diffInDays(CarbonImmutable::parse($date)) + 1;

            if ($day <= 30) {
                return self::result((int) $day, $set->hijri_month, $set->hijri_year, false);
            }
        }

        $profile ??= MasjidProfile::current();
        [$day, $month, $year] = self::tabular(CarbonImmutable::parse($date)->addDays($profile->hijri_offset_days));

        return self::result($day, $month, $year, $day >= 28 || $day <= 2);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function tabular(CarbonImmutable $date): array
    {
        $julian = (int) gregoriantojd($date->month, $date->day, $date->year);
        $l = $julian - 1948440 + 10632;
        $n = (int) floor(($l - 1) / 10631);
        $l = $l - 10631 * $n + 354;
        $j = (int) (floor((10985 - $l) / 5316) * floor((50 * $l) / 17719) + floor($l / 5670) * floor((43 * $l) / 15238));
        $l = $l - (int) (floor((30 - $j) / 15) * floor((17719 * $j) / 50)) - (int) (floor($j / 16) * floor((15238 * $j) / 43)) + 29;
        $month = (int) floor((24 * $l) / 709);
        $day = $l - (int) floor((709 * $month) / 24);

        return [$day, $month, 30 * $n + $j - 30];
    }

    /**
     * @return array{day: int, month: int, year: int, label: string, approximate: bool}
     */
    private static function result(int $day, int $month, int $year, bool $approximate): array
    {
        return ['day' => $day, 'month' => $month, 'year' => $year, 'label' => $day.' '.self::MONTHS[$month - 1].' '.$year.' H', 'approximate' => $approximate];
    }
}
