<div class="flex flex-col gap-4">
    <div role="tablist" aria-label="Bagian akademik" class="flex flex-wrap gap-1">
        @foreach (['mapel' => 'Mapel', 'penugasan' => 'Penugasan', 'komponen' => 'Komponen Nilai', 'aturan' => 'Aturan Absensi'] as $key => $label)
            <x-ui.tab :selected="$tab === $key" wire:key="tab-{{ $key }}" wire:click="selectTab('{{ $key }}')">{{ $label }}</x-ui.tab>
        @endforeach
    </div>

    @if ($tab === 'mapel')
        <x-ui.card title="Mata pelajaran">
            <x-slot:actions><x-ui.button icon="plus" wire:click="createSubject">Tambah Mapel</x-ui.button></x-slot:actions>
            @if ($subjects->isEmpty())
                <x-ui.empty-state icon="book-open" message="Belum ada mata pelajaran." class="py-2" />
            @else
                <x-ui.table caption="Daftar mata pelajaran">
                    <thead><tr><x-ui.th>Kode</x-ui.th><x-ui.th>Nama</x-ui.th><x-ui.th>Jenis</x-ui.th><x-ui.th numeric>KKM</x-ui.th><x-ui.th>Aksi</x-ui.th></tr></thead>
                    <tbody>
                        @foreach ($subjects as $subject)
                            <tr wire:key="s-{{ $subject->uuid }}">
                                <x-ui.td label="Kode"><span class="font-tabular">{{ $subject->code }}</span></x-ui.td>
                                <x-ui.td label="Nama"><span class="break-words">{{ $subject->name }}</span>@unless ($subject->is_active)<span class="block text-xs text-text-muted">Nonaktif</span>@endunless</x-ui.td>
                                <x-ui.td label="Jenis">{{ $types[$subject->type] }}</x-ui.td>
                                <x-ui.td label="KKM" class="text-right"><span class="font-tabular">{{ $subject->kkm }}</span></x-ui.td>
                                <x-ui.td label="Aksi"><x-ui.button variant="tertiary" wire:click="editSubject('{{ $subject->uuid }}')">Ubah<span class="sr-only"> {{ $subject->name }}</span></x-ui.button></x-ui.td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            @endif
        </x-ui.card>
    @elseif ($tab === 'penugasan')
        <x-ui.card title="Tambah penugasan">
            <form wire:submit="saveAssignment" class="grid gap-3 md:grid-cols-[1fr_1fr_1fr_auto] md:items-start">
                <x-ui.select label="Ustadz" name="teacher_id" placeholder="Pilih" :options="$teachers" wire:model="teacher_id" />
                <x-ui.select label="Mapel" name="subject_id" placeholder="Pilih" :options="$subjectOptions" wire:model="subject_id" />
                <x-ui.select label="Kelas" name="classroom_id" placeholder="Pilih" :options="$classrooms" wire:model="classroom_id" />
                <x-ui.button type="submit" class="md:mt-6" wire:loading.attr="disabled" wire:target="saveAssignment">Simpan</x-ui.button>
            </form>
        </x-ui.card>
        <x-ui.card title="Penugasan tahun ini">
            @if ($assignments->isEmpty())
                <x-ui.empty-state icon="users" message="Belum ada penugasan." class="py-2" />
            @else
                <x-ui.table caption="Penugasan mengajar">
                    <thead><tr><x-ui.th>Kelas</x-ui.th><x-ui.th>Mapel</x-ui.th><x-ui.th>Ustadz</x-ui.th><x-ui.th>Aksi</x-ui.th></tr></thead>
                    <tbody>
                        @foreach ($assignments as $assignment)
                            <tr wire:key="a-{{ $assignment->uuid }}">
                                <x-ui.td label="Kelas">{{ $assignment->classroom->name }}</x-ui.td>
                                <x-ui.td label="Mapel">{{ $assignment->subject->name }}</x-ui.td>
                                <x-ui.td label="Ustadz">{{ $assignment->teacher->name }}</x-ui.td>
                                <x-ui.td label="Aksi"><x-ui.button variant="tertiary" wire:click="removeAssignment('{{ $assignment->uuid }}')" wire:confirm="Hapus penugasan ini?">Hapus</x-ui.button></x-ui.td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            @endif
        </x-ui.card>
    @elseif ($tab === 'komponen')
        <x-ui.card title="Komponen nilai">
            <x-ui.banner :tone="$totalWeight === 100 ? 'success' : 'warning'" class="mb-3">Total bobot aktif {{ $totalWeight }}%.{{ $totalWeight === 100 ? '' : ' Harus tepat 100% sebelum rapor bisa dibuat.' }}</x-ui.banner>
            <ul class="flex flex-col">
                @foreach ($components as $gradeComponent)
                    <li wire:key="c-{{ $gradeComponent->uuid }}" class="flex flex-wrap items-center gap-3 border-b border-border py-2 last:border-b-0">
                        <span @class(['min-w-0 flex-1 text-base break-words', 'text-text-muted line-through' => ! $gradeComponent->is_active])>{{ $gradeComponent->name }}</span>
                        <label class="flex items-center gap-2 text-sm">Bobot
                            <input type="number" min="0" max="100" value="{{ $gradeComponent->weight }}" x-on:change="$wire.updateWeight(@js($gradeComponent->uuid), $event.target.value)" class="font-tabular min-h-11 w-20 rounded-control border border-text-muted bg-surface px-2 text-right text-base">
                        </label>
                        <x-ui.button variant="tertiary" wire:click="toggleComponent('{{ $gradeComponent->uuid }}')">{{ $gradeComponent->is_active ? 'Matikan' : 'Aktifkan' }}<span class="sr-only"> {{ $gradeComponent->name }}</span></x-ui.button>
                    </li>
                @endforeach
            </ul>
            <form wire:submit="saveComponent" class="mt-4 grid gap-3 border-t border-border pt-4 md:grid-cols-[1fr_120px_auto] md:items-start">
                <x-ui.input label="Nama komponen" name="component_name" wire:model="component_name" />
                <x-ui.input label="Bobot (%)" name="component_weight" inputmode="numeric" wire:model="component_weight" />
                <x-ui.button type="submit" variant="secondary" class="md:mt-6">Tambah</x-ui.button>
            </form>
        </x-ui.card>
    @else
        <x-ui.card title="Aturan absensi">
            <form wire:submit="saveRules" class="flex max-w-md flex-col gap-3">
                <x-ui.input label="Toleransi terlambat (menit)" name="late_tolerance" inputmode="numeric" required wire:model="late_tolerance" />
                <fieldset>
                    <legend class="text-sm font-semibold">Cara absen yang diizinkan</legend>
                    @foreach (['manual' => 'Manual oleh ustadz atau musyrif', 'card' => 'Tap kartu', 'qr' => 'Pindai QR'] + ($faceModule ? ['face' => 'Wajah di pos'] : []) as $key => $label)
                        <label class="flex min-h-11 items-center gap-3 text-base"><input type="checkbox" value="{{ $key }}" wire:model="verification_modes" class="size-5 accent-primary">{{ $label }}</label>
                    @endforeach
                    @error('verification_modes')
                        <p role="alert" class="flex items-start gap-1 text-sm text-danger"><x-ui.icon name="circle-alert" size="sm" class="mt-0.5" />{{ $message }}</p>
                    @enderror
                </fieldset>
                @if ($faceModule)
                    <fieldset class="flex flex-col gap-3 border-t border-border pt-3">
                        <legend class="text-sm font-semibold">Ambang wajah</legend>
                        <div class="grid grid-cols-2 gap-3">
                            <x-ui.input label="Hadir otomatis mulai" name="face_upper" inputmode="decimal" required wire:model="face_upper" />
                            <x-ui.input label="Ditolak di bawah" name="face_lower" inputmode="decimal" required wire:model="face_lower" />
                        </div>
                        <x-ui.input label="Simpan foto tinjauan (hari)" name="evidence_retention" inputmode="numeric" required wire:model="evidence_retention" />
                        <p class="text-sm text-text-muted">Di antara kedua ambang menunggu konfirmasi musyrif. Angka ini titik mulai, kalibrasi dulu di perangkat dan cahaya sungguhan.</p>
                        @if ($consentCount === 0)
                            <x-ui.banner tone="warning" role="alert">Modul wajah aktif, tetapi belum ada wali yang setuju. Santri belum bisa didaftarkan.</x-ui.banner>
                        @else
                            <p class="text-sm"><span class="font-tabular font-semibold">{{ $consentCount }}</span> santri sudah disetujui wali.</p>
                        @endif
                    </fieldset>
                @endif
                <x-ui.button type="submit" class="self-start" wire:loading.attr="disabled" wire:target="saveRules">Simpan Aturan</x-ui.button>
            </form>
        </x-ui.card>
    @endif

    <x-ui.modal name="form-mapel" :title="$editing ? 'Ubah mapel' : 'Tambah mapel'">
        <div class="flex flex-col gap-3">
            <x-ui.input label="Kode" name="code" required wire:model="code" />
            <x-ui.input label="Nama" name="name" required wire:model="name" />
            <x-ui.select label="Jenis" name="type" :options="$types" wire:model="type" />
            <x-ui.input label="KKM" name="kkm" inputmode="numeric" required wire:model="kkm" />
            <label class="flex min-h-11 items-center gap-3 text-base"><input type="checkbox" wire:model="is_active" class="size-5 accent-primary">Aktif</label>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button wire:click="saveSubject" wire:loading.attr="disabled" wire:target="saveSubject">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
