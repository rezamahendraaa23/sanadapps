@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'button',
    'icon' => null,
    'loading' => false,
    'loadingLabel' => 'Memproses…',
])

@php
    $classes = [
        'primary' => 'bg-primary text-on-primary hover:bg-primary-hover active:bg-primary-active',
        'secondary' => 'border border-primary bg-surface text-primary hover:bg-primary-soft',
        'tertiary' => 'text-primary hover:bg-primary-soft',
        'danger' => 'bg-danger text-on-primary hover:bg-danger-hover',
        'on-primary' => 'bg-on-primary text-primary hover:bg-primary-soft',
    ][$variant];

    $base = 'inline-flex min-h-12 md:min-h-11 items-center justify-center gap-2 rounded-control px-4 text-base font-semibold '
        .'transition-[background-color,transform] duration-150 ease-out active:scale-[0.98] '
        .'disabled:pointer-events-none disabled:opacity-40 '
        .$classes;
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($base) }}>
        @if ($icon)<x-ui.icon :name="$icon" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" @disabled($loading) @if ($loading) aria-busy="true" @endif {{ $attributes->class($base) }}>
        @if ($loading)
            <x-ui.icon name="loader-circle" class="animate-spin" />
            {{ $loadingLabel }}
        @else
            @if ($icon)<x-ui.icon :name="$icon" />@endif
            {{ $slot }}
        @endif
    </button>
@endif
