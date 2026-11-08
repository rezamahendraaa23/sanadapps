<div class="flex flex-col gap-4" wire:poll.10s>
    <x-ui.card title="Pasang layar baru">
        <p class="text-base">Buka <strong class="break-all">{{ url('/display') }}</strong> di TV atau laptop. Kode 6 angka muncul di layar, lalu ketik di sini.</p>
        <div class="mt-2 flex flex-wrap gap-2">
            <x-ui.button variant="secondary" icon="monitor" :href="url('/display')" target="_blank">Buka Layar</x-ui.button>
            <x-ui.button variant="secondary" icon="download" :href="url('/display?pasang=1')" target="_blank">Pasang Aplikasi Layar</x-ui.button>
        </div>
        <p class="mt-1 text-sm text-text-muted">Tombol pasangnya muncul di halaman layar. Butuh Chrome atau Edge; di server harus HTTPS.</p>
        <form wire:submit="claim" class="mt-3 grid gap-3 md:grid-cols-3">
            <x-ui.input label="Kode di layar" name="code" inputmode="numeric" autocomplete="off" required wire:model="code" />
            <x-ui.input label="Nama layar" name="name" required help="Contoh: TV Masjid Utama." wire:model="name" />
            <x-ui.input label="Lokasi" name="location" wire:model="location" />
            <x-ui.select label="Profil" name="profile" :options="$profiles" wire:model="profile" />
            <x-ui.select label="Orientasi" name="orientation" :options="['landscape' => 'Mendatar', 'portrait' => 'Tegak']" wire:model="orientation" />
            <x-ui.select label="Pola pengumuman" name="content_pattern" :options="['rotate' => 'Bergilir satu per satu', 'list' => 'Daftar, tiga sekaligus']" wire:model="content_pattern" />
            <x-ui.input label="Redup mulai" name="dim_from" type="time" help="Kosongkan bila tidak perlu." wire:model="dim_from" />
            <x-ui.input label="Redup sampai" name="dim_until" type="time" wire:model="dim_until" />
            <x-ui.button type="submit" class="md:col-span-3 md:justify-self-start" wire:loading.attr="disabled" wire:target="claim">Pasangkan</x-ui.button>
        </form>
    </x-ui.card>

    <x-ui.card title="Layar terpasang">
        @forelse ($screens as $screen)
            <div wire:key="s-{{ $screen->uuid }}" class="flex flex-col gap-2 border-b border-border py-3 last:border-b-0">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-semibold break-words">{{ $screen->name }} <span class="text-sm font-normal text-text-muted">{{ $profiles[$screen->profile] }}{{ $screen->location ? ', '.$screen->location : '' }}</span></p>
                        <p class="text-sm">{{ $screen->isOnline() ? 'Online' : 'Offline' }}{{ $screen->last_seen_at ? ', terakhir terlihat '.$screen->last_seen_at->setTimezone(config('sanad.display_timezone'))->translatedFormat('j M H.i') : ', belum pernah terhubung' }}{{ $screen->clock_offset_ms !== null && abs($screen->clock_offset_ms) > 2000 ? ', jam perangkat meleset '.round($screen->clock_offset_ms / 1000).' detik (sudah dikoreksi)' : '' }}</p>
                    </div>
                    <span @class(['inline-flex shrink-0 items-center gap-1 text-sm font-semibold', 'text-success' => $screen->isOnline(), 'text-danger' => ! $screen->isOnline()])><x-ui.icon :name="$screen->isOnline() ? 'wifi' : 'wifi-off'" size="sm" />{{ $screen->isOnline() ? 'Online' : 'Offline' }}</span>
                </div>
                @if ($last = $lastCommands[$screen->id] ?? null)
                    <p class="text-sm text-text-muted" aria-live="polite">Perintah terakhir: {{ ['reload' => 'muat ulang', 'test' => 'layar tes', 'dim' => 'redupkan', 'undim' => 'terangkan'][$last->command] ?? $last->command }}, {{ $last->acked_at ? 'sudah diterima layar' : 'menunggu layar' }}.@if ($dimmed[$screen->id] ?? false) Layar sedang redup.@endif</p>
                @endif
                <div class="flex flex-wrap gap-2">
                    <x-ui.button variant="secondary" icon="monitor" :href="route('staff.masjid.preview', $screen)" target="_blank">Pratinjau</x-ui.button>
                    <x-ui.button variant="tertiary" wire:click="command('{{ $screen->uuid }}', 'reload')">Muat Ulang<span class="sr-only"> {{ $screen->name }}</span></x-ui.button>
                    <x-ui.button variant="tertiary" wire:click="command('{{ $screen->uuid }}', 'test')">Layar Tes<span class="sr-only"> {{ $screen->name }}</span></x-ui.button>
                    <x-ui.button variant="tertiary" wire:click="command('{{ $screen->uuid }}', 'dim')">Redupkan<span class="sr-only"> {{ $screen->name }}</span></x-ui.button>
                    <x-ui.button variant="tertiary" wire:click="command('{{ $screen->uuid }}', 'undim')">Terangkan<span class="sr-only"> {{ $screen->name }}</span></x-ui.button>
                    @can('masjid.settings')
                        <x-ui.button variant="danger" wire:click="revoke('{{ $screen->uuid }}')" wire:confirm="Cabut layar {{ $screen->name }}? Layar harus dipasangkan ulang.">Cabut<span class="sr-only"> {{ $screen->name }}</span></x-ui.button>
                    @endcan
                </div>
            </div>
        @empty
            <x-ui.empty-state icon="monitor" message="Belum ada layar terpasang." class="py-2" />
        @endforelse
    </x-ui.card>
</div>
