@php
    $tabs = ['classrooms' => 'Kelas', 'dormitories' => 'Asrama', 'years' => 'Tahun ajaran'];
    $createLabel = ['classrooms' => 'Tambah Kelas', 'dormitories' => 'Tambah Asrama', 'years' => 'Tambah Tahun Ajaran'][$tab];
    $emptyMessage = ['classrooms' => 'Belum ada kelas. Tambahkan kelas sebelum mengimpor santri.', 'dormitories' => 'Belum ada asrama.', 'years' => ''][$tab];
    $formTitle = ['classrooms' => 'kelas', 'dormitories' => 'asrama', 'years' => 'tahun ajaran', 'terms' => 'periode'][$formTarget];
@endphp

<div class="flex flex-col gap-4">
    <div role="tablist" aria-label="Jenis data" class="flex flex-wrap gap-1">
        @foreach ($tabs as $key => $label)
            <x-ui.tab :selected="$tab === $key" wire:key="tab-{{ $key }}" wire:click="selectTab('{{ $key }}')">{{ $label }}</x-ui.tab>
        @endforeach
    </div>

    @if ($tab === 'years')
        @include('core.masters.periods')
    @else
    <x-ui.card>
        <div class="mb-4 flex justify-end">
            <x-ui.button icon="plus" class="w-full md:w-auto" wire:click="create">{{ $createLabel }}</x-ui.button>
        </div>

        @if ($rows->isEmpty())
            <x-ui.empty-state icon="school" :message="$emptyMessage" />
        @else
            <x-ui.table :caption="$tabs[$tab]">
                <thead>
                    <tr>
                        <x-ui.th>Nama</x-ui.th>
                        @if ($tab === 'classrooms')
                            <x-ui.th>Jenjang</x-ui.th><x-ui.th>Wali kelas</x-ui.th><x-ui.th numeric>Santri</x-ui.th>
                        @elseif ($tab === 'dormitories')
                            <x-ui.th>Untuk</x-ui.th><x-ui.th>Musyrif</x-ui.th><x-ui.th numeric>Penghuni</x-ui.th>
                        @else
                            @if ($tab === 'terms')<x-ui.th>Tahun ajaran</x-ui.th>@endif
                            <x-ui.th>Periode</x-ui.th><x-ui.th>Status</x-ui.th>
                        @endif
                        <x-ui.th>Aksi</x-ui.th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr wire:key="{{ $tab }}-{{ $row->id }}">
                            <x-ui.td label="Nama">{{ $row->name }}</x-ui.td>
                            @if ($tab === 'classrooms')
                                <x-ui.td label="Jenjang">{{ $row->level ?? '-' }}</x-ui.td>
                                <x-ui.td label="Wali kelas">{{ $row->homeroomTeacher?->name ?? 'Belum ada' }}</x-ui.td>
                                <x-ui.td label="Santri" numeric>{{ $row->students_count }}{{ $row->capacity ? ' / '.$row->capacity : '' }}</x-ui.td>
                            @elseif ($tab === 'dormitories')
                                <x-ui.td label="Untuk">{{ $row->gender === 'L' ? 'Putra' : 'Putri' }}</x-ui.td>
                                <x-ui.td label="Musyrif">{{ $row->musyrif?->name ?? 'Belum ada' }}</x-ui.td>
                                <x-ui.td label="Penghuni" numeric>{{ $row->students_count }}{{ $row->capacity ? ' / '.$row->capacity : '' }}</x-ui.td>
                            @else
                                @if ($tab === 'terms')<x-ui.td label="Tahun ajaran">{{ $row->academicYear->name }}</x-ui.td>@endif
                                <x-ui.td label="Periode">{{ $row->start_date->translatedFormat('j M Y') }} sampai {{ $row->end_date->translatedFormat('j M Y') }}</x-ui.td>
                                <x-ui.td label="Status">
                                    @if ($row->is_active)
                                        <x-ui.status-badge status="active" />
                                    @else
                                        <x-ui.button variant="tertiary" wire:click="activate({{ $row->id }})">Jadikan aktif<span class="sr-only"> {{ $row->name }}</span></x-ui.button>
                                    @endif
                                </x-ui.td>
                            @endif
                            <x-ui.td label="Aksi">
                                <span class="inline-flex gap-1">
                                    <x-ui.button variant="tertiary" wire:click="edit({{ $row->id }})">Ubah<span class="sr-only"> {{ $row->name }}</span></x-ui.button>
                                    <x-ui.button variant="tertiary" class="!text-danger hover:!bg-danger-soft" wire:click="askDelete({{ $row->id }})">Hapus<span class="sr-only"> {{ $row->name }}</span></x-ui.button>
                                </span>
                            </x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>
    @endif

    <x-ui.modal name="form-master" :title="($editingId ? 'Ubah ' : 'Tambah ').$formTitle">
        <form id="form-master" wire:submit="save" class="flex flex-col gap-4">
            @if ($formTarget === 'terms')
                <x-ui.select label="Tahun ajaran" name="form.academic_year_id" required placeholder="Pilih tahun ajaran" :options="$years" wire:model="form.academic_year_id" />
            @endif
            <x-ui.input label="Nama" name="form.name" required wire:model="form.name" :help="['years' => 'Contoh: 2026/2027', 'terms' => 'Contoh: Semester 2, atau Tahun penuh'][$formTarget] ?? null" />
            @if (in_array($formTarget, ['years', 'terms'], true))
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.input label="Tanggal mulai" name="form.start_date" type="date" required wire:model="form.start_date" />
                    <x-ui.input label="Tanggal selesai" name="form.end_date" type="date" required wire:model="form.end_date" />
                </div>
                @if ($formTarget === 'years' && ! $editingId)
                    <x-ui.select label="Dibagi menjadi" name="split" :options="$splits" wire:model="split" help="Periode di dalamnya dibuat otomatis sesuai pilihan ini." />
                @endif
            @elseif ($formTarget === 'classrooms')
                <x-ui.input label="Jenjang" name="form.level" wire:model="form.level" help="Contoh: 7, 10, atau Ula. Dipakai untuk tarif per jenjang." />
                <x-ui.select label="Wali kelas" name="form.homeroom_teacher_id" placeholder="Belum ditentukan" :options="$teachers" wire:model="form.homeroom_teacher_id" />
                <x-ui.input label="Kapasitas" name="form.capacity" inputmode="numeric" wire:model="form.capacity" />
            @else
                <x-ui.select label="Untuk" name="form.gender" required placeholder="Pilih" :options="['L' => 'Putra', 'P' => 'Putri']" wire:model="form.gender" />
                <x-ui.select label="Musyrif" name="form.musyrif_user_id" placeholder="Belum ditentukan" :options="$musyrifs" wire:model="form.musyrif_user_id" />
                <x-ui.input label="Kapasitas" name="form.capacity" inputmode="numeric" wire:model="form.capacity" />
            @endif
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button type="submit" form="form-master" wire:loading.attr="disabled" wire:target="save">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal name="hapus-master" title="Hapus data ini?">
        <p>Data yang dihapus tidak bisa dikembalikan.</p>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button variant="danger" wire:click="confirmDelete">Hapus</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
