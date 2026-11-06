<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div class="w-full md:max-w-xs">
            <x-ui.input label="Cari santri contoh" name="search" type="search" placeholder="Nama atau kode" wire:model.live.debounce.200ms="search" />
        </div>
        @unless ($failed)
            <x-ui.button variant="secondary" icon="circle-alert" wire:click="simulateFailure">Coba keadaan gagal</x-ui.button>
        @endunless
    </div>

    <div wire:loading.flex class="flex-col" wire:target="sortBy,retry,simulateFailure">
        <x-ui.skeleton label="Memuat tabel contoh" />
    </div>

    <div wire:loading.remove wire:target="sortBy,retry,simulateFailure">
        @if ($failed)
            <x-ui.error-state message="Data contoh gagal dimuat. Periksa koneksi, lalu muat ulang." code="CONTOH-500">
                <x-slot:action>
                    <x-ui.button icon="refresh-cw" wire:click="retry">Muat ulang</x-ui.button>
                </x-slot:action>
            </x-ui.error-state>
        @elseif ($rows->isEmpty())
            <x-ui.empty-state icon="search" message="Tidak ada santri contoh yang cocok dengan pencarian ini.">
                <x-slot:action>
                    <x-ui.button variant="secondary" wire:click="$set('search', '')">Hapus pencarian</x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
        @else
            <x-ui.table caption="Tagihan santri contoh">
                <thead>
                    <tr>
                        <x-ui.th sortable :direction="$sort === 'name' ? $direction : null" wire:click="sortBy('name')">Santri</x-ui.th>
                        <x-ui.th>Kode</x-ui.th>
                        <x-ui.th numeric sortable :direction="$sort === 'amount' ? $direction : null" wire:click="sortBy('amount')">Tagihan</x-ui.th>
                        <x-ui.th>Status</x-ui.th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr wire:key="{{ $row['code'] }}" class="hover:bg-primary-soft">
                            <x-ui.td label="Santri">{{ $row['name'] }}</x-ui.td>
                            <x-ui.td label="Kode">{{ $row['code'] }}</x-ui.td>
                            <x-ui.td label="Tagihan" numeric>Rp {{ number_format($row['amount'], 0, ',', '.') }}</x-ui.td>
                            <x-ui.td label="Status"><x-ui.status-badge :status="$row['status']" /></x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </div>
</div>
