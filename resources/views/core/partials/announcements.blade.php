<section class="flex flex-col gap-3">
    <h2 class="text-lg font-semibold">Pengumuman</h2>
    @forelse ($announcements as $announcement)
        <x-ui.card>
            <h3 class="text-base font-semibold break-words">{{ $announcement->title }}</h3>
            <p class="text-xs text-text-muted">{{ $announcement->publish_at->setTimezone(config('sanad.display_timezone'))->translatedFormat('j M Y') }}</p>
            <p class="mt-2 whitespace-pre-line break-words text-base">{{ $announcement->body }}</p>
        </x-ui.card>
    @empty
        <x-ui.card><x-ui.empty-state icon="megaphone" message="Belum ada pengumuman dari pesantren." class="py-2" /></x-ui.card>
    @endforelse
</section>
