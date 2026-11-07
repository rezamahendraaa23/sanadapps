@php $tz = config('sanad.display_timezone'); @endphp

<div class="flex flex-col gap-4">
    @include('leave.partials.gate-nav')
    <x-ui.card>
        <x-ui.page-toolbar>
            <div class="grid min-w-0 flex-1 gap-3 md:max-w-xl md:grid-cols-2">
                <x-ui.input label="Tanggal" name="date" type="date" wire:model.live="date" />
                <x-ui.select label="Hasil" name="result" placeholder="Semua hasil" :options="$results" wire:model.live="result" />
            </div>
            <x-ui.button variant="secondary" icon="file-down" :href="route('staff.gate.logs.export', array_filter(['tanggal' => $date, 'hasil' => $result]))">Ekspor</x-ui.button>
        </x-ui.page-toolbar>
        @if ($logs->isEmpty())
            <x-ui.empty-state icon="history" message="Belum ada catatan." class="py-2" />
        @else
            <x-ui.table caption="Riwayat keluar masuk gerbang">
                <thead><tr><x-ui.th>Waktu</x-ui.th><x-ui.th>Santri</x-ui.th><x-ui.th>Arah</x-ui.th><x-ui.th>Cara</x-ui.th><x-ui.th>Hasil</x-ui.th><x-ui.th>Catatan</x-ui.th></tr></thead>
                <tbody>
                    @foreach ($logs as $log)
                        <tr wire:key="l-{{ $log->uuid }}">
                            <x-ui.td label="Waktu">{{ $log->occurred_at->setTimezone($tz)->translatedFormat('j M, H.i') }}</x-ui.td>
                            <x-ui.td label="Santri"><span class="break-words">{{ $log->student?->name ?? 'Tidak dikenal' }}</span></x-ui.td>
                            <x-ui.td label="Arah">{{ $log->direction === 'out' ? 'Keluar' : 'Masuk' }}</x-ui.td>
                            <x-ui.td label="Cara">{{ $methods[$log->method] }}</x-ui.td>
                            <x-ui.td label="Hasil"><span class="inline-flex items-center gap-1 text-sm font-semibold"><x-ui.icon :name="$log->result === 'ok' ? 'circle-check' : 'circle-alert'" size="sm" />{{ $results[$log->result] }}</span></x-ui.td>
                            <x-ui.td label="Catatan"><span class="break-words">{{ collect([$log->deny_reason, $log->note, $log->pickup_name ? 'Dijemput '.$log->pickup_name : null])->filter()->join('. ') ?: '-' }}</span></x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
            <div class="mt-4">{{ $logs->links() }}</div>
        @endif
    </x-ui.card>
</div>
