<?php

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Models\Schedule;
use App\Modules\Academic\Support\AcademicException;
use App\Modules\Core\Models\Term;
use Illuminate\Support\Facades\DB;

class SaveSchedule
{
    /**
     * Bentrok ustadz, kelas, atau ruang ditolak dengan alasan (ACD-02, ACD-03).
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?Schedule $schedule = null): Schedule
    {
        return DB::transaction(function () use ($data, $schedule): Schedule {
            // Dikunci per term supaya dua admin tidak bisa menyimpan jadwal bentrok bersamaan.
            Term::query()->whereKey($data['term_id'])->lockForUpdate()->firstOrFail();

            if (($problem = $this->conflict($data, $schedule?->id)) !== null) {
                throw new AcademicException($problem);
            }

            if ($schedule !== null) {
                $schedule->update($data);

                return $schedule;
            }

            return Schedule::create($data);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function conflict(array $data, ?int $ignoreId = null): ?string
    {
        $overlap = Schedule::query()
            ->with(['teacher:id,name', 'classroom:id,name', 'subject:id,name'])
            ->where('term_id', $data['term_id'])
            ->where('day_of_week', $data['day_of_week'])
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time'])
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->get();

        foreach ($overlap as $other) {
            $when = $other->startLabel().' sampai '.$other->endLabel();

            if (filled($data['teacher_id'] ?? null) && (int) $data['teacher_id'] === $other->teacher_id) {
                return "Bentrok: {$other->teacher->name} sudah mengajar {$other->label()} pukul {$when}.";
            }

            if (filled($data['classroom_id'] ?? null) && (int) $data['classroom_id'] === $other->classroom_id) {
                return "Bentrok: {$other->classroom->name} sudah ada {$other->label()} pukul {$when}.";
            }

            if (filled($data['room'] ?? null) && mb_strtolower(trim($data['room'])) === mb_strtolower(trim((string) $other->room))) {
                return "Bentrok: ruang {$other->room} sudah dipakai {$other->label()} pukul {$when}.";
            }
        }

        return null;
    }
}
