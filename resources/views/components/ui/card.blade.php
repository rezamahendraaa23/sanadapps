@props(['title' => null, 'actions' => null])

<section {{ $attributes->class('rounded-card border border-border bg-surface p-4 shadow-sm md:p-6') }}>
    @if ($title || $actions)
        <header class="mb-4 flex flex-wrap items-center justify-between gap-2">
            @if ($title)<h3 class="text-lg font-semibold">{{ $title }}</h3>@endif
            {{ $actions }}
        </header>
    @endif
    {{ $slot }}
</section>
