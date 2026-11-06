@props(['label', 'numeric' => false])

<td data-label="{{ $label }}" {{ $attributes->class(['px-0 md:border-b md:border-border md:px-3 md:py-2', 'font-tabular text-right' => $numeric]) }}>
    <span class="min-w-0">{{ $slot }}</span>
</td>
