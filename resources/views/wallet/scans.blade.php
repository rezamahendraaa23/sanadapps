<div class="flex flex-col gap-4">
    @include('wallet.partials.card-nav')

    <x-ui.card>
        <x-ui.page-toolbar>
            <div class="grid min-w-0 flex-1 gap-3 md:grid-cols-3">
                <x-ui.select label="Hasil" name="result" placeholder="Semua hasil" :options="$results" wire:model.live="result" />
                <x-ui.select label="Akses" name="scope" placeholder="Semua akses" :options="$scopes" wire:model.live="scope" />
                <x-ui.input label="Tanggal" name="date" type="date" wire:model.live="date" />
            </div>
            <x-ui.button variant="secondary" icon="file-down" :href="route('staff.cards.scans.export', array_filter(['hasil' => $result, 'scope' => $scope, 'tanggal' => $date]))">Ekspor</x-ui.button>
        </x-ui.page-toolbar>

        @if ($scans->isEmpty())
            <x-ui.empty-state icon="scan-line" message="Belum ada pemindaian untuk filter ini." class="py-2" />
        @else
            <x-ui.table caption="Log pemindaian kartu">
                <thead><tr><x-ui.th>Waktu</x-ui.th><x-ui.th>Santri</x-ui.th><x-ui.th>Akses</x-ui.th><x-ui.th>Perangkat</x-ui.th><x-ui.th>Hasil</x-ui.th></tr></thead>
                <tbody>
                    @foreach ($scans as $scan)
                        <tr wire:key="pindai-{{ $scan->uuid }}">
                            <x-ui.td label="Waktu">{{ $scan->scanned_at->setTimezone(config('sanad.display_timezone'))->translatedFormat('j M, H.i.s') }}</x-ui.td>
                            <x-ui.td label="Santri">{{ $scan->card?->student?->name ?? 'Tidak dikenal' }}</x-ui.td>
                            <x-ui.td label="Akses">{{ $scopes[$scan->scope] }}</x-ui.td>
                            <x-ui.td label="Perangkat">{{ $scan->device?->name ?? 'Kasir' }}</x-ui.td>
                            <x-ui.td label="Hasil">
                                <span class="inline-flex items-center gap-1 text-sm font-semibold">
                                    <x-ui.icon :name="$scan->result === 'ok' ? 'circle-check' : 'circle-alert'" size="sm" />{{ $results[$scan->result] }}
                                </span>
                                @if ($scan->reason)<span class="block text-xs text-text-muted">{{ $scan->reason }}</span>@endif
                            </x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
            <div class="mt-4">{{ $scans->links() }}</div>
        @endif
    </x-ui.card>
</div>
