<div class="flex flex-col gap-4">
    @if ($initialPassword)
        <x-ui.banner tone="warning">Sandi awal santri: <strong class="font-tabular">{{ $initialPassword }}</strong>. Catat sekarang, sandi ini hanya tampil sekali.</x-ui.banner>
    @endif

    <x-ui.card>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex min-w-0 items-center gap-4">
                @if ($student->photo_path)
                    <img src="{{ route('staff.students.photo', $student) }}" alt="Foto {{ $student->name }}" class="size-16 rounded-full object-cover">
                @else
                    <span class="flex size-16 shrink-0 items-center justify-center rounded-full bg-primary-soft font-display text-2xl font-semibold text-primary" aria-hidden="true">{{ mb_strtoupper(mb_substr($student->name, 0, 1)) }}</span>
                @endif
                <div class="min-w-0">
                    <p class="text-xl font-semibold break-words">{{ $student->name }}</p>
                    <p class="text-sm text-text-muted">NIS <span class="font-tabular">{{ $student->nis }}</span> · {{ $student->classroom?->name ?? 'Belum ada kelas' }} · {{ $student->dormitory?->name ?? 'Belum ada asrama' }}</p>
                </div>
            </div>
            @if ($canManage)
                <div class="flex flex-wrap gap-2">
                    <x-ui.button variant="secondary" wire:click="issuePassword" wire:confirm="Buat sandi baru untuk {{ $student->name }}? Sandi lama langsung tidak berlaku.">Buat Sandi</x-ui.button>
                    <x-ui.button variant="secondary" icon="pencil" :href="route('staff.students.edit', $student)">Ubah Data</x-ui.button>
                </div>
            @endif
        </div>
    </x-ui.card>

    <div role="tablist" aria-label="Bagian data santri" class="flex flex-wrap gap-1">
        @foreach (['profil' => 'Profil', 'wali' => 'Wali', 'riwayat' => 'Riwayat kelas'] as $key => $label)
            <button
                type="button"
                role="tab"
                aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                wire:click="$set('tab', '{{ $key }}')"
                @class(['min-h-11 rounded-control px-4 text-base font-semibold', 'bg-primary text-on-primary' => $tab === $key, 'text-text-muted hover:bg-primary-soft' => $tab !== $key])
            >{{ $label }}</button>
        @endforeach
    </div>

    <x-ui.card>
        @if ($tab === 'wali')
            @forelse ($student->guardians as $guardian)
                <div wire:key="guardian-{{ $guardian->uuid }}" class="flex flex-wrap items-center justify-between gap-2 border-b border-border py-2 last:border-b-0">
                    <div class="min-w-0">
                        <p class="font-semibold">{{ $guardian->name }}</p>
                        <p class="text-sm text-text-muted">{{ ucfirst($guardian->pivot->relation) }} · <span class="font-tabular">{{ $guardian->phone_wa }}</span>{{ $guardian->pivot->is_payer ? ' · Pembayar' : '' }}</p>
                    </div>
                    @if ($canManage)
                        <x-ui.button variant="tertiary" wire:click="detachGuardian('{{ $guardian->uuid }}')" wire:confirm="Lepaskan {{ $guardian->name }} dari santri ini?">Lepaskan</x-ui.button>
                    @endif
                </div>
            @empty
                <x-ui.empty-state icon="contact" message="Belum ada wali yang terhubung. Tambahkan lewat Ubah Data." />
            @endforelse
        @elseif ($tab === 'riwayat')
            @if ($student->enrollments->isEmpty())
                <x-ui.empty-state icon="school" message="Belum ada riwayat kelas." />
            @else
                <x-ui.table caption="Riwayat kelas">
                    <thead><tr><x-ui.th>Kelas</x-ui.th><x-ui.th>Mulai</x-ui.th><x-ui.th>Selesai</x-ui.th></tr></thead>
                    <tbody>
                        @foreach ($student->enrollments->sortByDesc('started_at') as $enrollment)
                            <tr wire:key="enrollment-{{ $enrollment->id }}">
                                <x-ui.td label="Kelas">{{ $enrollment->classroom?->name ?? 'Kelas dihapus' }}</x-ui.td>
                                <x-ui.td label="Mulai">{{ $enrollment->started_at->setTimezone(config('sanad.display_timezone'))->translatedFormat('j M Y') }}</x-ui.td>
                                <x-ui.td label="Selesai">{{ $enrollment->ended_at?->setTimezone(config('sanad.display_timezone'))->translatedFormat('j M Y') ?? 'Sekarang' }}</x-ui.td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            @endif
        @else
            <dl class="grid gap-x-6 gap-y-3 md:grid-cols-2">
                @foreach ([
                    'Jenis kelamin' => $student->gender === 'L' ? 'Laki-laki' : 'Perempuan',
                    'NISN' => $student->nisn,
                    'Tempat lahir' => $student->birth_place,
                    'Tanggal lahir' => $student->birth_date?->translatedFormat('j F Y'),
                    'Tanggal masuk' => $student->entry_date?->translatedFormat('j F Y'),
                    'Status' => ['active' => 'Aktif', 'inactive' => 'Nonaktif', 'graduated' => 'Lulus', 'dropped' => 'Keluar'][$student->status],
                    'Alamat' => $student->address,
                ] as $label => $value)
                    <div>
                        <dt class="text-sm text-text-muted">{{ $label }}</dt>
                        <dd class="break-words">{{ $value ?: 'Belum diisi' }}</dd>
                    </div>
                @endforeach
                @foreach ($fields as $field)
                    <div>
                        <dt class="text-sm text-text-muted">{{ $field->label }}</dt>
                        <dd class="break-words">{{ $student->extra[$field->key] ?? 'Belum diisi' }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </x-ui.card>
</div>
