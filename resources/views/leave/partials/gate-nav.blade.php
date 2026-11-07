<nav aria-label="Bagian pos gerbang" class="flex flex-wrap gap-1">
    @foreach (['staff.gate.index' => 'Papan', 'staff.gate.scan' => 'Pindai', 'staff.gate.today' => 'Izin Hari Ini', 'staff.gate.logs' => 'Riwayat', 'staff.gate.shift' => 'Shift'] as $route => $label)
        <a href="{{ route($route) }}" wire:navigate @if (request()->routeIs($route)) aria-current="page" @endif
            @class(['inline-flex min-h-11 items-center rounded-control px-4 text-base font-semibold', 'bg-primary text-on-primary' => request()->routeIs($route), 'text-text-muted hover:bg-primary-soft' => ! request()->routeIs($route)])>{{ $label }}</a>
    @endforeach
</nav>
