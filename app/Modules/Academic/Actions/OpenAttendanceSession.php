<?php

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Models\AttendanceRecord;
use App\Modules\Academic\Models\AttendanceSession;
use App\Modules\Academic\Models\Schedule;
use App\Modules\Academic\Support\AcademicException;
use App\Modules\Academic\Support\AcademicSettings;
use App\Modules\Core\Models\Student;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class OpenAttendanceSession
{
    /**
     * Membuka sesi dari jadwal; membuka dua kali mengembalikan sesi yang sama.
     */
    public function fromSchedule(Schedule $schedule, string $date, string $source, int $userId): AttendanceSession
    {
        $existing = AttendanceSession::query()->where('schedule_id', $schedule->id)->whereDate('session_date', $date)->first();

        if ($existing !== null) {
            return $existing;
        }

        if ($schedule->classroom_id === null) {
            throw new AcademicException('Jadwal ini tidak punya kelas sasaran.');
        }

        try {
            return $this->create([
                'schedule_id' => $schedule->id,
                'session_date' => $date,
                'kind' => $schedule->activity_type === 'sholat' ? 'sholat' : ($schedule->activity_type === 'kbm' ? 'kelas' : 'kegiatan'),
                'title' => $schedule->label(),
                'classroom_id' => $schedule->classroom_id,
                'start_time' => $schedule->start_time,
                'source' => $source,
                'taken_by' => $userId,
            ], Student::query()->where('status', 'active')->where('classroom_id', $schedule->classroom_id)->pluck('id')->all());
        } catch (UniqueConstraintViolationException) {
            return AttendanceSession::query()->where('schedule_id', $schedule->id)->whereDate('session_date', $date)->firstOrFail();
        }
    }

    /**
     * Sesi asrama, sholat, atau kegiatan tanpa jadwal (ATT-02).
     */
    public function forDormitory(int $dormitoryId, string $kind, string $title, string $date, string $startTime, string $source, int $userId): AttendanceSession
    {
        return $this->create([
            'session_date' => $date,
            'kind' => $kind,
            'title' => $title,
            'dormitory_id' => $dormitoryId,
            'start_time' => $startTime,
            'source' => $source,
            'taken_by' => $userId,
        ], Student::query()->where('status', 'active')->where('dormitory_id', $dormitoryId)->pluck('id')->all());
    }

    /**
     * Manual: semua hadir, pengabsen hanya mengubah yang tidak hadir (ATT-01). Kartu dan QR: semua alpa
     * sampai memindai, karena yang tidak memindai memang tidak hadir (PRD Sistem 8.9).
     *
     * @param  array<string, mixed>  $data
     * @param  list<int>  $studentIds
     */
    private function create(array $data, array $studentIds): AttendanceSession
    {
        if (! in_array($data['source'], AcademicSettings::verificationModes(), true)) {
            throw new AcademicException('Cara absen ini tidak diizinkan pesantren.');
        }

        return DB::transaction(function () use ($data, $studentIds): AttendanceSession {
            $session = AttendanceSession::create($data + ['allowed_modes' => [$data['source']]]);
            $scanned = $data['source'] !== 'manual';

            foreach ($studentIds as $studentId) {
                AttendanceRecord::create([
                    'session_id' => $session->id,
                    'student_id' => $studentId,
                    'status' => $scanned ? 'alpa' : 'hadir',
                    'method' => $data['source'],
                ]);
            }

            return $session;
        });
    }
}
