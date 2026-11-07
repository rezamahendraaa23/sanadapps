@php $tz = $profile->timezone; $short = ['fajr' => 'Subuh', 'dhuhr' => 'Dzuhur', 'asr' => 'Ashar', 'maghrib' => 'Maghrib', 'isha' => 'Isya']; @endphp

<div class="flex flex-col gap-4">
    <div role="tablist" aria-label="Bagian masjid" class="flex flex-wrap gap-1">
        @foreach (['ringkasan' => 'Ringkasan', 'lokasi' => 'Lokasi & Metode', 'koreksi' => 'Koreksi & Iqamah', 'pembanding' => 'Uji Pembanding', 'bulanan' => 'Jadwal Bulanan'] as $key => $label)
            <x-ui.tab :selected="$tab === $key" wire:key="tab-{{ $key }}" wire:click="$set('tab', '{{ $key }}')">{{ $label }}</x-ui.tab>
        @endforeach
    </div>

    @if ($tab === 'ringkasan')
        @if (! $profile->hasLocation())
            <x-ui.banner tone="warning" role="alert">Lokasi masjid belum diisi, jadi jadwal belum bisa dihitung. Buka tab Lokasi & Metode.</x-ui.banner>
        @endif
        <div class="grid gap-4 lg:grid-cols-[2fr_1fr]">
            <x-ui.card :title="'Hari ini, '.now($tz)->translatedFormat('l j F Y')">
                <p class="text-sm text-text-muted">{{ $hijri['label'] }}{{ $hijri['approximate'] ? ' (perkiraan)' : '' }}</p>
                @if ($todayRow)
                    <dl class="mt-3 grid grid-cols-2 gap-2 md:grid-cols-4">
                        @foreach ($names as $key => $label)
                            <div class="rounded-control border border-border p-2"><dt class="text-sm text-text-muted">{{ $label }}</dt><dd class="font-display font-tabular text-2xl font-semibold">{{ str_replace(':', '.', $todayRow->{$key}) }}</dd></div>
                        @endforeach
                    </dl>
                    @if ($todayRow->source === 'override')<p class="mt-2 text-sm font-semibold">Ada jam yang diubah manual hari ini.</p>@endif
                @else
                    <x-ui.empty-state icon="clock" message="Jadwal hari ini belum tersedia." class="py-2" />
                @endif
            </x-ui.card>
            <x-ui.card title="Layar dan konten">
                <dl class="flex flex-col gap-2 text-base">
                    <div class="flex justify-between gap-2"><dt>Layar aktif</dt><dd class="font-tabular font-semibold">{{ $screens->count() }}</dd></div>
                    <div class="flex justify-between gap-2"><dt>Sedang online</dt><dd class="font-tabular font-semibold">{{ $screens->filter->isOnline()->count() }}</dd></div>
                    <div class="flex justify-between gap-2"><dt>Konten tayang</dt><dd class="font-tabular font-semibold">{{ $live }}</dd></div>
                    <div class="flex justify-between gap-2"><dt>Menunggu persetujuan</dt><dd class="font-tabular font-semibold">{{ $pending }}</dd></div>
                </dl>
                <div class="mt-3 flex flex-wrap gap-2">
                    <x-ui.button variant="secondary" :href="route('staff.masjid.screens')" wire:navigate>Layar</x-ui.button>
                    <x-ui.button variant="secondary" :href="route('staff.masjid.contents')" wire:navigate>Konten</x-ui.button>
                </div>
            </x-ui.card>
        </div>
    @elseif ($tab === 'lokasi')
        <x-ui.card title="Lokasi masjid">
            <div class="flex flex-col gap-3">
                <x-ui.input label="Nama masjid" name="name" required wire:model="name" :disabled="! $canSettings" />
                <x-ui.input label="Kota atau kabupaten" name="city" required help="Dipakai untuk mencari jadwal resmi pembanding." wire:model="city" :disabled="! $canSettings" />
                @if ($canSettings)
                    <div class="grid gap-3 md:grid-cols-[1fr_auto] md:items-start">
                        <x-ui.input label="Cari lokasi" name="query" help="Nama masjid, alamat, atau kota. Hanya teks ini yang dikirim ke layanan peta." wire:model="query" />
                        <x-ui.button variant="secondary" class="md:mt-6" wire:click="findLocation" wire:loading.attr="disabled" wire:target="findLocation">Cari Koordinat</x-ui.button>
                    </div>
                @endif
                @if ($foundLabel)<p class="text-sm break-words">Ditemukan: {{ $foundLabel }}. Periksa titiknya di peta sebelum menyimpan.</p>@endif
                <div class="grid gap-3 md:grid-cols-3">
                    <x-ui.input label="Lintang" name="latitude" inputmode="decimal" required wire:model.blur="latitude" :disabled="! $canSettings" />
                    <x-ui.input label="Bujur" name="longitude" inputmode="decimal" required wire:model.blur="longitude" :disabled="! $canSettings" />
                    <x-ui.select label="Zona waktu" name="timezone" :options="['Asia/Jakarta' => 'WIB', 'Asia/Makassar' => 'WITA', 'Asia/Jayapura' => 'WIT']" wire:model="timezone" :disabled="! $canSettings" />
                </div>
                @if (is_numeric($latitude) && is_numeric($longitude))
                    <a class="text-sm font-semibold text-primary underline" target="_blank" rel="noopener" href="https://www.openstreetmap.org/?mlat={{ $latitude }}&mlon={{ $longitude }}#map=17/{{ $latitude }}/{{ $longitude }}">Lihat titik di peta</a>
                @endif
            </div>
        </x-ui.card>
        <x-ui.card title="Metode dan mode">
            <div class="grid gap-3 md:grid-cols-3">
                <x-ui.input label="Sudut Subuh" name="fajr_angle" inputmode="decimal" help="Kemenag RI: 20" wire:model="fajr_angle" :disabled="! $canSettings" />
                <x-ui.input label="Sudut Isya" name="isha_angle" inputmode="decimal" help="Kemenag RI: 18" wire:model="isha_angle" :disabled="! $canSettings" />
                <x-ui.select label="Ashar" name="asr_method" :options="['standard' => 'Standar (1x bayangan)', 'hanafi' => 'Hanafi (2x bayangan)']" wire:model="asr_method" :disabled="! $canSettings" />
                <x-ui.input label="Imsak sebelum Subuh (menit)" name="imsak_minutes" inputmode="numeric" wire:model="imsak_minutes" :disabled="! $canSettings" />
                <x-ui.input label="Hitung mundur adzan (menit)" name="countdown_minutes" inputmode="numeric" wire:model="countdown_minutes" :disabled="! $canSettings" />
                <x-ui.select label="Sumber jadwal" name="schedule_source" :options="['computed' => 'Hitung sendiri', 'api' => 'Jadwal resmi bila ada, cadangan hitung sendiri']" wire:model="schedule_source" :disabled="! $canSettings" />
                <x-ui.input label="Koreksi tanggal hijriah (hari)" name="hijri_offset_days" inputmode="numeric" wire:model="hijri_offset_days" :disabled="! $canSettings" />
                <x-ui.input label="Ramadan mulai" name="ramadan_start" type="date" help="Isi setelah keputusan resmi." wire:model="ramadan_start" :disabled="! $canSettings" />
                <x-ui.input label="Ramadan selesai" name="ramadan_end" type="date" wire:model="ramadan_end" :disabled="! $canSettings" />
            </div>
            <label class="mt-3 flex min-h-11 items-center gap-3 text-base"><input type="checkbox" wire:model="friday_mode" @disabled(! $canSettings) class="size-5 accent-primary">Mode Jumat otomatis</label>
            <label class="flex min-h-11 items-center gap-3 text-base"><input type="checkbox" wire:model="auto_attendance" @disabled(! $canSettings) class="size-5 accent-primary">Buka sesi absensi sholat tiap asrama saat iqamah</label>
            @if ($canSettings)
                <x-ui.button class="mt-3" wire:click="saveProfile" wire:loading.attr="disabled" wire:target="saveProfile">Simpan dan Hitung Ulang</x-ui.button>
            @endif
        </x-ui.card>
        @if ($canSettings)
            <x-ui.card title="Awal bulan hijriah resmi">
                <p class="text-base">Isi setelah ada keputusan resmi, misalnya awal Ramadan. Sistem tidak menetapkannya sendiri.</p>
                <div class="mt-3 grid gap-3 md:grid-cols-[120px_1fr_1fr_auto] md:items-start">
                    <x-ui.input label="Tahun" name="hijri_year" inputmode="numeric" wire:model="hijri_year" />
                    <x-ui.select label="Bulan" name="hijri_month" :options="array_combine(range(1, 12), \App\Modules\Masjid\Support\HijriDate::MONTHS)" wire:model="hijri_month" />
                    <x-ui.input label="Tanggal 1 jatuh pada" name="hijri_start" type="date" wire:model="hijri_start" />
                    <x-ui.button variant="secondary" class="md:mt-6" wire:click="setHijriMonth">Simpan</x-ui.button>
                </div>
            </x-ui.card>
        @endif
    @elseif ($tab === 'koreksi')
        <x-ui.card title="Iqamah">
            <x-ui.table caption="Pengaturan iqamah">
                <thead><tr><x-ui.th>Sholat</x-ui.th><x-ui.th>Iqamah (menit)</x-ui.th><x-ui.th>Lama sholat</x-ui.th><x-ui.th>Jumat</x-ui.th><x-ui.th>Ramadan</x-ui.th></tr></thead>
                <tbody>
                    @foreach ($short as $key => $label)
                        <tr wire:key="iq-{{ $key }}">
                            <x-ui.td label="Sholat">{{ $label }}</x-ui.td>
                            @foreach (['minutes', 'duration', 'friday', 'ramadan'] as $field)
                                <x-ui.td :label="$field"><input type="text" inputmode="numeric" wire:model="iqamah.{{ $key }}.{{ $field }}" aria-label="{{ $label }} {{ ['minutes' => 'iqamah', 'duration' => 'lama sholat', 'friday' => 'Jumat', 'ramadan' => 'Ramadan'][$field] }}" class="min-h-11 w-20 rounded-control border border-text-muted bg-surface px-2 text-base"></x-ui.td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
            @error('iqamah.*')<p role="alert" class="text-sm text-danger">{{ $message }}</p>@enderror
            <x-ui.button class="mt-3" wire:click="saveIqamah" wire:loading.attr="disabled" wire:target="saveIqamah">Simpan Iqamah</x-ui.button>
        </x-ui.card>
        @if ($canSettings)
            <x-ui.card title="Koreksi menit (ihtiyath)">
                <p class="text-sm text-text-muted">Tanpa koreksi di sini, sistem memakai koreksi bawaan yang sudah dicocokkan dengan jadwal Kemenag.</p>
                <div class="mt-3 grid gap-3 md:grid-cols-[1fr_120px_1fr_auto] md:items-start">
                    <x-ui.select label="Waktu" name="adj_prayer" :options="$names" wire:model="adj_prayer" />
                    <x-ui.input label="Menit" name="adj_minutes" inputmode="numeric" help="Boleh negatif." wire:model="adj_minutes" />
                    <x-ui.input label="Berlaku mulai" name="adj_from" type="date" wire:model="adj_from" />
                    <x-ui.button variant="secondary" class="md:mt-6" wire:click="addAdjustment">Simpan</x-ui.button>
                </div>
                @foreach ($adjustments as $adjustment)
                    <p wire:key="adj-{{ $adjustment->id }}" class="border-b border-border py-1 text-base last:border-b-0">{{ $names[$adjustment->prayer] }} <span class="font-tabular">{{ $adjustment->minutes > 0 ? '+' : '' }}{{ $adjustment->minutes }} menit</span>, mulai {{ $adjustment->effective_from->translatedFormat('j M Y') }}</p>
                @endforeach
            </x-ui.card>
        @endif
        <x-ui.card title="Ubah jam pada tanggal tertentu">
            <div class="grid gap-3 md:grid-cols-4 md:items-start">
                <x-ui.input label="Tanggal" name="ovr_date" type="date" wire:model="ovr_date" />
                <x-ui.select label="Waktu" name="ovr_prayer" :options="$names" wire:model="ovr_prayer" />
                <x-ui.input label="Jam" name="ovr_time" type="time" wire:model="ovr_time" />
                <x-ui.input label="Alasan" name="ovr_reason" wire:model="ovr_reason" />
            </div>
            <x-ui.button variant="secondary" class="mt-3" wire:click="addOverride" wire:loading.attr="disabled" wire:target="addOverride">Simpan Perubahan</x-ui.button>
            @foreach ($overrides as $override)
                <div wire:key="ovr-{{ $override->uuid }}" class="mt-2 flex flex-wrap items-center justify-between gap-2 border-b border-border py-1 last:border-b-0">
                    <span class="text-base break-words">{{ $override->date->translatedFormat('j M Y') }}, {{ $names[$override->prayer] }} <span class="font-tabular">{{ substr($override->time, 0, 5) }}</span>: {{ $override->reason }}</span>
                    <x-ui.button variant="tertiary" wire:click="removeOverride('{{ $override->uuid }}')">Hapus<span class="sr-only"> perubahan {{ $override->date->toDateString() }}</span></x-ui.button>
                </div>
            @endforeach
        </x-ui.card>
    @elseif ($tab === 'pembanding')
        <x-ui.card title="Bandingkan dengan jadwal resmi">
            <p class="text-base">Sumbernya layanan tidak resmi yang memuat data Kemenag Bimas Islam, jadi dipakai sebagai pembanding. Selisih lebih dari 1 menit ditandai.</p>
            <div class="mt-3 grid gap-3 md:grid-cols-[200px_auto] md:items-start">
                <x-ui.input label="Bulan" name="month" type="month" wire:model.live="month" />
                @if ($canSettings)<x-ui.button class="md:mt-6" wire:click="fetchReference" wire:loading.attr="disabled" wire:target="fetchReference">Ambil Jadwal Resmi</x-ui.button>@endif
            </div>
            @if ($canSettings)
                <div class="mt-3 flex flex-col gap-1">
                    <label for="field-referenceFile" class="text-sm font-semibold">Atau impor manual (kolom: tanggal, subuh, terbit, dzuhur, ashar, maghrib, isya)</label>
                    <input id="field-referenceFile" type="file" accept=".xlsx,.csv" wire:model="referenceFile" class="min-h-11 w-full text-base">
                    @error('referenceFile')<p role="alert" class="text-sm text-danger">{{ $message }}</p>@enderror
                    @if ($referenceFile)<x-ui.button variant="secondary" class="self-start" wire:click="importReference">Impor</x-ui.button>@endif
                </div>
            @endif
        </x-ui.card>
        <x-ui.card title="Selisih">
            @if (! $comparison || $comparison['compared'] === 0)
                <x-ui.empty-state icon="scale" message="Belum ada jadwal pembanding untuk bulan ini." class="py-2" />
            @else
                <x-ui.banner :tone="$comparison['flagged'] ? 'warning' : 'success'">{{ $comparison['flagged'] ? $comparison['flagged'].' waktu berselisih lebih dari 1 menit. Atur koreksi sampai lolos.' : 'Semua waktu berselisih paling banyak 1 menit.' }}</x-ui.banner>
                <x-ui.table caption="Selisih jadwal">
                    <thead><tr><x-ui.th>Tanggal</x-ui.th>@foreach ($short as $label)<x-ui.th>{{ $label }}</x-ui.th>@endforeach</tr></thead>
                    <tbody>
                        @foreach ($comparison['rows'] as $row)
                            <tr wire:key="cmp-{{ $row['date'] }}">
                                <x-ui.td label="Tanggal">{{ \Carbon\CarbonImmutable::parse($row['date'])->translatedFormat('j M') }}</x-ui.td>
                                @foreach ($row['cells'] as $prayer => $cell)
                                    <x-ui.td :label="$short[$prayer]"><span @class(['font-tabular text-sm', 'font-bold text-danger' => $cell['bad']])>{{ $cell['ours'] ?? '-' }} / {{ $cell['reference'] ?? '-' }}{{ $cell['diff'] !== null ? ' ('.($cell['diff'] > 0 ? '+' : '').$cell['diff'].')' : '' }}{{ $cell['bad'] ? ' lebih dari 1 menit' : '' }}</span></x-ui.td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            @endif
        </x-ui.card>
    @else
        <x-ui.card title="Jadwal bulanan">
            <div class="flex flex-wrap items-end gap-2">
                <div class="w-48"><x-ui.input label="Bulan" name="month" type="month" wire:model.live="month" /></div>
                <x-ui.button variant="secondary" icon="file-down" wire:click="exportMonth">Excel</x-ui.button>
                <x-ui.button variant="secondary" icon="file-down" :href="route('staff.masjid.month-pdf', ['month' => $month])">PDF</x-ui.button>
            </div>
            @if ($monthRows->isEmpty())
                <x-ui.empty-state icon="calendar" message="Jadwal bulan ini belum dihitung. Jadwal dihitung 60 hari ke depan." class="py-2" />
            @else
                <x-ui.table caption="Jadwal bulanan">
                    <thead><tr><x-ui.th>Tanggal</x-ui.th>@foreach ($names as $label)<x-ui.th>{{ $label }}</x-ui.th>@endforeach</tr></thead>
                    <tbody>
                        @foreach ($monthRows as $row)
                            <tr wire:key="m-{{ $row->id }}"><x-ui.td label="Tanggal">{{ $row->date->translatedFormat('j D') }}</x-ui.td>@foreach (array_keys($names) as $key)<x-ui.td :label="$names[$key]"><span class="font-tabular">{{ str_replace(':', '.', $row->{$key}) }}</span></x-ui.td>@endforeach</tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            @endif
        </x-ui.card>
    @endif
</div>
