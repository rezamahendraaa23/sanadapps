<div>
    <x-ui.page-toolbar>
        <div class="grid w-full gap-3 md:grid-cols-3 lg:max-w-3xl">
            <x-ui.select label="Pengguna" name="userId" placeholder="Semua pengguna" :options="$users" wire:model.live="userId" />
            <x-ui.select label="Entitas" name="entity" placeholder="Semua entitas" :options="$entities" wire:model.live="entity" />
            <x-ui.input label="Tanggal" name="date" type="date" wire:model.live="date" />
        </div>
    </x-ui.page-toolbar>

    <x-ui.card>
        @if ($logs->isEmpty())
            <x-ui.empty-state icon="scroll-text" message="Belum ada catatan untuk filter ini.">
                <x-slot:action>
                    <x-ui.button variant="secondary" wire:click="clearFilters">Hapus filter</x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
        @else
            <x-ui.table caption="Catatan audit">
                <thead>
                    <tr>
                        <x-ui.th>Waktu</x-ui.th>
                        <x-ui.th>Pengguna</x-ui.th>
                        <x-ui.th>Aksi</x-ui.th>
                        <x-ui.th>Entitas</x-ui.th>
                        <x-ui.th>Rincian</x-ui.th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        <tr wire:key="audit-{{ $log->id }}">
                            <x-ui.td label="Waktu"><span class="font-tabular">{{ $log->created_at->setTimezone(config('sanad.display_timezone'))->format('d/m/Y H.i') }}</span></x-ui.td>
                            <x-ui.td label="Pengguna">{{ $log->user?->name ?? 'Sistem' }}</x-ui.td>
                            <x-ui.td label="Aksi">{{ ['created' => 'Dibuat', 'updated' => 'Diubah', 'deleted' => 'Dihapus', 'login' => 'Masuk', 'logout' => 'Keluar'][$log->action] ?? $log->action }}</x-ui.td>
                            <x-ui.td label="Entitas">{{ $log->entity_type }} {{ $log->entity_id ? '#'.$log->entity_id : '' }}</x-ui.td>
                            <x-ui.td label="Rincian">
                                <x-ui.button variant="tertiary" class="!min-h-11 !px-2" wire:click="showDetail({{ $log->id }})">Lihat<span class="sr-only"> rincian catatan {{ $log->id }}</span></x-ui.button>
                            </x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
            <div class="mt-4">{{ $logs->links() }}</div>
        @endif
    </x-ui.card>

    <x-ui.modal name="detail-audit" title="Rincian perubahan">
        @if ($detail)
            <div class="flex flex-col gap-3 text-sm">
                <p>{{ $detail->user?->name ?? 'Sistem' }}, {{ $detail->created_at->setTimezone(config('sanad.display_timezone'))->format('d/m/Y H.i') }}{{ $detail->ip ? ', IP '.$detail->ip : '' }}</p>
                @foreach (['Sebelum' => $detail->before, 'Sesudah' => $detail->after] as $heading => $values)
                    @if ($values)
                        <div>
                            <h3 class="font-semibold">{{ $heading }}</h3>
                            <dl class="mt-1 grid grid-cols-[minmax(0,1fr)_minmax(0,2fr)] gap-x-3 gap-y-1">
                                @foreach ($values as $key => $value)
                                    <dt class="text-text-muted">{{ $key }}</dt>
                                    <dd class="break-words">{{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}</dd>
                                @endforeach
                            </dl>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </x-ui.modal>
</div>
