{{--
    Grafik kolom satu warna. Kolom yang ditonjolkan (periode berjalan) memakai warna utama, sisanya warna redup dari ramp yang sama.
    Arahkan kursor atau fokus ke kolom untuk membaca nilainya; tabel di bawah memuat semua angka.
    items: list of [label, value (angka atau null), display (teks nilai), highlight (bool), hint (teks tambahan opsional)].
--}}
@props(['items', 'caption', 'max' => null, 'unit' => ''])

@php
    $top = $max ?? max(1, ...array_map(fn ($item) => (float) ($item['value'] ?? 0), $items));
    $default = collect($items)->last(fn ($item) => $item['value'] !== null) ?? end($items);
@endphp

<figure x-data="{ readout: @js(['label' => $default['label'], 'display' => $default['display'], 'hint' => $default['hint'] ?? null]) }" {{ $attributes->class('flex flex-col gap-3') }}>
    <figcaption class="sr-only">{{ $caption }}</figcaption>
    <p class="flex min-h-6 items-baseline gap-2 text-sm" aria-live="polite">
        <span class="font-display text-xl font-semibold" x-text="readout.display">{{ $default['display'] }}</span>
        <span class="text-text-muted" x-text="readout.label + (readout.hint ? ', ' + readout.hint : '')">{{ $default['label'] }}</span>
    </p>
    <div class="flex h-40 items-end gap-[2px] border-b border-border" x-on:mouseleave="readout = @js(['label' => $default['label'], 'display' => $default['display'], 'hint' => $default['hint'] ?? null])">
        @foreach ($items as $item)
            @php $height = $item['value'] === null || (float) $item['value'] <= 0 ? 0 : max(2, round((float) $item['value'] / $top * 100, 1)); @endphp
            <button type="button" class="group flex h-full min-w-0 flex-1 cursor-default flex-col justify-end focus-visible:outline-2 focus-visible:outline-primary"
                aria-label="{{ $item['label'] }}: {{ $item['display'] }}"
                x-on:mouseenter="readout = @js(['label' => $item['label'], 'display' => $item['display'], 'hint' => $item['hint'] ?? null])"
                x-on:focus="readout = @js(['label' => $item['label'], 'display' => $item['display'], 'hint' => $item['hint'] ?? null])">
                @if ($item['value'] === null)
                    <span class="mx-auto mb-1 size-1.5 rounded-full bg-border" title="Tidak ada data"></span>
                @else
                    <span @class(['block w-full rounded-t-[4px] transition-opacity group-hover:opacity-80', 'bg-primary' => $item['highlight'] ?? false, 'bg-primary-border' => ! ($item['highlight'] ?? false)]) style="height: {{ $height }}%"></span>
                @endif
            </button>
        @endforeach
    </div>
    <div class="flex gap-[2px] text-xs text-text-muted" aria-hidden="true">
        @foreach ($items as $i => $item)
            <span class="min-w-0 flex-1 truncate text-center">{{ count($items) > 8 && $i % 2 === 1 && $i !== count($items) - 1 ? '' : $item['short'] ?? $item['label'] }}</span>
        @endforeach
    </div>
    <details class="text-sm">
        <summary class="inline-flex min-h-11 cursor-pointer items-center font-semibold text-primary">Lihat tabel</summary>
        <table class="mt-1 w-full text-left">
            <tbody>
                @foreach ($items as $item)
                    <tr class="border-b border-border last:border-b-0"><th class="py-1 font-normal">{{ $item['label'] }}</th><td class="font-tabular py-1 text-right">{{ $item['display'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </details>
</figure>
