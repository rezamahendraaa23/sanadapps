@props(['label' => 'Memuat data'])

<div role="status" {{ $attributes->class('flex flex-col gap-3') }}>
    <span class="sr-only">{{ $label }}</span>
    @if ($slot->isEmpty())
        <div class="h-4 w-2/5 animate-pulse rounded-control bg-border"></div>
        <div class="h-8 w-3/5 animate-pulse rounded-control bg-border"></div>
        <div class="h-4 w-full animate-pulse rounded-control bg-border"></div>
    @else
        {{ $slot }}
    @endif
</div>
