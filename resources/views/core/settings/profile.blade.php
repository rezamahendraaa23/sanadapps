<div class="mx-auto grid w-full max-w-[720px] gap-4">
    <x-ui.card>
        <form wire:submit="save" class="flex flex-col gap-4">
            <x-ui.input label="Nama pesantren" name="name" required wire:model="name" />
            <x-ui.textarea label="Alamat" name="address" help="Tampil di kop kwitansi dan rapor." wire:model="address" />
            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.input label="Telepon" name="phone" type="tel" inputmode="tel" wire:model="phone" />
                <x-ui.input label="Email" name="email" type="email" wire:model="email" />
            </div>

            <div class="flex flex-col gap-2">
                <p class="text-sm font-semibold">Logo pesantren</p>
                <div class="flex flex-wrap items-center gap-4">
                    @if ($logo && method_exists($logo, 'temporaryUrl') && str_starts_with((string) $logo->getMimeType(), 'image/'))
                        <img src="{{ $logo->temporaryUrl() }}" alt="Pratinjau logo baru" class="size-16 rounded-control border border-border object-contain">
                    @elseif ($profile->logo_path)
                        <img src="{{ Storage::disk('public')->url($profile->logo_path) }}" alt="Logo saat ini" class="size-16 rounded-control border border-border object-contain">
                    @else
                        <span class="flex size-16 items-center justify-center rounded-full bg-primary-soft font-display text-2xl font-semibold text-primary" aria-hidden="true">{{ mb_strtoupper(mb_substr($name ?: 'S', 0, 1)) }}</span>
                    @endif
                    <div class="flex min-w-0 flex-1 flex-col gap-2">
                        <x-ui.file-drop label="Logo pesantren" name="logo" accept="image/png,image/jpeg,image/webp" hint="PNG, JPG, atau WebP, maksimal 2 MB" wire:model="logo" />
                    </div>
                    @if ($profile->logo_path)
                        <x-ui.button variant="tertiary" wire:click="removeLogo">Hapus logo</x-ui.button>
                    @endif
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <x-ui.input label="Warna utama" name="brand_color" required maxlength="7" autocomplete="off" help="Format #RRGGBB. Dipakai di tombol, menu aktif, dan kartu utama." wire:model.live.blur="brand_color" />
                @if ($colorIsValid)
                    <div class="flex flex-wrap items-center gap-3" aria-live="polite">
                        <span class="inline-flex min-h-11 items-center rounded-control px-4 text-base font-semibold text-on-primary" style="background-color: {{ $brand_color }}">Contoh tombol</span>
                        @if ($colorPasses)
                            <span class="flex items-center gap-1 text-sm text-success"><x-ui.icon name="circle-check" size="sm" />Kontras {{ number_format($contrast, 1, ',', '') }}:1, lolos</span>
                        @else
                            <span class="flex items-center gap-1 text-sm text-danger"><x-ui.icon name="circle-alert" size="sm" />Kontras {{ number_format($contrast, 1, ',', '') }}:1, belum lolos 4,5:1</span>
                        @endif
                    </div>
                @endif
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.select label="Zona waktu" name="timezone" required :options="['Asia/Jakarta' => 'WIB (Asia/Jakarta)', 'Asia/Makassar' => 'WITA (Asia/Makassar)', 'Asia/Jayapura' => 'WIT (Asia/Jayapura)']" wire:model="timezone" />
                <x-ui.input label="Pola NIS otomatis" name="nis_pattern" required help="Contoh {tahun}{urut:4} menjadi 20260001." wire:model="nis_pattern" />
            </div>

            <div class="flex justify-end">
                <x-ui.button type="submit" class="w-full md:w-auto" wire:loading.attr="disabled" wire:target="save,logo">
                    <span wire:loading.remove wire:target="save">Simpan Profil</span>
                    <span wire:loading wire:target="save">Menyimpan…</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
</div>
