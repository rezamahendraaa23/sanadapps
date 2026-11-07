@php
    $badges = ['hadir' => ['check', 'bg-success-soft text-success border-success'], 'izin' => ['door-open', 'bg-info-soft text-info border-info'], 'sakit' => ['thermometer', 'bg-warning-soft text-warning border-warning'], 'alpa' => ['x', 'bg-danger-soft text-danger border-danger'], 'terlambat' => ['clock', 'bg-warning-soft text-warning border-warning']];
    $letters = ['hadir' => 'H', 'izin' => 'I', 'sakit' => 'S', 'alpa' => 'A', 'terlambat' => 'T'];
@endphp

<div class="flex flex-col gap-4"
    x-data="{
        statuses: $wire.entangle('statuses'),
        key: 'sanad-absen-{{ $session->uuid }}',
        offline: ! navigator.onLine,
        search: '',
        init() {
            const draft = localStorage.getItem(this.key);
            if (draft) { this.statuses = { ...this.statuses, ...JSON.parse(draft) }; }
            this.$watch('statuses', (value) => localStorage.setItem(this.key, JSON.stringify(value)));
        },
        count(status) { return Object.values(this.statuses).filter((value) => value === status).length },
        allPresent() { for (const key in this.statuses) this.statuses[key] = 'hadir' },
    }"
    x-on:online.window="offline = false"
    x-on:offline.window="offline = true"
    x-on:attendance-saved.window="localStorage.removeItem(key)"
>
    <x-ui.card>
        <h2 class="text-lg font-semibold break-words">{{ $session->title }}</h2>
        <p class="text-sm text-text-muted">{{ $session->session_date->translatedFormat('l, j F Y') }}, mulai {{ str_replace(':', '.', substr((string) $session->start_time, 0, 5)) }}{{ $session->classroom ? ', '.$session->classroom->name : '' }}{{ $session->dormitory ? ', '.$session->dormitory->name : '' }}</p>
        @if ($session->isClosed())
            <p class="mt-1 inline-flex items-center gap-1 text-sm font-semibold text-success"><x-ui.icon name="circle-check" size="sm" />Tersimpan. Masih bisa dikoreksi.</p>
        @endif
    </x-ui.card>

    <div x-show="offline" x-cloak class="flex items-start gap-2 rounded-control bg-warning-soft p-3 text-base">
        <x-ui.icon name="wifi-off" class="mt-0.5 shrink-0" />Tidak tersambung. Perubahan tersimpan di HP ini, tekan Simpan setelah tersambung lagi.
    </div>

    @if ($scanning)
        <x-ui.card title="Siap memindai">
            <form wire:submit="submitScan" x-data x-init="$nextTick(() => $el.querySelector('input').focus())">
                <label for="field-scan" class="text-sm font-semibold">Tap kartu atau pindai QR</label>
                <input id="field-scan" type="password" autocomplete="off" wire:model="scan" class="mt-1 min-h-12 w-full rounded-control border border-text-muted bg-surface px-3 text-base">
            </form>
            @if ($lastScan)
                <p role="status" @class(['mt-3 flex items-center gap-2 rounded-control p-3 text-base font-semibold', 'bg-success-soft' => $lastScan['ok'], 'bg-danger-soft' => ! $lastScan['ok']])>
                    <x-ui.icon :name="$lastScan['ok'] ? 'circle-check' : 'circle-alert'" />
                    <span class="min-w-0 break-words">{{ $lastScan['name'] ? $lastScan['name'].': ' : '' }}{{ $lastScan['message'] }}</span>
                </p>
            @endif
        </x-ui.card>
    @endif

    <x-ui.card>
        @if ($records->isEmpty())
            <x-ui.empty-state icon="users" message="Belum ada santri aktif untuk sesi ini." class="py-2" />
        @else
            <div class="mb-3 flex flex-wrap items-end gap-2">
                <div class="min-w-0 flex-1">
                    <label for="cari-santri" class="text-sm font-semibold">Cari nama</label>
                    <input id="cari-santri" type="search" x-model="search" class="mt-1 min-h-11 w-full rounded-control border border-text-muted bg-surface px-3 text-base">
                </div>
                <x-ui.button variant="secondary" x-on:click="allPresent()">Semua Hadir</x-ui.button>
            </div>
            <ul class="flex flex-col">
                @foreach ($records as $record)
                    <li wire:key="r-{{ $record->uuid }}" class="flex flex-wrap items-center justify-between gap-2 border-b border-border py-2 last:border-b-0" x-show="@js(mb_strtolower($record->student->name)).includes(search.toLowerCase())">
                        <span class="min-w-0 flex-1 text-base break-words">{{ $record->student->name }}</span>
                        <span role="radiogroup" aria-label="Status {{ $record->student->name }}" class="flex shrink-0 gap-1">
                            @foreach ($letters as $status => $letter)
                                <button type="button" role="radio" :aria-checked="(statuses[@js($record->uuid)] === @js($status)).toString()" aria-label="{{ $labels[$status] }}" title="{{ $labels[$status] }}"
                                    x-on:click="statuses[@js($record->uuid)] = @js($status)"
                                    :class="statuses[@js($record->uuid)] === @js($status) ? @js($badges[$status][1]) : 'border-border text-text-muted'"
                                    class="inline-flex size-11 items-center justify-center rounded-control border text-sm font-semibold">{{ $letter }}</button>
                            @endforeach
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <div @class(['sticky z-[5] rounded-card border border-border bg-surface p-3 shadow-sm', 'bottom-20' => ! auth()->user()->can('academic.manage'), 'bottom-2' => auth()->user()->can('academic.manage')])>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="font-tabular text-sm" aria-live="polite">
                <span>H <strong x-text="count('hadir')"></strong></span>
                <span class="ml-2">I <strong x-text="count('izin')"></strong></span>
                <span class="ml-2">S <strong x-text="count('sakit')"></strong></span>
                <span class="ml-2">A <strong x-text="count('alpa')"></strong></span>
                <span class="ml-2">T <strong x-text="count('terlambat')"></strong></span>
            </p>
            <x-ui.button wire:click="save" wire:loading.attr="disabled" wire:target="save" x-bind:disabled="offline">Simpan</x-ui.button>
        </div>
    </div>
</div>
