<?php

namespace App\Modules\Masjid\Actions;

use App\Modules\Masjid\Models\MasjidProfile;
use App\Modules\Masjid\Models\PrayerReferenceTime;
use App\Modules\Masjid\Models\PrayerTime;
use App\Modules\Masjid\Support\MasjidException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Jadwal pembanding (MSJ-PRY-13). Layanannya tidak resmi, jadi hanya pembanding dan cadangan (PRD Masjid 8.8).
 */
class ReferenceSchedule
{
    private const BASE = 'https://api.myquran.com/v2/sholat';

    private const FIELDS = ['imsak' => 'imsak', 'fajr' => 'subuh', 'sunrise' => 'terbit', 'dhuha' => 'dhuha', 'dhuhr' => 'dzuhur', 'asr' => 'ashar', 'maghrib' => 'maghrib', 'isha' => 'isya'];

    public function __construct(private ComputePrayerTimes $computer) {}

    /**
     * @return int Jumlah hari tersimpan.
     */
    public function fetchMonth(string $month): int
    {
        $profile = MasjidProfile::current();

        if (blank($profile->city) && blank($profile->api_location_ref)) {
            throw new MasjidException('Isi nama kota dulu.');
        }

        try {
            $ref = $profile->api_location_ref ?: $this->findCity($profile);
            [$year, $number] = explode('-', $month);
            $days = Http::timeout(15)->withHeaders(['User-Agent' => 'SANAD-Masjid/1.0'])->get(self::BASE."/jadwal/{$ref}/{$year}/{$number}")->throw()->json('data.jadwal');
        } catch (MasjidException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw new MasjidException('Layanan jadwal tidak bisa dihubungi. Sistem tetap memakai hitungan sendiri.');
        }

        if (! is_array($days) || $days === []) {
            throw new MasjidException('Layanan jadwal tidak mengembalikan data bulan ini.');
        }

        foreach ($days as $day) {
            $values = collect(self::FIELDS)->map(fn (string $field) => $day[$field] ?? null)->all();
            PrayerReferenceTime::query()->updateOrCreate(['date' => $day['date'], 'city_ref' => $ref], [...$values, 'source_label' => 'API jadwal (data Kemenag Bimas Islam)']);
        }

        return count($days);
    }

    private function findCity(MasjidProfile $profile): string
    {
        $found = Http::timeout(15)->withHeaders(['User-Agent' => 'SANAD-Masjid/1.0'])->get(self::BASE.'/kota/cari/'.rawurlencode(trim((string) $profile->city)))->throw()->json('data');

        if (! is_array($found) || $found === []) {
            throw new MasjidException('Kota "'.$profile->city.'" tidak ada di layanan jadwal. Coba nama kabupaten atau kota yang lain.');
        }

        $ref = (string) $found[0]['id'];
        $profile->update(['api_location_ref' => $ref]);

        return $ref;
    }

    /**
     * Impor manual, satu baris per tanggal.
     *
     * @param  list<array<string, mixed>>  $rows  Kolom: tanggal, subuh, terbit, dzuhur, ashar, maghrib, isya.
     */
    public function import(array $rows): int
    {
        $count = 0;

        foreach ($rows as $row) {
            $date = $this->cell($row['tanggal'] ?? null);

            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }

            $values = [];

            foreach (self::FIELDS as $prayer => $field) {
                $values[$prayer] = isset($row[$field]) ? substr(str_replace('.', ':', $this->cell($row[$field])), 0, 5) : null;
            }

            PrayerReferenceTime::query()->updateOrCreate(['date' => $date, 'city_ref' => 'manual'], [...$values, 'source_label' => 'Impor manual']);
            $count++;
        }

        return $count;
    }

    private function cell(mixed $value): string
    {
        return trim((string) $value);
    }

    /**
     * Selisih menit: hitungan kita dikurangi pembanding; lebih dari 1 menit ditandai.
     *
     * @return array{rows: list<array<string, mixed>>, flagged: int, compared: int}
     */
    public function compare(string $month): array
    {
        $profile = MasjidProfile::current();
        $start = CarbonImmutable::parse($month.'-01');
        $references = PrayerReferenceTime::query()->whereBetween('date', [$start->toDateString(), $start->endOfMonth()->toDateString()])->orderBy('date')->get()->unique(fn ($row) => $row->date->toDateString());
        $rows = [];
        $flagged = 0;

        foreach ($references as $reference) {
            $date = $reference->date->toDateString();
            $ours = $profile->hasLocation() ? $this->computer->forDate($date, $profile) : null;
            $cells = [];

            foreach (PrayerTime::OBLIGATORY as $prayer) {
                $diff = $ours !== null && $reference->{$prayer} !== null ? self::minutes($ours[$prayer]) - self::minutes($reference->{$prayer}) : null;
                $bad = $diff !== null && abs($diff) > 1;
                $flagged += $bad ? 1 : 0;
                $cells[$prayer] = ['ours' => $ours[$prayer] ?? null, 'reference' => $reference->{$prayer}, 'diff' => $diff, 'bad' => $bad];
            }

            $rows[] = ['date' => $date, 'cells' => $cells];
        }

        return ['rows' => $rows, 'flagged' => $flagged, 'compared' => count($rows)];
    }

    private static function minutes(string $time): int
    {
        [$hour, $minute] = explode(':', $time);

        return (int) $hour * 60 + (int) $minute;
    }
}
