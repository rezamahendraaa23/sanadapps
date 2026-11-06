@inject('identity', \App\Support\SchoolIdentity::class)

<x-layouts.base title="Beranda">
    <main id="konten" class="pattern-geometric flex min-h-dvh items-center justify-center p-6">
        <div class="flex w-full max-w-[480px] flex-col items-center gap-4 rounded-card border border-border bg-surface p-8 text-center shadow-sm">
            @if ($identity->logoUrl())
                <img src="{{ $identity->logoUrl() }}" alt="Logo {{ $identity->displayName() }}" class="h-16 w-16 object-contain">
            @else
                <x-ui.logo variant="mark" :size="64" />
            @endif
            <div>
                <h1 class="font-display text-2xl font-semibold">{{ $identity->displayName() }}</h1>
                <p class="mt-1 text-base text-text-muted">{{ \App\Support\SchoolIdentity::TAGLINE }}</p>
            </div>
            <p class="text-base">Sistem sedang disiapkan. Halaman masuk dibuka setelah setup awal selesai.</p>
        </div>
    </main>
</x-layouts.base>
