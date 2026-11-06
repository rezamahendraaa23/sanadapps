@props(['variant' => 'full', 'tagline' => false, 'size' => 40])

{{-- Huruf S dari dua mata rantai yang bertemu di satu simpul: makna sanad, rantai periwayatan. --}}
<span {{ $attributes->class('inline-flex items-center gap-3') }}>
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 48 48" class="shrink-0" role="img" aria-label="Logo SANAD">
        <circle cx="24" cy="24" r="24" class="fill-primary" />
        <path d="M30.9 12A8 8 0 1 0 24 24A8 8 0 1 1 17.1 36" fill="none" stroke-width="4.5" stroke-linecap="round" class="stroke-on-primary" />
        <circle cx="24" cy="24" r="5" class="fill-primary" />
        <circle cx="24" cy="24" r="3.25" class="fill-accent-soft" />
    </svg>
    @if ($variant === 'full')
        <span class="flex flex-col">
            <span class="font-display text-xl font-semibold tracking-wide text-primary">SANAD</span>
            @if ($tagline)
                <span class="text-sm text-text-muted">{{ \App\Support\SchoolIdentity::TAGLINE }}</span>
            @endif
        </span>
    @endif
</span>
