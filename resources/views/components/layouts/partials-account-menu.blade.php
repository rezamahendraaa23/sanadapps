<div class="relative shrink-0" x-data="{ open: false }" x-on:keydown.escape.window="open = false" x-on:click.outside="open = false">
    <button
        type="button"
        class="inline-flex min-h-11 items-center gap-2 rounded-control px-2 hover:bg-primary-soft"
        :aria-expanded="open.toString()"
        aria-haspopup="menu"
        x-on:click="open = !open"
    >
        <span class="flex size-8 items-center justify-center rounded-full bg-primary-soft text-sm font-semibold text-primary" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
        <span class="hidden max-w-40 truncate text-sm font-semibold md:inline">{{ auth()->user()->name }}</span>
        <span class="sr-only md:hidden">Menu akun {{ auth()->user()->name }}</span>
    </button>
    <div x-show="open" x-cloak class="absolute top-full right-0 z-40 mt-1 w-64 rounded-control border border-border bg-surface p-2 shadow-md">
        <p class="truncate px-2 text-sm font-semibold">{{ auth()->user()->name }}</p>
        <p class="truncate px-2 pb-2 text-xs text-text-muted">{{ auth()->user()->getRoleNames()->map(fn ($role) => str_replace('_', ' ', $role))->join(', ') ?: 'Staf' }}</p>
        <form method="POST" action="{{ route('logout') }}" class="border-t border-border pt-2">
            @csrf
            <button type="submit" class="flex min-h-11 w-full items-center gap-2 rounded-control px-2 text-sm font-semibold text-danger hover:bg-danger-soft">
                <x-ui.icon name="log-out" />
                Keluar
            </button>
        </form>
    </div>
</div>
