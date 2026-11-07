<div class="flex flex-col gap-4">
    <x-ui.card>
        <x-ui.page-toolbar>
            <div class="min-w-0 flex-1 md:max-w-xs"><x-ui.select label="Asrama" name="dormitory" placeholder="Semua" :options="$dormitories" wire:model.live="dormitory" /></div>
            <x-ui.button icon="plus" x-data x-on:click="$dispatch('open-modal', 'tambah-barang')">Tambah Barang</x-ui.button>
        </x-ui.page-toolbar>
    </x-ui.card>

    <x-ui.card>
        @if ($items->isEmpty())
            <x-ui.empty-state icon="package" message="Belum ada barang." class="py-2" />
        @else
            <x-ui.table caption="Barang">
                <thead><tr><x-ui.th>Barang</x-ui.th><x-ui.th>Asrama</x-ui.th><x-ui.th numeric>Jumlah</x-ui.th><x-ui.th>Mutasi terakhir</x-ui.th><x-ui.th><span class="sr-only">Mutasi</span></x-ui.th></tr></thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr wire:key="i-{{ $item->uuid }}">
                            <x-ui.td label="Barang"><span class="font-semibold break-words">{{ $item->name }}</span>@if ($item->location)<span class="block text-sm text-text-muted break-words">{{ $item->location }}</span>@endif</x-ui.td>
                            <x-ui.td label="Asrama"><span class="break-words">{{ $item->dormitory?->name ?? '-' }}</span></x-ui.td>
                            <x-ui.td label="Jumlah" class="text-right"><span class="font-tabular font-semibold">{{ $item->quantity }}</span></x-ui.td>
                            <x-ui.td label="Mutasi terakhir">
                                @foreach ($item->movements as $movement)
                                    <span class="block text-sm">{{ $types[$movement->type] }} <span class="font-tabular">{{ $movement->quantity }}</span>{{ $movement->note ? ': '.$movement->note : '' }}</span>
                                @endforeach
                            </x-ui.td>
                            <x-ui.td label="Mutasi"><x-ui.button variant="tertiary" wire:click="askMove('{{ $item->uuid }}')">Catat Mutasi<span class="sr-only"> {{ $item->name }}</span></x-ui.button></x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>

    <x-ui.modal name="tambah-barang" title="Tambah barang">
        <div class="flex flex-col gap-3">
            <x-ui.select label="Asrama" name="new_dormitory" required placeholder="Pilih" :options="$dormitories" wire:model="new_dormitory" />
            <x-ui.input label="Nama barang" name="name" required wire:model="name" />
            <x-ui.input label="Kategori" name="category" wire:model="category" />
            <x-ui.input label="Jumlah awal" name="quantity" inputmode="numeric" required wire:model="quantity" />
            <x-ui.input label="Lokasi" name="location" wire:model="location" />
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button wire:click="save" wire:loading.attr="disabled" wire:target="save">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal name="mutasi-barang" title="Catat mutasi">
        <div class="flex flex-col gap-3">
            <x-ui.select label="Jenis" name="move_type" required :options="$types" wire:model="move_type" />
            <x-ui.input label="Jumlah" name="move_quantity" inputmode="numeric" required help="Penyesuaian boleh negatif." wire:model="move_quantity" />
            <x-ui.input label="Catatan" name="move_note" wire:model="move_note" />
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button wire:click="move" wire:loading.attr="disabled" wire:target="move">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
