<?php

namespace App\Modules\Masjid\Support;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Perhitungan waktu sholat dari posisi matahari (rumus NOAA), dengan parameter Kemenag RI sebagai bawaan.
 * Hasilnya menit sejak tengah malam waktu setempat, belum dibulatkan dan belum dikoreksi.
 */
final class PrayerCalculator
{
    private const SUN_ALTITUDE = 0.8333;

    /** Ketinggian matahari waktu dhuha. */
    public const DHUHA_ALTITUDE = 4.5;

    /**
     * @return array{fajr: float, sunrise: float, dhuha: float, dhuhr: float, asr: float, maghrib: float, isha: float}
     */
    public static function compute(string $date, float $latitude, float $longitude, string $timezone, float $fajrAngle = 20.0, float $ishaAngle = 18.0, int $asrFactor = 1): array
    {
        $zone = new DateTimeZone($timezone);
        $noon = new DateTimeImmutable($date.' 12:00:00', $zone);
        $offsetHours = $zone->getOffset($noon) / 3600;
        $julian = self::julianDay((int) $noon->format('Y'), (int) $noon->format('n'), (int) $noon->format('j'));

        $transit = function (float $hour) use ($julian, $longitude, $offsetHours): float {
            return 12 + $offsetHours - $longitude / 15 - self::equationOfTime($julian + ($hour - $offsetHours) / 24 - 0.5) / 60;
        };

        // Dihitung ulang pada perkiraan jamnya sendiri, karena deklinasi matahari berubah sepanjang hari.
        $event = function (float $guess, callable $angle, int $direction) use ($julian, $latitude, $offsetHours, $transit): float {
            $hour = $guess;

            for ($iteration = 0; $iteration < 3; $iteration++) {
                $declination = self::declination($julian + ($hour - $offsetHours) / 24 - 0.5);
                $hour = $transit($hour) + $direction * self::hourAngle($angle($declination), $latitude, $declination) / 15;
            }

            return $hour;
        };

        $dhuhr = $transit(12);
        $rise = fn () => -self::SUN_ALTITUDE;
        $asr = fn (float $declination) => rad2deg(atan(1 / ($asrFactor + tan(deg2rad(abs($latitude - $declination))))));

        $hours = [
            'fajr' => $event(5, fn () => -$fajrAngle, -1),
            'sunrise' => $event(6, $rise, -1),
            'dhuha' => $event(6.5, fn () => self::DHUHA_ALTITUDE, -1),
            'dhuhr' => $dhuhr,
            'asr' => $event(15, $asr, 1),
            'maghrib' => $event(18, $rise, 1),
            'isha' => $event(19, fn () => -$ishaAngle, 1),
        ];

        return array_map(fn (float $hour) => $hour * 60, $hours);
    }

    private static function julianDay(int $year, int $month, int $day): float
    {
        if ($month <= 2) {
            $year--;
            $month += 12;
        }

        $century = (int) floor($year / 100);

        return floor(365.25 * ($year + 4716)) + floor(30.6001 * ($month + 1)) + $day + 2 - $century + (int) floor($century / 4) - 1524.5 + 0.5;
    }

    private static function hourAngle(float $altitude, float $latitude, float $declination): float
    {
        $lat = deg2rad($latitude);
        $dec = deg2rad($declination);
        $cosine = (sin(deg2rad($altitude)) - sin($lat) * sin($dec)) / (cos($lat) * cos($dec));

        return rad2deg(acos(max(-1.0, min(1.0, $cosine))));
    }

    /**
     * @return array{0: float, 1: float} Bujur ekliptika dan kemiringan orbit, derajat.
     */
    private static function sun(float $julian): array
    {
        $t = ($julian - 2451545.0) / 36525;
        $meanLongitude = fmod(280.46646 + $t * (36000.76983 + $t * 0.0003032), 360);
        $anomaly = deg2rad(357.52911 + $t * (35999.05029 - 0.0001537 * $t));
        $center = sin($anomaly) * (1.914602 - $t * (0.004817 + 0.000014 * $t)) + sin(2 * $anomaly) * (0.019993 - 0.000101 * $t) + sin(3 * $anomaly) * 0.000289;
        $omega = deg2rad(125.04 - 1934.136 * $t);
        $longitude = $meanLongitude + $center - 0.00569 - 0.00478 * sin($omega);
        $obliquity = 23 + (26 + (21.448 - $t * (46.815 + $t * (0.00059 - $t * 0.001813))) / 60) / 60 + 0.00256 * cos($omega);

        return [$longitude, $obliquity];
    }

    private static function declination(float $julian): float
    {
        [$longitude, $obliquity] = self::sun($julian);

        return rad2deg(asin(sin(deg2rad($obliquity)) * sin(deg2rad($longitude))));
    }

    /**
     * @return float Menit.
     */
    private static function equationOfTime(float $julian): float
    {
        $t = ($julian - 2451545.0) / 36525;
        [, $obliquity] = self::sun($julian);
        $meanLongitude = deg2rad(fmod(280.46646 + $t * (36000.76983 + $t * 0.0003032), 360));
        $anomaly = deg2rad(357.52911 + $t * (35999.05029 - 0.0001537 * $t));
        $eccentricity = 0.016708634 - $t * (0.000042037 + 0.0000001267 * $t);
        $y = tan(deg2rad($obliquity) / 2) ** 2;

        return rad2deg($y * sin(2 * $meanLongitude) - 2 * $eccentricity * sin($anomaly) + 4 * $eccentricity * $y * sin($anomaly) * cos(2 * $meanLongitude) - 0.5 * $y * $y * sin(4 * $meanLongitude) - 1.25 * $eccentricity * $eccentricity * sin(2 * $anomaly)) * 4;
    }
}
