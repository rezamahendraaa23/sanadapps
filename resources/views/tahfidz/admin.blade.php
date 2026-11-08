<div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div role="tablist" aria-label="Bagian tahfidz" class="flex flex-wrap gap-1">
            @foreach (['rekap' => 'Rekap', 'target' => 'Target', 'amalan' => 'Amalan Mutabaah'] as $key => $label)
                <x-ui.tab :selected="$tab === $key" wire:key="tab-{{ $key }}" wire:click="selectTab('{{ $key }}')">{{ $label }}</x-ui.tab>
            @endforeach
        </div>
        <x-ui.button variant="secondary" :href="route('staff.tahfidz.index')" wire:navigate>Catat Setoran</x-ui.button>
    </div>

    @if ($tab === 'rekap')
        <x-ui.card>
            <x-ui.page-toolbar>
                <div class="grid min-w-0 flex-1 gap-3 md:grid-cols-3">
                    <x-ui.select label="Kelas" name="classroom" placeholder="Semua kelas" :options="$classrooms" wire:model.live="classroom" />
                    <x-ui.input label="Dari" name="from" type="date" wire:model.live="from" />
                    <x-ui.input label="Sampai" name="to" type="date" wire:model.live="to" />
                </div>
                <x-ui.button variant="secondary" icon="file-down" :href="route('staff.tahfidz.export', $params)">Ekspor</x-ui.button>
            </x-ui.page-toolbar>
            @if ($rows->isEmpty())
                <x-ui.empty-state icon="book-open" message="Belum ada setoran atau mutabaah di rentang ini." class="py-2" />
            @else
                <x-ui.table caption="Rekap setoran dan mutabaah">
                    <thead><tr><x-ui.th>Santri</x-ui.th><x-ui.th numeric>Ziyadah (hlm)</x-ui.th><x-ui.th numeric>Murojaah</x-ui.th><x-ui.th numeric>Tahsin</x-ui.th><x-ui.th numeric>Amalan</x-ui.th><x-ui.th>Ringkasan</x-ui.th></tr></thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr wire:key="r-{{ $row['student']->uuid }}">
                                <x-ui.td label="Santri"><span class="break-words">{{ $row['student']->name }}</span></x-ui.td>
                                <x-ui.td label="Ziyadah (hlm)" class="text-right"><span class="font-tabular">{{ rtrim(rtrim(number_format($row['ziyadah_pages'], 2, ',', ''), '0'), ',') }}</span></x-ui.td>
                                <x-ui.td label="Murojaah" class="text-right"><span class="font-tabular">{{ $row['murojaah'] }}</span></x-ui.td>
                                <x-ui.td label="Tahsin" class="text-right"><span class="font-tabular">{{ $row['tahsin'] }}</span></x-ui.td>
                                <x-ui.td label="Amalan" class="text-right"><span class="font-tabular">{{ $row['mutabaah'] }}</span></x-ui.td>
                                <x-ui.td label="Ringkasan"><x-ui.button variant="tertiary" icon="file-down" :href="route('staff.tahfidz.summary', $row['student'])">PDF<span class="sr-only"> {{ $row['student']->name }}</span></x-ui.button></x-ui.td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            @endif
        </x-ui.card>
    @elseif ($tab === 'target')
        <x-ui.card title="Target periode aktif untuk satu kelas">
            @if (! $hasTerm)
                <x-ui.banner tone="warning">Belum ada periode aktif.</x-ui.banner>
            @endif
            <form wire:submit="saveTarget" class="mt-2 grid gap-3 md:grid-cols-[1fr_140px_140px_auto] md:items-start">
                <x-ui.select label="Kelas" name="target_classroom" placeholder="Pilih" :options="$classrooms" wire:model="target_classroom" />
                <x-ui.input label="Halaman" name="target_pages" inputmode="decimal" wire:model="target_pages" />
                <x-ui.input label="Juz" name="target_juz" inputmode="decimal" wire:model="target_juz" />
                <x-ui.button type="submit" class="md:mt-6">Simpan Target</x-ui.button>
            </form>
        </x-ui.card>
    @else
        <x-ui.card title="Amalan mutabaah">
            <ul class="flex flex-col">
                @forelse ($items as $item)
                    <li wire:key="a-{{ $item->uuid }}" class="flex items-center justify-between gap-2 border-b border-border py-2 last:border-b-0">
                        <span @class(['min-w-0 text-base break-words', 'text-text-muted line-through' => ! $item->is_active])>{{ $item->name }}@if ($item->category)<span class="block text-sm text-text-muted">{{ $item->category }}</span>@endif</span>
                        <x-ui.button variant="tertiary" wire:click="toggleItem('{{ $item->uuid }}')">{{ $item->is_active ? 'Matikan' : 'Aktifkan' }}<span class="sr-only"> {{ $item->name }}</span></x-ui.button>
                    </li>
                @empty
                    <li><x-ui.empty-state icon="list-checks" message="Belum ada amalan." class="py-2" /></li>
                @endforelse
            </ul>
            <form wire:submit="saveItem" class="mt-4 grid gap-3 border-t border-border pt-4 md:grid-cols-[1fr_1fr_auto] md:items-start">
                <x-ui.input label="Nama amalan" name="item_name" wire:model="item_name" />
                <x-ui.input label="Kategori" name="item_category" wire:model="item_category" />
                <x-ui.button type="submit" variant="secondary" class="md:mt-6">Tambah</x-ui.button>
            </form>
        </x-ui.card>
    @endif
</div>
