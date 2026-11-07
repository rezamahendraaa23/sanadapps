@php
    $statusLabels = \App\Modules\Academic\Models\AttendanceRecord::STATUSES;
    $leaveLabels = ['pulang' => 'Pulang', 'keluar' => 'Keluar', 'sakit' => 'Sakit', 'kegiatan' => 'Kegiatan'];
    $money = fn (int $value) => \App\Modules\Finance\Support\Rupiah::format($value);
@endphp

<div class="flex flex-col gap-4">
    @forelse ($history as $year)
        <x-ui.card wire:key="tahun-{{ $loop->index }}">
            <h3 class="font-display text-xl font-semibold break-words">Tahun ajaran {{ $year['name'] }}@if ($year['active'])<span class="ml-2 text-sm font-normal text-text-muted">(berjalan)</span>@endif</h3>
            @php($sections = $year['sections'])
            <dl class="mt-3 flex flex-col">
                @isset($sections['classes'])
                    <div class="grid gap-1 border-t border-border py-3 md:grid-cols-[160px_1fr]">
                        <dt class="text-sm font-semibold">Kelas</dt>
                        <dd class="flex flex-col gap-1 text-base">
                            @foreach ($sections['classes'] as $class)
                                <span class="break-words">{{ $class['name'] }} <span class="text-sm text-text-muted">{{ $class['started'] }} sampai {{ $class['ended'] ?? 'sekarang' }}</span></span>
                            @endforeach
                        </dd>
                    </div>
                @endisset
                @isset($sections['attendance'])
                    <div class="grid gap-1 border-t border-border py-3 md:grid-cols-[160px_1fr]">
                        <dt class="text-sm font-semibold">Kehadiran</dt>
                        <dd class="flex flex-wrap gap-x-4 gap-y-1 text-base">
                            @foreach ($statusLabels as $key => $label)
                                @isset($sections['attendance'][$key])
                                    <span>{{ $label }} <strong class="font-tabular">{{ $sections['attendance'][$key] }}</strong></span>
                                @endisset
                            @endforeach
                        </dd>
                    </div>
                @endisset
                @isset($sections['reports'])
                    <div class="grid gap-1 border-t border-border py-3 md:grid-cols-[160px_1fr]">
                        <dt class="text-sm font-semibold">Rapor</dt>
                        <dd class="flex flex-col gap-1 text-base">
                            @foreach ($sections['reports'] as $report)
                                <span class="break-words">{{ $report['term'] }}: {{ $report['published'] ? 'Terbit' : 'Draf' }}</span>
                            @endforeach
                        </dd>
                    </div>
                @endisset
                @isset($sections['tahfidz'])
                    <div class="grid gap-1 border-t border-border py-3 md:grid-cols-[160px_1fr]">
                        <dt class="text-sm font-semibold">Tahfidz</dt>
                        <dd class="flex flex-wrap gap-x-4 gap-y-1 text-base">
                            <span><strong class="font-tabular">{{ $sections['tahfidz']['setoran'] }}</strong> setoran</span>
                            <span>Ziyadah <strong class="font-tabular">{{ rtrim(rtrim(number_format($sections['tahfidz']['ziyadah'], 2, ',', ''), '0'), ',') }}</strong> halaman</span>
                            <span>Murojaah <strong class="font-tabular">{{ rtrim(rtrim(number_format($sections['tahfidz']['murojaah'], 2, ',', ''), '0'), ',') }}</strong> halaman</span>
                        </dd>
                    </div>
                @endisset
                @isset($sections['exams'])
                    <div class="grid gap-1 border-t border-border py-3 md:grid-cols-[160px_1fr]">
                        <dt class="text-sm font-semibold">Ujian</dt>
                        <dd class="flex flex-col gap-1 text-base">
                            @foreach ($sections['exams'] as $exam)
                                <span class="flex justify-between gap-3"><span class="min-w-0 break-words">{{ $exam['title'] }}</span><span class="font-tabular shrink-0 font-semibold">{{ $exam['score'] === null ? 'Dikoreksi' : rtrim(rtrim(number_format($exam['score'], 2, ',', ''), '0'), ',') }}</span></span>
                            @endforeach
                        </dd>
                    </div>
                @endisset
                @isset($sections['leaves'])
                    <div class="grid gap-1 border-t border-border py-3 md:grid-cols-[160px_1fr]">
                        <dt class="text-sm font-semibold">Izin</dt>
                        <dd class="flex flex-wrap gap-x-4 gap-y-1 text-base">
                            @foreach ($sections['leaves'] as $type => $count)
                                <span>{{ $leaveLabels[$type] ?? $type }} <strong class="font-tabular">{{ $count }}</strong></span>
                            @endforeach
                        </dd>
                    </div>
                @endisset
                @isset($sections['finance'])
                    <div class="grid gap-1 border-t border-border py-3 md:grid-cols-[160px_1fr]">
                        <dt class="text-sm font-semibold">Tagihan</dt>
                        <dd class="flex flex-wrap gap-x-4 gap-y-1 text-base">
                            <span>Ditagih <strong class="font-tabular">{{ $money($sections['finance']['billed']) }}</strong></span>
                            <span>Dibayar <strong class="font-tabular">{{ $money($sections['finance']['paid']) }}</strong></span>
                        </dd>
                    </div>
                @endisset
                @isset($sections['wallet'])
                    <div class="grid gap-1 border-t border-border py-3 md:grid-cols-[160px_1fr]">
                        <dt class="text-sm font-semibold">Dompet</dt>
                        <dd class="flex flex-wrap gap-x-4 gap-y-1 text-base">
                            <span>Top-up <strong class="font-tabular">{{ $money($sections['wallet']['topup']) }}</strong></span>
                            <span>Belanja <strong class="font-tabular">{{ $money($sections['wallet']['spend']) }}</strong></span>
                        </dd>
                    </div>
                @endisset
            </dl>
        </x-ui.card>
    @empty
        <x-ui.card><x-ui.empty-state icon="history" message="Belum ada catatan untuk santri ini." class="py-2" /></x-ui.card>
    @endforelse
</div>
