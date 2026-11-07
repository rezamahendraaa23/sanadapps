@props(['title', 'role', 'active' => 'home', 'homeUrl' => null, 'preview' => false, 'actions' => null])

@inject('navigation', \App\Support\Navigation::class)
@inject('identity', \App\Support\SchoolIdentity::class)

@php($items = $navigation->portal($role, $homeUrl, $preview))

<x-layouts.base :title="$title">
    <header class="sticky top-0 z-10 border-b border-border bg-surface pt-[env(safe-area-inset-top)]">
        <div class="mx-auto flex min-h-14 w-full max-w-[480px] items-center gap-3 px-4">
            <x-ui.logo variant="mark" :size="32" />
            <div class="min-w-0 flex-1">
                <h1 class="truncate text-lg font-semibold">{{ $title }}</h1>
                <p class="truncate text-xs text-text-muted">{{ $identity->displayName() }}</p>
            </div>
            {{ $actions }}
            @auth
                @if (auth()->user()->type === 'guardian')
                    @php($unread = auth()->user()->inAppNotifications()->whereNull('read_at')->count())
                    <a href="{{ route('wali.more') }}" wire:navigate class="relative inline-flex size-11 items-center justify-center rounded-control text-text-muted hover:bg-primary-soft" aria-label="Notifikasi{{ $unread ? ', '.$unread.' belum dibaca' : '' }}">
                        <x-ui.icon name="bell" />
                        @if ($unread)
                            <span class="absolute top-1 right-1 flex min-w-5 items-center justify-center rounded-full bg-danger px-1 text-xs font-semibold text-on-primary" aria-hidden="true">{{ $unread > 9 ? '9+' : $unread }}</span>
                        @endif
                    </a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex size-11 items-center justify-center rounded-control text-text-muted hover:bg-primary-soft" aria-label="Keluar">
                        <x-ui.icon name="log-out" />
                    </button>
                </form>
            @endauth
        </div>
    </header>

    <main id="konten" tabindex="-1" class="mx-auto flex w-full max-w-[480px] flex-col gap-4 p-4 pb-[calc(5.5rem+env(safe-area-inset-bottom))] outline-none">
        {{ $slot }}
    </main>

    <nav aria-label="Navigasi portal" class="no-print fixed inset-x-0 bottom-0 z-10 border-t border-border bg-surface pb-[env(safe-area-inset-bottom)]">
        <ul class="mx-auto flex w-full max-w-[480px]">
            @foreach ($items as $item)
                @php($isActive = $item['key'] === $active)
                <li class="min-w-11 flex-auto">
                    @if ($item['url'])
                        <a
                            href="{{ $item['url'] }}"
                            wire:navigate
                            @if ($isActive) aria-current="page" @endif
                            @class([
                                'flex min-h-16 flex-col items-center justify-center gap-1 px-0.5 text-center text-xs',
                                'font-semibold text-primary' => $isActive,
                                'text-text-muted' => ! $isActive,
                            ])
                        >
                            <x-ui.icon :name="$item['icon']" />
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @else
                        <span class="flex min-h-16 flex-col items-center justify-center gap-1 px-0.5 text-center text-xs text-text-muted" title="{{ $item['label'] }} belum dibuat">
                            <x-ui.icon :name="$item['icon']" />
                            <span>{{ $item['label'] }}<span class="sr-only"> (belum dibuat)</span></span>
                        </span>
                    @endif
                </li>
            @endforeach
        </ul>
    </nav>
</x-layouts.base>
