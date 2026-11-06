<div
    class="relative hidden lg:block"
    x-data="{ open: false }"
    x-on:click.outside="open = false"
    x-on:keydown.escape.window="open = false"
    x-on:keydown.window="if ($event.key === '/' && ! ['INPUT', 'TEXTAREA', 'SELECT'].includes($event.target.tagName)) { $event.preventDefault(); $refs.search.focus(); }"
>
    <label for="pencarian-cepat" class="sr-only">Cari santri atau NIS. Tekan garis miring untuk fokus.</label>
    <input
        id="pencarian-cepat"
        x-ref="search"
        type="search"
        placeholder="Cari santri atau NIS ( / )"
        autocomplete="off"
        wire:model.live.debounce.250ms="query"
        x-on:focus="open = true"
        x-on:input="open = true"
        class="min-h-11 w-72 rounded-control border border-text-muted bg-surface px-3 text-sm placeholder:text-text-muted"
    >
    <div x-show="open && $wire.query.trim().length >= 2" x-cloak class="absolute top-full right-0 z-40 mt-1 w-80 rounded-control border border-border bg-surface p-2 shadow-md">
        <ul>
            @foreach ($results as $student)
                <li wire:key="hasil-{{ $student->uuid }}">
                    <a href="{{ route('staff.students.show', $student) }}" class="flex min-h-11 flex-col justify-center rounded-control px-2 hover:bg-primary-soft">
                        <span class="text-sm font-semibold">{{ $student->name }}</span>
                        <span class="text-xs text-text-muted">NIS {{ $student->nis }}{{ $student->classroom ? ' · '.$student->classroom->name : '' }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
        @if ($searched && $results->isEmpty())
            <p class="p-2 text-sm text-text-muted">Tidak ada santri yang cocok.</p>
        @endif
    </div>
</div>
