<span {{ $attributes->class('inline-flex items-center gap-1.5 rounded-control border-4 border-double border-accent px-3 py-1 font-display text-base font-semibold tracking-wide text-accent') }}>
    <x-ui.icon name="check" size="sm" />
    {{ $slot }}
</span>
