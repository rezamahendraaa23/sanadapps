<div class="flex flex-col gap-4">
    @include('wallet.partials.card-nav')

    @if ($newKey)
        <x-ui.banner tone="warning" x-data="{ copied: false }">
            <p class="font-semibold">Simpan kunci ini sekarang. Setelah ditutup, kunci tidak bisa dilihat lagi.</p>
            <code class="mt-2 block rounded-control bg-surface p-2 text-sm break-all">{{ $newKey }}</code>
            <div class="mt-2 flex flex-wrap gap-2">
                <x-ui.button variant="secondary" icon="copy" x-on:click="navigator.clipboard?.writeText(@js($newKey)); copied = true"><span x-text="copied ? 'Tersalin' : 'Salin'">Salin</span></x-ui.button>
                <x-ui.button variant="tertiary" wire:click="dismissKey">Sudah Disimpan</x-ui.button>
            </div>
        </x-ui.banner>
    @endif

    <x-ui.card title="Perangkat pemindai">
        <x-slot:actions>
            <x-ui.button icon="plus" x-data x-on:click="$dispatch('open-modal', 'tambah-perangkat')">Tambah Perangkat</x-ui.button>
        </x-slot:actions>
        @if ($devices->isEmpty())
            <x-ui.empty-state icon="scan-line" message="Belum ada perangkat. Kasir di browser tidak memerlukan perangkat terdaftar." class="py-2" />
        @else
            <x-ui.table caption="Daftar perangkat">
                <thead><tr><x-ui.th>Nama</x-ui.th><x-ui.th>Akses</x-ui.th><x-ui.th>Terakhir aktif</x-ui.th><x-ui.th>Status</x-ui.th><x-ui.th>Aksi</x-ui.th></tr></thead>
                <tbody>
                    @foreach ($devices as $device)
                        <tr wire:key="alat-{{ $device->uuid }}">
                            <x-ui.td label="Nama"><span class="font-semibold">{{ $device->name }}</span><span class="block text-sm text-text-muted">{{ $device->location }}</span></x-ui.td>
                            <x-ui.td label="Akses">{{ collect($device->scopes)->map(fn ($scope) => $scopeLabels[$scope] ?? $scope)->join(', ') }}</x-ui.td>
                            <x-ui.td label="Terakhir aktif">{{ $device->last_seen_at?->setTimezone(config('sanad.display_timezone'))->translatedFormat('j M, H.i') ?? 'Belum pernah' }}</x-ui.td>
                            <x-ui.td label="Status"><x-ui.status-badge :status="$device->is_active ? 'active' : 'blocked'" /></x-ui.td>
                            <x-ui.td label="Aksi">
                                <div class="flex flex-wrap gap-1">
                                    <x-ui.button variant="tertiary" wire:click="rotate('{{ $device->uuid }}')" wire:confirm="Buat kunci baru? Kunci lama langsung tidak berlaku.">Ganti Kunci</x-ui.button>
                                    <x-ui.button variant="tertiary" wire:click="toggle('{{ $device->uuid }}')" wire:confirm="{{ $device->is_active ? 'Cabut akses perangkat ini?' : 'Aktifkan lagi perangkat ini?' }}">{{ $device->is_active ? 'Cabut' : 'Aktifkan' }}</x-ui.button>
                                </div>
                            </x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>

    <x-ui.modal name="tambah-perangkat" title="Tambah perangkat">
        <div class="flex flex-col gap-3">
            <x-ui.input label="Nama" name="name" required wire:model="name" />
            <x-ui.input label="Lokasi" name="location" wire:model="location" />
            <x-ui.select label="Jenis" name="type" :options="['reader_usb' => 'Reader kartu', 'camera_qr' => 'Kamera QR']" wire:model="type" />
            <fieldset>
                <legend class="text-sm font-semibold">Akses</legend>
                @foreach ($scopeLabels as $key => $label)
                    <label class="flex min-h-11 items-center gap-3 text-base">
                        <input type="checkbox" value="{{ $key }}" wire:model="scopes" class="size-5 accent-primary">{{ $label }}
                    </label>
                @endforeach
                @error('scopes')
                    <p role="alert" class="flex items-start gap-1 text-sm text-danger"><x-ui.icon name="circle-alert" size="sm" class="mt-0.5" />{{ $message }}</p>
                @enderror
            </fieldset>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button wire:click="save" wire:loading.attr="disabled" wire:target="save">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
