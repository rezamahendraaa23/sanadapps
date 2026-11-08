<?php

namespace App\Modules\Core\Support;

use App\Modules\Academic\Models\AttendanceRecord;
use App\Modules\Admission\Models\Applicant;
use App\Modules\Affairs\Models\StudentRiskScore;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\User;
use App\Modules\Finance\Models\CashTransaction;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\Payment;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Masjid\Models\DisplayContent;
use App\Modules\Pos\Models\PosOrder;
use Carbon\CarbonImmutable;

/**
 * Angka dashboard staf: tiap bagian hanya dihitung bila modulnya aktif dan pengguna berhak.
 * Semua perbandingan memakai periode yang setara (7 hari vs 7 hari sebelumnya, bulan ini vs bulan lalu sampai tanggal yang sama).
 */
final class DashboardMetrics
{
    private CarbonImmutable $today;

    public function __construct(private ModuleRegistry $modules)
    {
        $this->today = CarbonImmutable::now(config('sanad.display_timezone'))->startOfDay();
    }

    /** Rupiah ringkas untuk kartu angka: Rp 12,4 jt. Angka lengkap tetap ada di tabel dan halaman rinci. */
    public static function rupiahShort(int $amount): string
    {
        $abs = abs($amount);
        [$value, $suffix] = match (true) {
            $abs >= 1_000_000_000 => [$amount / 1_000_000_000, ' M'],
            $abs >= 1_000_000 => [$amount / 1_000_000, ' jt'],
            $abs >= 1_000 => [$amount / 1_000, ' rb'],
            default => [$amount, ''],
        };

        $text = number_format($value, $suffix === '' ? 0 : 1, ',', '.');

        return 'Rp '.($suffix === '' ? $text : preg_replace('/,0$/', '', $text)).$suffix;
    }

    /** Selisih bertanda: +3,2 poin, -12%. */
    public static function signed(float $value, string $unit): string
    {
        return ($value > 0 ? '+' : ($value < 0 ? '-' : '')).number_format(abs($value), abs($value) < 10 && fmod(abs($value), 1.0) !== 0.0 ? 1 : 0, ',', '.').$unit;
    }

    private function on(string $module): bool
    {
        return $this->modules->enabled($module);
    }

    /**
     * @return array<string, mixed>
     */
    public function for(User $user): array
    {
        $finance = $this->on('finance') && $user->can('finance.view');

        return [
            'asOf' => $this->today,
            'students' => $user->can('viewAny', Student::class) ? $this->students($user) : null,
            'attendance' => $this->on('attendance') && $user->canAny(['academic.manage', 'students.view-all']) ? $this->attendance() : null,
            'collection' => $finance ? $this->collection() : null,
            'arrears' => $finance ? $this->arrears() : null,
            'cashTrend' => $finance ? $this->cashTrend() : null,
            'outside' => $this->on('leave') && $user->canAny(['leave.manage', 'leave.decide']) ? $this->outside() : null,
            'mart' => $this->on('pos') && $user->canAny(['pos.view', 'merchants.manage']) ? $this->mart() : null,
            'actions' => $this->actions($user),
        ];
    }

    /**
     * @return array{value: int, new: int, male: int, female: int}
     */
    private function students(User $user): array
    {
        $active = Student::query()->visibleTo($user)->where('status', 'active');

        return [
            'value' => (clone $active)->count(),
            'new' => (clone $active)->whereDate('entry_date', '>=', $this->today->startOfMonth())->count(),
            'male' => (clone $active)->where('gender', 'L')->count(),
            'female' => (clone $active)->where('gender', 'P')->count(),
        ];
    }

    /**
     * Kehadiran harian 14 hari: hadir dan terlambat dihitung hadir.
     *
     * @return array{rate: ?float, previous: ?float, days: list<array{date: string, rate: ?float, total: int}>}
     */
    private function attendance(): array
    {
        $from = $this->today->subDays(13);
        $rows = AttendanceRecord::query()
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.session_id')
            ->whereBetween('attendance_sessions.session_date', [$from->toDateString(), $this->today->toDateString()])
            ->groupBy('attendance_sessions.session_date')
            ->selectRaw("attendance_sessions.session_date as day, count(*) as total, sum(case when attendance_records.status in ('hadir', 'terlambat') then 1 else 0 end) as present")
            ->get()->keyBy(fn ($row) => CarbonImmutable::parse($row->day)->toDateString());

        $days = [];
        for ($i = 0; $i < 14; $i++) {
            $date = $from->addDays($i)->toDateString();
            $row = $rows[$date] ?? null;
            $days[] = ['date' => $date, 'total' => (int) ($row->total ?? 0), 'rate' => $row && $row->total > 0 ? round($row->present / $row->total * 100, 1) : null];
        }

        $rate = function (array $slice): ?float {
            $total = array_sum(array_column($slice, 'total'));
            $present = array_sum(array_map(fn ($day) => $day['rate'] === null ? 0 : $day['rate'] / 100 * $day['total'], $slice));

            return $total > 0 ? round($present / $total * 100, 1) : null;
        };

        return ['rate' => $rate(array_slice($days, 7)), 'previous' => $rate(array_slice($days, 0, 7)), 'days' => $days];
    }

    /**
     * Tingkat penagihan: terbayar dibanding total tagihan periode bulan ini, dibandingkan bulan lalu.
     *
     * @return array{rate: ?float, previous: ?float, billed: int, paid: int}
     */
    private function collection(): array
    {
        $period = fn (CarbonImmutable $month) => Invoice::query()->where('status', '!=', 'void')->whereDate('period', $month->startOfMonth()->toDateString())
            ->selectRaw('coalesce(sum(total), 0) as billed, coalesce(sum(paid_total), 0) as paid')->first();
        $now = $period($this->today);
        $last = $period($this->today->subMonthNoOverflow());

        return [
            'billed' => (int) $now->billed,
            'paid' => (int) $now->paid,
            'rate' => $now->billed > 0 ? round($now->paid / $now->billed * 100, 1) : null,
            'previous' => $last->billed > 0 ? round($last->paid / $last->billed * 100, 1) : null,
        ];
    }

    /**
     * Tunggakan per umur keterlambatan; makin lama makin gelap.
     *
     * @return array{total: int, students: int, buckets: list<array{label: string, amount: int, count: int}>}
     */
    private function arrears(): array
    {
        $rows = Invoice::query()->whereIn('status', ['unpaid', 'partial', 'overdue'])->whereDate('due_date', '<', $this->today->toDateString())
            ->get(['student_id', 'due_date', 'total', 'paid_total']);
        $buckets = [['label' => '1 sampai 30 hari', 'max' => 30], ['label' => '31 sampai 60 hari', 'max' => 60], ['label' => '61 sampai 90 hari', 'max' => 90], ['label' => 'Lebih dari 90 hari', 'max' => PHP_INT_MAX]];
        $out = array_map(fn ($bucket) => ['label' => $bucket['label'], 'amount' => 0, 'count' => 0], $buckets);

        foreach ($rows as $invoice) {
            $days = (int) CarbonImmutable::parse($invoice->due_date)->diffInDays($this->today);
            foreach ($buckets as $i => $bucket) {
                if ($days <= $bucket['max']) {
                    $out[$i]['amount'] += max(0, $invoice->total - $invoice->paid_total);
                    $out[$i]['count']++;
                    break;
                }
            }
        }

        return ['total' => array_sum(array_column($out, 'amount')), 'students' => $rows->pluck('student_id')->unique()->count(), 'buckets' => $out];
    }

    /**
     * Kas masuk 6 bulan terakhir, bulan berjalan sampai hari ini.
     *
     * @return list<array{month: string, label: string, amount: int, current: bool}>
     */
    private function cashTrend(): array
    {
        $start = $this->today->startOfMonth()->subMonths(5);
        $sums = CashTransaction::query()->where('type', 'in')->whereDate('transaction_date', '>=', $start->toDateString())
            ->selectRaw("to_char(transaction_date, 'YYYY-MM') as month, sum(amount) as amount")->groupBy('month')->pluck('amount', 'month');

        return array_map(function (int $i) use ($start, $sums) {
            $month = $start->addMonths($i);

            return ['month' => $month->format('Y-m'), 'label' => $month->translatedFormat('M'), 'amount' => (int) ($sums[$month->format('Y-m')] ?? 0), 'current' => $i === 5];
        }, range(0, 5));
    }

    /**
     * @return array{out: int, late: int, pending: int}
     */
    private function outside(): array
    {
        return [
            'out' => LeaveRequest::query()->whereIn('status', ['out', 'late'])->count(),
            'late' => LeaveRequest::query()->where('status', 'late')->count(),
            'pending' => LeaveRequest::query()->where('status', 'pending')->count(),
        ];
    }

    /**
     * Omzet bersih mart 7 hari vs 7 hari sebelumnya, plus tren harian 14 hari.
     *
     * @return array{value: int, previous: int, days: list<array{date: string, amount: int}>}
     */
    private function mart(): array
    {
        $from = $this->today->subDays(13);
        $sums = PosOrder::query()->where('status', 'paid')->where('created_at', '>=', $from->setTimezone(config('app.timezone')))
            ->selectRaw("to_char(created_at at time zone ?, 'YYYY-MM-DD') as day, sum(total) as amount", [config('sanad.display_timezone')])
            ->groupBy('day')->pluck('amount', 'day');
        $days = array_map(fn (int $i) => ['date' => $d = $from->addDays($i)->toDateString(), 'amount' => (int) ($sums[$d] ?? 0)], range(0, 13));

        return ['value' => array_sum(array_column(array_slice($days, 7), 'amount')), 'previous' => array_sum(array_column(array_slice($days, 0, 7), 'amount')), 'days' => $days];
    }

    /**
     * Antrian yang menunggu orang: urut dari yang paling mendesak.
     *
     * @return list<array{label: string, count: int, route: string, tone: string, icon: string}>
     */
    private function actions(User $user): array
    {
        $items = [];
        $add = function (bool $allowed, string $label, \Closure $count, string $route, string $tone, string $icon) use (&$items): void {
            if ($allowed && ($n = $count()) > 0) {
                $items[] = ['label' => $label, 'count' => $n, 'route' => $route, 'tone' => $tone, 'icon' => $icon];
            }
        };

        $add($this->on('leave') && $user->canAny(['leave.manage', 'leave.decide']), 'Santri terlambat kembali', fn () => LeaveRequest::query()->where('status', 'late')->count(), 'staff.leaves.index', 'danger', 'clock-alert');
        $add($this->on('analytics') && $user->can('analytics.view'), 'Santri berisiko tinggi', fn () => StudentRiskScore::query()->where('level', 'high')->whereDate('computed_on', '>=', $this->today->subDays(7))->distinct('student_id')->count('student_id'), 'staff.analytics.index', 'danger', 'triangle-alert');
        $add($this->on('payment_manual') && $user->can('finance.verify'), 'Bukti transfer menunggu verifikasi', fn () => Payment::query()->where('status', 'review')->count(), 'staff.transfers.index', 'warning', 'hourglass');
        $add($this->on('leave') && $user->canAny(['leave.manage', 'leave.decide']), 'Izin menunggu keputusan', fn () => LeaveRequest::query()->where('status', 'pending')->count(), 'staff.leaves.index', 'warning', 'door-open');
        $add($this->on('masjid_display') && $user->can('masjid.approve'), 'Konten layar menunggu persetujuan', fn () => DisplayContent::query()->where('status', 'pending')->count(), 'staff.masjid.contents', 'info', 'monitor');
        $add($this->on('ppdb') && $user->can('ppdb.manage'), 'Pendaftar PPDB perlu verifikasi', fn () => Applicant::query()->whereIn('status', ['submitted', 'paid'])->count(), 'staff.ppdb.applicants.index', 'info', 'user-plus');

        return $items;
    }
}
