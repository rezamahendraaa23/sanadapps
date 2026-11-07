@php
    $tone = match (true) {
        $leave->status === 'approved', $leave->status === 'returned' => 'bg-success-soft text-success',
        $leave->status === 'late', $leave->status === 'rejected' => 'bg-danger-soft text-danger',
        $leave->status === 'out' => 'bg-info-soft text-info',
        $leave->status === 'pending' => 'hatch border border-warning bg-surface text-text',
        default => 'border border-border bg-bg text-text-muted',
    };
    $icon = ['approved' => 'circle-check', 'returned' => 'circle-check', 'late' => 'clock-alert', 'rejected' => 'circle-alert', 'out' => 'door-open', 'pending' => 'hourglass', 'cancelled' => 'ban'][$leave->status];
@endphp
<span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-sm font-semibold {{ $tone }}"><x-ui.icon :name="$icon" size="sm" />{{ $leave->statusLabel() }}</span>
