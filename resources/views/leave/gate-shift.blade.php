@php $tz = config('sanad.display_timezone'); @endphp

<div class="flex flex-col gap-4">
    @include('leave.partials.gate-nav')

    @if ($previous)
        <x-ui.card title="Serah terima terakhir">
            <p class="text-base">{{ $previous->user->name }}, selesai {{ $previous->ended_at->setTimezone($tz)->translatedFormat('j M, H.i') }}. Saat itu {{ $previous->outside_count_at_end }} santri di luar.</p>
            @if ($previous->handover_note)<p class="mt-2 rounded-control bg-bg p-3 text-base break-words">{{ $previous->handover_note }}</p>@endif
        </x-ui.card>
    @endif

    <x-ui.card title="Masih di luar sekarang">
        @forelse ($outside as $leave)
            <div wire:key="o-{{ $leave->uuid }}" class="flex justify-between gap-3 border-b border-border py-2 text-base last:border-b-0">
                <span class="min-w-0 break-words">{{ $leave->student->name }}</span>
                <span class="font-tabular shrink-0 text-sm">Kembali {{ $leave->end_at->setTimezone($tz)->translatedFormat('j M, H.i') }}</span>
            </div>
        @empty
            <x-ui.empty-state icon="circle-check" message="Tidak ada santri di luar." class="py-2" />
        @endforelse
    </x-ui.card>

    <x-ui.card :title="$current ? 'Shift Anda berjalan sejak '.$current->started_at->setTimezone($tz)->format('H.i') : 'Shift'">
        @if ($current)
            <div class="flex flex-col gap-3">
                <x-ui.textarea label="Catatan serah terima" name="note" rows="3" wire:model="note" />
                <x-ui.button class="self-start" wire:click="end" wire:loading.attr="disabled" wire:target="end">Akhiri Shift</x-ui.button>
            </div>
        @else
            <x-ui.button wire:click="start" wire:loading.attr="disabled" wire:target="start">Terima Shift</x-ui.button>
        @endif
    </x-ui.card>
</div>
