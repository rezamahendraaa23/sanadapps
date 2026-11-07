<div class="flex flex-col gap-4">
    <x-ui.card>
        <x-ui.page-toolbar class="!mb-0">
            <div class="grid min-w-0 flex-1 gap-3 md:max-w-xl md:grid-cols-2">
                <x-ui.select label="Term" name="termId" :options="$terms" wire:model.live="termId" />
                <x-ui.select label="Kelas" name="classroomId" placeholder="Semua kelas" :options="$classrooms" wire:model.live="classroomId" />
            </div>
            @if ($terms !== [])
                <div class="flex flex-wrap gap-2">
                    <x-ui.button variant="secondary" icon="copy" x-data x-on:click="$dispatch('open-modal', 'salin-jadwal')">Salin dari Term Lain</x-ui.button>
                    <x-ui.button icon="plus" wire:click="create(1)">Tambah Jadwal</x-ui.button>
                </div>
            @endif
        </x-ui.page-toolbar>
    </x-ui.card>

    @if ($terms !== [] && ! $termActive)
        <x-ui.banner tone="warning">Term ini belum aktif. Ustadz dan santri baru melihat jadwalnya setelah term diaktifkan di Kelas &amp; Asrama.</x-ui.banner>
    @endif

    @if ($terms === [])
        <x-ui.card><x-ui.empty-state icon="calendar-days" message="Belum ada term. Buat dulu di Kelas & Asrama." class="py-2" /></x-ui.card>
    @else
        <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($days as $day => $dayName)
                <section wire:key="hari-{{ $day }}" class="flex min-w-0 flex-col gap-2 rounded-card border border-border bg-surface p-3">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-base font-semibold">{{ $dayName }}</h2>
                        <button type="button" wire:click="create({{ $day }})" class="inline-flex size-11 items-center justify-center rounded-control hover:bg-primary-soft" aria-label="Tambah jadwal {{ $dayName }}"><x-ui.icon name="plus" /></button>
                    </div>
                    @forelse ($schedules[$day] ?? [] as $schedule)
                        <button type="button" wire:key="j-{{ $schedule->uuid }}" wire:click="edit('{{ $schedule->uuid }}')" class="flex min-h-11 flex-col rounded-control border-l-4 border-primary bg-bg p-2 text-left hover:bg-primary-soft">
                            <span class="font-tabular text-sm font-semibold">{{ $schedule->startLabel() }} sampai {{ $schedule->endLabel() }}</span>
                            <span class="text-base break-words">{{ $schedule->label() }}</span>
                            <span class="text-xs break-words text-text-muted">{{ collect([$activities[$schedule->activity_type], $schedule->classroom?->name, $schedule->teacher?->name, $schedule->room])->filter()->join(', ') }}</span>
                        </button>
                    @empty
                        <p class="text-sm text-text-muted">Kosong</p>
                    @endforelse
                </section>
            @endforeach
        </div>
    @endif

    <x-ui.modal name="form-jadwal" :title="$editing ? 'Ubah jadwal' : 'Tambah jadwal'">
        <div class="flex flex-col gap-3">
            @error('conflict')
                <x-ui.banner tone="danger" role="alert">{{ $message }}</x-ui.banner>
            @enderror
            <div class="grid grid-cols-2 gap-3">
                <x-ui.select label="Hari" name="day_of_week" :options="$days" wire:model="day_of_week" />
                <x-ui.select label="Jenis" name="activity_type" :options="$activities" wire:model.live="activity_type" />
                <x-ui.input label="Mulai" name="start_time" type="time" required wire:model="start_time" />
                <x-ui.input label="Selesai" name="end_time" type="time" required wire:model="end_time" />
            </div>
            <x-ui.select label="Kelas" name="form_classroom" placeholder="Semua santri" :options="$classrooms" wire:model="form_classroom" />
            @if ($activity_type === 'kbm')
                <x-ui.select label="Mapel" name="subject_id" placeholder="Pilih" :options="$subjects" wire:model="subject_id" />
            @else
                <x-ui.input label="Nama kegiatan" name="title" wire:model="title" />
            @endif
            <x-ui.select label="Ustadz" name="teacher_id" placeholder="Tidak ada" :options="$teachers" wire:model="teacher_id" />
            <x-ui.input label="Ruang" name="room" wire:model="room" />
            @if ($editing)
                <x-ui.button variant="tertiary" class="self-start" wire:click="remove" wire:confirm="Hapus jadwal ini?">Hapus Jadwal</x-ui.button>
            @endif
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button wire:click="save" wire:loading.attr="disabled" wire:target="save">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal name="salin-jadwal" title="Salin jadwal">
        <p class="mb-3 text-base">Hanya untuk term yang masih kosong. Jadwal yang ada tidak ditimpa.</p>
        <x-ui.select label="Salin dari" name="copy_from" placeholder="Pilih term" :options="$terms" wire:model="copy_from" />
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button wire:click="copy" wire:loading.attr="disabled" wire:target="copy">Salin</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
