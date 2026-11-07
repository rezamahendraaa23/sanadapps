<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class SchoolDate
{
    /**
     * Tanggal hari ini menurut zona pesantren, sebagai tengah malam UTC. Kolom bertipe date dibaca
     * Laravel sebagai tengah malam UTC, jadi selisih hari dengan nilai ini selalu bilangan bulat.
     */
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::parse(now(config('sanad.display_timezone'))->toDateString(), 'UTC');
    }
}
