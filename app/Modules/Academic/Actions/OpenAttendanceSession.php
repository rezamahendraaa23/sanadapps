<?php

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Models\AttendanceRecord;
use App\Modules\Academic\Models\AttendanceSession;
use App\Modules\Academic\Models\Schedule;
use App\Modules\Academic\Support\AcademicException;
use App\Modules\Academic\Support\AcademicSettings;
use App\Modules\Core\Models\Student;
use App\Modules\Leave\Models\LeaveRequest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class OpenAttendanceSession
{
    /**
     * Membuka sesi dari jadwal; membuka dua kali mengembalikan sesi yang sama.
     */
    public function fromSchedule(Schedule $schedule, string $date, string $source, int $userId, ?array $modes = null): AttendanceSession
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
            ], Student::query()->where('status', 'active')->where('classroom_id', $schedule->classroom_id)->pluck('id')->all(), $modes);
        } catch (UniqueConstraintViolationException) {
            return AttendanceSession::query()->where('schedule_id', $schedule->id)->whereDate('session_date', $date)->firstOrFail();
        }
    }

    /**
     * Sesi asrama, sholat, atau kegiatan tanpa jadwal (ATT-02).
     */
    public function forDormitory(int $dormitoryId, string $kind, string $title, string $date, string $startTime, string $source, int $userId, ?array $modes = null): AttendanceSession
    {
        return $this->forGroup(null, $dormitoryId, $kind, $title, $date, $startTime, $source, $userId, $modes);
    }

    /**
     * Sesi tanpa jadwal untuk semua santri aktif, satu kelas, atau satu asrama.
     *
     * @param  list<string>|null  $modes  cara absen yang boleh dipakai di sesi ini; kosong berarti hanya $source
     */
    public function forGroup(?int $classroomId, ?int $dormitoryId, string $kind, string $title, string $date, string $startTime, string $source, int $userId, ?array $modes = null): AttendanceSession
    {
        return $this->create([
            'session_date' => $date,
            'kind' => $kind,
            'title' => $title,
            'classroom_id' => $classroomId,
            'dormitory_id' => $dormitoryId,
            'start_time' => $startTime,
            'source' => $source,
            'taken_by' => $userId,
        ], Student::query()->where('status', 'active')
            ->when($classroomId, fn ($query) => $query->where('classroom_id', $classroomId))
            ->when($dormitoryId, fn ($query) => $query->where('dormitory_id', $dormitoryId))
            ->pluck('id')->all(), $modes);
    }

    /**
     * Manual: semua hadir, pengabsen hanya mengubah yang tidak hadir (ATT-01). Kartu dan QR: semua alpa
     * sampai memindai, karena yang tidak memindai memang tidak hadir (PRD Sistem 8.9).
     *
     * Beberapa cara sekaligus boleh dipakai (manual, kartu, QR, wajah).
     *
     * @param  array<string, mixed>  $data
     * @param  list<int>  $studentIds
     * @param  list<string>|null  $modes
     */
    private function create(array $data, array $studentIds, ?array $modes = null): AttendanceSession
    {
        $allowed = AcademicSettings::verificationModes();
        $modes = array_values(array_unique([$data['source'], ...($modes ?? [])]));

        if (array_diff($modes, $allowed) !== []) {
            throw new AcademicException('Cara absen ini tidak diizinkan pesantren.');
        }

        return DB::transaction(function () use ($data, $studentIds, $modes): AttendanceSession {
            $session = AttendanceSession::create($data + ['allowed_modes' => $modes]);
            // Cara utama menentukan status awal: manual berarti hadir semua, pindai berarti alpa sampai tercatat.
            $scanned = $data['source'] !== 'manual';
            $onLeave = LeaveRequest::query()->whereIn('student_id', $studentIds)
                ->where('start_at', '<=', now())->where('end_at', '>=', now())
                ->where(fn ($query) => $query->where('status', 'approved')->orWhere(fn ($outside) => $outside->outside()))
                ->pluck('type', 'student_id');

            foreach ($studentIds as $studentId) {
                $leaveType = $onLeave[$studentId] ?? null;

                AttendanceRecord::create([
                    'session_id' => $session->id,
                    'student_id' => $studentId,
                    // Santri dengan izin aktif tidak dihitung alpa (PRD Sistem 8.9).
                    'status' => $leaveType !== null ? ($leaveType === 'sakit' ? 'sakit' : 'izin') : ($scanned ? 'alpa' : 'hadir'),
                    'method' => $data['source'],
                ]);
            }

            return $session;
        });
    }
}
