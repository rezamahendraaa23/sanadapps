<div class="flex flex-col gap-4">
    <div role="tablist" aria-label="Bagian kurikulum" class="flex flex-wrap gap-1">
        @foreach (['kurikulum' => 'Kurikulum & Fase', 'rombongan' => 'Rombongan', 'jeda' => 'Jeda Fase', 'evaluasi' => 'Evaluasi & Sertifikat', 'desain' => 'Desain Sertifikat'] as $key => $label)
            <x-ui.tab :selected="$tab === $key" wire:key="tab-{{ $key }}" wire:click="selectTab('{{ $key }}')">{{ $label }}</x-ui.tab>
        @endforeach
    </div>

    @if ($tab === 'kurikulum')
        <x-ui.card>
            <p class="text-base">Susun jenjang belajar per fase, misalnya Perkenalan 3 bulan, lalu Dasar, lalu Lanjutan. Tiap fase punya ujian akhir, nilai lulus, rapor fase, dan sertifikat. Pesantren tidak harus memakai dua semester.</p>
            <form wire:submit="saveCurriculum" class="mt-3 flex flex-wrap items-end gap-3">
                <div class="min-w-60 flex-1"><x-ui.input label="Nama kurikulum" name="c_name" required help="Contoh: Program Tahfidz, Program Kitab Kuning." wire:model="c_name" /></div>
                <x-ui.button type="submit" icon="plus">Buat Kurikulum</x-ui.button>
            </form>
        </x-ui.card>

        @forelse ($curricula as $curriculum)
            <x-ui.card wire:key="cur-{{ $curriculum->uuid }}" :title="$curriculum->name">
                <x-slot:actions><x-ui.button variant="secondary" icon="plus" wire:click="newPhase('{{ $curriculum->uuid }}')">Tambah Fase<span class="sr-only"> {{ $curriculum->name }}</span></x-ui.button></x-slot:actions>
                @if ($curriculum->phases->isEmpty())
                    <x-ui.empty-state icon="milestone" message="Belum ada fase." class="py-2" />
                @else
                    <ol class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        @foreach ($curriculum->phases as $phase)
                            <li wire:key="ph-{{ $phase->uuid }}" class="relative flex flex-col gap-2 rounded-card border border-border bg-bg p-4">
                                <div class="flex items-center gap-3">
                                    <span class="font-display inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-primary font-semibold text-on-primary">{{ $loop->iteration }}</span>
                                    <span class="min-w-0">
                                        <span class="block font-semibold break-words">{{ $phase->name }}</span>
                                        <span class="font-tabular block text-xs text-text-muted">{{ $phase->code }}, {{ $phase->durationLabel() }}</span>
                                    </span>
                                </div>
                                <p class="text-sm">Lulus bila nilai akhir minimal <span class="font-tabular font-semibold">{{ rtrim(rtrim(number_format($phase->min_score, 2, ',', '.'), '0'), ',') }}</span>.</p>
                                <p class="text-sm text-text-muted">{{ $phase->has_certificate ? 'Dapat sertifikat.' : 'Tanpa sertifikat.' }} {{ $phase->classrooms_count }} rombongan.</p>
                                @if ($phase->description)<p class="text-sm break-words">{{ $phase->description }}</p>@endif
                                <div class="mt-auto flex flex-wrap gap-1 pt-1">
                                    <x-ui.button variant="tertiary" wire:click="editPhase('{{ $phase->uuid }}')">Ubah<span class="sr-only"> {{ $phase->name }}</span></x-ui.button>
                                    @unless ($loop->first)<x-ui.button variant="tertiary" wire:click="movePhase('{{ $phase->uuid }}', -1)" aria-label="Pindah {{ $phase->name }} lebih awal"><x-ui.icon name="arrow-left" size="sm" /></x-ui.button>@endunless
                                    @unless ($loop->last)<x-ui.button variant="tertiary" wire:click="movePhase('{{ $phase->uuid }}', 1)" aria-label="Pindah {{ $phase->name }} lebih akhir"><x-ui.icon name="arrow-right" size="sm" /></x-ui.button>@endunless
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-ui.card>
        @empty
            <x-ui.card><x-ui.empty-state icon="milestone" message="Belum ada kurikulum." class="py-2" /></x-ui.card>
        @endforelse
    @elseif ($tab === 'rombongan')
        <x-ui.card title="Mulai fase serentak">
            <p class="mb-3 text-sm text-text-muted">Semua kelas memakai satu tanggal mulai agar irama fase sama. Pilih fase tiap kelas; kelas yang dikosongkan tidak diubah. Tanggal selesai dihitung dari durasi fase, melompati jeda fase.</p>
            <form wire:submit="startTogether" wire:confirm="Mulai fase untuk kelas yang dipilih? Tanggal mulai dan selesai fase kelas itu akan diganti." class="flex flex-col gap-3">
                <div class="max-w-60"><x-ui.input label="Mulai" name="a_start" type="date" required wire:model="a_start" /></div>
                <x-ui.table caption="Rencana fase tiap kelas">
                    <thead><tr><x-ui.th>Kelas</x-ui.th><x-ui.th>Sekarang</x-ui.th><x-ui.th>Fase berikutnya</x-ui.th></tr></thead>
                    <tbody>
                        @foreach ($classrooms as $room)
                            <tr wire:key="plan-{{ $room->uuid }}">
                                <x-ui.td label="Kelas"><span class="font-semibold">{{ $room->name }}</span> <span class="text-sm text-text-muted">{{ $room->students_count }} santri</span></x-ui.td>
                                <x-ui.td label="Sekarang">{{ $room->phase ? $room->phase->curriculum->name.', '.$room->phase->name.' sampai '.$room->phase_ends_on->translatedFormat('j M Y') : 'Belum di fase' }}</x-ui.td>
                                <x-ui.td label="Fase berikutnya">
                                    <label for="plan-{{ $room->uuid }}" class="sr-only">Fase berikutnya untuk {{ $room->name }}</label>
                                    <select id="plan-{{ $room->uuid }}" wire:model="together.{{ $room->uuid }}" class="min-h-11 w-full rounded-control border border-text-muted bg-surface px-3 text-base">
                                        <option value="">Tidak diubah</option>
                                        @foreach ($phaseOptions as $uuid => $label)
                                            <option value="{{ $uuid }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </x-ui.td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
                @error('together')<p role="alert" class="text-sm text-danger">{{ $message }}</p>@enderror
                <x-ui.button type="submit" class="self-start" icon="play" wire:loading.attr="disabled" wire:target="startTogether">Mulai Fase</x-ui.button>
            </form>
        </x-ui.card>
        <x-ui.card title="Rombongan berjalan">
            @php($running = $classrooms->whereNotNull('phase_id'))
            @if ($running->isEmpty())
                <x-ui.empty-state icon="users" message="Belum ada kelas yang ditempatkan di fase." class="py-2" />
            @else
                <ul class="flex flex-col">
                    @foreach ($running as $room)
                        @php($total = max(1, $room->phase_started_on->diffInDays($room->phase_ends_on) + 1))
                        @php($done = max(0, min($total, $room->phase_started_on->diffInDays(\Carbon\CarbonImmutable::parse($today), false) + 1)))
                        <li wire:key="room-{{ $room->uuid }}" class="flex flex-col gap-2 border-b border-border py-3 last:border-b-0 md:flex-row md:items-center md:justify-between">
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold break-words">{{ $room->name }} <span class="font-normal text-text-muted">{{ $room->phase->curriculum->name }}, {{ $room->phase->name }}</span></p>
                                <p class="text-sm text-text-muted">{{ $room->phase_started_on->translatedFormat('j M Y') }} sampai {{ $room->phase_ends_on->translatedFormat('j M Y') }}, {{ $room->students_count }} santri</p>
                                <div class="mt-2 h-2 w-full max-w-md overflow-hidden rounded-full bg-border" role="progressbar" aria-label="Kemajuan fase {{ $room->name }}" aria-valuemin="0" aria-valuemax="{{ $total }}" aria-valuenow="{{ $done }}">
                                    <div class="h-full rounded-full bg-primary" style="width: {{ round($done / $total * 100) }}%"></div>
                                </div>
                            </div>
                            <x-ui.button variant="secondary" wire:click="openEvaluation('{{ $room->uuid }}')">Evaluasi<span class="sr-only"> {{ $room->name }}</span></x-ui.button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    @elseif ($tab === 'jeda')
        <x-ui.card title="Jeda fase">
            <p class="text-base">Masa ketika pelajaran reguler berhenti. Hitungan fase ikut berhenti, jadi fase tiga bulan tetap tiga bulan pelajaran reguler, dan nilai selama jeda tidak masuk nilai fase.</p>
            <ul class="mt-2 flex flex-col gap-1 text-sm text-text-muted">
                <li><strong class="text-text">Program khusus:</strong> tetap belajar, tapi pelajarannya lain. Contoh: Program Ramadan.</li>
                <li><strong class="text-text">Libur:</strong> tidak ada pelajaran. Contoh: libur Idul Fitri.</li>
            </ul>
            <div class="mt-4 flex flex-wrap gap-2">
                <x-ui.button icon="moon" wire:click="suggestRamadan">Isi Program Ramadan</x-ui.button>
                <x-ui.button variant="secondary" icon="plus" wire:click="newBreak">Tambah Jeda</x-ui.button>
            </div>
        </x-ui.card>
        <x-ui.card title="Daftar jeda">
            @forelse ($breaks as $break)
                <div wire:key="jeda-{{ $break->uuid }}" class="flex flex-wrap items-center justify-between gap-2 border-b border-border py-3 last:border-b-0">
                    <div class="min-w-0">
                        <p class="flex flex-wrap items-center gap-2 font-semibold">{{ $break->name }}
                            <span @class(['rounded-full px-2 py-0.5 text-xs font-semibold', 'bg-primary-soft text-primary' => $break->kind === 'special', 'bg-accent-soft text-accent' => $break->kind === 'holiday'])>{{ \App\Modules\Academic\Models\AcademicBreak::KINDS[$break->kind] }}</span>
                        </p>
                        <p class="text-sm text-text-muted">{{ $break->starts_on->translatedFormat('j M Y') }} sampai {{ $break->ends_on->translatedFormat('j M Y') }}, {{ $break->days() }} hari</p>
                    </div>
                    <span class="inline-flex gap-1">
                        <x-ui.button variant="tertiary" wire:click="editBreak('{{ $break->uuid }}')">Ubah<span class="sr-only"> {{ $break->name }}</span></x-ui.button>
                        <x-ui.button variant="tertiary" class="!text-danger hover:!bg-danger-soft" wire:click="deleteBreak('{{ $break->uuid }}')" wire:confirm="Hapus {{ $break->name }}? Tanggal selesai fase akan dihitung ulang.">Hapus<span class="sr-only"> {{ $break->name }}</span></x-ui.button>
                    </span>
                </div>
            @empty
                <x-ui.empty-state icon="calendar-off" message="Belum ada jeda fase." class="py-2" />
            @endforelse
        </x-ui.card>
    @elseif ($tab === 'desain')
        <x-ui.card title="Tulisan Arab di sertifikat">
            <form wire:submit="saveDesignSettings" class="grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(0,16rem)_auto] md:items-end">
                <x-ui.input label="Nama pesantren dalam bahasa Arab" name="school_name_ar" dir="rtl" help="Contoh: مَعْهَدُ الْمُخْتَارِ الْإِسْلَامِي. Kosongkan untuk memakai nama biasa." wire:model="school_name_ar" />
                <x-ui.select label="Desain bawaan" name="default_design" :options="collect($designs)->map(fn ($design) => $design['name'])->all()" wire:model="default_design" />
                <x-ui.button type="submit">Simpan</x-ui.button>
            </form>
            <p class="mt-2 text-sm text-text-muted">Judul dan nama pesantren berbahasa Arab dengan khat. Isi dari "diberikan kepada" sampai tanda tangan tetap bahasa Indonesia. Penandatangan dan kota diambil dari pengaturan Surat.</p>
        </x-ui.card>
        <x-ui.card title="Logo di sertifikat">
            <p class="text-sm text-text-muted">Bisa lebih dari satu, misalnya logo pesantren, yayasan, dan mitra. Tampil berurutan dari kiri. Tanpa logo khusus, dipakai logo pesantren dari Profil.</p>
            <div class="mt-3 flex flex-wrap gap-3">
                @for ($i = 0; $i < $logoCount; $i++)
                    <div wire:key="logo-{{ $i }}" class="flex flex-col items-center gap-1 rounded-control border border-border p-2">
                        <img src="{{ route('staff.curriculum.logo', $i) }}" alt="Logo {{ $i + 1 }}" class="h-16 w-24 object-contain">
                        <div class="flex gap-1">
                            @if ($i > 0)<button type="button" wire:click="moveLogo({{ $i }}, -1)" class="inline-flex size-11 items-center justify-center rounded-control hover:bg-primary-soft" aria-label="Geser logo {{ $i + 1 }} ke kiri"><x-ui.icon name="arrow-left" size="sm" /></button>@endif
                            @if ($i < $logoCount - 1)<button type="button" wire:click="moveLogo({{ $i }}, 1)" class="inline-flex size-11 items-center justify-center rounded-control hover:bg-primary-soft" aria-label="Geser logo {{ $i + 1 }} ke kanan"><x-ui.icon name="arrow-right" size="sm" /></button>@endif
                            <button type="button" wire:click="removeLogo({{ $i }})" wire:confirm="Hapus logo ini dari sertifikat?" class="inline-flex size-11 items-center justify-center rounded-control text-danger hover:bg-danger-soft" aria-label="Hapus logo {{ $i + 1 }}"><x-ui.icon name="trash-2" size="sm" /></button>
                        </div>
                    </div>
                @endfor
            </div>
            @if ($logoCount < \App\Modules\Academic\Livewire\CurriculumBoard::MAX_LOGOS)
                <form wire:submit="addLogo" class="mt-3 flex flex-wrap items-end gap-3">
                    <div class="flex flex-col gap-1">
                        <label for="field-newLogo" class="text-sm font-semibold">Tambah logo (PNG transparan paling bagus)</label>
                        <input id="field-newLogo" type="file" accept="image/png,image/jpeg,image/webp" wire:model="newLogo" class="min-h-11 text-base">
                        @error('newLogo')<p role="alert" class="text-sm font-semibold text-danger">{{ $message }}</p>@enderror
                    </div>
                    <x-ui.button type="submit" icon="upload" wire:loading.attr="disabled" wire:target="addLogo,newLogo">Unggah Logo</x-ui.button>
                </form>
            @endif
        </x-ui.card>
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($designs as $key => $design)
                <article wire:key="design-{{ $key }}" @class(['flex flex-col gap-3 rounded-card border bg-surface p-3 shadow-sm', 'border-primary ring-2 ring-primary' => $default_design === $key, 'border-border' => $default_design !== $key])>
                    <div class="relative aspect-[297/210] w-full overflow-hidden rounded-control bg-bg" x-data="{ scale: 0.3 }" x-init="scale = $el.clientWidth / 1123; new ResizeObserver(() => scale = $el.clientWidth / 1123).observe($el)">
                        <iframe wire:key="frame-{{ $key }}-{{ $logoCount }}" src="{{ route('staff.curriculum.design', $key) }}?v={{ $logoCount }}" title="Pratinjau {{ $design['name'] }}" loading="lazy" tabindex="-1" class="pointer-events-none absolute top-0 left-0 h-[794px] w-[1123px] origin-top-left border-0" :style="`transform: scale(${scale})`"></iframe>
                    </div>
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h3 class="font-semibold">{{ $loop->iteration }}. {{ $design['name'] }}</h3>
                            <p class="text-sm text-text-muted">{{ $design['note'] }}</p>
                        </div>
                        @if ($default_design === $key)
                            <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-primary-soft px-3 py-1 text-sm font-semibold text-primary"><x-ui.icon name="check" size="sm" />Bawaan</span>
                        @else
                            <x-ui.button variant="secondary" class="shrink-0" wire:click="useDesign('{{ $key }}')">Jadikan Bawaan<span class="sr-only"> {{ $design['name'] }}</span></x-ui.button>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @else
        @if (! $classroom)
            <x-ui.card title="Pilih rombongan">
                @forelse ($classrooms->whereNotNull('phase_id') as $room)
                    <button type="button" wire:key="pick-{{ $room->uuid }}" wire:click="openEvaluation('{{ $room->uuid }}')" class="flex min-h-11 w-full items-center justify-between gap-2 border-b border-border py-2 text-left last:border-b-0 hover:text-primary">
                        <span class="font-semibold break-words">{{ $room->name }}</span><span class="text-sm text-text-muted">{{ $room->phase->name }}</span>
                    </button>
                @empty
                    <x-ui.empty-state icon="users" message="Belum ada kelas di fase. Tempatkan di tab Rombongan." class="py-2" />
                @endforelse
            </x-ui.card>
        @else
            <x-ui.card>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="font-display text-xl font-semibold break-words">{{ $classroom->name }}, {{ $classroom->phase->name }}</h2>
                        <p class="text-sm text-text-muted">{{ $classroom->phase->curriculum->name }}. {{ $classroom->phase_started_on->translatedFormat('j M Y') }} sampai {{ $classroom->phase_ends_on->translatedFormat('j M Y') }}. Lulus bila nilai minimal {{ rtrim(rtrim(number_format($classroom->phase->min_score, 2, ',', '.'), '0'), ',') }}.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button variant="secondary" icon="calculator" wire:click="evaluate" wire:loading.attr="disabled" wire:target="evaluate">Hitung Nilai</x-ui.button>
                        @if ($results->isNotEmpty())
                            <x-ui.button variant="secondary" icon="file-text" :href="route('staff.curriculum.report', $classroom)">Rapor Fase PDF</x-ui.button>
                            @if ($classroom->phase->has_certificate && $results->whereNotNull('certificate_no')->isNotEmpty())
                                <x-ui.button variant="secondary" icon="award" :href="route('staff.curriculum.certificate', $classroom)">Sertifikat PDF</x-ui.button>
                            @endif
                        @endif
                    </div>
                </div>
                @error('evaluate')<p role="alert" class="mt-2 flex items-center gap-1 text-sm font-semibold text-danger"><x-ui.icon name="circle-alert" size="sm" />{{ $message }}</p>@enderror
            </x-ui.card>

            <x-ui.card title="Hasil fase">
                @if ($results->isEmpty())
                    <x-ui.empty-state icon="calculator" message="Belum dihitung. Tekan Hitung Nilai setelah ujian akhir fase dinilai." class="py-2" />
                @else
                    <x-slot:actions>
                        @if ($results->where('status', 'pending')->isNotEmpty())
                            <x-ui.button variant="tertiary" wire:click="suggest">Isi Usulan dari Nilai</x-ui.button>
                        @endif
                    </x-slot:actions>
                    <x-ui.table caption="Hasil fase {{ $classroom->name }}">
                        <thead><tr><x-ui.th>Santri</x-ui.th><x-ui.th>Nilai per mapel</x-ui.th><x-ui.th numeric>Nilai akhir</x-ui.th><x-ui.th>Keputusan</x-ui.th></tr></thead>
                        <tbody>
                            @foreach ($results as $result)
                                <tr wire:key="res-{{ $result->uuid }}">
                                    <x-ui.td label="Santri"><span class="font-semibold break-words">{{ $result->student->name }}</span><span class="font-tabular block text-xs text-text-muted">{{ $result->student->nis }}</span></x-ui.td>
                                    <x-ui.td label="Nilai per mapel">
                                        <span class="flex flex-wrap gap-1">
                                            @forelse ($result->scores as $row)
                                                <span class="font-tabular rounded-full bg-bg px-2 py-0.5 text-xs">{{ $row['name'] }} {{ number_format((float) $row['score'], 0) }}</span>
                                            @empty
                                                <span class="text-sm text-text-muted">Belum ada nilai</span>
                                            @endforelse
                                        </span>
                                    </x-ui.td>
                                    <x-ui.td label="Nilai akhir" class="text-right"><span @class(['font-tabular font-semibold', 'text-danger' => $result->final_score !== null && $result->final_score < $classroom->phase->min_score])>{{ $result->final_score === null ? '-' : number_format($result->final_score, 2, ',', '.') }}</span></x-ui.td>
                                    <x-ui.td label="Keputusan">
                                        @if ($result->status === 'pending' && $result->final_score !== null)
                                            <label class="sr-only" for="dec-{{ $result->uuid }}">Keputusan {{ $result->student->name }}</label>
                                            <select id="dec-{{ $result->uuid }}" wire:model="decisions.{{ $result->uuid }}" class="min-h-11 rounded-control border border-text-muted bg-surface px-2 text-base">
                                                <option value="">Belum</option>
                                                <option value="passed">Lulus</option>
                                                <option value="failed">Belum lulus</option>
                                            </select>
                                        @else
                                            <span @class(['inline-flex rounded-full px-2 py-0.5 text-sm font-semibold', 'bg-success-soft' => $result->status === 'passed', 'bg-warning-soft' => $result->status === 'failed', 'bg-bg' => $result->status === 'pending'])>{{ \App\Modules\Academic\Models\PhaseResult::LABELS[$result->status] }}</span>
                                            @if ($result->certificate_no)<span class="font-tabular block text-xs text-text-muted">{{ $result->certificate_no }}</span>@endif
                                            @if ($result->promoted_at)<span class="block text-xs text-text-muted">Sudah naik fase</span>@endif
                                        @endif
                                    </x-ui.td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if ($results->where('status', 'pending')->isNotEmpty())
                            <x-ui.button wire:click="decide" wire:confirm="Simpan keputusan? Keputusan yang tersimpan tidak dihitung ulang." wire:loading.attr="disabled" wire:target="decide">Simpan Keputusan</x-ui.button>
                        @endif
                        @if ($classroom->phase->has_certificate && $results->where('status', 'passed')->whereNull('certificate_no')->isNotEmpty())
                            <x-ui.button variant="secondary" icon="award" wire:click="certify" wire:loading.attr="disabled" wire:target="certify">Beri Nomor Sertifikat</x-ui.button>
                        @endif
                    </div>
                @endif
            </x-ui.card>

            @if ($results->where('status', 'passed')->whereNull('promoted_at')->isNotEmpty())
                <x-ui.card title="Pindah ke kelas berikutnya">
                    <p class="text-base">Pilih kelas tujuan tiap santri yang lulus, misalnya ada yang ke jalur kitab dan ada yang ke jalur tahfidz. Yang dikosongkan tetap di kelas ini.</p>
                    @if ($mapTargets === [])
                        <p class="mt-2 text-sm text-text-muted">Belum ada kelas lain yang ditempatkan di fase. Mulai fasenya dulu di tab Rombongan.</p>
                    @else
                        <div class="mt-3 flex flex-wrap items-end gap-2">
                            <div class="min-w-64"><x-ui.select label="Isi semua dengan" name="mapAll" placeholder="Pilih kelas" :options="$mapTargets" wire:model="mapAll" /></div>
                            <x-ui.button variant="secondary" wire:click="applyMapAll">Isi Semua</x-ui.button>
                        </div>
                        <x-ui.table caption="Kelas tujuan tiap santri" class="mt-3">
                            <thead><tr><x-ui.th>Santri</x-ui.th><x-ui.th>Kelas tujuan</x-ui.th></tr></thead>
                            <tbody>
                                @foreach ($results->where('status', 'passed')->whereNull('promoted_at') as $result)
                                    <tr wire:key="map-{{ $result->uuid }}">
                                        <x-ui.td label="Santri">{{ $result->student->name }} <span class="text-sm text-text-muted">{{ $result->student->nis }}</span></x-ui.td>
                                        <x-ui.td label="Kelas tujuan">
                                            <label for="map-{{ $result->uuid }}" class="sr-only">Kelas tujuan {{ $result->student->name }}</label>
                                            <select id="map-{{ $result->uuid }}" wire:model="mapping.{{ $result->uuid }}" class="min-h-11 w-full rounded-control border border-text-muted bg-surface px-3 text-base">
                                                <option value="">Tetap di kelas ini</option>
                                                @foreach ($mapTargets as $uuid => $label)
                                                    <option value="{{ $uuid }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </x-ui.td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-ui.table>
                        @error('promoteTo')<p role="alert" class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
                        <x-ui.button icon="arrow-up-right" class="mt-3" wire:click="promote" wire:confirm="Pindahkan santri ke kelas tujuannya masing-masing?">Pindahkan</x-ui.button>
                    @endif
                </x-ui.card>
            @endif
        @endif
    @endif

    <x-ui.modal name="form-libur" :title="$editingBreak ? 'Ubah jeda fase' : 'Tambah jeda fase'">
        <form id="form-libur" wire:submit="saveBreak" class="flex flex-col gap-3">
            <x-ui.input label="Nama" name="b_name" required help="Contoh: Program Ramadan 2027, atau Libur Idul Fitri." wire:model="b_name" />
            <x-ui.select label="Jenis" name="b_kind" :options="\App\Modules\Academic\Models\AcademicBreak::KINDS" wire:model="b_kind" />
            <div class="grid gap-3 md:grid-cols-2">
                <x-ui.input label="Mulai" name="b_start" type="date" required wire:model="b_start" />
                <x-ui.input label="Selesai" name="b_end" type="date" required wire:model="b_end" />
            </div>
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button type="submit" form="form-libur" wire:loading.attr="disabled" wire:target="saveBreak">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal name="form-fase" :title="$editingPhase ? 'Ubah fase' : 'Tambah fase'">
        <div class="flex flex-col gap-3">
            <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_8rem]">
                <x-ui.input label="Nama fase" name="p_name" required help="Contoh: Masa Perkenalan." wire:model="p_name" />
                <x-ui.input label="Kode" name="p_code" required help="Masuk ke nomor sertifikat." wire:model="p_code" />
            </div>
            <div class="grid gap-3 md:grid-cols-2">
                <x-ui.input label="Durasi (minggu)" name="p_weeks" inputmode="numeric" required help="12 minggu kira-kira 3 bulan." wire:model="p_weeks" />
                <x-ui.input label="Nilai lulus minimal" name="p_min" inputmode="decimal" required wire:model="p_min" />
            </div>
            <x-ui.textarea label="Keterangan" name="p_description" wire:model="p_description" />
            <label class="flex min-h-11 items-center gap-3 text-base"><input type="checkbox" wire:model="p_certificate" class="size-5 accent-primary">Lulusan fase ini mendapat sertifikat</label>
            <x-ui.input label="Judul sertifikat" name="p_certificate_title" help="Contoh: Sertifikat Kelulusan Masa Perkenalan." wire:model="p_certificate_title" />
            <x-ui.input label="Judul sertifikat dalam bahasa Arab" name="p_title_ar" dir="rtl" help="Kosongkan untuk memakai: شَهَادَةُ إِتْمَامِ الْمَرْحَلَةِ" wire:model="p_title_ar" />
            <label class="flex min-h-11 items-center gap-3 text-base"><input type="checkbox" wire:model="p_back" class="size-5 accent-primary">Cetak dua sisi: belakangnya hasil ujian</label>
            <x-ui.select label="Desain sertifikat" name="p_design" placeholder="Ikuti desain bawaan" :options="collect($designs)->map(fn ($design) => $design['name'])->all()" wire:model="p_design" />
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button wire:click="savePhase" wire:loading.attr="disabled" wire:target="savePhase">Simpan Fase</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
