<div class="flex flex-col gap-4">
    <div role="tablist" aria-label="Bagian notifikasi" class="flex flex-wrap gap-1">
        @foreach (['template' => 'Template', 'log' => 'Log pengiriman'] as $key => $label)
            <x-ui.tab :selected="$tab === $key" wire:key="tab-{{ $key }}" wire:click="selectTab('{{ $key }}')">{{ $label }}</x-ui.tab>
        @endforeach
    </div>

    @if ($tab === 'template')
        <div class="grid gap-3 md:grid-cols-2">
            @foreach ($events as $key => $event)
                @php($enabled = $templates[$key]->is_enabled ?? true)
                <x-ui.card wire:key="event-{{ $key }}" class="flex flex-col gap-2">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="min-w-0 text-base font-semibold">{{ $event['label'] }}</h2>
                        @if ($enabled)
                            <x-ui.status-badge status="active" class="shrink-0" />
                        @else
                            <span class="inline-flex shrink-0 items-center gap-1 rounded-full border border-border bg-bg px-2.5 py-0.5 text-sm font-semibold text-text-muted"><x-ui.icon name="ban" size="sm" />Mati</span>
                        @endif
                    </div>
                    <p class="whitespace-pre-line break-words text-sm text-text-muted">{{ $templates[$key]->body ?? $event['body'] }}</p>
                    <div class="mt-auto flex flex-wrap gap-2">
                        <x-ui.button variant="secondary" wire:click="edit('{{ $key }}')">Ubah<span class="sr-only"> {{ $event['label'] }}</span></x-ui.button>
                        <x-ui.button variant="tertiary" wire:click="toggle('{{ $key }}')">{{ $enabled ? 'Matikan' : 'Aktifkan' }}<span class="sr-only"> {{ $event['label'] }}</span></x-ui.button>
                    </div>
                </x-ui.card>
            @endforeach
        </div>
    @else
        <x-ui.card>
            <div class="mb-3 w-full md:max-w-xs">
                <x-ui.select label="Status" name="logStatus" placeholder="Semua status" :options="['queued' => 'Antre', 'sent' => 'Terkirim', 'delivered' => 'Sampai', 'failed' => 'Gagal']" wire:model.live="logStatus" />
            </div>
            @if ($logs->isEmpty())
                <x-ui.empty-state icon="message-circle" message="Belum ada pesan untuk filter ini." class="py-2" />
            @else
                <x-ui.table caption="Log pengiriman WhatsApp">
                    <thead><tr><x-ui.th>Waktu</x-ui.th><x-ui.th>Penerima</x-ui.th><x-ui.th>Pemicu</x-ui.th><x-ui.th>Status</x-ui.th><x-ui.th>Aksi</x-ui.th></tr></thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr wire:key="log-{{ $log->uuid }}">
                                <x-ui.td label="Waktu">{{ $log->created_at->setTimezone(config('sanad.display_timezone'))->translatedFormat('j M, H.i') }}</x-ui.td>
                                <x-ui.td label="Penerima"><span class="font-tabular">{{ $log->recipient }}</span></x-ui.td>
                                <x-ui.td label="Pemicu">{{ $events[$log->event_key]['label'] ?? $log->event_key }}</x-ui.td>
                                <x-ui.td label="Status">
                                    <span class="inline-flex items-center gap-1 text-sm font-semibold">
                                        <x-ui.icon :name="['queued' => 'clock', 'sent' => 'check', 'delivered' => 'circle-check', 'failed' => 'circle-alert'][$log->status]" size="sm" />
                                        {{ ['queued' => 'Antre', 'sent' => 'Terkirim', 'delivered' => 'Sampai', 'failed' => 'Gagal'][$log->status] }}
                                    </span>
                                    @if ($log->error)<span class="block text-xs text-danger">{{ $log->error }}</span>@endif
                                </x-ui.td>
                                <x-ui.td label="Aksi">
                                    @if ($log->status === 'failed')
                                        <x-ui.button variant="tertiary" wire:click="retry('{{ $log->uuid }}')">Ulangi</x-ui.button>
                                    @else
                                        -
                                    @endif
                                </x-ui.td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
                <div class="mt-4">{{ $logs->links() }}</div>
            @endif
        </x-ui.card>
    @endif

    <x-ui.modal name="edit-template" :title="$editing ? 'Ubah: '.$events[$editing]['label'] : 'Ubah template'">
        @if ($editing)
            <div class="flex flex-col gap-3">
                <x-ui.textarea label="Isi pesan" name="body" required rows="6" wire:model.live.blur="body" />
                <div>
                    <p class="text-sm font-semibold">Sisipkan variabel</p>
                    <div class="mt-1 flex flex-wrap gap-1">
                        @foreach ($events[$editing]['variables'] as $variable)
                            <button type="button" wire:key="var-{{ $variable }}" wire:click="insertVariable('{{ $variable }}')" class="min-h-11 rounded-full border border-primary-border px-3 text-sm text-primary hover:bg-primary-soft">{{ '{'.$variable.'}' }}</button>
                        @endforeach
                    </div>
                </div>
                <div>
                    <p class="text-sm font-semibold">Pratinjau</p>
                    <div class="mt-1 rounded-control bg-bg p-3">
                        <p class="ml-auto max-w-[85%] rounded-control bg-primary-soft p-3 text-sm whitespace-pre-line break-words">{{ $preview }}</p>
                    </div>
                </div>
                <x-ui.button variant="tertiary" class="self-start" wire:click="restoreDefault">Kembalikan Teks Bawaan</x-ui.button>
            </div>
        @endif
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button wire:click="save" wire:loading.attr="disabled" wire:target="save">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
