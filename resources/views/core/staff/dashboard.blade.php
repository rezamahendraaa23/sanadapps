@php
    use App\Modules\Core\Support\DashboardMetrics as M;
    use App\Modules\Finance\Support\Rupiah;

    $doneCount = collect($steps)->where('done', true)->count();
    $percent = $steps ? (int) round($doneCount / count($steps) * 100) : 0;

    // Arah perubahan: naik baik untuk kehadiran, penagihan, omzet.
    $tone = fn (?float $diff, bool $upIsGood = true) => $diff === null || abs($diff) < 0.05 ? 'neutral' : (($diff > 0) === $upIsGood ? 'good' : 'bad');
    $pct = fn (?float $value) => $value === null ? null : number_format($value, $value == floor($value) ? 0 : 1, ',', '.').'%';

    $att = $m['attendance'];
    $attDiff = $att && $att['rate'] !== null && $att['previous'] !== null ? $att['rate'] - $att['previous'] : null;
    $col = $m['collection'];
    $colDiff = $col && $col['rate'] !== null && $col['previous'] !== null ? $col['rate'] - $col['previous'] : null;
    $mart = $m['mart'];
    $martDiff = $mart && $mart['previous'] > 0 ? ($mart['value'] - $mart['previous']) / $mart['previous'] * 100 : null;
    $arrears = $m['arrears'];
    $arrearsTop = $arrears ? max(1, ...array_column($arrears['buckets'], 'amount')) : 1;
@endphp

<div class="flex flex-col gap-4">
    @if ($steps)
        <x-ui.card>
            <h2 class="rule-double pb-2 text-xl font-semibold">Setup awal pesantren</h2>
            <div class="mt-3 flex items-center gap-3">
                <div class="h-2 flex-1 rounded-full bg-border" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100" aria-label="Progres setup">
                    <div class="h-2 rounded-full bg-primary" style="width: {{ $percent }}%"></div>
                </div>
                <span class="font-tabular text-sm font-semibold">{{ $doneCount }} dari {{ count($steps) }}</span>
            </div>
            <ol class="mt-3 flex flex-col">
                @foreach ($steps as $step)
                    <li wire:key="step-{{ $step['key'] }}" class="flex flex-wrap items-center gap-3 border-b border-border py-2 last:border-b-0">
                        @if ($step['done'])
                            <x-ui.icon name="circle-check" class="text-success" />
                        @else
                            <x-ui.icon name="circle-dashed" class="text-text-muted" />
                        @endif
                        <div class="min-w-0 flex-1 basis-48">
                            <a href="{{ route($step['route']) }}" wire:navigate class="font-semibold text-primary hover:underline">{{ $step['label'] }}</a>
                            <p class="text-sm text-text-muted">{{ $step['done'] ? 'Selesai.' : $step['hint'] }}</p>
                        </div>
                        @if (! $step['done'] && $step['can_mark'])
                            <x-ui.button variant="tertiary" wire:click="mark('{{ $step['key'] }}')">Tandai selesai<span class="sr-only">: {{ $step['label'] }}</span></x-ui.button>
                        @endif
                    </li>
                @endforeach
            </ol>
            @if ($doneCount === count($steps))
                <div class="mt-3 flex justify-end">
                    <x-ui.button variant="secondary" wire:click="dismissChecklist">Sembunyikan Daftar Ini</x-ui.button>
                </div>
            @endif
        </x-ui.card>
    @endif

    <p class="text-sm text-text-muted">Ringkasan per {{ $m['asOf']->translatedFormat('l, j F Y') }}. Perbandingan memakai periode setara sebelumnya.</p>

    {{-- Baris angka utama --}}
    <section aria-label="Angka utama" class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
        @if ($m['students'])
            <x-ui.stat-tile label="Santri aktif" icon="users" :value="number_format($m['students']['value'], 0, ',', '.')" :href="route('staff.students.index')"
                :delta="$m['students']['new'] > 0 ? '+'.$m['students']['new'].' santri' : null" :tone="$m['students']['new'] > 0 ? 'good' : 'neutral'"
                :note="($m['students']['new'] > 0 ? 'masuk bulan ini. ' : '').$m['students']['male'].' putra, '.$m['students']['female'].' putri'" />
        @endif
        @if ($att)
            <x-ui.stat-tile label="Kehadiran 7 hari" icon="calendar-check" :value="$pct($att['rate'])" :href="route('staff.attendance.index')"
                :delta="$attDiff === null ? null : M::signed($attDiff, ' poin')" :tone="$tone($attDiff)" :note="$attDiff === null ? null : 'dibanding 7 hari sebelumnya'"
                :trend="array_column($att['days'], 'rate')" />
        @endif
        @if ($col)
            <x-ui.stat-tile label="Tingkat penagihan bulan ini" icon="receipt-text" :value="$pct($col['rate'])" :href="route('staff.invoices.index')"
                :delta="$colDiff === null ? null : M::signed($colDiff, ' poin')" :tone="$tone($colDiff)"
                :note="$col['billed'] > 0 ? M::rupiahShort($col['paid']).' dari '.M::rupiahShort($col['billed']).($colDiff === null ? '' : ', dibanding bulan lalu') : 'Belum ada tagihan bulan ini'" />
        @endif
        @if ($arrears)
            <x-ui.stat-tile label="Tunggakan" icon="clock-alert" :value="M::rupiahShort($arrears['total'])" :href="route('staff.arrears.index')"
                :note="$arrears['students'] > 0 ? $arrears['students'].' santri menunggak' : 'Tidak ada santri menunggak'" />
        @endif
        @if ($m['outside'])
            <x-ui.stat-tile label="Santri sedang di luar" icon="door-open" :value="(string) $m['outside']['out']" :href="route('staff.leaves.index')"
                :delta="$m['outside']['late'] > 0 ? $m['outside']['late'].' terlambat kembali' : null" delta-icon="triangle-alert" :tone="$m['outside']['late'] > 0 ? 'bad' : 'neutral'"
                :note="$m['outside']['pending'] > 0 ? $m['outside']['pending'].' izin menunggu keputusan' : 'Tidak ada izin menunggu'" />
        @endif
        @if ($mart)
            <x-ui.stat-tile label="Omzet mart 7 hari" icon="store" :value="M::rupiahShort($mart['value'])" :href="route('staff.merchants.index')"
                :delta="$martDiff === null ? null : M::signed($martDiff, '%')" :tone="$tone($martDiff)" :note="$martDiff === null ? null : 'dibanding 7 hari sebelumnya'"
                :trend="array_column($mart['days'], 'amount')" />
        @endif
    </section>

    <div class="grid gap-4 lg:grid-cols-2">
        @if ($m['cashTrend'])
            <x-ui.card title="Kas masuk 6 bulan">
                <x-ui.chart.columns caption="Kas masuk per bulan, enam bulan terakhir" :items="array_map(fn ($row) => [
                    'label' => \Illuminate\Support\Carbon::parse($row['month'].'-01')->translatedFormat('F Y'),
                    'short' => $row['label'],
                    'value' => $row['amount'],
                    'display' => Rupiah::format($row['amount']),
                    'highlight' => $row['current'],
                    'hint' => $row['current'] ? 'berjalan sampai hari ini' : null,
                ], $m['cashTrend'])" />
            </x-ui.card>
        @endif
        @if ($att)
            <x-ui.card title="Kehadiran harian 14 hari">
                @if (collect($att['days'])->every(fn ($day) => $day['rate'] === null))
                    <x-ui.empty-state icon="calendar-check" message="Belum ada sesi absensi dalam 14 hari terakhir." class="py-6" />
                @else
                <x-ui.chart.columns caption="Persentase kehadiran per hari, 14 hari terakhir" :max="100" :items="array_map(fn ($day) => [
                    'label' => \Illuminate\Support\Carbon::parse($day['date'])->translatedFormat('l, j M'),
                    'short' => \Illuminate\Support\Carbon::parse($day['date'])->format('j'),
                    'value' => $day['rate'],
                    'display' => $day['rate'] === null ? 'Tidak ada sesi' : $pct($day['rate']),
                    'highlight' => $day['date'] === $m['asOf']->toDateString(),
                    'hint' => $day['total'] > 0 ? $day['total'].' catatan' : null,
                ], $att['days'])" />
                @endif
            </x-ui.card>
        @endif
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <x-ui.card title="Perlu tindakan">
            @forelse ($m['actions'] as $action)
                <a href="{{ route($action['route']) }}" wire:navigate @class([
                    'mb-2 flex min-h-12 items-center justify-between gap-3 rounded-control border p-3 font-semibold last:mb-0 hover:bg-bg',
                    'border-danger' => $action['tone'] === 'danger',
                    'hatch border-warning' => $action['tone'] === 'warning',
                    'border-border' => $action['tone'] === 'info',
                ])>
                    <span class="flex items-center gap-2">
                        <x-ui.icon :name="$action['icon']" @class(['text-danger' => $action['tone'] === 'danger', 'text-warning' => $action['tone'] === 'warning', 'text-primary' => $action['tone'] === 'info']) />{{ $action['label'] }}
                    </span>
                    <span class="flex items-center gap-1"><span class="font-display font-tabular text-xl">{{ $action['count'] }}</span><x-ui.icon name="chevron-right" size="sm" class="text-text-muted" /></span>
                </a>
            @empty
                <x-ui.empty-state message="Tidak ada yang menunggu saat ini." class="py-2" />
            @endforelse
        </x-ui.card>

        @if ($arrears)
            <x-ui.card title="Umur tunggakan">
                @if ($arrears['total'] === 0)
                    <x-ui.empty-state icon="circle-check" message="Tidak ada tagihan lewat jatuh tempo." class="py-2" />
                @else
                    {{-- Satu warna, makin lama makin pekat: urutan umur sudah bermakna, jadi tidak diurut ulang menurut jumlah. --}}
                    <ul class="flex flex-col gap-3" aria-label="Tunggakan menurut lama keterlambatan">
                        @foreach ($arrears['buckets'] as $i => $bucket)
                            <li>
                                <div class="mb-1 flex items-baseline justify-between gap-2 text-sm">
                                    <span>{{ $bucket['label'] }}</span>
                                    <span class="font-tabular font-semibold">{{ Rupiah::format($bucket['amount']) }} <span class="font-normal text-text-muted">({{ $bucket['count'] }} tagihan)</span></span>
                                </div>
                                <div class="h-3 rounded-full bg-bg">
                                    <div class="h-3 rounded-full" style="width: {{ $bucket['amount'] > 0 ? max(2, round($bucket['amount'] / $arrearsTop * 100, 1)) : 0 }}%; background: color-mix(in srgb, var(--color-accent) {{ [40, 60, 80, 100][$i] }}%, var(--color-surface))"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-3 text-sm text-text-muted">Tagihan yang lebih dari 90 hari paling sulit tertagih; dahulukan saat menghubungi wali.</p>
                @endif
            </x-ui.card>
        @endif
    </div>
</div>
