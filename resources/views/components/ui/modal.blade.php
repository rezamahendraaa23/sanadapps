@props(['name', 'title', 'footer' => null])

{{-- Layar kecil tampil sebagai bottom sheet, mulai 768 px sebagai modal di tengah. --}}
<div
    x-data="{ open: false, isMine: (detail) => [detail].flat().includes(@js($name)) }"
    x-on:open-modal.window="if (isMine($event.detail)) open = true"
    x-on:close-modal.window="if (isMine($event.detail)) open = false"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-100 flex items-end justify-center md:items-center md:p-6"
>
    <div
        class="absolute inset-0 bg-scrim"
        x-on:click="open = false"
        x-show="open"
        x-transition:enter="transition-opacity duration-250 ease-enter"
        x-transition:enter-start="opacity-0"
        x-transition:leave="transition-opacity duration-175 ease-exit"
        x-transition:leave-end="opacity-0"
    ></div>
    <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="modal-{{ $name }}-title"
        x-show="open"
        x-trap.noscroll="open"
        x-transition:enter="transition duration-250 ease-enter"
        x-transition:enter-start="translate-y-6 opacity-0"
        x-transition:leave="transition duration-175 ease-exit"
        x-transition:leave-end="translate-y-6 opacity-0"
        {{ $attributes->class('relative flex max-h-[90dvh] w-full flex-col rounded-t-modal bg-surface shadow-lg md:max-w-lg md:rounded-b-modal') }}
    >
        <header class="flex items-start justify-between gap-4 p-4 md:p-6 md:pb-4">
            <h2 id="modal-{{ $name }}-title" class="text-lg font-semibold">{{ $title }}</h2>
            <button
                type="button"
                class="-m-2 inline-flex size-11 shrink-0 items-center justify-center rounded-control hover:bg-primary-soft"
                aria-label="Tutup"
                x-on:click="open = false"
            >
                <x-ui.icon name="x" />
            </button>
        </header>
        <div class="overflow-y-auto px-4 pb-4 md:px-6">{{ $slot }}</div>
        @if ($footer)
            <footer class="flex flex-col-reverse gap-2 border-t border-border p-4 pb-[max(1rem,env(safe-area-inset-bottom))] md:flex-row md:justify-end md:px-6">
                {{ $footer }}
            </footer>
        @endif
    </div>
</div>
