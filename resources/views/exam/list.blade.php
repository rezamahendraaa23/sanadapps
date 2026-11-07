@php
    $tz = config('sanad.display_timezone');
    $labels = ['draft' => 'Draf', 'published' => 'Terbit', 'closed' => 'Ditutup'];
@endphp

<div class="flex flex-col gap-4">
    <div class="flex flex-wrap justify-end gap-2">
        <x-ui.button variant="secondary" icon="book-marked" :href="$banksUrl" wire:navigate>Bank Soal</x-ui.button>
        <x-ui.button icon="plus" wire:click="createExam" wire:loading.attr="disabled" wire:target="createExam">Buat Ujian</x-ui.button>
    </div>

    @forelse ($exams as $exam)
        <x-ui.card wire:key="u-{{ $exam->uuid }}" class="flex flex-col gap-1">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <h2 class="min-w-0 text-base font-semibold break-words">{{ $exam->title }}</h2>
                <span class="shrink-0 rounded-full border border-border px-2.5 py-0.5 text-sm font-semibold">{{ $labels[$exam->status] }}</span>
            </div>
            <p class="text-sm text-text-muted">{{ $exam->subject?->name ?? 'Belum ada mapel' }}, {{ $exam->questions_count }} soal, {{ $exam->attempts_count }} peserta mulai</p>
            @if ($exam->start_at)<p class="font-tabular text-sm">{{ $exam->start_at->setTimezone($tz)->translatedFormat('j M, H.i') }} sampai {{ $exam->end_at?->setTimezone($tz)->translatedFormat('j M, H.i') }}</p>@endif
            <div class="mt-2 flex flex-wrap gap-2">
                @if ($exam->status === 'draft')
                    <x-ui.button variant="secondary" :href="route($routePrefix.'edit', $exam)" wire:navigate>Lanjut Menyusun<span class="sr-only"> {{ $exam->title }}</span></x-ui.button>
                @else
                    <x-ui.button variant="secondary" :href="route($routePrefix.'monitor', $exam)" wire:navigate>Monitor<span class="sr-only"> {{ $exam->title }}</span></x-ui.button>
                    <x-ui.button variant="tertiary" :href="route($routePrefix.'results', $exam)" wire:navigate>Hasil<span class="sr-only"> {{ $exam->title }}</span></x-ui.button>
                @endif
            </div>
        </x-ui.card>
    @empty
        <x-ui.card><x-ui.empty-state icon="file-pen-line" message="Belum ada ujian." class="py-2" /></x-ui.card>
    @endforelse
    <div>{{ $exams->links() }}</div>
</div>
