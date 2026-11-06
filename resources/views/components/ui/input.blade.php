@props(['label', 'name', 'type' => 'text', 'help' => null, 'error' => null, 'required' => false, 'id' => null])

@php
    $inputId = $id ?? 'field-'.$name;
    $message = $error ?? $errors->first($name);
    $describedBy = trim(($help ? $inputId.'-help ' : '').($message ? $inputId.'-error' : ''));
@endphp

<div class="flex flex-col gap-1">
    <label for="{{ $inputId }}" class="text-sm font-semibold">
        {{ $label }}
        @if ($required)<span class="text-danger" aria-hidden="true">*</span>@endif
    </label>
    <input
        id="{{ $inputId }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @required($required)
        @if ($message) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->class([
            'min-h-12 md:min-h-11 w-full min-w-0 rounded-control border bg-surface px-3 text-base placeholder:text-text-muted',
            'border-danger' => $message,
            'border-text-muted' => ! $message,
        ]) }}
    >
    @if ($help)
        <p id="{{ $inputId }}-help" class="text-sm text-text-muted">{{ $help }}</p>
    @endif
    @if ($message)
        <p id="{{ $inputId }}-error" role="alert" class="flex items-start gap-1 text-sm text-danger">
            <x-ui.icon name="circle-alert" size="sm" class="mt-0.5" />
            {{ $message }}
        </p>
    @endif
</div>
