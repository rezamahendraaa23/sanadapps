@php
    use App\Modules\Finance\Support\Rupiah;
    $categories = ['monthly' => 'Bulanan', 'yearly' => 'Tahunan', 'one_time' => 'Sekali', 'custom' => 'Kustom'];
@endphp

<div class="grid gap-4 min-[80rem]:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
    <x-ui.card>
        <div class="mb-3 flex items-center justify-between gap-2">
            <h2 class="text-lg font-semibold">Daftar jenis</h2>
            <x-ui.button icon="plus" wire:click="create">Tambah</x-ui.button>
        </div>
        @if ($types->isEmpty())
            <x-ui.empty-state icon="tags" message="Belum ada jenis tagihan. Tambahkan SPP, uang makan, dan lainnya." />
        @else
            <ul class="flex flex-col gap-1">
                @foreach ($types as $item)
                    <li wire:key="type-{{ $item->uuid }}">
                        <button
                            type="button"
                            wire:click="$set('selected', '{{ $item->uuid }}')"
                            @if ($selected === $item->uuid) aria-current="true" @endif
                            @class(['flex min-h-11 w-full flex-col rounded-control p-2 text-left hover:bg-primary-soft', 'bg-primary-soft' => $selected === $item->uuid])
                        >
                            <span class="font-semibold">{{ $item->name }} <span class="font-tabular text-sm font-normal text-text-muted">{{ $item->code }}</span></span>
                            <span class="text-sm text-text-muted">{{ $categories[$item->category] }} · {{ $item->rates_count }} tarif{{ $item->is_active ? '' : ' · Nonaktif' }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <div class="flex min-w-0 flex-col gap-4">
        @if ($type === null)
            <x-ui.card><x-ui.empty-state icon="tags" message="Pilih jenis tagihan untuk mengatur tarifnya." /></x-ui.card>
        @else
            <x-ui.card :title="'Tarif '.$type->name">
                <x-slot:actions>
                    <x-ui.button variant="secondary" wire:click="edit('{{ $type->uuid }}')">Ubah Jenis</x-ui.button>
                </x-slot:actions>

                @if ($rates->isEmpty())
                    <x-ui.empty-state icon="banknote" message="Belum ada tarif. Santri dilewati saat generate sampai tarif diatur." class="py-2" />
                @else
                    <x-ui.table caption="Daftar tarif">
                        <thead><tr><x-ui.th>Sasaran</x-ui.th><x-ui.th numeric>Nominal</x-ui.th><x-ui.th>Berlaku</x-ui.th><x-ui.th>Aksi</x-ui.th></tr></thead>
                        <tbody>
                            @foreach ($rates as $rate)
                                <tr wire:key="rate-{{ $rate->uuid }}">
                                    <x-ui.td label="Sasaran">{{ match ($rate->scope) {
                                        'default' => 'Semua santri',
                                        'level' => 'Jenjang '.$rate->scope_level,
                                        'classroom' => 'Kelas '.($classrooms[$rate->scope_id] ?? 'dihapus'),
                                        'dormitory' => 'Asrama '.($dormitories[$rate->scope_id] ?? 'dihapus'),
                                        'student' => 'Santri '.($students[$rate->scope_id] ?? 'dihapus'),
                                    } }}</x-ui.td>
                                    <x-ui.td label="Nominal" numeric>{{ Rupiah::format($rate->amount) }}</x-ui.td>
                                    <x-ui.td label="Berlaku">{{ $rate->valid_from->translatedFormat('j M Y') }} sampai {{ $rate->valid_to?->translatedFormat('j M Y') ?? 'seterusnya' }}</x-ui.td>
                                    <x-ui.td label="Aksi">
                                        @if ($rate->valid_to === null)
                                            <x-ui.button variant="tertiary" wire:click="endRate('{{ $rate->uuid }}')" wire:confirm="Akhiri tarif ini hari ini?">Akhiri</x-ui.button>
                                        @else
                                            -
                                        @endif
                                    </x-ui.td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                @endif

                <form wire:submit="addRate" class="mt-4 grid gap-4 border-t border-border pt-4 md:grid-cols-2">
                    <x-ui.select label="Berlaku untuk" name="rate_scope" required :options="['default' => 'Semua santri', 'level' => 'Satu jenjang', 'classroom' => 'Satu kelas', 'dormitory' => 'Satu asrama', 'student' => 'Satu santri']" wire:model.live="rate_scope" />
                    @if ($rate_scope === 'level')
                        <x-ui.select label="Jenjang" name="rate_target" required placeholder="Pilih jenjang" :options="$levels->all()" wire:model="rate_target" />
                    @elseif ($rate_scope === 'classroom')
                        <x-ui.select label="Kelas" name="rate_target" required placeholder="Pilih kelas" :options="$classrooms->all()" wire:model="rate_target" />
                    @elseif ($rate_scope === 'dormitory')
                        <x-ui.select label="Asrama" name="rate_target" required placeholder="Pilih asrama" :options="$dormitories->all()" wire:model="rate_target" />
                    @elseif ($rate_scope === 'student')
                        <x-ui.input label="NIS santri" name="rate_target" required wire:model="rate_target" />
                    @endif
                    <x-ui.input-rupiah label="Nominal" name="rate_amount" required wire:model="rate_amount" />
                    <x-ui.input label="Berlaku mulai" name="rate_from" type="date" required wire:model="rate_from" />
                    <x-ui.input label="Berlaku sampai" name="rate_to" type="date" help="Kosongkan bila tidak ada batas." wire:model="rate_to" />
                    <div class="flex items-end justify-end md:col-span-2">
                        <x-ui.button type="submit" class="w-full md:w-auto" wire:loading.attr="disabled" wire:target="addRate">Tambah Tarif</x-ui.button>
                    </div>
                </form>
            </x-ui.card>

            <x-ui.card title="Cek tarif efektif">
                <x-ui.input label="NIS santri" name="previewNis" help="Menampilkan tarif yang berlaku hari ini sesuai urutan prioritas." wire:model.live.debounce.300ms="previewNis" />
                @if ($preview)
                    <p class="mt-3 text-base" aria-live="polite">
                        @if (! $preview['found'])
                            NIS tidak ditemukan.
                        @elseif ($preview['rate'] === null)
                            {{ $preview['name'] }}: tarif belum diatur, santri ini akan dilewati.
                        @else
                            {{ $preview['name'] }}: <strong class="font-tabular">{{ Rupiah::format($preview['rate']['amount']) }}</strong> ({{ $preview['rate']['source'] }})
                        @endif
                    </p>
                @endif
            </x-ui.card>
        @endif
    </div>

    <x-ui.modal name="form-jenis" :title="$editingId ? 'Ubah jenis tagihan' : 'Tambah jenis tagihan'">
        <form id="form-jenis" wire:submit="save" class="flex flex-col gap-4">
            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.input label="Kode" name="code" required help="Contoh: SPP, MAKAN." wire:model="code" />
                <x-ui.input label="Nama" name="name" required wire:model="name" />
                <x-ui.select label="Kategori" name="category" required :options="$categories" wire:model="category" />
                <x-ui.input label="Hari jatuh tempo" name="due_day" inputmode="numeric" help="Tanggal 1 sampai 31." wire:model="due_day" />
            </div>
            <label class="flex min-h-11 items-center gap-3 text-base"><input type="checkbox" wire:model="is_mandatory" class="size-5 accent-primary">Wajib untuk semua santri aktif</label>
            <label class="flex min-h-11 items-center gap-3 text-base"><input type="checkbox" wire:model="allow_partial" class="size-5 accent-primary">Boleh dicicil</label>
            <div class="grid gap-4 md:grid-cols-3">
                <x-ui.select label="Denda" name="late_fee_type" placeholder="Tanpa denda" :options="['fixed' => 'Nominal tetap', 'percent' => 'Persen']" wire:model.live="late_fee_type" />
                @if ($late_fee_type)
                    <x-ui.input :label="$late_fee_type === 'percent' ? 'Nilai denda (%)' : 'Nilai denda (Rp)'" name="late_fee_value" inputmode="numeric" required wire:model="late_fee_value" />
                    <x-ui.input label="Setelah (hari)" name="late_fee_after_days" inputmode="numeric" wire:model="late_fee_after_days" />
                @endif
            </div>
            <label class="flex min-h-11 items-center gap-3 text-base"><input type="checkbox" wire:model="is_active" class="size-5 accent-primary">Aktif</label>
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button type="submit" form="form-jenis" wire:loading.attr="disabled" wire:target="save">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
