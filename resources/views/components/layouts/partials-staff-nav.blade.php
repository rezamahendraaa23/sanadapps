@props(['groups', 'active' => null, 'collapsible' => false, 'workspace' => null, 'homeUrl' => null])

<nav aria-label="{{ $workspace ? 'Menu '.$workspace['label'] : 'Menu utama' }}" class="flex-1 overflow-y-auto px-3 py-4" data-keep-scroll="menu">
    @if ($workspace)
        {{-- Di dalam ruang kerja: jalan pulang selalu di atas, lalu nama ruang kerjanya. --}}
        <a href="{{ $homeUrl }}" wire:navigate title="Kembali ke menu utama" class="mb-3 flex min-h-11 items-center gap-3 rounded-control px-2 text-sm font-semibold text-text-muted hover:bg-primary-soft hover:text-primary">
            <x-ui.icon name="arrow-left" />
            <span @if ($collapsible) x-show="!collapsed" @endif>Menu utama</span>
        </a>
        <div class="mb-4 flex items-center gap-3 rounded-card bg-primary px-3 py-3 text-on-primary" title="{{ $workspace['label'] }}">
            <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-control bg-on-primary/15"><x-ui.icon :name="$workspace['icon']" /></span>
            <span class="min-w-0" @if ($collapsible) x-show="!collapsed" @endif>
                <span class="block text-xs opacity-80">Ruang kerja</span>
                <span class="font-display block truncate text-base font-semibold">{{ $workspace['label'] }}</span>
            </span>
        </div>
    @endif
    @foreach ($groups as $group)
        <div class="mb-4">
            <h2 class="mb-1 px-3 text-xs font-semibold text-text-muted" @if ($collapsible) x-show="!collapsed" @endif>{{ $group['label'] }}</h2>
            <ul class="flex flex-col gap-1">
                @foreach ($group['items'] as $item)
                    @php($isActive = $item['key'] === $active)
                    <li>
                        @if ($item['url'])
                            <a
                                href="{{ $item['url'] }}"
                                wire:navigate
                                title="{{ $item['label'] }}"
                                @if ($isActive) aria-current="page" @endif
                                @class([
                                    'flex min-h-11 items-center gap-3 rounded-control border-l-4 px-2 text-sm font-semibold',
                                    'border-primary bg-primary-soft text-primary' => $isActive,
                                    'border-transparent hover:bg-primary-soft' => ! $isActive,
                                ])
                            >
                                <x-ui.icon :name="$item['icon']" />
                                @if ($item['door'] ?? false)
                                    <span class="min-w-0 flex-1" @if ($collapsible) x-show="!collapsed" @endif>
                                        <span class="block">{{ $item['label'] }}</span>
                                        <span class="block truncate text-xs font-normal text-text-muted">{{ $item['hint'] }}</span>
                                    </span>
                                    <x-ui.icon name="chevron-right" size="sm" class="text-text-muted" x-show="!collapsed" />
                                @else
                                    <span @if ($collapsible) x-show="!collapsed" @endif>{{ $item['label'] }}</span>
                                @endif
                            </a>
                        @else
                            <span title="{{ $item['label'] }} belum dibuat" class="flex min-h-11 items-center gap-3 border-l-4 border-transparent px-2 text-sm text-text-muted">
                                <x-ui.icon :name="$item['icon']" />
                                <span @if ($collapsible) x-show="!collapsed" @endif>
                                    {{ $item['label'] }}
                                    <span class="block text-xs">Belum dibuat</span>
                                </span>
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</nav>
