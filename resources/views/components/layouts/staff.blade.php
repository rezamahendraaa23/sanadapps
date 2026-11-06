@props(['title', 'active' => 'home', 'homeUrl' => null, 'preview' => false, 'breadcrumb' => null, 'actions' => null])

@inject('navigation', \App\Support\Navigation::class)

@php($groups = $navigation->staff($homeUrl, $preview))

<x-layouts.base :title="$title">
    <div x-data="{ collapsed: $persist(false).as('sanad-sidebar-collapsed'), drawer: false }" x-on:keydown.escape.window="drawer = false">
        <aside
            class="fixed inset-y-0 left-0 z-20 hidden flex-col border-r border-border bg-surface lg:flex"
            :class="collapsed ? 'w-[72px]' : 'w-[264px]'"
        >
            <a href="{{ $homeUrl ?? route('staff.dashboard') }}" class="flex min-h-16 items-center border-b border-border px-4">
                <span x-show="collapsed"><x-ui.logo variant="mark" /></span>
                <span x-show="!collapsed"><x-ui.logo /></span>
            </a>
            <x-layouts.partials-staff-nav :groups="$groups" :active="$active" collapsible />
        </aside>

        <div x-show="drawer" x-cloak class="fixed inset-0 z-100 lg:hidden">
            <div
                class="absolute inset-0 bg-scrim"
                x-on:click="drawer = false"
                x-show="drawer"
                x-transition.opacity.duration.200ms
            ></div>
            <aside
                role="dialog"
                aria-modal="true"
                aria-label="Menu"
                class="absolute inset-y-0 left-0 flex w-[min(280px,85vw)] flex-col bg-surface shadow-lg"
                x-show="drawer"
                x-trap.noscroll="drawer"
                x-transition:enter="transition duration-200 ease-enter"
                x-transition:enter-start="-translate-x-full"
                x-transition:leave="transition duration-150 ease-exit"
                x-transition:leave-end="-translate-x-full"
                x-on:click="if ($event.target.closest('a')) drawer = false"
            >
                <div class="flex min-h-16 items-center justify-between border-b border-border px-4">
                    <x-ui.logo />
                    <button type="button" class="inline-flex size-11 items-center justify-center rounded-control hover:bg-primary-soft" aria-label="Tutup menu" x-on:click="drawer = false">
                        <x-ui.icon name="x" />
                    </button>
                </div>
                <x-layouts.partials-staff-nav :groups="$groups" :active="$active" />
            </aside>
        </div>

        <div :class="collapsed ? 'lg:pl-[72px]' : 'lg:pl-[264px]'">
            <header class="sticky top-0 z-10 flex min-h-16 items-center gap-2 border-b border-border bg-surface px-4">
                <button type="button" class="inline-flex min-h-11 items-center gap-2 rounded-control px-2 font-semibold hover:bg-primary-soft lg:hidden" x-on:click="drawer = true">
                    <x-ui.icon name="menu" />
                    Menu
                </button>
                <button
                    type="button"
                    class="hidden size-11 items-center justify-center rounded-control hover:bg-primary-soft lg:inline-flex"
                    :aria-label="collapsed ? 'Lebarkan menu samping' : 'Lipat menu samping'"
                    :aria-expanded="(!collapsed).toString()"
                    x-on:click="collapsed = !collapsed"
                >
                    <x-ui.icon name="panel-left" />
                </button>
                <div class="min-w-0 flex-1">
                    @if ($breadcrumb)
                        <div class="truncate text-xs text-text-muted">{{ $breadcrumb }}</div>
                    @endif
                    <h1 class="font-display truncate text-lg font-semibold md:text-2xl">{{ $title }}</h1>
                </div>
                @if ($actions)
                    <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
                @endif
                @auth
                    <x-layouts.partials-account-menu />
                @endauth
            </header>
            <main id="konten" tabindex="-1" class="mx-auto w-full max-w-[1280px] p-4 outline-none md:p-6">
                {{ $slot }}
            </main>
        </div>
    </div>
</x-layouts.base>
