@props(['label', 'value', 'action' => null])

{{-- Maksimal satu per layar: angka terpenting bagi peran yang sedang melihat. --}}
<section {{ $attributes->class('on-primary @container rounded-card bg-primary p-6 text-on-primary shadow-sm') }}>
    <h2 class="text-base font-semibold">{{ $label }}</h2>
    <p class="font-display font-tabular mt-1 text-[clamp(1.75rem,12.5cqi,2.75rem)] leading-[1.15] font-semibold">{{ $value }}</p>
    @if ($slot->isNotEmpty())
        <div class="mt-2 text-base">{{ $slot }}</div>
    @endif
    @if ($action)
        <div class="mt-4 flex flex-wrap gap-2">{{ $action }}</div>
    @endif
</section>
