<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Models\AcademicYear;
use App\Modules\Core\Models\Term;
use App\Support\SchoolDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Tahun ajaran dibuat sekaligus dengan term-nya, lalu langsung aktif bila belum ada periode yang berjalan.
 * Tahun ajaran aktif yang masih berlaku tidak pernah diganti diam-diam; itu tetap lewat tombol "Jadikan aktif".
 */
class CreateAcademicYear
{
    public const SPLITS = ['year' => 'Setahun penuh', 'semester' => 'Dua semester'];

    public function __construct(private SetActivePeriod $periods) {}

    /**
     * @param  array{name: string, start_date: string, end_date: string}  $data
     * @return array{year: AcademicYear, activated: bool}
     */
    public function handle(array $data, string $split): array
    {
        return DB::transaction(function () use ($data, $split): array {
            $year = AcademicYear::create($data);
            $start = CarbonImmutable::parse($data['start_date']);
            $end = CarbonImmutable::parse($data['end_date']);

            if ($split === 'semester' && $start->addMonthsNoOverflow(6)->lt($end)) {
                $middle = $start->addMonthsNoOverflow(6);
                $this->term($year, 'Semester 1', $start, $middle->subDay());
                $this->term($year, 'Semester 2', $middle, $end);
            } else {
                $this->term($year, 'Tahun penuh', $start, $end);
            }

            $activated = $this->shouldActivate();
            if ($activated) {
                $today = SchoolDate::today()->toDateString();
                $term = $year->terms()->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)->first()
                    ?? $year->terms()->orderBy('start_date')->first();
                $this->periods->term($term);
            }

            return ['year' => $year->fresh(), 'activated' => $activated];
        });
    }

    /**
     * Saran isian: tahun ajaran sesudah yang terakhir, atau yang sedang berjalan (Juli sampai Juni) bila belum ada.
     *
     * @return array{name: string, start_date: string, end_date: string}
     */
    public static function suggestion(): array
    {
        $latest = AcademicYear::query()->orderByDesc('end_date')->first();
        $today = SchoolDate::today();
        $start = $latest ? CarbonImmutable::parse($latest->end_date)->addDay() : CarbonImmutable::create($today->month >= 7 ? $today->year : $today->year - 1, 7, 1);
        $end = $start->addYear()->subDay();

        return ['name' => $start->year.'/'.$end->year, 'start_date' => $start->toDateString(), 'end_date' => $end->toDateString()];
    }

    /**
     * Aktif otomatis hanya bila belum ada tahun aktif, atau tahun aktif sudah lewat masanya.
     */
    private function shouldActivate(): bool
    {
        $active = AcademicYear::query()->where('is_active', true)->first();

        return $active === null || CarbonImmutable::parse($active->end_date)->lt(SchoolDate::today());
    }

    private function term(AcademicYear $year, string $name, CarbonImmutable $start, CarbonImmutable $end): Term
    {
        return $year->terms()->create(['name' => $name, 'start_date' => $start->toDateString(), 'end_date' => $end->toDateString()]);
    }
}
