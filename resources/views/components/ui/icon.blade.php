@props(['name', 'size' => 'md'])

@php($pixels = ['sm' => 16, 'md' => 20, 'lg' => 24][$size])

{{ svg('lucide-'.$name, trim('shrink-0 '.$attributes->get('class')), [
    'width' => $pixels,
    'height' => $pixels,
    'stroke-width' => '1.75',
    'aria-hidden' => 'true',
    'focusable' => 'false',
]) }}
