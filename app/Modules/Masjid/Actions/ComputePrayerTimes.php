<?php

namespace App\Modules\Masjid\Actions;

use App\Modules\Masjid\Models\MasjidProfile;
use App\Modules\Masjid\Models\PrayerAdjustment;
use App\Modules\Masjid\Models\PrayerOverride;
use App\Modules\Masjid\Models\PrayerReferenceTime;
use App\Modules\Masjid\Models\PrayerTime;
use App\Modules\Masjid\Support\PrayerCalculator;
use App\Support\SchoolDate;
use Carbon\CarbonImmutable;

class ComputePrayerTimes
{
    public const DAYS_AHEAD = 60;

    /**
     * Koreksi bawaan hasil ukur terhadap jadwal Kemenag 2026 di empat kota dan tiga zona waktu:
     * tanpa ini adzan tampil 2 sampai 4 menit lebih awal dari jadwal resmi.
     */
    public const DEFAULT_IHTIYATH = ['fajr' => 2, 'sunrise' => -3, 'dhuha' => 2, 'dhuhr' => 3, 'asr' => 2, 'maghrib' => 3, 'isha' => 2];

    /**
     * Hanya hari ini dan seterusnya: mengubah parameter tidak mengubah masa lalu (PRD Masjid 8.1).
     */
    public function handle(?string $from = null, int $days = self::DAYS_AHEAD): int
    {
        $profile = MasjidProfile::current();

        if (! $profile->hasLocation()) {
            return 0;
        }

        $start = CarbonImmutable::parse(max($from ?? SchoolDate::today()->toDateString(), SchoolDate::today()->toDateString()));
        $count = 0;

        for ($offset = 0; $offset < $days; $offset++) {
            $date = $start->addDays($offset)->toDateString();
            PrayerTime::query()->updateOrCreate(['date' => $date], [...$this->forDate($date, $profile), 'computed_at' => now()]);
            $count++;
        }

        return $count;
    }

    /**
     * @return array<string, string>
     */
    public function forDate(string $date, MasjidProfile $profile): array
    {
        $source = 'computed';
        $reference = $profile->schedule_source === 'api' ? PrayerReferenceTime::query()->whereDate('date', $date)->first() : null;

        if ($reference !== null && $reference->fajr !== null) {
            $source = 'imported';
            $times = collect(['imsak', 'fajr', 'sunrise', 'dhuha', 'dhuhr', 'asr', 'maghrib', 'isha'])->mapWithKeys(fn (string $prayer) => [$prayer => (string) $reference->{$prayer}])->all();
        } else {
            $times = $this->calculated($date, $profile);
        }

        foreach (PrayerOverride::query()->whereDate('date', $date)->get() as $override) {
            $times[$override->prayer] = substr((string) $override->time, 0, 5);
            $source = 'override';
        }

        return [...$times, 'source' => $source];
    }

    /**
     * @return array<string, string>
     */
    private function calculated(string $date, MasjidProfile $profile): array
    {
        $raw = PrayerCalculator::compute($date, $profile->latitude, $profile->longitude, $profile->timezone, $profile->fajr_angle, $profile->isha_angle, $profile->asr_method === 'hanafi' ? 2 : 1);
        $minutes = [];

        foreach ($raw as $prayer => $value) {
            $minutes[$prayer] = (int) round($value) + $this->adjustment($prayer, $date);
        }

        $minutes['imsak'] = $minutes['fajr'] - $profile->imsak_minutes + $this->adjustment('imsak', $date);

        return collect(['imsak', 'fajr', 'sunrise', 'dhuha', 'dhuhr', 'asr', 'maghrib', 'isha'])
            ->mapWithKeys(fn (string $prayer) => [$prayer => sprintf('%02d:%02d', intdiv($minutes[$prayer], 60), $minutes[$prayer] % 60)])->all();
    }

    private function adjustment(string $prayer, string $date): int
    {
        $row = PrayerAdjustment::query()->where('prayer', $prayer)->whereDate('effective_from', '<=', $date)->orderByDesc('effective_from')->first();

        return $row !== null ? $row->minutes : (self::DEFAULT_IHTIYATH[$prayer] ?? 0);
    }
}
