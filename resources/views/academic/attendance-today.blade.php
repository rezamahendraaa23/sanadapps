<div class="flex flex-col gap-4">
    <div class="flex flex-wrap justify-end gap-2">
        <x-ui.button variant="secondary" icon="list-checks" :href="$reviewUrl" wire:navigate>Tinjauan{{ $reviewCount > 0 ? ' ('.$reviewCount.')' : '' }}</x-ui.button>
        @can('academic.manage')
            <x-ui.button variant="secondary" icon="chart-column" :href="route('staff.attendance.recap')" wire:navigate>Rekap Absensi</x-ui.button>
        @endcan
    </div>

    <p class="flex items-start gap-2 rounded-control bg-primary-soft p-3 text-sm">
        <x-ui.icon name="info" size="sm" class="mt-0.5 shrink-0 text-primary" />
        <span>Satu sesi bisa diabsen dengan cara apa saja{{ count($modes) > 1 ? ': '.implode(', ', $modes) : '' }}. Pilih yang paling praktis di lapangan.</span>
    </p>

    @if (! $hasTerm)
        <x-ui.card><x-ui.empty-state icon="calendar-days" message="Belum ada periode aktif. Hubungi admin akademik." class="py-2" /></x-ui.card>
    @endif

    <x-ui.card title="Jadwal hari ini">
        @if ($schedules->isEmpty())
            <x-ui.empty-state icon="calendar-check" message="Tidak ada jadwal mengajar hari ini." class="py-2" />
        @else
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

    @if ($targets !== [])
        <x-ui.card title="Absen tanpa jadwal">
            <p class="mb-3 text-sm text-text-muted">Untuk sholat berjamaah, apel asrama, atau kegiatan. Jam dicatat otomatis saat sesi dibuka.</p>
            <form wire:submit="openGroup" class="flex flex-col gap-4">
                <fieldset class="flex flex-col gap-1">
                    <legend class="mb-1 text-sm font-semibold">Jenis</legend>
                    <div class="grid grid-cols-3 gap-2 md:max-w-md">
                        @foreach (['sholat' => 'Sholat', 'asrama' => 'Asrama', 'kegiatan' => 'Kegiatan'] as $value => $label)
                            <label class="relative flex min-h-11 cursor-pointer items-center justify-center rounded-control border border-text-muted px-3 text-base font-semibold has-checked:border-primary has-checked:bg-primary-soft has-checked:text-primary has-focus-visible:outline-2 has-focus-visible:outline-primary">
                                <input type="radio" name="dorm_kind" value="{{ $value }}" wire:model.live="dorm_kind" class="absolute inset-0 size-full cursor-pointer appearance-none rounded-control opacity-0">{{ $label }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <div class="grid gap-3 md:grid-cols-2">
                    <x-ui.select label="Peserta" name="target" placeholder="Pilih" :options="$targets" wire:model="target" />
                    <x-ui.input label="Nama sesi" name="dorm_title" required wire:model="dorm_title" />
                </div>
                <x-ui.button type="submit" icon="play" class="self-end" wire:loading.attr="disabled" wire:target="openGroup">Mulai Absen</x-ui.button>
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
