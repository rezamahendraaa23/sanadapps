@props(['status'])

@php
    $tones = [
        'success' => 'bg-success-soft text-success',
        'warning' => 'bg-warning-soft text-warning',
        'danger' => 'bg-danger-soft text-danger',
        'info' => 'bg-info-soft text-info',
        'neutral' => 'border border-border bg-bg text-text-muted',
        'waiting' => 'hatch border border-warning bg-surface text-text',
    ];

    [$label, $icon, $tone] = [
        'paid' => ['Lunas', 'circle-check', 'success'],
        'unpaid' => ['Belum bayar', 'receipt-text', 'neutral'],
        'partial' => ['Sebagian', 'circle-dashed', 'info'],
        'overdue' => ['Terlambat', 'clock-alert', 'danger'],
        'review' => ['Menunggu verifikasi', 'hourglass', 'waiting'],
        'void' => ['Void', 'ban', 'neutral'],
        'present' => ['Hadir', 'check', 'success'],
        'excused' => ['Izin', 'door-open', 'info'],
        'sick' => ['Sakit', 'thermometer', 'warning'],
        'absent' => ['Alpa', 'x', 'danger'],
        'late' => ['Terlambat', 'clock', 'warning'],
        'active' => ['Aktif', 'circle-check', 'success'],
        'blocked' => ['Diblokir', 'lock', 'danger'],
    ][$status];
@endphp

<span {{ $attributes->class('inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-sm font-semibold '.$tones[$tone]) }}>
    <x-ui.icon :name="$icon" size="sm" />
    {{ $label }}
</span>
