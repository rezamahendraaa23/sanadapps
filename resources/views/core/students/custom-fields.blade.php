<div>
    <x-ui.page-toolbar>
        <p class="max-w-prose text-base">Data tambahan yang ingin dicatat untuk setiap santri, misalnya golongan darah. Field tampil di form dan detail santri.</p>
        <x-ui.button icon="plus" wire:click="create">Tambah Field</x-ui.button>
    </x-ui.page-toolbar>

    <x-ui.card>
        @if ($fields->isEmpty())
            <x-ui.empty-state icon="list-checks" message="Belum ada field tambahan." />
        @else
            <x-ui.table caption="Field tambahan santri">
                <thead><tr><x-ui.th>Nama</x-ui.th><x-ui.th>Jenis</x-ui.th><x-ui.th>Wajib</x-ui.th><x-ui.th>Status</x-ui.th><x-ui.th>Aksi</x-ui.th></tr></thead>
                <tbody>
                    @foreach ($fields as $field)
                        <tr wire:key="field-{{ $field->uuid }}">
                            <x-ui.td label="Nama">{{ $field->label }}</x-ui.td>
                            <x-ui.td label="Jenis">{{ ['text' => 'Teks', 'number' => 'Angka', 'select' => 'Pilihan', 'date' => 'Tanggal'][$field->type] }}</x-ui.td>
                            <x-ui.td label="Wajib">{{ $field->is_required ? 'Ya' : 'Tidak' }}</x-ui.td>
                            <x-ui.td label="Status">{{ $field->is_active ? 'Dipakai' : 'Disembunyikan' }}</x-ui.td>
                            <x-ui.td label="Aksi"><x-ui.button variant="tertiary" wire:click="edit('{{ $field->uuid }}')">Ubah<span class="sr-only"> {{ $field->label }}</span></x-ui.button></x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>

    <x-ui.modal name="form-field" :title="$editingId ? 'Ubah field' : 'Tambah field'">
        <form id="form-field" wire:submit="save" class="flex flex-col gap-4">
            <x-ui.input label="Nama field" name="label" required wire:model="label" />
            <x-ui.select label="Jenis" name="type" required :options="['text' => 'Teks', 'number' => 'Angka', 'select' => 'Pilihan', 'date' => 'Tanggal']" wire:model.live="type" />
            @if ($type === 'select')
                <x-ui.input label="Pilihan" name="options" required help="Pisahkan dengan koma, contoh: A, B, AB, O." wire:model="options" />
            @endif
            <label class="flex min-h-11 items-center gap-3 text-base"><input type="checkbox" wire:model="is_required" class="size-5 accent-primary">Wajib diisi</label>
            <label class="flex min-h-11 items-center gap-3 text-base"><input type="checkbox" wire:model="is_active" class="size-5 accent-primary">Tampilkan di form</label>
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button type="submit" form="form-field" wire:loading.attr="disabled" wire:target="save">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
