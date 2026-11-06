@props(['groups', 'active' => null, 'collapsible' => false])

<nav aria-label="Menu utama" class="flex-1 overflow-y-auto px-3 py-4">
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
                                title="{{ $item['label'] }}"
                                @if ($isActive) aria-current="page" @endif
                                @class([
                                    'flex min-h-11 items-center gap-3 rounded-control border-l-4 px-2 text-sm font-semibold',
                                    'border-primary bg-primary-soft text-primary' => $isActive,
                                    'border-transparent hover:bg-primary-soft' => ! $isActive,
                                ])
                            >
                                <x-ui.icon :name="$item['icon']" />
                                <span @if ($collapsible) x-show="!collapsed" @endif>{{ $item['label'] }}</span>
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
