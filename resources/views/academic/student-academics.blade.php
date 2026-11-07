@php
    $labels = \App\Modules\Academic\Models\AttendanceRecord::STATUSES;
    $activities = \App\Modules\Academic\Models\Schedule::ACTIVITIES;
    $badge = ['hadir' => 'present', 'izin' => 'excused', 'sakit' => 'sick', 'alpa' => 'absent', 'terlambat' => 'late'];
@endphp

<div class="flex flex-col gap-4">
    @if (! $child)
        <x-ui.card><x-ui.empty-state icon="users" message="Akun ini belum terhubung ke data santri. Hubungi admin pesantren." /></x-ui.card>
    @else
        @if ($children->count() > 1)
            <nav aria-label="Pilih anak" class="flex gap-2 overflow-x-auto pb-1">
                @foreach ($children as $item)
                    <button type="button" wire:key="anak-{{ $item->uuid }}" wire:click="$set('childUuid', '{{ $item->uuid }}')" @if ($item->is($child)) aria-current="true" @endif
                        @class(['inline-flex min-h-11 shrink-0 items-center rounded-full border px-4 text-sm font-semibold', 'border-primary bg-primary-soft text-primary' => $item->is($child), 'border-border' => ! $item->is($child)])>{{ $item->name }}</button>
                @endforeach
            </nav>
        @endif

        <div role="tablist" aria-label="Bagian perkembangan" class="grid grid-flow-col gap-1 rounded-control bg-surface p-1">
            @foreach ($segments as $key => $label)
                <x-ui.tab :selected="$segment === $key" wire:key="seg-{{ $key }}" wire:click="$set('segment', '{{ $key }}')">{{ $label }}</x-ui.tab>
            @endforeach
        </div>

        @if ($isGuardian && app(\App\Modules\Core\Support\ModuleRegistry::class)->enabled('tahfidz'))
            <a href="{{ route('wali.tahfidz', ['anak' => $child->uuid]) }}" wire:navigate class="flex min-h-11 items-center justify-between gap-2 rounded-card border border-border bg-surface p-4 text-base font-semibold">
                <span class="flex min-w-0 items-center gap-3"><x-ui.icon name="book-open" />Tahfidz dan mutabaah</span>
                <x-ui.icon name="chevron-right" class="shrink-0" />
            </a>
        @endif

        @include('affairs.student-summary')

        @if ($segment === 'absensi')
            <x-ui.card title="Kehadiran bulan ini">
                @if ($attendance)
                    <p class="font-display font-tabular text-3xl font-semibold">{{ $attendance['percent'] }}%</p>
                    <dl class="mt-2 grid grid-cols-3 gap-2 text-sm">
                        @foreach (['hadir' => 'Hadir', 'terlambat' => 'Terlambat', 'izin' => 'Izin', 'sakit' => 'Sakit', 'alpa' => 'Alpa'] as $key => $label)
                            <div><dt class="text-text-muted">{{ $label }}</dt><dd class="font-tabular font-semibold">{{ $attendance[$key] }}</dd></div>
                        @endforeach
                    </dl>
                @else
                    <x-ui.empty-state icon="calendar-check" message="Belum ada absensi bulan ini." class="py-2" />
                @endif
            </x-ui.card>
            @if ($recent->isNotEmpty())
                <x-ui.card title="Riwayat terakhir">
                    <ul class="flex flex-col">
                        @foreach ($recent as $record)
                            <li wire:key="rec-{{ $record->uuid }}" class="flex items-center justify-between gap-2 border-b border-border py-2 last:border-b-0">
                                <span class="min-w-0"><span class="block text-base break-words">{{ $record->session->title }}</span><span class="block text-xs text-text-muted">{{ $record->session->session_date->translatedFormat('j M Y') }}</span></span>
                                <x-ui.status-badge :status="$badge[$record->status]" class="shrink-0" />
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif
        @elseif ($segment === 'nilai')
            @forelse ($cards as $card)
                <x-ui.card wire:key="rapor-{{ $card->uuid }}" :title="'Rapor '.$card->term->name">
                    <ul class="flex flex-col">
                        @foreach ($card->items->sortBy('subject.name') as $item)
                            <li class="flex items-center justify-between gap-2 border-b border-border py-2 last:border-b-0">
                                <span class="min-w-0 text-base break-words">{{ $item->subject->name }}@if ($item->remark)<span class="block text-xs text-danger">{{ $item->remark }}</span>@endif</span>
                                <span class="font-tabular shrink-0 text-right font-semibold">{{ rtrim(rtrim(number_format($item->final_score, 2, ',', ''), '0'), ',') }} <span class="text-sm font-normal text-text-muted">({{ $item->predicate }})</span></span>
                            </li>
                        @endforeach
                    </ul>
                    @if ($card->homeroom_note)<p class="mt-3 text-sm"><strong>Catatan wali kelas:</strong> {{ $card->homeroom_note }}</p>@endif
                    <x-ui.button variant="secondary" icon="file-down" class="mt-3 w-full" :href="route('report-cards.pdf', $card)">Unduh Rapor</x-ui.button>
                </x-ui.card>
            @empty
                <x-ui.card><x-ui.empty-state icon="file-text" message="Rapor belum terbit." class="py-2" /></x-ui.card>
            @endforelse
        @else
            <x-ui.card title="Jadwal">
                <nav aria-label="Pilih hari" class="mb-3 flex gap-1 overflow-x-auto pb-1">
                    @foreach ($days as $number => $name)
                        <button type="button" wire:key="h-{{ $number }}" wire:click="$set('day', {{ $number }})" @if ($number === $selected) aria-current="true" @endif
                            @class(['inline-flex min-h-11 shrink-0 items-center rounded-full border px-3 text-sm font-semibold', 'border-primary bg-primary-soft text-primary' => $number === $selected, 'border-border' => $number !== $selected])>{{ $name }}</button>
                    @endforeach
                </nav>
                @forelse ($schedules as $schedule)
                    @php($running = $isToday && (string) $schedule->start_time <= $time && (string) $schedule->end_time > $time)
                    <div wire:key="j-{{ $schedule->uuid }}" @class(['border-b border-border py-2 last:border-b-0', 'border-l-4 !border-l-primary pl-3' => $running])>
                        <p class="font-tabular text-sm font-semibold">{{ $schedule->startLabel() }} sampai {{ $schedule->endLabel() }}@if ($running) <span class="font-sans text-primary">, sedang berlangsung</span>@endif</p>
                        <p class="text-base break-words">{{ $schedule->label() }}</p>
                        <p class="text-sm text-text-muted">{{ collect([$activities[$schedule->activity_type], $schedule->teacher?->name, $schedule->room])->filter()->join(', ') }}</p>
                    </div>
                @empty
                    <x-ui.empty-state icon="calendar-days" message="Tidak ada jadwal." class="py-2" />
                @endforelse
            </x-ui.card>
        @endif
    @endif
</div>
