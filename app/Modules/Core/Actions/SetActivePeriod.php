<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Models\AcademicYear;
use App\Modules\Core\Models\Term;
use Illuminate\Support\Facades\DB;

class SetActivePeriod
{
    /**
     * Yang lama dimatikan dulu dalam satu transaksi agar indeks "satu aktif" tidak bentrok.
     */
    public function year(AcademicYear $year): void
    {
        DB::transaction(function () use ($year): void {
            AcademicYear::query()->where('is_active', true)->whereKeyNot($year->id)->get()->each->update(['is_active' => false]);
            Term::query()->where('is_active', true)->where('academic_year_id', '!=', $year->id)->get()->each->update(['is_active' => false]);
            $year->update(['is_active' => true]);
        });
    }

    public function term(Term $term): void
    {
        DB::transaction(function () use ($term): void {
            $this->year($term->academicYear);
            Term::query()->where('is_active', true)->whereKeyNot($term->id)->get()->each->update(['is_active' => false]);
            $term->update(['is_active' => true]);
        });
    }
}
