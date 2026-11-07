<x-layouts.base title="Absen Wajah">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <div class="flex min-h-dvh flex-col bg-bg">
        <header class="border-b border-border bg-surface">
            <div class="mx-auto flex min-h-14 w-full max-w-[720px] items-center gap-2 px-3">
                <a href="{{ $backUrl }}" class="inline-flex size-11 shrink-0 items-center justify-center rounded-control" aria-label="Kembali ke daftar absensi"><x-ui.icon name="chevron-left" /></a>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold">{{ $session->title }}</p>
                    <p class="text-xs text-text-muted">Absen wajah</p>
                </div>
            </div>
        </header>

        <main class="mx-auto flex w-full max-w-[560px] flex-1 flex-col gap-3 p-3" data-mesin="{{ $config['browser'] ? 'browser' : 'server' }}" x-data="faceKiosk(@js($config))">
            @if (! $available)
                <x-ui.banner tone="danger" role="alert">Pencocokan wajah belum tersedia di server ini. Pakai kartu atau manual.</x-ui.banner>
            @else
                <div x-show="denied" x-cloak role="alert" class="rounded-card border border-border bg-surface p-4">
                    <h1 class="text-lg font-semibold">Kamera tidak bisa dibuka</h1>
                    <p class="mt-1 text-base">Izinkan kamera di pengaturan browser, atau pakai kartu atau manual.</p>
                </div>
                <div x-show="!denied" class="relative mx-auto aspect-[3/4] w-full max-w-[380px] overflow-hidden rounded-card bg-text">
                    <video x-ref="video" autoplay playsinline muted class="size-full -scale-x-100 object-cover" aria-label="Kamera"></video>
                    <div class="pointer-events-none absolute inset-[12%] rounded-[50%] border-2 border-on-primary"></div>
                </div>
                <p x-show="challenge" x-cloak class="text-center font-display text-2xl font-semibold" aria-live="polite" x-text="challenge"></p>
                <p x-show="config.browser && !denied" class="text-center text-base" aria-live="polite" x-text="status"></p>
                @unless ($config['browser'])
                <button type="button" x-show="!denied" :disabled="busy || !ready" x-on:click="scan()" class="min-h-12 w-full rounded-control bg-primary font-semibold text-on-primary disabled:opacity-40" x-text="busy ? 'Memeriksa…' : 'Mulai Pindai'"></button>
                @endunless
                <div x-show="result" x-cloak role="status" class="rounded-card border p-4 text-center" :class="result?.ok ? (result?.outcome === 'pending' ? 'border-warning bg-warning-soft' : 'border-success bg-success-soft') : 'border-danger bg-danger-soft'">
                    <p class="text-xl font-semibold break-words" x-text="result?.name ?? ''"></p>
                    <p class="text-base" x-text="result?.message ?? ''"></p>
                </div>
            @endif
            <x-ui.button variant="secondary" :href="$backUrl">Pakai Kartu atau Manual</x-ui.button>
        </main>
    </div>
</x-layouts.base>
