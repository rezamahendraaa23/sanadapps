@props(['tone' => 'info'])

@php
    [$classes, $icon] = [
        'info' => ['bg-info-soft', 'info'],
        'warning' => ['bg-warning-soft', 'triangle-alert'],
        'danger' => ['bg-danger-soft', 'circle-alert'],
        'success' => ['bg-success-soft', 'circle-check'],
    ][$tone];
@endphp

<div {{ $attributes->class('flex items-start gap-2 rounded-control p-3 text-base text-text '.$classes) }}>
    <x-ui.icon :name="$icon" class="mt-0.5" />
    <div class="min-w-0">{{ $slot }}</div>
</div>
