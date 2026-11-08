{{-- Garis tren kecil: riwayat di warna redup, titik terakhir di warna utama. Nilai kosong dilewati. --}}
@props(['values', 'label' => 'Tren'])

@php
    $points = array_values($values);
    $known = array_filter($points, fn ($v) => $v !== null);
    $min = $known ? min($known) : 0;
    $max = $known ? max($known) : 1;
    $span = $max - $min ?: 1;
    $count = max(count($points) - 1, 1);
    $coords = [];
    foreach ($points as $i => $v) {
        if ($v !== null) {
            $coords[] = [round($i / $count * 100, 2), round(26 - ($v - $min) / $span * 22, 2)];
        }
    }
    $last = end($coords) ?: null;
@endphp

@if (count($coords) >= 2)
    {{-- Garis boleh meregang mengikuti lebar kartu; titik akhirnya elemen terpisah agar tetap bulat. --}}
    <div class="relative h-8 w-full" role="img" aria-label="{{ $label }}, tren {{ count($points) }} hari">
        <svg viewBox="0 0 100 30" preserveAspectRatio="none" class="absolute inset-0 h-full w-full text-text-muted" aria-hidden="true">
            <polyline points="{{ collect($coords)->map(fn ($c) => $c[0].','.$c[1])->implode(' ') }}" fill="none" stroke="currentColor" stroke-opacity=".55" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round" stroke-linecap="round" />
        </svg>
        <span class="absolute size-2 -translate-x-1/2 -translate-y-1/2 rounded-full bg-primary ring-2 ring-surface" style="left: {{ $last[0] }}%; top: {{ round($last[1] / 30 * 100, 2) }}%"></span>
    </div>
@endif
