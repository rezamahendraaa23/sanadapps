<div class="flex flex-col gap-4" wire:poll.10s>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-base text-text-muted">Buka <strong class="break-all text-text">{{ url('/display') }}</strong> di TV atau laptop, lalu pasangkan dengan kode yang muncul.</p>
        <div class="flex flex-wrap gap-2">
            <x-ui.button variant="secondary" icon="download" :href="url('/display?pasang=1')" target="_blank">Pasang Aplikasi Layar</x-ui.button>
            <x-ui.button icon="plus" wire:click="startClaim">Pasang Layar</x-ui.button>
        </div>
    </div>

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
                @if ($charts !== [])
                    @php($shownCharts = $screen->org_charts ?? [])
                    <div class="flex flex-wrap items-center gap-2 rounded-control bg-bg p-2" role="group" aria-label="Struktur pesantren di {{ $screen->name }}">
                        <span class="flex items-center gap-1 px-1 text-sm font-semibold"><x-ui.icon name="network" size="sm" class="text-primary" />Struktur</span>
                        @foreach ($charts as $chartKey => $chartName)
                            @php($on = in_array($chartKey, $shownCharts, true))
                            <button type="button" wire:key="tg-{{ $screen->uuid }}-{{ $chartKey }}" wire:click="toggleChart('{{ $screen->uuid }}', '{{ $chartKey }}')" wire:loading.attr="disabled" aria-pressed="{{ $on ? 'true' : 'false' }}"
                                @class(['inline-flex min-h-11 items-center gap-2 rounded-full border px-3 text-sm font-semibold transition-colors', 'border-primary bg-primary text-on-primary' => $on, 'border-border bg-surface text-text-muted hover:border-primary' => ! $on])>
                                <span @class(['relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors', 'bg-on-primary/30' => $on, 'bg-border' => ! $on]) aria-hidden="true"><span @class(['absolute top-0.5 size-4 rounded-full bg-surface shadow transition-all', 'left-[1.125rem]' => $on, 'left-0.5' => ! $on])></span></span>
                                {{ $chartName }}<span class="sr-only">{{ $on ? ', tampil' : ', tidak tampil' }}</span>
                            </button>
                        @endforeach
                        @if ($shownCharts !== [])
                            <button type="button" wire:click="toggleOrgMode('{{ $screen->uuid }}')" wire:loading.attr="disabled" aria-pressed="{{ $screen->org_mode === 'always' ? 'true' : 'false' }}"
                                class="inline-flex min-h-11 items-center gap-1 rounded-full px-3 text-sm font-semibold text-primary hover:bg-primary-soft">
                                <x-ui.icon :name="$screen->org_mode === 'always' ? 'pin' : 'repeat'" size="sm" />{{ $screen->org_mode === 'always' ? 'Tampil terus' : 'Bergiliran' }}<span class="sr-only">, ketuk untuk mengganti</span>
                            </button>
                        @else
                            <span class="px-1 text-sm text-text-muted">Tidak tampil di layar ini.</span>
                        @endif
                    </div>
                @endif
                @if ($last = $lastCommands[$screen->id] ?? null)
                    <p class="text-sm text-text-muted" aria-live="polite">Perintah terakhir: {{ ['reload' => 'muat ulang', 'test' => 'layar tes', 'dim' => 'redupkan', 'undim' => 'terangkan'][$last->command] ?? $last->command }}, {{ $last->acked_at ? 'sudah diterima layar' : 'menunggu layar' }}.@if ($dimmed[$screen->id] ?? false) Layar sedang redup.@endif</p>
                @endif
                <div class="flex flex-wrap gap-2">
                    <x-ui.button variant="secondary" icon="pencil" wire:click="editScreen('{{ $screen->uuid }}')">Ubah<span class="sr-only"> {{ $screen->name }}</span></x-ui.button>
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

    <x-ui.modal name="ubah-layar" title="Ubah layar" class="md:max-w-2xl">
        {{-- Hanya dirender saat mengubah, agar id isian tidak kembar dengan popup Pasang Layar. --}}
        @if ($editing)
            @include('masjid.partials.screen-form', ['formId' => 'form-ubah-layar', 'submit' => 'updateScreen', 'withCode' => false])
        @endif
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button type="submit" form="form-ubah-layar" wire:loading.attr="disabled" wire:target="updateScreen">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal name="pasang-layar" title="Pasang layar baru" class="md:max-w-2xl">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-control bg-bg p-3">
            <p class="text-sm">Buka <strong class="break-all">{{ url('/display') }}</strong> di TV atau laptop. Kode 6 angka muncul di sana.</p>
            <x-ui.button variant="secondary" icon="monitor" :href="url('/display')" target="_blank">Buka Layar</x-ui.button>
        </div>
        @unless ($editing)
            @include('masjid.partials.screen-form', ['formId' => 'form-pasang', 'submit' => 'claim', 'withCode' => true])
        @endunless
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button type="submit" form="form-pasang" wire:loading.attr="disabled" wire:target="claim">Pasangkan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
