<div>
    <x-ui.card title="Rekening transfer manual">
        <x-slot:actions>
            <x-ui.button icon="plus" wire:click="create">Tambah Rekening</x-ui.button>
        </x-slot:actions>
        @forelse ($accounts as $account)
            <div wire:key="rekening-{{ $account->uuid }}" class="flex flex-wrap items-center justify-between gap-2 border-b border-border py-2 last:border-b-0">
                <div class="min-w-0">
                    <p class="font-semibold">{{ $account->bank_name }} <span class="font-tabular">{{ $account->account_no }}</span></p>
                    <p class="text-sm text-text-muted">{{ $account->account_holder }}{{ $account->is_active ? '' : ' · Disembunyikan dari wali' }}</p>
                </div>
                <x-ui.button variant="tertiary" wire:click="edit('{{ $account->uuid }}')">Ubah<span class="sr-only"> {{ $account->bank_name }}</span></x-ui.button>
            </div>
        @empty
            <x-ui.empty-state icon="landmark" message="Belum ada rekening. Wali belum bisa membayar lewat transfer sampai rekening ditambahkan." class="py-2" />
        @endforelse
        <label class="mt-3 flex min-h-11 items-center gap-3 border-t border-border pt-3 text-base">
            <input type="checkbox" wire:model.live="uniqueCode" class="size-5 accent-primary">
            Tambahkan kode unik 3 digit agar mutasi mudah dicocokkan
        </label>
    </x-ui.card>

    <x-ui.modal name="form-rekening" :title="$editingId ? 'Ubah rekening' : 'Tambah rekening'">
        <form id="form-rekening" wire:submit="save" class="flex flex-col gap-4">
            <x-ui.input label="Nama bank" name="bank_name" required wire:model="bank_name" />
            <x-ui.input label="Nomor rekening" name="account_no" inputmode="numeric" required wire:model="account_no" />
            <x-ui.input label="Atas nama" name="account_holder" required wire:model="account_holder" />
            <label class="flex min-h-11 items-center gap-3 text-base"><input type="checkbox" wire:model="is_active" class="size-5 accent-primary">Tampilkan ke wali</label>
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button type="submit" form="form-rekening" wire:loading.attr="disabled" wire:target="save">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
