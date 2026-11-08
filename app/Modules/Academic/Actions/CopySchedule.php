<?php

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Models\Schedule;
use App\Modules\Academic\Support\AcademicException;
use Illuminate\Support\Facades\DB;

class CopySchedule
{
    /**
     * @return int Jumlah jadwal yang disalin.
     */
    public function handle(int $fromTermId, int $toTermId): int
    {
        if ($fromTermId === $toTermId) {
            throw new AcademicException('Pilih periode yang berbeda.');
        }

        if (Schedule::query()->where('term_id', $toTermId)->exists()) {
            throw new AcademicException('Periode tujuan sudah punya jadwal. Salin hanya untuk periode yang masih kosong.');
        }

        return DB::transaction(function () use ($fromTermId, $toTermId): int {
            $sources = Schedule::query()->where('term_id', $fromTermId)->get();

            foreach ($sources as $source) {
                Schedule::create([...$source->only(['classroom_id', 'subject_id', 'teacher_id', 'day_of_week', 'start_time', 'end_time', 'room', 'activity_type', 'title']), 'term_id' => $toTermId]);
            }

            return $sources->count();
        });
    }
}
