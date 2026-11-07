<?php

namespace App\Modules\Masjid\Actions;

use App\Modules\Academic\Actions\OpenAttendanceSession;
use App\Modules\Academic\Models\AttendanceSession;
use App\Modules\Academic\Models\Schedule;
use App\Modules\Core\Models\Dormitory;
use App\Modules\Masjid\Models\IqamahSetting;
use App\Modules\Masjid\Models\MasjidProfile;
use App\Modules\Masjid\Models\PrayerTime;
use App\Support\SchoolDate;
use Carbon\CarbonImmutable;

/**
 * Sambungan ke jadwal santri dan absensi sholat (MSJ-PRY-11, MSJ-PRY-12).
 */
class ApplyPrayerToSanad
{
    public function __construct(private OpenAttendanceSession $sessions) {}

    /**
     * Blok jadwal berjangkar mengikuti waktu sholat hari ini; durasinya tetap.
     */
    public function shiftAnchoredSchedules(): int
    {
        $today = PrayerTime::query()->whereDate('date', SchoolDate::today()->toDateString())->first();

        if ($today === null) {
            return 0;
        }

        $count = 0;

        foreach (Schedule::query()->whereNotNull('anchor_prayer')->get() as $schedule) {
            $oldStart = CarbonImmutable::parse('2000-01-01 '.$schedule->start_time);
            $duration = $oldStart->diffInMinutes(CarbonImmutable::parse('2000-01-01 '.$schedule->end_time));
            $start = CarbonImmutable::parse('2000-01-01 '.$today->{$schedule->anchor_prayer})->addMinutes($schedule->anchor_offset_minutes);
            $schedule->update(['start_time' => $start->format('H:i'), 'end_time' => $start->addMinutes((int) $duration)->format('H:i')]);
            $count++;
        }

        return $count;
    }

    /**
     * Pada waktu iqamah, sesi absensi sholat tiap asrama sudah siap dipakai musyrif.
     */
    public function openAttendance(): int
    {
        $profile = MasjidProfile::current();

        if (! $profile->auto_attendance) {
            return 0;
        }

        $date = SchoolDate::today()->toDateString();
        $today = PrayerTime::query()->whereDate('date', $date)->first();

        if ($today === null) {
            return 0;
        }

        $now = now($profile->timezone);
        $count = 0;

        foreach (IqamahSetting::query()->get() as $setting) {
            $iqamah = CarbonImmutable::parse($date.' '.$today->{$setting->prayer}, $profile->timezone)->addMinutes($setting->minutes_after_adhan);

            if ($now->lessThan($iqamah) || $now->greaterThan($iqamah->addMinutes(10))) {
                continue;
            }

            $title = 'Sholat '.PrayerTime::NAMES[$setting->prayer];

            foreach (Dormitory::query()->whereNotNull('musyrif_user_id')->get() as $dormitory) {
                $exists = AttendanceSession::query()->whereDate('session_date', $date)->where('dormitory_id', $dormitory->id)->where('title', $title)->exists();

                if (! $exists) {
                    $this->sessions->forDormitory($dormitory->id, 'sholat', $title, $date, $iqamah->format('H:i'), 'manual', $dormitory->musyrif_user_id);
                    $count++;
                }
            }
        }

        return $count;
    }
}
