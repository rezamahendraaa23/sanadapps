@props(['label', 'name', 'value' => null, 'help' => null, 'error' => null, 'required' => false])

@php
    $inputId = 'field-'.$name;
    $message = $error ?? $errors->first($name);
    $wireAttributes = $attributes->whereStartsWith('wire:model')->getAttributes();
    $wireKey = array_key_first($wireAttributes);
    $wireModel = $wireKey ? $wireAttributes[$wireKey] : null;
    $source = $wireModel
        ? '$wire.entangle('.\Illuminate\Support\Js::from($wireModel).')'.(str_contains($wireKey, '.live') ? '.live' : '')
        : \Illuminate\Support\Js::from((string) old($name, $value));
@endphp

<div
    class="flex flex-col gap-1"
    x-data="{
        raw: {{ $source }},
        get formatted() { const digits = String(this.raw ?? '').replace(/\D/g, ''); return digits ? Number(digits).toLocaleString('id-ID') : '' },
        update(text) { this.raw = text.replace(/\D/g, '').replace(/^0+/, '') },
    }"
>
    <label for="{{ $inputId }}" class="text-sm font-semibold">
        {{ $label }}
        @if ($required)<span class="text-danger" aria-hidden="true">*</span>@endif
    </label>
    <div @class([
        'flex min-h-12 md:min-h-11 items-center gap-2 rounded-control border bg-surface px-3 focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-primary',
        'border-danger' => $message,
        'border-text-muted' => ! $message,
    ])>
        <span class="text-text-muted" aria-hidden="true">Rp</span>
        <input
            id="{{ $inputId }}"
            type="text"
            inputmode="numeric"
            autocomplete="off"
            class="font-tabular w-full min-w-0 bg-transparent text-right text-base outline-none focus-visible:outline-none"
            :value="formatted"
            x-on:input="update($event.target.value); $event.target.value = formatted"
            @required($required)
            @if ($message) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif
        >
        @unless ($wireModel)
            <input type="hidden" name="{{ $name }}" :value="raw">
        @endunless
    </div>
    @if ($help)
        <p class="text-sm text-text-muted">{{ $help }}</p>
    @endif
    @if ($message)
        <p id="{{ $inputId }}-error" role="alert" class="flex items-start gap-1 text-sm text-danger">
            <x-ui.icon name="circle-alert" size="sm" class="mt-0.5" />
            {{ $message }}
        </p>
    @endif
</div>
