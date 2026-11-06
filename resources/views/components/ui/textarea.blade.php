@props(['label', 'name', 'help' => null, 'error' => null, 'required' => false, 'rows' => 3])

@php
    $inputId = 'field-'.$name;
    $message = $error ?? $errors->first($name);
@endphp

<div class="flex flex-col gap-1">
    <label for="{{ $inputId }}" class="text-sm font-semibold">
        {{ $label }}
        @if ($required)<span class="text-danger" aria-hidden="true">*</span>@endif
    </label>
    <textarea
        id="{{ $inputId }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        @required($required)
        @if ($message) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif
        {{ $attributes->class(['w-full min-w-0 rounded-control border bg-surface p-3 text-base', 'border-danger' => $message, 'border-text-muted' => ! $message]) }}
    ></textarea>
    @if ($help)<p class="text-sm text-text-muted">{{ $help }}</p>@endif
    @if ($message)
        <p id="{{ $inputId }}-error" role="alert" class="flex items-start gap-1 text-sm text-danger">
            <x-ui.icon name="circle-alert" size="sm" class="mt-0.5" />
            {{ $message }}
        </p>
    @endif
</div>
