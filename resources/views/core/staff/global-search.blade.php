<div
    class="relative hidden lg:block"
    x-data="{
        open: false,
        links() { return [...this.$refs.panel.querySelectorAll('a')] },
        move(step) {
            const links = this.links();
            if (! links.length) return;
            const index = links.indexOf(document.activeElement);
            links[(index + step + links.length) % links.length].focus();
        },
    }"
    x-on:click.outside="open = false"
    x-on:keydown.escape.window="open = false"
    x-on:keydown.window="
        const slash = $event.key === '/' && ! ['INPUT', 'TEXTAREA', 'SELECT'].includes($event.target.tagName);
        {{-- Ctrl+F atau Cmd+F membuka pencarian SANAD; bila kotak cari tersembunyi (layar kecil), pencarian bawaan browser tetap jalan. --}}
        const find = ($event.ctrlKey || $event.metaKey) && ! $event.altKey && ! $event.shiftKey && $event.key.toLowerCase() === 'f' && $refs.search.offsetParent !== null;
        if (slash || find) { $event.preventDefault(); $refs.search.focus(); $refs.search.select(); open = true; }
    "
    x-on:keydown.down.prevent="open = true; move(1)"
    x-on:keydown.up.prevent="move(-1)"
>
    <label for="pencarian-cepat" class="sr-only">Cari menu, santri, wali, tagihan, atau surat. Tekan Ctrl+F atau garis miring untuk fokus.</label>
    <div class="relative">
        <x-ui.icon name="search" size="sm" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-text-muted" />
        <input
            id="pencarian-cepat"
            x-ref="search"
            type="search"
            placeholder="Cari apa saja (Ctrl+F)"
            autocomplete="off"
            wire:model.live.debounce.250ms="query"
            x-on:focus="open = true"
            x-on:input="open = true"
            x-on:keydown.enter.prevent="links()[0]?.click()"
            class="min-h-11 w-80 rounded-control border border-text-muted bg-surface pr-3 pl-9 text-sm placeholder:text-text-muted"
        >
    </div>
    <div x-ref="panel" x-show="open && $wire.query.trim().length >= 2" x-cloak class="absolute top-full right-0 z-40 mt-1 max-h-[70vh] w-[26rem] overflow-y-auto rounded-card border border-border bg-surface p-2 shadow-lg">
        @php
            $groups = [
                ['Menu', $menus, fn ($row) => [$row['url'], $row['label'], $row['group'], $row['icon']]],
                ['Santri', $results, fn ($row) => [route('staff.students.show', $row), $row->name, 'NIS '.$row->nis.($row->classroom ? ', '.$row->classroom->name : ''), 'user']],
                ['Wali', $guardians, fn ($row) => [route('staff.guardians.index', ['cari' => $row->name]), $row->name, $row->phone_wa ?: 'Tanpa nomor', 'contact']],
                ['Tagihan', $invoices, fn ($row) => [route('staff.invoices.show', $row), $row->invoice_no, (string) $row->student?->name, 'receipt-text']],
                ['Surat', $letters, fn ($row) => [route('staff.letters.index', ['tab' => 'arsip', 'cari' => $row->letter_no]), $row->letter_no, $row->subject, 'mail']],
            ];
        @endphp
        @foreach ($groups as [$title, $rows, $map])
            @if ($rows->isNotEmpty())
                <p class="px-2 pt-2 pb-1 text-xs font-semibold tracking-wide text-text-muted uppercase">{{ $title }}</p>
                <ul>
                    @foreach ($rows as $row)
                        @php
                            [$url, $label, $sub, $icon] = $map($row);
                        @endphp
                        <li wire:key="cari-{{ $title }}-{{ $loop->index }}-{{ md5($url) }}">
                            <a href="{{ $url }}" wire:navigate class="flex min-h-11 items-center gap-3 rounded-control px-2 hover:bg-primary-soft focus:bg-primary-soft focus:outline-none">
                                <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-control bg-bg text-primary"><x-ui.icon :name="$icon" size="sm" /></span>
                                <span class="min-w-0">
                                    <span @class(['block truncate text-sm font-semibold', 'font-tabular' => in_array($title, ['Tagihan', 'Surat'], true)])>{{ $label }}</span>
                                    <span class="block truncate text-xs text-text-muted">{{ $sub }}</span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endforeach
        @if ($searched && $menus->isEmpty() && $results->isEmpty() && $guardians->isEmpty() && $invoices->isEmpty() && $letters->isEmpty())
            <p class="p-2 text-sm text-text-muted">Tidak ada yang cocok.</p>
        @endif
        <p class="mt-1 border-t border-border px-2 pt-2 text-xs text-text-muted">Panah atas dan bawah untuk memilih, Enter untuk membuka.</p>
    </div>
</div>
