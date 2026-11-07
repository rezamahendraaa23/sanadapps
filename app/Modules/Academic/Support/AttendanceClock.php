<?php

namespace App\Modules\Academic\Support;

use App\Modules\Academic\Models\AttendanceSession;
use Carbon\CarbonImmutable;
use DateTimeInterface;

final class AttendanceClock
{
    /**
     * Hadir atau terlambat, dihitung dari jam mulai sesi dan toleransi pesantren.
     */
    public static function status(AttendanceSession $session, ?DateTimeInterface $at = null): string
    {
        $zone = config('sanad.display_timezone');
        $start = CarbonImmutable::parse($session->session_date->toDateString().' '.$session->start_time, $zone);
        $moment = $at === null ? now($zone) : CarbonImmutable::instance($at)->setTimezone($zone);

        return $moment->greaterThan($start->addMinutes(AcademicSettings::lateTolerance())) ? 'terlambat' : 'hadir';
    }
}
