{{-- Tahun ajaran dan term dalam satu tampilan bertingkat; term selalu berada di dalam tahun ajarannya. --}}
<div class="flex flex-col gap-4">
    <x-ui.card>
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div class="max-w-2xl">
                <h2 class="text-lg font-semibold">Untuk apa tahun ajaran aktif?</h2>
                <p class="mt-1 text-sm text-text-muted">Menjadi patokan santri baru masuk, jadwal pelajaran, nilai dan rapor, catatan pelanggaran, dan kartu santri. Term adalah pembagian di dalamnya: satu periode setahun penuh (cocok untuk sistem fase) atau dua semester.</p>
                <p class="mt-2 flex items-start gap-2 text-sm"><x-ui.icon name="refresh-cw" size="sm" class="mt-0.5 shrink-0 text-primary" />Cukup diatur sekali. Saat term atau tahun ajaran berakhir, sistem mengaktifkan term berikutnya dan membuat tahun ajaran baru sendiri.</p>
            </div>
            @if ($rows->isNotEmpty())
                <x-ui.button icon="plus" class="w-full shrink-0 md:w-auto" wire:click="create">Tambah Tahun Ajaran</x-ui.button>
            @endif
        </div>
    </x-ui.card>

    @if ($rows->isEmpty())
        <x-ui.card>
            <h2 class="text-lg font-semibold">Mulai cepat</h2>
            <p class="mt-1 text-base">Tahun ajaran <strong>{{ $suggestion['name'] }}</strong>, {{ \Illuminate\Support\Carbon::parse($suggestion['start_date'])->translatedFormat('j F Y') }} sampai {{ \Illuminate\Support\Carbon::parse($suggestion['end_date'])->translatedFormat('j F Y') }}.</p>
            <fieldset class="mt-4 grid gap-2 md:grid-cols-2">
                <legend class="mb-2 text-sm font-semibold">Dibagi menjadi</legend>
                @foreach (['year' => ['Setahun penuh', 'Satu periode. Cocok bila kenaikan diatur per fase kurikulum.'], 'semester' => ['Dua semester', 'Semester 1 dan Semester 2, masing-masing enam bulan.']] as $value => [$label, $hint])
                    <label class="flex min-h-11 cursor-pointer items-start gap-3 rounded-control border border-border p-3 has-checked:border-primary has-checked:bg-primary-soft">
                        <input type="radio" name="split-cepat" value="{{ $value }}" wire:model="split" class="mt-1 size-5 accent-primary">
                        <span><span class="block font-semibold">{{ $label }}</span><span class="block text-sm text-text-muted">{{ $hint }}</span></span>
                    </label>
                @endforeach
            </fieldset>
            <div class="mt-4 flex flex-wrap gap-2">
                <x-ui.button icon="check" wire:click="quickStart" wire:loading.attr="disabled" wire:target="quickStart">Buat dan Aktifkan</x-ui.button>
                <x-ui.button variant="secondary" wire:click="create">Atur Tanggal Sendiri</x-ui.button>
            </div>
        </x-ui.card>
    @endif

    @foreach ($rows as $year)
        <x-ui.card wire:key="year-{{ $year->id }}" @class(['border-primary' => $year->is_active])>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-display flex flex-wrap items-center gap-2 text-xl font-semibold">
                        {{ $year->name }}
                        @if ($year->is_active)<x-ui.status-badge status="active" />@endif
                    </h2>
                    <p class="text-sm text-text-muted">{{ $year->start_date->translatedFormat('j M Y') }} sampai {{ $year->end_date->translatedFormat('j M Y') }}</p>
                </div>
                <div class="flex flex-wrap gap-1">
                    @unless ($year->is_active)
                        <x-ui.button variant="secondary" wire:click="activate({{ $year->id }}, 'years')">Jadikan aktif<span class="sr-only"> {{ $year->name }}</span></x-ui.button>
                    @endunless
                    <x-ui.button variant="tertiary" wire:click="edit({{ $year->id }}, 'years')">Ubah<span class="sr-only"> {{ $year->name }}</span></x-ui.button>
                    @unless ($year->is_active)
                        <x-ui.button variant="tertiary" class="!text-danger hover:!bg-danger-soft" wire:click="askDelete({{ $year->id }}, 'years')">Hapus<span class="sr-only"> {{ $year->name }}</span></x-ui.button>
                    @endunless
                </div>
            </div>

            <div class="mt-4 border-t border-border pt-3">
                @if ($year->terms->isEmpty())
                    <p class="text-sm text-text-muted">Belum ada term di tahun ajaran ini.</p>
                @else
                    <x-ui.table :caption="'Term '.$year->name">
                        <thead>
                            <tr><x-ui.th>Term</x-ui.th><x-ui.th>Periode</x-ui.th><x-ui.th>Status</x-ui.th><x-ui.th>Aksi</x-ui.th></tr>
                        </thead>
                        <tbody>
                            @foreach ($year->terms as $term)
                                <tr wire:key="term-{{ $term->id }}">
                                    <x-ui.td label="Term">{{ $term->name }}</x-ui.td>
                                    <x-ui.td label="Periode">{{ $term->start_date->translatedFormat('j M Y') }} sampai {{ $term->end_date->translatedFormat('j M Y') }}</x-ui.td>
                                    <x-ui.td label="Status">
                                        @if ($term->is_active)
                                            <x-ui.status-badge status="active" />
                                        @else
                                            <x-ui.button variant="tertiary" wire:click="activate({{ $term->id }}, 'terms')">Jadikan aktif<span class="sr-only"> {{ $term->name }} {{ $year->name }}</span></x-ui.button>
                                        @endif
                                    </x-ui.td>
                                    <x-ui.td label="Aksi">
                                        <span class="inline-flex gap-1">
                                            <x-ui.button variant="tertiary" wire:click="edit({{ $term->id }}, 'terms')">Ubah<span class="sr-only"> {{ $term->name }}</span></x-ui.button>
                                            @unless ($term->is_active)
                                                <x-ui.button variant="tertiary" class="!text-danger hover:!bg-danger-soft" wire:click="askDelete({{ $term->id }}, 'terms')">Hapus<span class="sr-only"> {{ $term->name }}</span></x-ui.button>
                                            @endunless
                                        </span>
                                    </x-ui.td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                @endif
                <x-ui.button variant="tertiary" icon="plus" class="mt-2" wire:click="createTerm({{ $year->id }})">Tambah Term<span class="sr-only"> di {{ $year->name }}</span></x-ui.button>
            </div>
        </x-ui.card>
    @endforeach
</div>
