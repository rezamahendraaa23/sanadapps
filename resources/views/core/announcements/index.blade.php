<div>
    <x-ui.page-toolbar>
        <p class="max-w-prose text-base">Pengumuman tampil di portal wali dan santri sesuai penerimanya.</p>
        <x-ui.button icon="plus" wire:click="create">Tulis Pengumuman</x-ui.button>
    </x-ui.page-toolbar>

    <div class="flex flex-col gap-3">
        @forelse ($announcements as $announcement)
            <x-ui.card wire:key="announcement-{{ $announcement->uuid }}">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold break-words">{{ $announcement->title }}</h2>
                        <p class="text-sm text-text-muted">
                            {{ match ($announcement->audience) { 'classroom' => 'Kelas '.($classrooms[$announcement->audience_id] ?? 'dihapus'), 'dormitory' => 'Asrama '.($dormitories[$announcement->audience_id] ?? 'dihapus'), default => 'Semua wali dan santri' } }}
                            ·
                            {{ $announcement->publish_at?->isFuture() ? 'Terjadwal ' : 'Tayang ' }}{{ $announcement->publish_at?->setTimezone(config('sanad.display_timezone'))->translatedFormat('j M Y, H.i') }}
                        </p>
                    </div>
                    <span class="inline-flex gap-1">
                        <x-ui.button variant="tertiary" wire:click="edit('{{ $announcement->uuid }}')">Ubah<span class="sr-only"> {{ $announcement->title }}</span></x-ui.button>
                        <x-ui.button variant="tertiary" class="!text-danger hover:!bg-danger-soft" wire:click="delete('{{ $announcement->uuid }}')" wire:confirm="Hapus pengumuman ini?">Hapus<span class="sr-only"> {{ $announcement->title }}</span></x-ui.button>
                    </span>
                </div>
                <p class="mt-2 whitespace-pre-line break-words text-base">{{ $announcement->body }}</p>
            </x-ui.card>
        @empty
            <x-ui.card><x-ui.empty-state icon="megaphone" message="Belum ada pengumuman." /></x-ui.card>
        @endforelse
        <div>{{ $announcements->links() }}</div>
    </div>

    <x-ui.modal name="form-pengumuman" :title="$editingId ? 'Ubah pengumuman' : 'Tulis pengumuman'">
        <form id="form-pengumuman" wire:submit="save" class="flex flex-col gap-4">
            <x-ui.input label="Judul" name="title" required wire:model="title" />
            <x-ui.textarea label="Isi" name="body" required rows="5" wire:model="body" />
            <x-ui.select label="Penerima" name="audience" required :options="['all' => 'Semua wali dan santri', 'classroom' => 'Satu kelas', 'dormitory' => 'Satu asrama']" wire:model.live="audience" />
            @if ($audience === 'classroom')
                <x-ui.select label="Kelas" name="audience_id" required placeholder="Pilih kelas" :options="$classrooms" wire:model="audience_id" />
            @elseif ($audience === 'dormitory')
                <x-ui.select label="Asrama" name="audience_id" required placeholder="Pilih asrama" :options="$dormitories" wire:model="audience_id" />
            @endif
            <x-ui.input label="Jadwal tayang" name="publish_at" type="datetime-local" help="Kosongkan agar langsung tayang." wire:model="publish_at" />
            @if ($waEnabled && ! $editingId)
                <label class="flex min-h-11 items-center gap-3 text-base">
                    <input type="checkbox" wire:model="send_wa" class="size-5 accent-primary">
                    Kirim juga lewat WhatsApp ke wali
                </label>
            @endif
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button type="submit" form="form-pengumuman" wire:loading.attr="disabled" wire:target="save">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
