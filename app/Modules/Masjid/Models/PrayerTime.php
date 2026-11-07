<?php

namespace App\Modules\Masjid\Models;

use Illuminate\Database\Eloquent\Model;

class PrayerTime extends Model
{
    public const NAMES = ['imsak' => 'Imsak', 'fajr' => 'Subuh', 'sunrise' => 'Terbit', 'dhuha' => 'Dhuha', 'dhuhr' => 'Dzuhur', 'asr' => 'Ashar', 'maghrib' => 'Maghrib', 'isha' => 'Isya'];

    public const OBLIGATORY = ['fajr', 'dhuhr', 'asr', 'maghrib', 'isha'];

    public $timestamps = false;

    /**
     * Sholat wajib berikutnya dari sekarang, untuk kartu di portal.
     *
     * @return array{0: string, 1: string}|null Nama dan jam.
     */
    public static function next(string $timezone): ?array
    {
        $now = now($timezone);

        foreach (self::query()->whereDate('date', '>=', $now->toDateString())->orderBy('date')->limit(2)->get() as $row) {
            foreach (self::OBLIGATORY as $prayer) {
                if ($row->date->toDateString().' '.$row->{$prayer} > $now->format('Y-m-d H:i')) {
                    return [self::NAMES[$prayer], $row->{$prayer}];
                }
            }
        }

        return null;
    }

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'computed_at' => 'immutable_datetime'];
    }
}
