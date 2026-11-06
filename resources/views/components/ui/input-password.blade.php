@props(['label', 'name', 'autocomplete' => 'current-password', 'error' => null, 'help' => null])

@php
    $inputId = 'field-'.$name;
    $message = $error ?? $errors->first($name);
@endphp

<div class="flex flex-col gap-1" x-data="{ visible: false }">
    <label for="{{ $inputId }}" class="text-sm font-semibold">{{ $label }}</label>
    <div class="relative">
        <input
            id="{{ $inputId }}"
            name="{{ $name }}"
            :type="visible ? 'text' : 'password'"
            type="password"
            autocomplete="{{ $autocomplete }}"
            @if ($message) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif
            {{ $attributes->class(['min-h-12 md:min-h-11 w-full min-w-0 rounded-control border bg-surface pr-12 pl-3 text-base', 'border-danger' => $message, 'border-text-muted' => ! $message]) }}
        >
        <button
            type="button"
            class="absolute inset-y-0 right-0 inline-flex w-11 items-center justify-center rounded-control text-text-muted hover:text-primary"
            :aria-label="visible ? 'Sembunyikan sandi' : 'Tampilkan sandi'"
            :aria-pressed="visible.toString()"
            x-on:click="visible = !visible"
        >
            <span x-show="!visible"><x-ui.icon name="eye" /></span>
            <span x-show="visible" x-cloak><x-ui.icon name="eye-off" /></span>
        </button>
    </div>
    @if ($help)<p class="text-sm text-text-muted">{{ $help }}</p>@endif
    @if ($message)
        <p id="{{ $inputId }}-error" role="alert" class="flex items-start gap-1 text-sm text-danger">
            <x-ui.icon name="circle-alert" size="sm" class="mt-0.5" />
            {{ $message }}
        </p>
    @endif
</div>
