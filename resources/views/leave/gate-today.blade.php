@php $tz = config('sanad.display_timezone'); @endphp

<div class="flex flex-col gap-4">
    @include('leave.partials.gate-nav')
    <x-ui.card title="Izin yang berlaku hari ini">
        @forelse ($leaves as $leave)
            <div wire:key="i-{{ $leave->uuid }}" class="flex flex-wrap items-center justify-between gap-2 border-b border-border py-2 last:border-b-0">
                <span class="min-w-0"><span class="block text-base font-semibold break-words">{{ $leave->student->name }}</span><span class="block text-sm text-text-muted">{{ $leave->student->classroom?->name }}, {{ $types[$leave->type] }}. {{ $leave->start_at->setTimezone($tz)->format('H.i') }} sampai {{ $leave->end_at->setTimezone($tz)->translatedFormat('j M, H.i') }}</span></span>
                @include('leave.partials.status', ['leave' => $leave])
            </div>
        @empty
            <x-ui.empty-state icon="door-open" message="Tidak ada izin hari ini." class="py-2" />
        @endforelse
    </x-ui.card>
</div>
