@php($canManage = auth()->user()->can('create', \App\Modules\Core\Models\Guardian::class))

<div>
    <x-ui.page-toolbar>
        <div class="w-full md:max-w-sm">
            <x-ui.input label="Cari nama atau nomor" name="search" type="search" wire:model.live.debounce.300ms="search" />
        </div>
        @if ($canManage)
            <x-ui.button icon="plus" wire:click="create">Tambah Wali</x-ui.button>
        @endif
    </x-ui.page-toolbar>

    <x-ui.card>
        @if ($guardians->isEmpty())
            <x-ui.empty-state icon="contact" :message="$search !== '' ? 'Tidak ada wali yang cocok dengan pencarian ini.' : 'Belum ada wali. Wali juga otomatis terdaftar saat mengimpor santri.'" />
        @else
            <x-ui.table caption="Daftar wali">
                <thead><tr><x-ui.th>Nama</x-ui.th><x-ui.th>WhatsApp</x-ui.th><x-ui.th>Pekerjaan</x-ui.th><x-ui.th numeric>Anak</x-ui.th>@if ($canManage)<x-ui.th>Aksi</x-ui.th>@endif</tr></thead>
                <tbody>
                    @foreach ($guardians as $guardian)
                        <tr wire:key="guardian-{{ $guardian->uuid }}">
                            <x-ui.td label="Nama">{{ $guardian->name }}</x-ui.td>
                            <x-ui.td label="WhatsApp"><span class="font-tabular">{{ $guardian->phone_wa }}</span></x-ui.td>
                            <x-ui.td label="Pekerjaan">{{ $guardian->occupation ?? '-' }}</x-ui.td>
                            <x-ui.td label="Anak" numeric>{{ $guardian->students_count }}</x-ui.td>
                            @if ($canManage)
                                <x-ui.td label="Aksi"><x-ui.button variant="tertiary" wire:click="edit('{{ $guardian->uuid }}')">Ubah<span class="sr-only"> {{ $guardian->name }}</span></x-ui.button></x-ui.td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
            <div class="mt-4">{{ $guardians->links() }}</div>
        @endif
    </x-ui.card>

    <x-ui.modal name="form-wali" :title="$editingId ? 'Ubah wali' : 'Tambah wali'">
        <form id="form-wali" wire:submit="save" class="flex flex-col gap-4">
            <x-ui.input label="Nama" name="name" required wire:model="name" />
            <x-ui.input label="Nomor WhatsApp" name="phone_wa" type="tel" inputmode="tel" required wire:model="phone_wa" />
            <x-ui.input label="Email" name="email" type="email" wire:model="email" />
            <x-ui.input label="Pekerjaan" name="occupation" wire:model="occupation" />
            <x-ui.textarea label="Alamat" name="address" wire:model="address" />
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button type="submit" form="form-wali" wire:loading.attr="disabled" wire:target="save">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
