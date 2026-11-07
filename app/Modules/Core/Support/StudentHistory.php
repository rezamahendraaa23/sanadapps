<?php

namespace App\Modules\Core\Support;

use App\Modules\Academic\Models\AttendanceRecord;
use App\Modules\Academic\Models\ReportCard;
use App\Modules\Affairs\Models\DisciplineRecord;
use App\Modules\Affairs\Models\HealthRecord;
use App\Modules\Core\Models\AcademicYear;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\User;
use App\Modules\Exam\Models\ExamAttempt;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Tahfidz\Models\TahfidzRecord;
use App\Modules\Wallet\Models\Wallet;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Perjalanan santri per tahun ajaran, dari semua modul yang aktif dan boleh dilihat pengguna.
 */
class StudentHistory
{
    public function __construct(private ModuleRegistry $modules) {}

    /**
     * @return list<array<string, mixed>> Tahun terbaru lebih dulu; tahun tanpa catatan tidak ikut.
     */
    public function build(Student $student, User $viewer): array
    {
        $zone = config('sanad.display_timezone');
        $blocks = [];

        foreach (AcademicYear::query()->with('terms')->orderByDesc('start_date')->get() as $year) {
            $startDate = $year->start_date->toDateString();
            $endDate = $year->end_date->toDateString();
            $from = CarbonImmutable::parse($startDate, $zone)->startOfDay()->utc();
            $until = CarbonImmutable::parse($endDate, $zone)->endOfDay()->utc();

            $block = array_filter([
                'classes' => $this->classes($student, $from, $until),
                'attendance' => $this->modules->enabled('attendance') ? $this->attendance($student, $startDate, $endDate) : null,
                'reports' => $this->modules->enabled('grading') ? $this->reports($student, $year->terms->pluck('id')->all()) : null,
                'tahfidz' => $this->modules->enabled('tahfidz') ? $this->tahfidz($student, $startDate, $endDate) : null,
                'leaves' => $this->modules->enabled('leave') ? $this->leaves($student, $from, $until) : null,
                'exams' => $this->modules->enabled('exam') ? $this->exams($student, $from, $until) : null,
                'discipline' => $this->modules->enabled('discipline') ? $this->discipline($student, $startDate, $endDate) : null,
                'health' => $viewer->canAny(['health.manage', 'health.summary']) && $this->modules->enabled('health') ? $this->health($student, $startDate, $endDate) : null,
                'finance' => $viewer->can('finance.view') && $this->modules->enabled('finance') ? $this->finance($student, $startDate, $endDate) : null,
                'wallet' => $viewer->can('wallet.view') && $this->modules->enabled('wallet') ? $this->wallet($student, $from, $until) : null,
            ]);

            if ($block !== []) {
                $blocks[] = ['name' => $year->name, 'active' => (bool) $year->is_active, 'sections' => $block];
            }
        }

        return $blocks;
    }

    /**
     * @return list<array{name: string, started: string, ended: string|null}>
     */
    private function classes(Student $student, CarbonImmutable $from, CarbonImmutable $until): array
    {
        return $student->enrollments()->with('classroom:id,name')
            ->where('started_at', '<=', $until)
            ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>=', $from))
            ->orderBy('started_at')->get()
            ->map(fn ($enrollment) => [
                'name' => $enrollment->classroom?->name ?? 'Kelas dihapus',
                'started' => $enrollment->started_at->setTimezone(config('sanad.display_timezone'))->translatedFormat('j M Y'),
                'ended' => $enrollment->ended_at?->setTimezone(config('sanad.display_timezone'))->translatedFormat('j M Y'),
            ])->all();
    }

    /**
     * @return array<string, int>|null
     */
    private function attendance(Student $student, string $start, string $end): ?array
    {
        $counts = AttendanceRecord::query()->where('student_id', $student->id)
            ->whereHas('session', fn ($query) => $query->whereBetween('session_date', [$start, $end]))
            ->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->map(fn ($value) => (int) $value)->all();

        return $counts === [] ? null : $counts;
    }

    /**
     * @param  list<int>  $termIds
     * @return list<array{term: string, published: bool}>|null
     */
    private function reports(Student $student, array $termIds): ?array
    {
        $cards = ReportCard::query()->with('term:id,name')->where('student_id', $student->id)->whereIn('term_id', $termIds)->orderBy('term_id')->get();

        return $cards->isEmpty() ? null : $cards->map(fn (ReportCard $card) => ['term' => $card->term->name, 'published' => $card->status === 'published'])->all();
    }

    /**
     * @return array{setoran: int, ziyadah: float, murojaah: float}|null
     */
    private function tahfidz(Student $student, string $start, string $end): ?array
    {
        $records = TahfidzRecord::query()->where('student_id', $student->id)->whereBetween('record_date', [$start, $end])->get(['type', 'pages']);

        return $records->isEmpty() ? null : [
            'setoran' => $records->count(),
            'ziyadah' => (float) $records->where('type', 'ziyadah')->sum('pages'),
            'murojaah' => (float) $records->where('type', 'murojaah')->sum('pages'),
        ];
    }

    /**
     * @return array<string, int>|null
     */
    private function leaves(Student $student, CarbonImmutable $from, CarbonImmutable $until): ?array
    {
        $counts = LeaveRequest::query()->where('student_id', $student->id)->whereBetween('start_at', [$from, $until])
            ->whereNotIn('status', ['pending', 'rejected', 'cancelled'])
            ->selectRaw('type, COUNT(*) AS total')->groupBy('type')->pluck('total', 'type')->map(fn ($value) => (int) $value)->all();

        return $counts === [] ? null : $counts;
    }

    /**
     * @return list<array{title: string, score: float|null}>|null
     */
    private function exams(Student $student, CarbonImmutable $from, CarbonImmutable $until): ?array
    {
        $attempts = ExamAttempt::query()->with('exam:id,title')->where('student_id', $student->id)->whereBetween('started_at', [$from, $until])->whereNot('status', 'in_progress')->orderBy('started_at')->get();

        return $attempts->isEmpty() ? null : $attempts->map(fn (ExamAttempt $attempt) => ['title' => $attempt->exam->title, 'score' => $attempt->status === 'graded' ? $attempt->score : null])->all();
    }

    /**
     * @return array{points: int, count: int}|null
     */
    private function discipline(Student $student, string $start, string $end): ?array
    {
        $row = DisciplineRecord::query()->where('student_id', $student->id)->whereBetween('occurred_on', [$start, $end])->selectRaw('COUNT(*) AS total, COALESCE(SUM(points), 0) AS points')->first();

        return (int) $row->total === 0 ? null : ['points' => (int) $row->points, 'count' => (int) $row->total];
    }

    /**
     * Hanya jumlah per hasil, tanpa keluhan dan obat.
     *
     * @return array<string, int>|null
     */
    private function health(Student $student, string $start, string $end): ?array
    {
        $counts = HealthRecord::query()->where('student_id', $student->id)->whereBetween('visit_date', [$start, $end])
            ->selectRaw('outcome, COUNT(*) AS total')->groupBy('outcome')->pluck('total', 'outcome')->map(fn ($value) => (int) $value)->all();

        return $counts === [] ? null : $counts;
    }

    /**
     * @return array{billed: int, paid: int}|null
     */
    private function finance(Student $student, string $start, string $end): ?array
    {
        $row = Invoice::query()->where('student_id', $student->id)->whereNot('status', 'void')->whereBetween('due_date', [$start, $end])
            ->selectRaw('COUNT(*) AS invoices, COALESCE(SUM(total), 0) AS billed, COALESCE(SUM(paid_total), 0) AS paid')->first();

        return (int) $row->invoices === 0 ? null : ['billed' => (int) $row->billed, 'paid' => (int) $row->paid];
    }

    /**
     * @return array{topup: int, spend: int}|null
     */
    private function wallet(Student $student, CarbonImmutable $from, CarbonImmutable $until): ?array
    {
        $walletId = Wallet::query()->where('student_id', $student->id)->value('id');

        if ($walletId === null) {
            return null;
        }

        $sums = DB::table('wallet_transactions')->where('wallet_id', $walletId)->whereBetween('created_at', [$from, $until])
            ->whereIn('type', ['topup', 'spend'])->selectRaw('type, SUM(amount) AS total')->groupBy('type')->pluck('total', 'type');

        return $sums->isEmpty() ? null : ['topup' => (int) ($sums['topup'] ?? 0), 'spend' => (int) ($sums['spend'] ?? 0)];
    }
}
