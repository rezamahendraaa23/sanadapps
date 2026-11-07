<?php

namespace App\Modules\Masjid\Models;

use App\Modules\Core\Models\SchoolProfile;
use App\Modules\Core\Support\Audited;
use Illuminate\Database\Eloquent\Model;

class MasjidProfile extends Model
{
    use Audited;

    protected $table = 'masjid_profile';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'fajr_angle' => 'float', 'isha_angle' => 'float', 'friday_mode' => 'boolean', 'auto_attendance' => 'boolean', 'ramadan_start' => 'date', 'ramadan_end' => 'date', 'geocode_at' => 'immutable_datetime'];
    }

    public static function current(): self
    {
        // Dimuat ulang agar nilai bawaan dari database ikut terbaca.
        return self::query()->first() ?? self::create(['name' => SchoolProfile::current()->name, 'timezone' => SchoolProfile::current()->timezone ?: 'Asia/Jakarta'])->refresh();
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function isRamadan(string $date): bool
    {
        return $this->ramadan_start !== null && $this->ramadan_end !== null && $date >= $this->ramadan_start->toDateString() && $date <= $this->ramadan_end->toDateString();
    }
}
