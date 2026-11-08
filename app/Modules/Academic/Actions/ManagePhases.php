<?php

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Models\Curriculum;
use App\Modules\Academic\Models\CurriculumPhase;
use App\Modules\Academic\Models\GradeComponent;
use App\Modules\Academic\Models\PhaseResult;
use App\Modules\Academic\Models\Subject;
use App\Modules\Academic\Support\AcademicException;
use App\Modules\Academic\Support\PhaseCalendar;
use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Siklus fase: rombongan ditempatkan di fase, nilai selama masa fase dihitung, pengelola menetapkan
 * lulus atau belum, sertifikat diberi nomor, lalu yang lulus naik ke rombongan fase berikutnya.
 */
class ManagePhases
{
    /**
     * @param  array{name: string, code: string, duration_weeks: int, min_score: float, has_certificate: bool, certificate_title?: string|null, description?: string|null}  $data
     */
    public function savePhase(Curriculum $curriculum, array $data, ?CurriculumPhase $phase = null): CurriculumPhase
    {
        if ($phase !== null) {
            $phase->update($data);

            return $phase;
        }

        return DB::transaction(function () use ($curriculum, $data): CurriculumPhase {
            Curriculum::query()->whereKey($curriculum->id)->lockForUpdate()->first();

            return CurriculumPhase::create([...$data, 'curriculum_id' => $curriculum->id, 'sort_order' => (int) $curriculum->phases()->max('sort_order') + 1]);
        });
    }

    /**
     * Tukar urutan dengan fase di atas atau di bawahnya.
     */
    public function move(CurriculumPhase $phase, int $direction): void
    {
        DB::transaction(function () use ($phase, $direction): void {
            $neighbour = CurriculumPhase::query()->where('curriculum_id', $phase->curriculum_id)
                ->where('sort_order', $direction < 0 ? '<' : '>', $phase->sort_order)
                ->orderBy('sort_order', $direction < 0 ? 'desc' : 'asc')->lockForUpdate()->first();

            if ($neighbour === null) {
                return;
            }

            [$a, $b] = [$phase->sort_order, $neighbour->sort_order];
            // Nilai sementara agar indeks unik tidak bentrok saat bertukar.
            $phase->update(['sort_order' => 32000]);
            $neighbour->update(['sort_order' => $a]);
            $phase->update(['sort_order' => $b]);
        });
    }

    public function assign(Classroom $classroom, CurriculumPhase $phase, string $startedOn): Classroom
    {
        $start = CarbonImmutable::parse($startedOn);
        $classroom->update([
            'phase_id' => $phase->id,
            'phase_started_on' => $start->toDateString(),
            'phase_ends_on' => PhaseCalendar::end($start, $phase->duration_weeks)['ends_on']->toDateString(),
        ]);

        return $classroom;
    }

    /**
     * Banyak kelas mulai fase pada tanggal yang sama, agar irama fase seluruh pesantren seragam.
     *
     * @param  array<int, CurriculumPhase>  $plan  id kelas => fase
     */
    public function startTogether(array $plan, string $startedOn): int
    {
        return DB::transaction(function () use ($plan, $startedOn): int {
            foreach ($plan as $classroomId => $phase) {
                $this->assign(Classroom::query()->lockForUpdate()->findOrFail($classroomId), $phase, $startedOn);
            }

            return count($plan);
        });
    }

    /**
     * Setelah kalender libur berubah, tanggal selesai fase yang sedang berjalan dihitung ulang.
     */
    public function recalculateEnds(): int
    {
        $count = 0;
        Classroom::query()->with('phase')->whereNotNull('phase_id')->whereNotNull('phase_started_on')->get()
            ->each(function (Classroom $classroom) use (&$count): void {
                $end = PhaseCalendar::end(CarbonImmutable::parse($classroom->phase_started_on), $classroom->phase->duration_weeks)['ends_on']->toDateString();
                if ($classroom->phase_ends_on?->toDateString() !== $end) {
                    $classroom->update(['phase_ends_on' => $end]);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Nilai mapel memakai rumus rapor (rata-rata komponen kali bobot), hanya dari penilaian kelas ini
     * selama masa fase, apa pun periodenya.
     *
     * @return array<int, float>
     */
    public function subjectScores(Student $student, Classroom $classroom): array
    {
        $rows = DB::table('grades')
            ->join('assessments', 'assessments.id', '=', 'grades.assessment_id')
            ->where('grades.student_id', $student->id)->where('assessments.classroom_id', $classroom->id)
            ->whereRaw('COALESCE(assessments.assessed_on, assessments.created_at::date) BETWEEN ? AND ?', [$classroom->phase_started_on, $classroom->phase_ends_on])
            // Penilaian selama jeda (misalnya Program Ramadan) milik program itu, bukan fase reguler.
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('academic_breaks')
                ->whereRaw('COALESCE(assessments.assessed_on, assessments.created_at::date) BETWEEN academic_breaks.starts_on AND academic_breaks.ends_on'))
            ->selectRaw('assessments.subject_id, assessments.component_id, AVG(grades.score * 100.0 / assessments.max_score) AS average')
            ->groupBy('assessments.subject_id', 'assessments.component_id')
            ->get()->groupBy('subject_id');

        $weights = GradeComponent::query()->pluck('weight', 'id');
        $scores = [];

        foreach ($rows as $subjectId => $components) {
            $sum = 0.0;
            $weightSum = 0;
            foreach ($components as $component) {
                $weight = (int) ($weights[$component->component_id] ?? 0);
                $sum += (float) $component->average * $weight;
                $weightSum += $weight;
            }
            if ($weightSum > 0) {
                $scores[(int) $subjectId] = round($sum / $weightSum, 2);
            }
        }

        return $scores;
    }

    /**
     * Menghitung ulang hasil fase untuk semua santri aktif di rombongan. Hasil yang sudah ditetapkan tidak diubah.
     */
    public function evaluate(Classroom $classroom): int
    {
        $phase = $classroom->phase;

        if ($phase === null || $classroom->phase_started_on === null) {
            throw new AcademicException('Kelas ini belum ditempatkan di fase.');
        }

        $students = Student::query()->where('status', 'active')->where('classroom_id', $classroom->id)->get();
        if ($students->isEmpty()) {
            throw new AcademicException('Kelas ini belum punya santri aktif.');
        }

        $subjects = Subject::query()->pluck('name', 'id');
        $count = 0;

        DB::transaction(function () use ($students, $classroom, $phase, $subjects, &$count): void {
            foreach ($students as $student) {
                $result = PhaseResult::query()->firstOrNew(['student_id' => $student->id, 'phase_id' => $phase->id, 'started_on' => $classroom->phase_started_on->toDateString()]);

                if ($result->exists && $result->status !== 'pending') {
                    continue;
                }

                $scores = $this->subjectScores($student, $classroom);
                $result->fill([
                    'classroom_id' => $classroom->id,
                    'ended_on' => $classroom->phase_ends_on->toDateString(),
                    'scores' => collect($scores)->mapWithKeys(fn (float $score, int $id) => [$id => ['name' => $subjects[$id] ?? 'Mapel', 'score' => $score]])->all(),
                    'final_score' => $scores === [] ? null : round(array_sum($scores) / count($scores), 2),
                ])->save();
                $count++;
            }
        });

        return $count;
    }

    /**
     * @param  array<string, string>  $decisions  uuid hasil ke passed atau failed
     */
    public function decide(Classroom $classroom, array $decisions, User $user): int
    {
        $count = 0;

        DB::transaction(function () use ($classroom, $decisions, $user, &$count): void {
            foreach ($decisions as $uuid => $status) {
                if (! in_array($status, ['passed', 'failed'], true)) {
                    continue;
                }
                $result = PhaseResult::query()->where('uuid', $uuid)->where('classroom_id', $classroom->id)->lockForUpdate()->first();
                if ($result === null || $result->certificate_no !== null || $result->final_score === null) {
                    continue;
                }
                $result->update(['status' => $status, 'decided_by' => $user->id, 'decided_at' => now()]);
                $count++;
            }
        });

        return $count;
    }

    /**
     * Nomor sertifikat berurutan per tahun, contoh 012/SRT/PRK/2026.
     */
    public function certify(Classroom $classroom): int
    {
        $phase = $classroom->phase;
        if ($phase === null || ! $phase->has_certificate) {
            throw new AcademicException('Fase ini tidak memakai sertifikat.');
        }

        $count = 0;
        DB::transaction(function () use ($classroom, $phase, &$count): void {
            $results = PhaseResult::query()->where('classroom_id', $classroom->id)->where('phase_id', $phase->id)->where('status', 'passed')->whereNull('certificate_no')->orderBy('id')->lockForUpdate()->get();
            $year = now(config('sanad.display_timezone'))->year;

            foreach ($results as $result) {
                $sequence = (int) DB::selectOne(
                    'INSERT INTO document_counters (key, value) VALUES (?, 1) ON CONFLICT (key) DO UPDATE SET value = document_counters.value + 1 RETURNING value',
                    ['SERTIFIKAT-'.$year],
                )->value;
                $result->update(['certificate_no' => sprintf('%03d/SRT/%s/%d', $sequence, $phase->code, $year), 'certified_at' => now()]);
                $count++;
            }
        });

        return $count;
    }

    /**
     * Semua santri yang lulus pindah ke satu kelas tujuan; yang belum lulus tetap di tempatnya.
     */
    public function promote(Classroom $from, Classroom $to): int
    {
        $passed = PhaseResult::query()->where('classroom_id', $from->id)->where('phase_id', $from->phase_id)->where('status', 'passed')->whereNull('promoted_at')->pluck('id');

        return $this->promoteMapped($from, $passed->mapWithKeys(fn (int $id) => [$id => $to->id])->all());
    }

    /**
     * Pemetaan per santri: tiap santri yang lulus bisa ke kelas berbeda, misalnya jalur kitab atau tahfidz.
     * Kelas tujuan harus sudah ditempatkan di fase; santri tanpa tujuan tetap di kelasnya.
     *
     * @param  array<int, int>  $map  id hasil fase => id kelas tujuan
     */
    public function promoteMapped(Classroom $from, array $map): int
    {
        $targets = Classroom::query()->whereIn('id', array_unique(array_values($map)))->get()->keyBy('id');
        foreach ($targets as $target) {
            if ($target->id === $from->id) {
                throw new AcademicException('Kelas tujuan tidak boleh kelas ini sendiri.');
            }
            if ($target->phase_id === null) {
                throw new AcademicException($target->name.' belum ditempatkan di fase. Tempatkan dulu di tab Rombongan.');
            }
        }

        $count = 0;
        DB::transaction(function () use ($from, $map, $targets, &$count): void {
            $results = PhaseResult::query()->whereIn('id', array_keys($map))->where('classroom_id', $from->id)->where('phase_id', $from->phase_id)
                ->where('status', 'passed')->whereNull('promoted_at')->lockForUpdate()->get();

            foreach ($results as $result) {
                $target = $targets[$map[$result->id]] ?? null;
                if ($target === null) {
                    continue;
                }
                Student::query()->whereKey($result->student_id)->where('classroom_id', $from->id)->update(['classroom_id' => $target->id]);
                $result->update(['promoted_to_classroom_id' => $target->id, 'promoted_at' => now()]);
                $count++;
            }
        });

        return $count;
    }
}
