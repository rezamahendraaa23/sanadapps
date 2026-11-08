{{--
    Kartu angka: label, nilai, perubahan terhadap periode yang disebut, dan garis tren opsional.
    value null berarti belum ada data. tone menyatakan arah x baik buruk: good, bad, atau neutral. Warna tidak pernah sendirian; selalu ada tanda dan kata.
--}}
@props(['label', 'value', 'delta' => null, 'deltaIcon' => null, 'tone' => 'neutral', 'note' => null, 'trend' => null, 'href' => null, 'icon' => null])

<{{ $href ? 'a' : 'div' }} @if ($href) href="{{ $href }}" wire:navigate @endif {{ $attributes->class(['flex min-w-0 flex-col gap-2 rounded-card border border-border bg-surface p-4 shadow-sm', 'transition-colors hover:border-primary' => $href]) }}>
    <p class="flex items-center gap-2 text-sm font-semibold text-text-muted">
        @if ($icon)<x-ui.icon :name="$icon" size="sm" />@endif{{ $label }}
    </p>
    @if ($value === null)
        <p class="flex min-h-[1.875rem] items-end text-base text-text-muted">Belum ada data</p>
    @else
        <p class="font-display text-3xl leading-none font-semibold">{{ $value }}</p>
    @endif
    <div class="flex min-h-5 flex-wrap items-center gap-x-2 gap-y-1 text-xs">
        @if ($delta !== null)
            <span @class([
                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-semibold',
                'bg-success-soft text-success' => $tone === 'good',
                'bg-danger-soft text-danger' => $tone === 'bad',
                'bg-bg text-text-muted' => $tone === 'neutral',
            ])>
                <x-ui.icon :name="$deltaIcon ?? (str_starts_with($delta, '-') ? 'trending-down' : (str_starts_with($delta, '+') ? 'trending-up' : 'minus'))" size="sm" />{{ $delta }}
            </span>
        @endif
        @if ($note)<span class="text-text-muted">{{ $note }}</span>@endif
    </div>
    @if ($trend)
        <x-ui.chart.sparkline :values="$trend" :label="$label" />
    @endif
</{{ $href ? 'a' : 'div' }}>
