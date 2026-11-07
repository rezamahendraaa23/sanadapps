@props(['selected' => false])

<button
    type="button"
    role="tab"
    aria-selected="{{ $selected ? 'true' : 'false' }}"
    {{ $attributes->class('min-h-11 rounded-control px-4 text-base font-semibold text-text-muted hover:bg-primary-soft aria-selected:bg-primary aria-selected:text-on-primary aria-selected:hover:bg-primary') }}
>{{ $slot }}</button>
