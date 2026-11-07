@php use App\Modules\Academic\Models\Schedule; @endphp

<div class="flex flex-col gap-4">
    <x-ui.card>
        <p class="text-lg font-semibold">Assalamu'alaikum, {{ $user->name }}</p>
    </x-ui.card>

    @if ($next)
        <x-ui.hero-card :label="$next->start_time <= now(config('sanad.display_timezone'))->format('H:i:s') ? 'Sedang berlangsung' : 'Kelas berikutnya'" :value="$next->startLabel()">
            {{ $next->label() }}, {{ $next->classroom?->name }}.
            <x-slot:action>
                <x-ui.button variant="on-primary" class="w-full" :href="route('ustadz.attendance')" wire:navigate>Mulai Absensi</x-ui.button>
            </x-slot:action>
        </x-ui.hero-card>
    @endif

    @if ($openSessions > 0)
        <x-ui.banner tone="warning">{{ $openSessions }} sesi absensi hari ini belum disimpan.</x-ui.banner>
    @endif

    @foreach ($dormitories as $dormitory)
        <x-ui.card wire:key="asrama-{{ $dormitory->id }}">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0"><p class="text-base font-semibold break-words">{{ $dormitory->name }}</p><p class="text-sm text-text-muted">{{ $dormitory->students_count }} santri</p></div>
                <x-ui.button variant="secondary" class="shrink-0" :href="route('musyrif.attendance')" wire:navigate>Absensi</x-ui.button>
            </div>
        </x-ui.card>
    @endforeach

    @if ($role === 'ustadz')
        <x-ui.card title="Jadwal mengajar">
            <nav aria-label="Pilih hari" class="mb-3 flex gap-1 overflow-x-auto pb-1">
                @foreach (Schedule::DAYS as $number => $name)
                    <button type="button" wire:key="h-{{ $number }}" wire:click="$set('day', {{ $number }})" @if ($number === $selected) aria-current="true" @endif
                        @class(['inline-flex min-h-11 shrink-0 items-center rounded-full border px-3 text-sm font-semibold', 'border-primary bg-primary-soft text-primary' => $number === $selected, 'border-border' => $number !== $selected])>{{ $name }}</button>
                @endforeach
            </nav>
            @forelse ($schedules as $schedule)
                <div wire:key="j-{{ $schedule->uuid }}" class="border-b border-border py-2 last:border-b-0">
                    <p class="font-tabular text-sm font-semibold">{{ $schedule->startLabel() }} sampai {{ $schedule->endLabel() }}</p>
                    <p class="text-base break-words">{{ $schedule->label() }}</p>
                    <p class="text-sm text-text-muted">{{ collect([$schedule->classroom?->name, $schedule->room])->filter()->join(', ') }}</p>
                </div>
            @empty
                <x-ui.empty-state icon="calendar-days" message="Tidak ada jadwal." class="py-2" />
            @endforelse
        </x-ui.card>
    @endif
</div>
