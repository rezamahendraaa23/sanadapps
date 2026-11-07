<div class="relative shrink-0" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
    <button
        type="button"
        class="relative inline-flex size-11 items-center justify-center rounded-control hover:bg-primary-soft"
        aria-label="Notifikasi{{ $unread ? ', '.$unread.' belum dibaca' : '' }}"
        :aria-expanded="open.toString()"
        x-on:click="open = !open"
    >
        <x-ui.icon name="bell" />
        @if ($unread)
            <span class="absolute top-1 right-1 flex min-w-4 items-center justify-center rounded-full bg-danger px-1 text-xs font-semibold text-on-primary" aria-hidden="true">{{ $unread > 9 ? '9+' : $unread }}</span>
        @endif
    </button>
    <div x-show="open" x-cloak class="absolute top-full right-0 z-40 mt-1 w-[min(20rem,85vw)] rounded-control border border-border bg-surface p-2 shadow-md">
        @if ($items->isEmpty())
            <p class="p-2 text-sm text-text-muted">Belum ada notifikasi.</p>
        @else
            <ul class="flex max-h-80 flex-col overflow-y-auto">
                @foreach ($items as $item)
                    <li wire:key="notif-{{ $item->uuid }}" class="border-b border-border last:border-b-0">
                        @if ($item->link)
                            <a href="{{ $item->link }}" wire:navigate class="block min-h-11 rounded-control p-2 hover:bg-primary-soft">
                                <span @class(['block text-sm', 'font-semibold' => ! $item->read_at])>{{ $item->title }}</span>
                                @if ($item->body)<span class="block text-xs text-text-muted">{{ $item->body }}</span>@endif
                            </a>
                        @else
                            <div class="p-2">
                                <span @class(['block text-sm', 'font-semibold' => ! $item->read_at])>{{ $item->title }}</span>
                                @if ($item->body)<span class="block text-xs text-text-muted">{{ $item->body }}</span>@endif
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
            @if ($unread)
                <x-ui.button variant="tertiary" class="mt-1 w-full" wire:click="markAllRead">Tandai semua dibaca</x-ui.button>
            @endif
        @endif
    </div>
</div>
