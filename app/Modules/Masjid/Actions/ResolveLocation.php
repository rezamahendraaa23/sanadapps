<?php

namespace App\Modules\Masjid\Actions;

use App\Modules\Masjid\Support\MasjidException;
use Illuminate\Support\Facades\Http;
use Throwable;

class ResolveLocation
{
    private const ENDPOINT = 'https://nominatim.openstreetmap.org/search';

    private const ZONES = [[0, 114.5, 'Asia/Jakarta'], [114.5, 126, 'Asia/Makassar'], [126, 180, 'Asia/Jayapura']];

    /**
     * Dipanggil hanya saat admin mencari lokasi, dan hanya nama tempat yang dikirim (PRD Masjid 8.8).
     *
     * @return array{latitude: float, longitude: float, label: string, timezone: string}
     */
    public function handle(string $query): array
    {
        try {
            $response = Http::timeout(10)->withHeaders(['User-Agent' => 'SANAD-Masjid/1.0'])
                ->get(self::ENDPOINT, ['q' => $query, 'format' => 'jsonv2', 'limit' => 1, 'countrycodes' => 'id']);
            $found = $response->successful() ? ($response->json()[0] ?? null) : null;
        } catch (Throwable $exception) {
            report($exception);

            throw new MasjidException('Layanan pencarian lokasi tidak bisa dihubungi. Isi koordinat manual.');
        }

        if ($found === null) {
            throw new MasjidException('Lokasi tidak ditemukan. Coba nama kota, atau isi koordinat manual.');
        }

        $longitude = (float) $found['lon'];

        return ['latitude' => round((float) $found['lat'], 6), 'longitude' => round($longitude, 6), 'label' => (string) $found['display_name'], 'timezone' => self::timezoneFor($longitude)];
    }

    /**
     * Pembagian WIB, WITA, WIT memakai perkiraan bujur; admin bisa mengubahnya.
     */
    public static function timezoneFor(float $longitude): string
    {
        foreach (self::ZONES as [$from, $to, $zone]) {
            if ($longitude >= $from && $longitude < $to) {
                return $zone;
            }
        }

        return 'Asia/Jakarta';
    }
}
