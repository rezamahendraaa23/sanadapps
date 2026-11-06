<div>
    <x-ui.page-toolbar>
        <div class="w-full md:max-w-xs">
            <x-ui.input label="Cari modul" name="search" type="search" wire:model.live.debounce.200ms="search" />
        </div>
    </x-ui.page-toolbar>

    @if ($modules->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="search" message="Tidak ada modul yang cocok dengan pencarian ini.">
                <x-slot:action>
                    <x-ui.button variant="secondary" wire:click="$set('search', '')">Hapus pencarian</x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($modules as $module)
                <x-ui.card wire:key="module-{{ $module['key'] }}" class="flex flex-col gap-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="text-lg font-semibold">{{ $module['name'] }}</h2>
                            <p class="mt-1 text-sm text-text-muted">{{ $module['description'] }}</p>
                        </div>
                        @if ($module['enabled'])
                            <x-ui.status-badge status="active" class="shrink-0" />
                        @else
                            <span class="inline-flex shrink-0 items-center gap-1 rounded-full border border-border bg-bg px-2.5 py-0.5 text-sm font-semibold text-text-muted"><x-ui.icon name="ban" size="sm" />Mati</span>
                        @endif
                    </div>

                    @if ($module['dependency_names'])
                        <p class="text-sm text-text-muted">Butuh: {{ implode(', ', $module['dependency_names']) }}</p>
                    @endif

                    @if ($blockedKey === $module['key'])
                        <x-ui.banner tone="warning"><span role="alert">{{ $blockedMessage }}</span></x-ui.banner>
                    @endif

                    <div class="mt-auto">
                        @if ($module['key'] === 'core')
                            <p class="flex min-h-11 items-center gap-2 text-sm text-text-muted"><x-ui.icon name="lock" size="sm" />Selalu aktif</p>
                        @elseif ($module['enabled'])
                            <x-ui.button variant="secondary" class="w-full" wire:click="askDisable('{{ $module['key'] }}')">Matikan</x-ui.button>
                        @else
                            <x-ui.button class="w-full" wire:click="enable('{{ $module['key'] }}')" wire:loading.attr="disabled" wire:target="enable('{{ $module['key'] }}')">Aktifkan</x-ui.button>
                        @endif
                    </div>
                </x-ui.card>
            @endforeach
        </div>
    @endif

    <x-ui.modal name="matikan-modul" title="Matikan modul ini?">
        <p class="text-base"><strong>{{ $confirmingName }}</strong> akan dimatikan. Data tidak dihapus, menu disembunyikan dan bisa diaktifkan lagi kapan saja.</p>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button variant="danger" wire:click="confirmDisable">Matikan Modul</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
