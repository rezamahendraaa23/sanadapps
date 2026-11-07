<?php

namespace App\Modules\Affairs\Actions;

use App\Modules\Affairs\Models\DutyAssignment;
use App\Modules\Affairs\Models\DutyRoster;
use App\Modules\Affairs\Support\AffairsException;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ManageDuty
{
    public function assign(DutyRoster $roster, string $type, int $assigneeId, string $date): DutyAssignment
    {
        if (CarbonImmutable::parse($date)->isoWeekday() !== $roster->day_of_week) {
            throw new AffairsException('Tanggal itu bukan hari '.DutyRoster::DAYS[$roster->day_of_week].'.');
        }

        try {
            return DB::transaction(fn () => DutyAssignment::create(['roster_id' => $roster->id, 'assignee_type' => $type, 'assignee_id' => $assigneeId, 'duty_date' => $date]));
        } catch (UniqueConstraintViolationException) {
            throw new AffairsException('Orang ini sudah terjadwal di piket tersebut.');
        }
    }

    /**
     * Tukar piket: dua penugasan bertukar orang, tanggal dan rosternya tetap (PKT-01).
     */
    public function swap(DutyAssignment $first, DutyAssignment $second): void
    {
        if ($first->id === $second->id) {
            throw new AffairsException('Pilih dua penugasan yang berbeda.');
        }

        DB::transaction(function () use ($first, $second): void {
            [$a, $b] = DutyAssignment::query()->whereIn('id', [$first->id, $second->id])->orderBy('id')->lockForUpdate()->get()->all();

            if ($a->status !== 'scheduled' || $b->status !== 'scheduled') {
                throw new AffairsException('Piket yang sudah berjalan tidak bisa ditukar.');
            }

            $people = [[$a->assignee_type, $a->assignee_id], [$b->assignee_type, $b->assignee_id]];

            try {
                DB::transaction(function () use ($a, $b, $people): void {
                    DB::table('duty_assignments')->where('id', $a->id)->update(['assignee_type' => 'student', 'assignee_id' => 0]);
                    $b->update(['assignee_type' => $people[0][0], 'assignee_id' => $people[0][1]]);
                    $a->update(['assignee_type' => $people[1][0], 'assignee_id' => $people[1][1]]);
                });
            } catch (UniqueConstraintViolationException) {
                throw new AffairsException('Salah satu orang sudah terjadwal di piket tujuan.');
            }
        });
    }
}
