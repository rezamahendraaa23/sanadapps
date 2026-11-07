<div class="flex flex-col gap-4">
    <x-ui.card>
        <x-ui.page-toolbar>
            <div class="grid min-w-0 flex-1 gap-3 md:grid-cols-3">
                <x-ui.select label="Kelas" name="classroom" placeholder="Semua kelas" :options="$classrooms" wire:model.live="classroom" />
                <x-ui.input label="Dari" name="from" type="date" wire:model.live="from" />
                <x-ui.input label="Sampai" name="to" type="date" wire:model.live="to" />
            </div>
            <div class="flex flex-wrap gap-2">
                <x-ui.button variant="secondary" icon="file-down" :href="route('staff.attendance.export', $params)">Ekspor Excel</x-ui.button>
                <x-ui.button variant="secondary" icon="file-down" :href="route('staff.attendance.pdf', $params)">Ekspor PDF</x-ui.button>
            </div>
        </x-ui.page-toolbar>

        @if ($rows->isEmpty())
            <x-ui.empty-state icon="calendar-check" message="Belum ada absensi tersimpan di rentang ini." class="py-2" />
        @else
            <p class="mb-3 text-base">Rata-rata kehadiran <strong class="font-tabular">{{ $average }}%</strong> dari {{ $rows->count() }} santri.</p>
            <x-ui.table caption="Rekap kehadiran per santri">
                <thead><tr><x-ui.th>Santri</x-ui.th><x-ui.th numeric>Hadir</x-ui.th><x-ui.th numeric>Terlambat</x-ui.th><x-ui.th numeric>Izin</x-ui.th><x-ui.th numeric>Sakit</x-ui.th><x-ui.th numeric>Alpa</x-ui.th><x-ui.th numeric>Kehadiran</x-ui.th></tr></thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr wire:key="rekap-{{ $row['student']->uuid }}">
                            <x-ui.td label="Santri"><span class="break-words">{{ $row['student']->name }}</span></x-ui.td>
                            @foreach (['hadir' => 'Hadir', 'terlambat' => 'Terlambat', 'izin' => 'Izin', 'sakit' => 'Sakit', 'alpa' => 'Alpa'] as $key => $label)
                                <x-ui.td :label="$label" class="text-right"><span class="font-tabular">{{ $row[$key] }}</span></x-ui.td>
                            @endforeach
                            <x-ui.td label="Kehadiran" class="text-right"><span class="font-tabular font-semibold">{{ $row['percent'] }}%</span></x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>
</div>
