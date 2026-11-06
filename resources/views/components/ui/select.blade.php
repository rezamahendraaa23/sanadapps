@props(['label', 'name', 'options' => [], 'placeholder' => null, 'help' => null, 'error' => null, 'required' => false, 'id' => null])

@php
    $inputId = $id ?? 'field-'.$name;
    $message = $error ?? $errors->first($name);
@endphp

<div class="flex flex-col gap-1">
    <label for="{{ $inputId }}" class="text-sm font-semibold">
        {{ $label }}
        @if ($required)<span class="text-danger" aria-hidden="true">*</span>@endif
    </label>
    <select
        id="{{ $inputId }}"
        name="{{ $name }}"
        @required($required)
        @if ($message) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif
        {{ $attributes->class(['min-h-12 md:min-h-11 w-full min-w-0 rounded-control border bg-surface px-3 text-base', 'border-danger' => $message, 'border-text-muted' => ! $message]) }}
    >
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $value => $text)
            <option value="{{ $value }}">{{ $text }}</option>
        @endforeach
    </select>
    @if ($help)<p class="text-sm text-text-muted">{{ $help }}</p>@endif
    @if ($message)
        <p id="{{ $inputId }}-error" role="alert" class="flex items-start gap-1 text-sm text-danger">
            <x-ui.icon name="circle-alert" size="sm" class="mt-0.5" />
            {{ $message }}
        </p>
    @endif
</div>
