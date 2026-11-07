<x-layouts.base title="Jadwal Sholat">
    <main id="konten" class="mx-auto flex w-full max-w-[560px] flex-col gap-4 p-4">
        <div>
            <h1 class="font-display text-2xl font-semibold break-words">{{ $profile->name }}</h1>
            <p class="text-sm text-text-muted">{{ now($profile->timezone)->translatedFormat('l, j F Y') }}, {{ $hijri['label'] }}{{ $hijri['approximate'] ? ' (perkiraan)' : '' }}</p>
        </div>
        @if ($today)
            <x-ui.card title="Jadwal hari ini">
                <dl class="flex flex-col">
                    @foreach (\App\Modules\Masjid\Models\PrayerTime::NAMES as $key => $name)
                        <div class="flex justify-between gap-3 border-b border-border py-2 text-lg last:border-b-0"><dt>{{ $name }}</dt><dd class="font-tabular font-semibold">{{ str_replace(':', '.', $today->{$key}) }}</dd></div>
                    @endforeach
                </dl>
            </x-ui.card>
            <x-ui.button variant="secondary" icon="file-down" :href="route('masjid.public.month')">Unduh Jadwal Bulan Ini</x-ui.button>
        @else
            <x-ui.card><x-ui.empty-state icon="clock" message="Jadwal belum tersedia." class="py-2" /></x-ui.card>
        @endif
        @foreach ($contents as $content)
            <x-ui.card :title="$content->title"><p class="text-base break-words whitespace-pre-line">{{ $content->body['text'] ?? '' }}</p></x-ui.card>
        @endforeach
    </main>
</x-layouts.base>
