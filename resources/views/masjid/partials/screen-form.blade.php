{{-- Form layar, dipakai saat memasang dan mengubah. Disusun per bagian agar urutannya jelas. --}}
<form id="{{ $formId }}" wire:submit="{{ $submit }}" class="flex flex-col gap-5">
    <section class="flex flex-col gap-3">
        <h3 class="flex items-center gap-2 text-base font-semibold"><span class="inline-flex size-7 items-center justify-center rounded-full bg-primary text-sm text-on-primary">1</span>{{ $withCode ? 'Sambungkan' : 'Nama layar' }}</h3>
        <div @class(['grid gap-3', 'md:grid-cols-[12rem_minmax(0,1fr)]' => $withCode])>
            @if ($withCode)
                <div class="flex flex-col gap-1">
                    <label for="field-code" class="text-sm font-semibold">Kode di layar <span class="text-danger" aria-hidden="true">*</span></label>
                    <input id="field-code" name="code" inputmode="numeric" autocomplete="off" maxlength="6" required wire:model="code" placeholder="000000"
                        @class(['font-tabular min-h-14 w-full rounded-control border bg-surface px-3 text-center text-2xl font-semibold tracking-[0.3em]', 'border-danger' => $errors->has('code'), 'border-text-muted' => ! $errors->has('code')])>
                    @error('code')<p role="alert" class="text-sm text-danger">{{ $message }}</p>@enderror
                </div>
            @endif
            <x-ui.input label="Nama layar" name="name" required help="Contoh: TV Masjid Utama." wire:model="name" />
        </div>
    </section>

    <section class="flex flex-col gap-3 border-t border-border pt-4">
        <h3 class="flex items-center gap-2 text-base font-semibold"><span class="inline-flex size-7 items-center justify-center rounded-full bg-primary text-sm text-on-primary">2</span>Tempat dan tampilan</h3>
        <div class="grid gap-3 md:grid-cols-2">
            <x-ui.input label="Lokasi" name="location" help="Contoh: serambi masjid." wire:model="location" />
            <x-ui.select label="Profil" name="profile" :options="$profiles" wire:model="profile" />
            <x-ui.select label="Orientasi" name="orientation" :options="['landscape' => 'Mendatar', 'portrait' => 'Tegak']" wire:model="orientation" />
            <x-ui.select label="Pola pengumuman" name="content_pattern" :options="['rotate' => 'Bergilir satu per satu', 'list' => 'Daftar, tiga sekaligus']" wire:model="content_pattern" />
        </div>
        <p class="text-sm text-text-muted">Profil "Hanya pengumuman" menampilkan konten tanpa jam dan hitung mundur sholat.</p>
    </section>

    <section class="flex flex-col gap-3 border-t border-border pt-4">
        <h3 class="flex items-center gap-2 text-base font-semibold"><span class="inline-flex size-7 items-center justify-center rounded-full bg-primary text-sm text-on-primary">3</span>Struktur pesantren</h3>
        @if ($charts === [])
            <p class="text-sm text-text-muted">Belum ada bagan. Susun dulu di menu Struktur Pesantren.</p>
        @else
            <fieldset class="flex flex-wrap gap-2">
                <legend class="sr-only">Bagan yang tampil di layar ini</legend>
                @foreach ($charts as $chartKey => $chartName)
                    <label wire:key="oc-{{ $formId }}-{{ $chartKey }}" class="flex min-h-11 cursor-pointer items-center gap-2 rounded-full border border-border px-4 text-base has-checked:border-primary has-checked:bg-primary-soft">
                        <input type="checkbox" value="{{ $chartKey }}" wire:model="org_charts" class="size-5 accent-primary">{{ $chartName }}
                    </label>
                @endforeach
            </fieldset>
            <x-ui.select label="Cara tampil struktur" name="org_mode" :options="['rotate' => 'Ikut bergiliran dengan konten lain', 'always' => 'Tampil terus (layar khusus struktur)']" wire:model="org_mode" />
        @endif
    </section>

    <section class="flex flex-col gap-3 border-t border-border pt-4">
        <h3 class="flex items-center gap-2 text-base font-semibold"><span class="inline-flex size-7 items-center justify-center rounded-full bg-primary text-sm text-on-primary">4</span>Jam redup <span class="text-sm font-normal text-text-muted">(tidak wajib)</span></h3>
        <div class="grid gap-3 md:grid-cols-2">
            <x-ui.input label="Redup mulai" name="dim_from" type="time" wire:model="dim_from" />
            <x-ui.input label="Redup sampai" name="dim_until" type="time" wire:model="dim_until" />
        </div>
        <p class="text-sm text-text-muted">Misalnya 22.00 sampai 03.30 agar layar tidak terlalu terang di malam hari.</p>
    </section>
</form>
