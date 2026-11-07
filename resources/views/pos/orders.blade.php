@php use App\Modules\Finance\Support\Rupiah; @endphp

<div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-display text-xl font-semibold break-words">{{ $merchant->name }}</h2>
        @include('pos.partials.merchant-nav')
    </div>

    <div role="tablist" aria-label="Bagian transaksi" class="flex flex-wrap gap-1">
        @foreach (['transaksi' => 'Transaksi', 'shift' => 'Shift Kasir'] as $key => $label)
            <x-ui.tab :selected="$tab === $key" wire:key="tab-{{ $key }}" wire:click="selectTab('{{ $key }}')">{{ $label }}</x-ui.tab>
        @endforeach
    </div>

    @if ($tab === 'transaksi')
        <x-ui.card>
            <div class="mb-3 grid gap-3 md:grid-cols-[minmax(0,1fr)_14rem]">
                <x-ui.input label="Cari nomor struk atau pembeli" name="search" type="search" autocomplete="off" wire:model.live.debounce.300ms="search" />
                <x-ui.input label="Tanggal" name="date" type="date" wire:model.live="date" />
            </div>
            @if ($orders->isEmpty())
                <x-ui.empty-state icon="receipt-text" message="Tidak ada transaksi yang cocok." class="py-2" />
            @else
                <x-ui.table caption="Transaksi">
                    <thead><tr><x-ui.th>Struk</x-ui.th><x-ui.th>Pembeli</x-ui.th><x-ui.th>Bayar</x-ui.th><x-ui.th numeric>Total</x-ui.th><x-ui.th>Aksi</x-ui.th></tr></thead>
                    <tbody>
                        @foreach ($orders as $row)
                            <tr wire:key="o-{{ $row->uuid }}">
                                <x-ui.td label="Struk"><span class="font-tabular font-semibold">{{ $row->receipt_no }}</span><span class="block text-xs text-text-muted">{{ $row->created_at->setTimezone(config('sanad.display_timezone'))->translatedFormat('j M H.i') }}, {{ $row->cashier?->name ?? 'Perangkat' }}</span></x-ui.td>
                                <x-ui.td label="Pembeli"><span class="break-words">{{ $row->buyerName() }}</span></x-ui.td>
                                <x-ui.td label="Bayar">{{ $row->isCash() ? 'Tunai' : ($row->scan_method === 'qr' ? 'QR' : 'Kartu') }}</x-ui.td>
                                <x-ui.td label="Total" class="text-right">
                                    <span @class(['font-tabular font-semibold', 'line-through text-text-muted' => $row->status === 'void'])>{{ Rupiah::format($row->total) }}</span>
                                    @if ($row->status === 'void')<span class="block text-xs text-text-muted">Dibatalkan</span>@elseif ($row->returned_amount > 0)<span class="block text-xs text-warning">Retur {{ Rupiah::format($row->returned_amount) }}</span>@endif
                                </x-ui.td>
                                <x-ui.td label="Aksi"><x-ui.button variant="tertiary" wire:click="open('{{ $row->uuid }}')">Rincian<span class="sr-only"> {{ $row->receipt_no }}</span></x-ui.button></x-ui.td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
                <div class="mt-3">{{ $orders->links() }}</div>
            @endif
        </x-ui.card>
    @else
        <x-ui.card title="Shift kasir">
            @if ($shifts->isEmpty())
                <x-ui.empty-state icon="clock" message="Belum ada shift. Kasir membuka shift dari layar kasir." class="py-2" />
            @else
                <x-ui.table caption="Shift kasir">
                    <thead><tr><x-ui.th>Kasir</x-ui.th><x-ui.th>Waktu</x-ui.th><x-ui.th numeric>Tunai seharusnya</x-ui.th><x-ui.th numeric>Dihitung</x-ui.th><x-ui.th numeric>Selisih</x-ui.th></tr></thead>
                    <tbody>
                        @foreach ($shifts as $shift)
                            @php($difference = $shift->difference())
                            <tr wire:key="sh-{{ $shift->uuid }}">
                                <x-ui.td label="Kasir">{{ $shift->cashier->name }}</x-ui.td>
                                <x-ui.td label="Waktu">
                                    {{ $shift->opened_at->setTimezone(config('sanad.display_timezone'))->translatedFormat('j M H.i') }}
                                    <span class="block text-xs text-text-muted">{{ $shift->closed_at ? 'Tutup '.$shift->closed_at->setTimezone(config('sanad.display_timezone'))->format('H.i').', '.$shift->order_count.' transaksi' : 'Masih terbuka' }}</span>
                                </x-ui.td>
                                <x-ui.td label="Tunai seharusnya" class="text-right"><span class="font-tabular">{{ $shift->expected_cash === null ? '-' : Rupiah::format($shift->expected_cash) }}</span></x-ui.td>
                                <x-ui.td label="Dihitung" class="text-right"><span class="font-tabular">{{ $shift->counted_cash === null ? '-' : Rupiah::format($shift->counted_cash) }}</span></x-ui.td>
                                <x-ui.td label="Selisih" class="text-right">
                                    @if ($difference === null)
                                        <span class="text-text-muted">-</span>
                                    @else
                                        <span @class(['font-tabular font-semibold', 'text-success' => $difference === 0, 'text-danger' => $difference !== 0])>{{ $difference === 0 ? 'Cocok' : ($difference > 0 ? '+' : '-').Rupiah::format(abs($difference)) }}</span>
                                        @if ($shift->note)<span class="block text-xs text-text-muted">{{ $shift->note }}</span>@endif
                                    @endif
                                </x-ui.td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
                <div class="mt-3">{{ $shifts->links() }}</div>
            @endif
        </x-ui.card>
    @endif

    <x-ui.modal name="detail-transaksi" title="Rincian transaksi">
        @if ($order)
            <div class="flex flex-col gap-3">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="font-tabular font-semibold">{{ $order->receipt_no }}</p>
                        <p class="text-sm text-text-muted">{{ $order->buyerName() }}, {{ $order->isCash() ? 'tunai' : 'kartu atau QR' }}</p>
                    </div>
                    <x-ui.button variant="secondary" icon="printer" :href="route('staff.pos.receipt', $order)" target="_blank">Cetak Struk</x-ui.button>
                </div>
                <ul class="flex flex-col">
                    @foreach ($order->items as $item)
                        <li wire:key="it-{{ $item->id }}" class="border-b border-border py-2 last:border-b-0">
                            <div class="flex items-center justify-between gap-3">
                                <span class="min-w-0">
                                    <span class="block font-semibold break-words">{{ $item->name }}</span>
                                    <span class="font-tabular block text-sm text-text-muted">{{ $item->qty }} x {{ Rupiah::format($item->unit_price) }}{{ $item->discount > 0 ? ', promo -'.Rupiah::format($item->discount) : '' }}{{ $item->returned_qty > 0 ? ', diretur '.$item->returned_qty : '' }}</span>
                                </span>
                                <span class="shrink-0 text-right">
                                    <span class="font-tabular block font-semibold">{{ Rupiah::format($item->amount) }}</span>
                                    @if ($canReturn && $order->status === 'paid' && $item->returned_qty < $item->qty && $returnItem !== $item->id)
                                        <button type="button" wire:click="startReturn({{ $item->id }})" class="min-h-11 text-sm font-semibold text-primary hover:underline">Retur<span class="sr-only"> {{ $item->name }}</span></button>
                                    @endif
                                </span>
                            </div>
                            @if ($returnItem === $item->id)
                                <div class="mt-2 flex flex-col gap-2 rounded-control bg-bg p-3">
                                    <x-ui.input label="Jumlah diretur" name="returnQty" inputmode="numeric" required :help="'Paling banyak '.($item->qty - $item->returned_qty).'.'" wire:model="returnQty" />
                                    <label class="flex min-h-11 items-center gap-3 text-base"><input type="checkbox" wire:model="restock" class="size-5 accent-primary">Barang masih layak, masukkan lagi ke stok</label>
                                    <x-ui.input label="Alasan" name="returnReason" required help="Misalnya: kemasan bocor, salah ambil." wire:model="returnReason" />
                                    <div class="flex flex-wrap gap-2">
                                        <x-ui.button icon="undo-2" wire:click="confirmReturn" wire:loading.attr="disabled" wire:target="confirmReturn">Catat Retur</x-ui.button>
                                        <x-ui.button variant="tertiary" wire:click="cancelReturn">Batal</x-ui.button>
                                    </div>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <div class="flex justify-between gap-3 text-base font-semibold"><span>Total</span><span class="font-tabular">{{ Rupiah::format($order->total) }}</span></div>
                @foreach ($order->returns as $return)
                    <p wire:key="ret-{{ $return->id }}" class="text-sm text-text-muted">Retur {{ $return->qty }} {{ $return->item->name }}, {{ Rupiah::format($return->amount) }}{{ $return->restock ? ', masuk stok' : ', rusak' }}: {{ $return->reason }}</p>
                @endforeach
            </div>
        @endif
    </x-ui.modal>
</div>
