<x-layouts.portal title="Selesai" role="santri" active="exams">
    <x-ui.card class="text-center">
        <x-ui.icon name="circle-check" size="xl" class="mx-auto text-success" />
        <h2 class="font-display mt-3 text-2xl font-semibold">Jawaban terkirim</h2>
        <p class="text-base break-words">{{ $attempt->exam->title }}</p>
        <p class="text-sm text-text-muted">Dikumpulkan {{ $attempt->submitted_at?->setTimezone(config('sanad.display_timezone'))->translatedFormat('j M Y, H.i') }}{{ $attempt->wasAutoSubmitted() ? ', otomatis saat waktu habis' : '' }}</p>
        @if ($attempt->showsResult() && $attempt->status === 'graded')
            <p class="font-display font-tabular mt-3 text-hero font-semibold">{{ rtrim(rtrim(number_format((float) $attempt->score, 2, ',', ''), '0'), ',') }}</p>
            @if ($attempt->exam->pass_score !== null)
                <p class="text-base font-semibold">{{ $attempt->score >= $attempt->exam->pass_score ? 'Lulus' : 'Belum lulus' }}</p>
            @endif
        @elseif ($attempt->showsResult())
            <p class="mt-3 text-base">Esai sedang dikoreksi. Nilai menyusul.</p>
        @else
            <p class="mt-3 text-base">Hasil akan diumumkan.</p>
        @endif
        <x-ui.button variant="secondary" class="mt-4 w-full" :href="route('santri.exams')">Kembali ke Daftar Ujian</x-ui.button>
    </x-ui.card>
</x-layouts.portal>
