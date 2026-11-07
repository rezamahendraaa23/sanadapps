@php $tz = config('sanad.display_timezone'); @endphp

<div class="flex flex-col gap-4">
    <div role="tablist" aria-label="Daftar ujian" class="grid grid-cols-3 gap-1 rounded-control bg-surface p-1">
        @foreach (['tersedia' => 'Tersedia', 'mendatang' => 'Mendatang', 'selesai' => 'Selesai'] as $key => $label)
            <x-ui.tab class="!px-1" :selected="$tab === $key" wire:key="tab-{{ $key }}" wire:click="$set('tab', '{{ $key }}')">{{ $label }}</x-ui.tab>
        @endforeach
    </div>

    @php($list = ['tersedia' => $available, 'mendatang' => $upcoming, 'selesai' => $finished][$tab] ?? $available)
    @forelse ($list as $exam)
        <x-ui.card wire:key="u-{{ $exam->uuid }}" class="flex flex-col gap-1">
            <h2 class="text-base font-semibold break-words">{{ $exam->title }}</h2>
            <p class="text-sm text-text-muted">{{ $exam->subject?->name }}{{ $exam->subject ? ', ' : '' }}{{ $exam->duration_minutes }} menit</p>
            <p class="font-tabular text-sm">{{ $exam->start_at?->setTimezone($tz)->translatedFormat('j M, H.i') }} sampai {{ $exam->end_at?->setTimezone($tz)->translatedFormat('j M, H.i') }}</p>
            @if ($tab === 'tersedia')
                <x-ui.button class="mt-2 w-full" :href="route('santri.exams.prepare', $exam)" wire:navigate>{{ collect($attempts[$exam->id] ?? [])->contains('status', 'in_progress') ? 'Lanjutkan' : 'Mulai' }}<span class="sr-only"> {{ $exam->title }}</span></x-ui.button>
            @elseif ($tab === 'selesai')
                @php($latest = collect($attempts[$exam->id] ?? [])->first())
                @if ($latest)
                    <x-ui.button variant="secondary" class="mt-2 w-full" :href="route('exam.done', $latest)">Lihat Hasil<span class="sr-only"> {{ $exam->title }}</span></x-ui.button>
                @endif
            @endif
        </x-ui.card>
    @empty
        <x-ui.card><x-ui.empty-state icon="file-pen-line" :message="['tersedia' => 'Tidak ada ujian yang tersedia sekarang.', 'mendatang' => 'Belum ada ujian yang dijadwalkan.', 'selesai' => 'Belum ada ujian yang selesai.'][$tab] ?? 'Tidak ada ujian.'" class="py-2" /></x-ui.card>
    @endforelse
</div>
