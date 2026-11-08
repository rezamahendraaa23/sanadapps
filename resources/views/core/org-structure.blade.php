<div class="flex flex-col gap-4">
    <style>
        /* Bagan pohon: garis penghubung lembut dari atasan ke bawahan. */
        .org-tree { position: relative; isolation: isolate; overflow-x: auto; padding: 0.75rem 0 1.5rem }
        .org-tree ul { position: relative; display: flex; justify-content: center; margin: 0; padding: 1.75rem 0 0 }
        .org-tree > ul { padding-top: 0; min-width: max-content; margin: 0 auto }
        .org-tree li { position: relative; display: flex; flex-direction: column; align-items: center; padding: 1.75rem 0.5rem 0; list-style: none }
        .org-tree li::before, .org-tree li::after { content: ''; position: absolute; top: 0; right: 50%; width: 50%; height: 1.75rem; border-top: 2px solid color-mix(in srgb, var(--color-primary) 30%, transparent) }
        .org-tree li::after { right: auto; left: 50%; border-left: 2px solid color-mix(in srgb, var(--color-primary) 30%, transparent) }
        .org-tree li:only-child::before, .org-tree li:only-child::after { display: none }
        .org-tree li:only-child { padding-top: 0 }
        .org-tree li:first-child::before, .org-tree li:last-child::after { border: 0 none }
        .org-tree li:last-child::before { border-right: 2px solid color-mix(in srgb, var(--color-primary) 30%, transparent); border-radius: 0 0.9rem 0 0 }
        .org-tree li:first-child::after { border-radius: 0.9rem 0 0 0 }
        .org-tree ul ul::before { content: ''; position: absolute; top: 0; left: 50%; height: 1.75rem; border-left: 2px solid color-mix(in srgb, var(--color-primary) 30%, transparent) }
        .org-tree > ul > li { padding-top: 0 }
        .org-tree > ul > li::before, .org-tree > ul > li::after { display: none }
    </style>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div role="tablist" aria-label="Bagan" class="flex flex-wrap gap-1">
            @foreach ($charts as $item)
                <x-ui.tab :selected="$chart->key === $item->key" wire:key="chart-{{ $item->key }}" wire:click="selectChart('{{ $item->key }}')">{{ $item->name }}</x-ui.tab>
            @endforeach
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="toggleTv" role="switch" aria-checked="{{ $chart->show_on_tv ? 'true' : 'false' }}" @class(['inline-flex min-h-11 items-center gap-2 rounded-full border px-4 text-sm font-semibold', 'border-primary bg-primary-soft text-primary' => $chart->show_on_tv, 'border-border bg-surface text-text-muted' => ! $chart->show_on_tv])>
                <x-ui.icon name="monitor" size="sm" />{{ $chart->show_on_tv ? 'Tampil di layar TV' : 'Tidak tampil di TV' }}
            </button>
            <x-ui.button variant="secondary" icon="plus" wire:click="add">Tambah Puncak</x-ui.button>
        </div>
    </div>

    <x-ui.card>
        <p class="text-sm text-text-muted">Tekan <strong>+</strong> di kotak mana saja untuk menambah bawahannya. Garis penghubung tersambung sendiri. Bagan yang lebar bisa digeser ke samping.</p>
        @if ($tree === [])
            <x-ui.empty-state icon="network" message="Bagan masih kosong. Mulai dari Tambah Puncak." class="py-6" />
        @else
            <div class="org-tree mt-4">
                <ul>
                    @foreach ($tree as $node)
                        @include('core.partials.org-node', ['node' => $node, 'depth' => 0])
                    @endforeach
                </ul>
            </div>
        @endif
    </x-ui.card>

    <x-ui.modal name="form-jabatan" :title="$editing ? 'Ubah jabatan' : 'Tambah jabatan'">
        <div class="flex flex-col gap-3">
            @if (! $editing)
                <p class="rounded-control bg-bg p-3 text-sm">{{ $parentPosition ? 'Di bawah: '.$parentPosition->title.($parentPosition->person_name ? ', '.$parentPosition->person_name : '') : 'Kotak paling atas bagan.' }}</p>
            @endif
            <x-ui.input label="Jabatan" name="title" required help="Contoh: Pengasuh, Rois, Bagian Keamanan." wire:model="title" />
            <x-ui.input label="Nama" name="person_name" help="Kosongkan bila jabatan belum terisi." wire:model="person_name" />
            <x-ui.input label="Keterangan" name="note" help="Tidak wajib, misalnya periode 2025 sampai 2027." wire:model="note" />
            <div class="flex flex-col gap-1">
                <label for="field-photo" class="text-sm font-semibold">Foto (tidak wajib)</label>
                <input id="field-photo" type="file" accept="image/jpeg,image/png,image/webp" wire:model="photo" class="min-h-11 text-base">
                @error('photo')<p role="alert" class="text-sm font-semibold text-danger">{{ $message }}</p>@enderror
                @if ($editingPosition?->photo_path)
                    <label class="flex min-h-11 items-center gap-3 text-base"><input type="checkbox" wire:model="removePhoto" class="size-5 accent-primary">Hapus foto sekarang</label>
                @endif
            </div>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button wire:click="save" wire:loading.attr="disabled" wire:target="save,photo">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
