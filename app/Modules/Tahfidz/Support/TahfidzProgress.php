<?php

namespace App\Modules\Tahfidz\Support;

use App\Modules\Academic\Support\StudentSchedule;
use App\Modules\Core\Models\Student;
use App\Modules\Tahfidz\Models\QuranSurah;
use App\Modules\Tahfidz\Models\TahfidzRecord;
use App\Modules\Tahfidz\Models\TahfidzTarget;
use Illuminate\Support\Collection;

final class TahfidzProgress
{
    private const PAGES_PER_JUZ = 20;

    /**
     * Progres target term aktif; hanya ziyadah yang menambah hafalan.
     *
     * @return array{pages: float, target_pages: float, percent: int|null, juz: float, target_juz: float, has_target: bool}
     */
    public static function forStudent(Student $student): array
    {
        $term = StudentSchedule::activeTerm();
        $target = $term ? TahfidzTarget::query()->where('student_id', $student->id)->where('term_id', $term->id)->first() : null;
        $pages = $term
            ? (float) TahfidzRecord::query()->where('student_id', $student->id)->where('type', 'ziyadah')->whereBetween('record_date', [$term->start_date->toDateString(), $term->end_date->toDateString()])->sum('pages')
            : 0.0;
        $targetPages = (float) ($target?->target_pages ?? 0);
        $targetJuz = (float) ($target?->target_juz ?? 0);
        $goal = $targetPages > 0 ? $targetPages : $targetJuz * self::PAGES_PER_JUZ;

        return [
            'pages' => $pages,
            'target_pages' => $targetPages,
            'percent' => $goal > 0 ? min(100, (int) floor($pages / $goal * 100)) : null,
            'juz' => round($pages / self::PAGES_PER_JUZ, 1),
            'target_juz' => $targetJuz,
            'has_target' => $target !== null,
        ];
    }

    /**
     * Peta hafalan per juz (THF-03): surat yang sudah disetor penuh lewat ziyadah dianggap hafal,
     * lalu dihitung porsinya per juz memakai juz awal tiap surat.
     *
     * @return Collection<int, array{juz: int, surahs: int, memorized: int, percent: int}>
     */
    public static function juzMap(Student $student): Collection
    {
        $memorized = self::memorizedSurahs($student);

        return QuranSurah::query()->orderBy('number')->get()->groupBy('juz_start')
            ->pipe(function (Collection $byJuz) use ($memorized) {
                return collect(range(1, 30))->map(function (int $juz) use ($byJuz, $memorized) {
                    $surahs = $byJuz[$juz] ?? collect();
                    $done = $surahs->whereIn('number', $memorized)->count();

                    return ['juz' => $juz, 'surahs' => $surahs->count(), 'memorized' => $done, 'percent' => $surahs->isEmpty() ? 0 : (int) round($done / $surahs->count() * 100)];
                });
            });
    }

    /**
     * @return list<int> Nomor surat yang seluruh ayatnya sudah tercakup setoran ziyadah.
     */
    public static function memorizedSurahs(Student $student): array
    {
        $surahs = QuranSurah::query()->pluck('ayah_count', 'number');
        $covered = [];

        foreach (TahfidzRecord::query()->where('student_id', $student->id)->where('type', 'ziyadah')->where('grade', '!=', 'rasib')->get() as $record) {
            for ($number = $record->surah_from; $number <= $record->surah_to; $number++) {
                $start = $number === $record->surah_from ? $record->ayah_from : 1;
                $end = $number === $record->surah_to ? $record->ayah_to : (int) $surahs[$number];
                $covered[$number][] = [$start, $end];
            }
        }

        $done = [];

        foreach ($covered as $number => $ranges) {
            usort($ranges, fn (array $a, array $b) => $a[0] <=> $b[0]);
            $reach = 0;

            foreach ($ranges as [$start, $end]) {
                if ($start > $reach + 1) {
                    break;
                }

                $reach = max($reach, $end);
            }

            if ($reach >= (int) $surahs[$number]) {
                $done[] = $number;
            }
        }

        return $done;
    }
}
