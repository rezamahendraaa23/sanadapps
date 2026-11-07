@php use App\Modules\Finance\Support\Rupiah; @endphp

<div class="flex flex-col gap-4">
    <x-ui.card>
        <div class="mb-3 w-full md:max-w-xs">
            <x-ui.select label="Tampilkan" name="filter" :options="['aktif' => 'Perlu tindakan', 'semua' => 'Semua']" wire:model.live="filter" />
        </div>
        @if ($payouts->isEmpty())
            <x-ui.empty-state icon="circle-check" message="Tidak ada pencairan yang perlu ditindak." class="py-2" />
        @else
            <x-ui.table caption="Pencairan merchant">
                <thead><tr><x-ui.th>Merchant</x-ui.th><x-ui.th numeric>Nominal</x-ui.th><x-ui.th>Cara</x-ui.th><x-ui.th>Status</x-ui.th><x-ui.th>Aksi</x-ui.th></tr></thead>
                <tbody>
                    @foreach ($payouts as $payout)
                        <tr wire:key="p-{{ $payout->uuid }}">
                            <x-ui.td label="Merchant">
                                <span class="font-semibold break-words">{{ $payout->merchant->name }}</span>
                                <span class="block text-xs text-text-muted">{{ $payout->requester?->name }}, {{ $payout->created_at->setTimezone(config('sanad.display_timezone'))->translatedFormat('j M Y') }}</span>
                                @if ($payout->note)<span class="block text-sm break-words">{{ $payout->note }}</span>@endif
                            </x-ui.td>
                            <x-ui.td label="Nominal" class="text-right"><span class="font-tabular font-semibold">{{ Rupiah::format($payout->amount) }}</span></x-ui.td>
                            <x-ui.td label="Cara">{{ $payout->method === 'cash' ? 'Tunai' : 'Transfer' }}</x-ui.td>
                            <x-ui.td label="Status">{{ $labels[$payout->status] }}@if ($payout->reject_reason)<span class="block text-xs text-text-muted break-words">{{ $payout->reject_reason }}</span>@endif</x-ui.td>
                            <x-ui.td label="Aksi">
                                <div class="flex flex-wrap gap-1">
                                    @if ($payout->status === 'requested')
                                        <x-ui.button wire:click="approve('{{ $payout->uuid }}')" wire:confirm="Setujui pencairan {{ Rupiah::format($payout->amount) }}?">Setujui<span class="sr-only"> {{ $payout->merchant->name }}</span></x-ui.button>
                                    @elseif ($payout->status === 'approved')
                                        <x-ui.button wire:click="pay('{{ $payout->uuid }}')" wire:confirm="Tandai sudah dibayar {{ Rupiah::format($payout->amount) }}? Saldo merchant berkurang dan kas keluar dicatat.">Tandai Dibayar<span class="sr-only"> {{ $payout->merchant->name }}</span></x-ui.button>
                                    @endif
                                    @if (in_array($payout->status, ['requested', 'approved'], true))
                                        <x-ui.button variant="tertiary" wire:click="askReject('{{ $payout->uuid }}')">Tolak<span class="sr-only"> {{ $payout->merchant->name }}</span></x-ui.button>
                                    @endif
                                </div>
                            </x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
            <div class="mt-4">{{ $payouts->links() }}</div>
        @endif
    </x-ui.card>

    <x-ui.modal name="tolak-pencairan" title="Tolak pencairan">
        <x-ui.input label="Alasan" name="rejectReason" required wire:model="rejectReason" />
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button variant="danger" wire:click="reject" wire:loading.attr="disabled" wire:target="reject">Tolak Pencairan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
