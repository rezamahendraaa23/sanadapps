@props(['caption', 'stack' => true])

<div {{ $attributes->class('overflow-x-auto') }}>
    <table @class(['w-full border-collapse text-sm', 'table-stack' => $stack])>
        <caption class="sr-only">{{ $caption }}</caption>
        {{ $slot }}
    </table>
</div>
