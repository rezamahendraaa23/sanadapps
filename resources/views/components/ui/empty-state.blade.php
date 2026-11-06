@props(['icon' => 'circle-check', 'message', 'action' => null])

<div {{ $attributes->class('flex flex-col items-center gap-3 px-4 py-8 text-center') }}>
    <x-ui.icon :name="$icon" size="lg" class="text-text-muted" />
    <p class="max-w-prose text-base">{{ $message }}</p>
    {{ $action }}
</div>
