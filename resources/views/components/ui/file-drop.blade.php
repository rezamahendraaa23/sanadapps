{{--
    Kotak unggah: klik untuk memilih atau seret berkas ke kotak. Isian berkas asli menutupi kotak secara transparan,
    jadi klik, sentuhan, seret-lepas, dan keyboard semuanya ditangani browser. wire:model diteruskan lewat atribut.
--}}
@props(['label', 'name', 'accept' => null, 'hint' => null, 'multiple' => false, 'required' => false, 'icon' => 'upload'])

@php($id = 'field-'.str_replace('.', '-', $name))
@php($invalid = $errors->has($name) || $errors->has($name.'.*'))

<div class="flex min-w-0 flex-col gap-1">
    <label for="{{ $id }}" class="text-sm font-semibold">{{ $label }}@if ($required) <span class="text-danger" aria-hidden="true">*</span>@endif</label>
    <div x-data="{ names: [], over: false }"
        @class([
            'relative flex min-h-24 flex-col items-center justify-center gap-1 rounded-control border-2 border-dashed p-4 text-center transition-colors has-focus-visible:outline-2 has-focus-visible:outline-primary',
            'border-danger' => $invalid,
        ])
        :class="over ? 'border-primary bg-primary-soft' : '{{ $invalid ? '' : 'border-border hover:border-primary' }}'">
        <x-ui.icon :name="$icon" class="text-primary" />
        <p x-show="names.length === 0" class="text-sm"><span class="font-semibold text-primary">Pilih berkas</span> atau seret ke sini</p>
        <p x-show="names.length > 0" x-cloak class="text-sm font-semibold break-all" x-text="names.join(', ')"></p>
        @if ($hint)<p class="text-xs text-text-muted">{{ $hint }}</p>@endif
        <p wire:loading wire:target="{{ $name }}" class="text-xs font-semibold text-primary">Mengunggah...</p>
        <input id="{{ $id }}" type="file" @if ($accept) accept="{{ $accept }}" @endif @if ($multiple) multiple @endif
            @if ($invalid) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            {{ $attributes->class('absolute inset-0 size-full cursor-pointer opacity-0') }}
            x-on:change="names = [...$el.files].map((file) => file.name)"
            x-on:dragenter="over = true" x-on:dragleave="over = false" x-on:drop="over = false">
    </div>
    @error($name)<p id="{{ $id }}-error" role="alert" class="flex items-start gap-1 text-sm text-danger"><x-ui.icon name="circle-alert" size="sm" class="mt-0.5" />{{ $message }}</p>@enderror
    @error($name.'.*')<p role="alert" class="flex items-start gap-1 text-sm text-danger"><x-ui.icon name="circle-alert" size="sm" class="mt-0.5" />{{ $message }}</p>@enderror
</div>
