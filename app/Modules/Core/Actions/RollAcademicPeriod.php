<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Models\AcademicYear;
use App\Modules\Core\Models\Term;
use App\Support\SchoolDate;

/**
 * Pergantian periode otomatis, dijalankan tiap malam: admin cukup mengatur tahun ajaran sekali.
 * - Term aktif yang sudah lewat diganti term berikutnya di tahun yang sama.
 * - Tahun ajaran aktif yang sudah lewat diganti tahun berikutnya; bila belum ada, dibuat dengan pembagian yang sama.
 */
class RollAcademicPeriod
{
    public function __construct(private SetActivePeriod $periods, private CreateAcademicYear $create) {}

    /**
     * @return string|null Ringkasan perubahan, atau null bila tidak ada yang berganti.
     */
    public function handle(): ?string
    {
        $today = SchoolDate::today()->toDateString();
        $year = AcademicYear::query()->where('is_active', true)->first();

        if ($year === null) {
            return null;
        }

        if ($year->end_date->toDateString() < $today) {
            return $this->nextYear($year, $today);
        }

        $term = Term::query()->where('is_active', true)->first();
        if ($term !== null && $term->end_date->toDateString() < $today) {
            $next = $year->terms()->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)->first()
                ?? $year->terms()->whereDate('start_date', '>', $term->end_date->toDateString())->orderBy('start_date')->first();

            if ($next !== null) {
                $this->periods->term($next);

                return "Periode {$next->name} aktif.";
            }
        }

        return null;
    }

    private function nextYear(AcademicYear $ended, string $today): string
    {
        $existing = AcademicYear::query()->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)->first();

        if ($existing === null) {
            $split = $ended->terms()->count() >= 2 ? 'semester' : 'year';
            // Bila server lama mati, tahun yang terlewat dibuat berurutan sampai mencakup hari ini (paling banyak lima).
            for ($i = 0; $i < 5 && $existing === null; $i++) {
                $created = $this->create->handle(CreateAcademicYear::suggestion(), $split)['year'];
                $existing = $created->end_date->toDateString() >= $today ? $created : null;
            }
        }

        $year = $existing ?? AcademicYear::query()->orderByDesc('end_date')->firstOrFail();
        $term = $year->terms()->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)->first()
            ?? $year->terms()->orderBy('start_date')->first();
        $term ? $this->periods->term($term) : $this->periods->year($year);

        return "Tahun ajaran {$year->name} aktif.";
    }
}
