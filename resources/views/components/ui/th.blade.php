@props(['sortable' => false, 'direction' => null, 'numeric' => false])

<th
    scope="col"
    @if ($sortable) aria-sort="{{ ['asc' => 'ascending', 'desc' => 'descending'][$direction] ?? 'none' }}" @endif
    @class([
        'sticky top-0 border-b border-border bg-surface px-3 py-2 font-semibold text-text-muted',
        'text-right' => $numeric,
        'text-left' => ! $numeric,
    ])
>
    @if ($sortable)
        <button type="button" {{ $attributes->class('inline-flex min-h-11 items-center gap-1 rounded-control hover:text-primary') }}>
            {{ $slot }}
            <x-ui.icon :name="['asc' => 'arrow-up', 'desc' => 'arrow-down'][$direction] ?? 'chevrons-up-down'" size="sm" />
        </button>
    @else
        {{ $slot }}
    @endif
</th>
