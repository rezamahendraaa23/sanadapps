<div class="flex flex-col gap-4">
    <div class="flex flex-wrap justify-end gap-2">
        <x-ui.button variant="secondary" icon="list-checks" :href="$reviewUrl" wire:navigate>Tinjauan{{ $reviewCount > 0 ? ' ('.$reviewCount.')' : '' }}</x-ui.button>
        @can('academic.manage')
            <x-ui.button variant="secondary" icon="chart-column" :href="route('staff.attendance.recap')" wire:navigate>Rekap Absensi</x-ui.button>
        @endcan
    </div>

    @if (! $hasTerm)
        <x-ui.card><x-ui.empty-state icon="calendar-days" message="Belum ada periode aktif. Hubungi admin akademik." class="py-2" /></x-ui.card>
    @endif

    <x-ui.card title="Jadwal hari ini">
        @if ($schedules->isEmpty())
            <x-ui.empty-state icon="calendar-check" message="Tidak ada jadwal mengajar hari ini." class="py-2" />
        @else
            @if (count($modes) > 1)
                <div class="mb-3 w-full md:max-w-xs"><x-ui.select label="Cara absen" name="class_source" :options="$modes" wire:model="class_source" /></div>
            @endif
            <ul class="flex flex-col">
                @foreach ($schedules as $schedule)
                    @php($opened = $openedBySchedule[$schedule->id] ?? null)
                    <li wire:key="j-{{ $schedule->uuid }}" class="flex flex-wrap items-center gap-3 border-b border-border py-3 last:border-b-0">
                        <span class="min-w-0 flex-1">
                            <span class="font-tabular block text-sm font-semibold">{{ $schedule->startLabel() }} sampai {{ $schedule->endLabel() }}</span>
                            <span class="block text-base break-words">{{ $schedule->label() }}</span>
                            <span class="block text-sm text-text-muted">{{ $schedule->classroom?->name }}</span>
                        </span>
                        @if ($opened?->isClosed())
                            <span class="inline-flex items-center gap-1 text-sm font-semibold text-success"><x-ui.icon name="circle-check" size="sm" />Tersimpan</span>
                        @endif
                        <x-ui.button :variant="$opened ? 'secondary' : 'primary'" wire:click="openSchedule('{{ $schedule->uuid }}')" wire:loading.attr="disabled" wire:target="openSchedule">
                            {{ $opened ? 'Buka Absensi' : 'Mulai Absensi' }}<span class="sr-only"> {{ $schedule->label() }}</span>
                        </x-ui.button>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    @if ($dormitories->isNotEmpty())
        <x-ui.card title="Sesi asrama, sholat, kegiatan">
            <form wire:submit="openDormitory" class="grid gap-3 md:grid-cols-2">
                <x-ui.select label="Asrama" name="dorm_id" placeholder="Pilih" :options="$dormitories->pluck('name', 'id')->all()" wire:model="dorm_id" />
                <x-ui.select label="Jenis" name="dorm_kind" :options="['asrama' => 'Asrama', 'sholat' => 'Sholat', 'kegiatan' => 'Kegiatan']" wire:model="dorm_kind" />
                <x-ui.input label="Nama sesi" name="dorm_title" required wire:model="dorm_title" />
                <x-ui.input label="Jam mulai" name="dorm_time" type="time" required wire:model="dorm_time" />
                @if (count($modes) > 1)
                    <x-ui.select label="Cara absen" name="dorm_source" :options="$modes" wire:model="dorm_source" />
                @endif
                <x-ui.button type="submit" class="md:col-span-2 md:justify-self-end" wire:loading.attr="disabled" wire:target="openDormitory">Buka Sesi</x-ui.button>
            </form>
            @if ($dormSessions->isNotEmpty())
                <ul class="mt-4 flex flex-col border-t border-border pt-2">
                    @foreach ($dormSessions as $session)
                        <li wire:key="d-{{ $session->uuid }}" class="border-b border-border last:border-b-0">
                            <a href="{{ \App\Modules\Academic\Livewire\AttendanceToday::sessionUrl($session) }}" wire:navigate class="flex min-h-11 items-center justify-between gap-2 py-2">
                                <span class="min-w-0 break-words">{{ $session->title }} <span class="text-sm text-text-muted">({{ $session->records_count }} santri)</span></span>
                                <x-ui.icon name="chevron-right" class="shrink-0" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    @endif
</div>
