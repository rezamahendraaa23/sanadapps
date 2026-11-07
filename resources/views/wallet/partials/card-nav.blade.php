@can('cards.manage')
    <nav aria-label="Bagian kartu" class="flex flex-wrap gap-1">
        @foreach (array_filter(['staff.cards.index' => 'Kartu', 'staff.cards.devices' => auth()->user()->can('devices.manage') ? 'Perangkat' : null, 'staff.cards.scans' => 'Log Pindai']) as $route => $label)
            <a href="{{ route($route) }}" wire:navigate @if (request()->routeIs($route)) aria-current="page" @endif
                @class(['inline-flex min-h-11 items-center rounded-control px-4 text-base font-semibold', 'bg-primary text-on-primary' => request()->routeIs($route), 'text-text-muted hover:bg-primary-soft' => ! request()->routeIs($route)])>{{ $label }}</a>
        @endforeach
    </nav>
@endcan
