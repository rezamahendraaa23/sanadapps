@props(['message', 'code' => null, 'action' => null])

<div role="alert" {{ $attributes->class('flex flex-col items-center gap-3 px-4 py-8 text-center') }}>
    <x-ui.icon name="circle-alert" size="lg" class="text-danger" />
    <p class="max-w-prose text-base">{{ $message }}</p>
    {{ $action }}
    @if ($code)<p class="text-xs text-text-muted">Kode: {{ $code }}</p>@endif
</div>
