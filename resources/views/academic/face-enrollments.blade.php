<div class="mx-auto flex w-full max-w-[860px] flex-col gap-4">
    @if (! $matcherAvailable)
        <x-ui.banner tone="danger" role="alert">Pencocokan wajah belum tersedia di server ini. Teknologinya belum dipilih, jadi absen wajah tidak berjalan.</x-ui.banner>
    @elseif (str_starts_with($matcherVersion, 'fake'))
        <x-ui.banner tone="warning">Mode pengembangan: pencocokan masih tiruan, bukan pengenalan wajah. Jangan dipakai untuk santri sungguhan.</x-ui.banner>
    @endif

    <x-ui.card title="Ringkasan">
        <dl class="grid grid-cols-3 gap-2 text-center">
            <div><dd class="font-display font-tabular text-2xl font-semibold">{{ $activeCount }}</dd><dt class="text-sm">Santri aktif</dt></div>
            <div><dd class="font-display font-tabular text-2xl font-semibold">{{ $consentCount }}</dd><dt class="text-sm">Wali setuju</dt></div>
            <div><dd class="font-display font-tabular text-2xl font-semibold">{{ $enrolledCount }}</dd><dt class="text-sm">Terdaftar</dt></div>
        </dl>
    </x-ui.card>

    <x-ui.card title="Pilih santri">
        <x-ui.input label="Cari nama atau NIS" name="search" type="search" autocomplete="off" wire:model.live.debounce.250ms="search" />
        @foreach ($results as $result)
            <button type="button" wire:key="s-{{ $result->uuid }}" wire:click="choose('{{ $result->uuid }}')" class="mt-1 flex min-h-11 w-full items-center justify-between gap-2 rounded-control px-2 text-left hover:bg-primary-soft"><span class="font-semibold break-words">{{ $result->name }}</span><span class="font-tabular shrink-0 text-sm text-text-muted">{{ $result->nis }}</span></button>
        @endforeach
    </x-ui.card>

    @if ($student)
        <x-ui.card :title="$student->name">
            @if (! $consent)
                <x-ui.banner tone="warning" role="alert">Belum ada persetujuan wali, jadi wajah belum bisa didaftarkan. Santri tetap absen dengan kartu atau manual.</x-ui.banner>
                <div class="mt-3 flex flex-wrap gap-2">
                    <x-ui.button wire:click="requestConsent" wire:loading.attr="disabled" wire:target="requestConsent">Minta Persetujuan Wali</x-ui.button>
                    <x-ui.button variant="secondary" x-data x-on:click="$dispatch('open-modal', 'persetujuan-kertas')">Catat Surat Kertas</x-ui.button>
                </div>
            @else
                <p class="flex items-center gap-2 text-base font-semibold"><x-ui.icon name="circle-check" class="text-success" />Wali sudah setuju{{ $consent->method === 'paper' ? ' lewat surat' : '' }}</p>

                @if ($enrollment)
                    <p class="mt-2 text-base">Wajah sudah terdaftar. Ambil ulang di bawah untuk mengganti.</p>
                    <x-ui.button variant="danger" class="mt-2" wire:click="erase" wire:confirm="Hapus data wajah {{ $student->name }}?" wire:loading.attr="disabled" wire:target="erase">Hapus Data Wajah</x-ui.button>
                @endif

                <div class="mt-4 flex flex-col gap-3" x-data="faceEnroll({{ \App\Modules\Academic\Actions\EnrollFace::MIN_PHOTOS }})">
                    <div x-show="error" x-cloak role="alert" class="rounded-control bg-warning-soft p-3 text-base"><span x-text="error"></span> Pilih foto dari berkas di bawah.</div>
                    <div x-show="!error" class="relative mx-auto aspect-[3/4] w-full max-w-[320px] overflow-hidden rounded-card bg-text">
                        <video x-ref="video" autoplay playsinline muted class="size-full -scale-x-100 object-cover" aria-label="Kamera"></video>
                        <div class="pointer-events-none absolute inset-[12%] rounded-[50%] border-2 border-on-primary"></div>
                    </div>
                    <p class="text-center text-base" aria-live="polite"><span class="font-tabular font-semibold" x-text="shots.length"></span> dari <span x-text="needed"></span> foto. Minta santri menoleh sedikit tiap foto, di tempat terang.</p>
                    <div class="flex flex-wrap justify-center gap-2">
                        <x-ui.button icon="camera" x-show="!error" x-bind:disabled="busy || !ready" x-on:click="shoot()">Ambil Foto</x-ui.button>
                        <x-ui.button variant="secondary" x-show="shots.length > 0" x-on:click="shots = []">Ulangi</x-ui.button>
                        <x-ui.button x-show="shots.length >= needed" x-bind:disabled="busy" x-on:click="send()">Simpan Wajah</x-ui.button>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label for="field-photos" class="text-sm font-semibold">Atau pilih foto</label>
                        <input id="field-photos" type="file" accept="image/jpeg,image/png" multiple wire:model="photos" class="min-h-11 w-full text-base">
                        @if (count($photos) > 0)
                            <x-ui.button variant="secondary" class="self-start" wire:click="enroll" wire:loading.attr="disabled" wire:target="enroll,photos">Simpan {{ count($photos) }} Foto</x-ui.button>
                        @endif
                    </div>
                    @error('photos')<p role="alert" class="flex items-start gap-1 text-sm text-danger"><x-ui.icon name="circle-alert" size="sm" class="mt-0.5" />{{ $message }}</p>@enderror
                    @error('photos.*')<p role="alert" class="flex items-start gap-1 text-sm text-danger"><x-ui.icon name="circle-alert" size="sm" class="mt-0.5" />{{ $message }}</p>@enderror
                </div>
            @endif
        </x-ui.card>
    @endif

    <x-ui.modal name="persetujuan-kertas" title="Surat persetujuan">
        <div class="flex flex-col gap-3">
            <x-ui.select label="Wali yang menandatangani" name="paper_guardian" placeholder="Pilih" :options="$guardians" wire:model="paper_guardian" />
            <div class="flex flex-col gap-1">
                <label for="field-paper_document" class="text-sm font-semibold">Scan surat</label>
                <input id="field-paper_document" type="file" accept="image/jpeg,image/png,application/pdf" wire:model="paper_document" class="min-h-11 w-full text-base">
                @error('paper_document')<p role="alert" class="text-sm text-danger">{{ $message }}</p>@enderror
            </div>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button wire:click="recordPaper" wire:loading.attr="disabled" wire:target="recordPaper,paper_document">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
