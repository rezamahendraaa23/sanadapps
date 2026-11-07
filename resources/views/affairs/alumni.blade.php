<div class="flex flex-col gap-4">
    <x-ui.card>
        <x-ui.page-toolbar>
            <div class="min-w-0 flex-1"><x-ui.input label="Cari nama atau NIS" name="search" type="search" autocomplete="off" wire:model.live.debounce.300ms="search" /></div>
            <x-ui.button variant="secondary" icon="file-down" :href="route('staff.alumni.export')">Ekspor</x-ui.button>
        </x-ui.page-toolbar>
    </x-ui.card>

    <x-ui.card>
        @if ($profiles->isEmpty())
            <x-ui.empty-state icon="user-check" message="Belum ada alumni. Santri yang lulus otomatis masuk ke sini." class="py-2" />
        @else
            <x-ui.table caption="Alumni">
                <thead><tr><x-ui.th>Nama</x-ui.th><x-ui.th>Lulus</x-ui.th><x-ui.th>Kegiatan</x-ui.th><x-ui.th>Kontak</x-ui.th><x-ui.th><span class="sr-only">Ubah</span></x-ui.th></tr></thead>
                <tbody>
                    @foreach ($profiles as $profile)
                        <tr wire:key="a-{{ $profile->uuid }}">
                            <x-ui.td label="Nama"><span class="font-semibold break-words">{{ $profile->student->name }}</span></x-ui.td>
                            <x-ui.td label="Lulus">{{ $profile->graduated_on?->translatedFormat('j M Y') ?? '-' }}</x-ui.td>
                            <x-ui.td label="Kegiatan"><span class="break-words">{{ $profile->current_activity ?: '-' }}</span></x-ui.td>
                            <x-ui.td label="Kontak"><span class="break-words">{{ $profile->contact_phone ?: ($profile->contact_email ?: '-') }}</span></x-ui.td>
                            <x-ui.td label="Ubah"><x-ui.button variant="tertiary" wire:click="edit('{{ $profile->uuid }}')">Ubah<span class="sr-only"> {{ $profile->student->name }}</span></x-ui.button></x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
        <div class="mt-3">{{ $profiles->links() }}</div>
    </x-ui.card>

    <x-ui.modal name="ubah-alumni" title="Ubah data alumni">
        <div class="flex flex-col gap-3">
            <x-ui.input label="Kegiatan sekarang" name="current_activity" wire:model="current_activity" />
            <x-ui.input label="Telepon" name="contact_phone" type="tel" wire:model="contact_phone" />
            <x-ui.input label="Email" name="contact_email" type="email" wire:model="contact_email" />
            <x-ui.textarea label="Catatan" name="notes" wire:model="notes" />
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button wire:click="save" wire:loading.attr="disabled" wire:target="save">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
