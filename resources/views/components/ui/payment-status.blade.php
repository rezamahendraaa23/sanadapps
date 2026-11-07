@props(['status'])

@php
    [$label, $icon, $classes] = [
        'pending' => ['Menunggu bukti', 'clock', 'border border-border bg-bg text-text-muted'],
        'review' => ['Menunggu verifikasi', 'hourglass', 'hatch border border-warning bg-surface text-text'],
        'settlement' => ['Lunas', 'circle-check', 'bg-success-soft text-success'],
        'failed' => ['Ditolak', 'circle-alert', 'bg-danger-soft text-danger'],
        'expired' => ['Kedaluwarsa', 'clock-alert', 'border border-border bg-bg text-text-muted'],
        'refunded' => ['Dikembalikan', 'refresh-cw', 'bg-info-soft text-info'],
    ][$status];
@endphp

<span {{ $attributes->class('inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-sm font-semibold '.$classes) }}>
    <x-ui.icon :name="$icon" size="sm" />
    {{ $label }}
</span>
