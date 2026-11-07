@php
    use App\Modules\Finance\Support\Rupiah;
    $maxHour = max(1, (int) $hours->max('orders'));
    $ledgerLabels = ['sale' => 'Penjualan', 'commission' => 'Komisi', 'refund' => 'Pembatalan', 'payout' => 'Pencairan', 'adjust' => 'Penyesuaian'];
@endphp

<div class="flex flex-col gap-4">
    @if ($merchant)
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-display text-xl font-semibold break-words">{{ $merchant->name }}</h2>
            @include('pos.partials.merchant-nav')
        </div>
    @else
        <a href="{{ route('staff.merchants.index') }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 self-start rounded-control px-3 text-base font-semibold text-text-muted hover:bg-primary-soft"><x-ui.icon name="chevron-left" size="sm" />Semua merchant</a>
    @endif

    <x-ui.card>
        <x-ui.page-toolbar class="!mb-0">
            <div class="grid min-w-0 flex-1 gap-3 md:grid-cols-2 lg:max-w-md">
                <x-ui.input label="Dari" name="from" type="date" wire:model.live="from" />
                <x-ui.input label="Sampai" name="to" type="date" wire:model.live="to" />
            </div>
            <x-ui.button variant="secondary" icon="file-down" :href="route('staff.merchants.report.export', array_filter($exportParams))">Ekspor</x-ui.button>
        </x-ui.page-toolbar>
    </x-ui.card>

    <div class="grid gap-4 md:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
        <x-ui.hero-card label="Omzet" :value="Rupiah::format($summary['revenue'])">{{ $summary['orders'] }} transaksi.</x-ui.hero-card>
        <x-ui.card>
            <dl class="flex flex-col gap-2 text-base">
                <div class="flex justify-between gap-3"><dt class="text-text-muted">Komisi pesantren</dt><dd class="font-tabular font-semibold">{{ Rupiah::format($summary['commission']) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-text-muted">Bagian merchant</dt><dd class="font-tabular font-semibold">{{ Rupiah::format($summary['net']) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-text-muted">Margin</dt><dd class="font-tabular font-semibold">{{ Rupiah::format($summary['margin']) }}</dd></div>
            </dl>
            @unless ($summary['margin_complete'])
                <p class="mt-2 text-sm text-text-muted">Margin belum lengkap: ada produk terjual tanpa harga modal.</p>
            @endunless
        </x-ui.card>
    </div>

    @if ($merchant)
        <x-ui.card title="Saldo merchant">
            @if ($canManage)
                <x-slot:actions>
                    <x-ui.button x-data x-on:click="$dispatch('open-modal', 'ajukan-pencairan')">Ajukan Pencairan</x-ui.button>
                </x-slot:actions>
            @endif
            <p class="font-display font-tabular text-2xl font-semibold">{{ Rupiah::format($balance) }}</p>
            <p class="text-sm text-text-muted">Bisa dicairkan {{ Rupiah::format($available) }}.</p>

            @if ($payouts->isNotEmpty())
                <h4 class="mt-4 text-base font-semibold">Pencairan terakhir</h4>
                <ul class="mt-1 flex flex-col">
                    @foreach ($payouts as $payout)
                        <li wire:key="payout-{{ $payout->uuid }}" class="flex flex-wrap items-center justify-between gap-2 border-b border-border py-2 last:border-b-0">
                            <span class="min-w-0"><span class="font-tabular font-semibold">{{ Rupiah::format($payout->amount) }}</span><span class="block text-xs text-text-muted">{{ $payout->created_at->setTimezone(config('sanad.display_timezone'))->translatedFormat('j M Y') }}</span></span>
                            <span class="text-sm">{{ \App\Modules\Pos\Models\MerchantPayout::LABELS[$payout->status] }}@if ($payout->reject_reason): {{ $payout->reject_reason }}@endif</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.card title="Produk terlaris" class="min-w-0">
            @if ($products->isEmpty())
                <x-ui.empty-state icon="package" message="Belum ada penjualan di rentang ini." class="py-2" />
            @else
                <x-ui.table caption="Produk terlaris">
                    <thead><tr><x-ui.th>Produk</x-ui.th><x-ui.th numeric>Terjual</x-ui.th><x-ui.th numeric>Omzet</x-ui.th></tr></thead>
                    <tbody>
                        @foreach ($products as $product)
                            <tr><x-ui.td label="Produk"><span class="break-words">{{ $product->name }}</span></x-ui.td><x-ui.td label="Terjual" class="text-right"><span class="font-tabular">{{ (int) $product->qty }}</span></x-ui.td><x-ui.td label="Omzet" class="text-right"><span class="font-tabular">{{ Rupiah::format((int) $product->amount) }}</span></x-ui.td></tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            @endif
        </x-ui.card>

        <div class="flex min-w-0 flex-col gap-4">
            @if ($merchants)
                <x-ui.card title="Per merchant">
                    @forelse ($merchants as $row)
                        <div class="flex justify-between gap-3 border-b border-border py-2 last:border-b-0 text-base"><span class="min-w-0 break-words">{{ $row->name }} <span class="text-sm text-text-muted">({{ $row->orders }})</span></span><span class="font-tabular shrink-0">{{ Rupiah::format((int) $row->amount) }}</span></div>
                    @empty
                        <x-ui.empty-state icon="store" message="Belum ada penjualan." class="py-2" />
                    @endforelse
                </x-ui.card>
            @endif

            <x-ui.card title="Per kategori">
                @forelse ($categories as $row)
                    <div class="flex justify-between gap-3 border-b border-border py-2 last:border-b-0 text-base"><span class="min-w-0 break-words">{{ $row->name }}</span><span class="font-tabular shrink-0">{{ Rupiah::format((int) $row->amount) }}</span></div>
                @empty
                    <x-ui.empty-state icon="tags" message="Belum ada penjualan." class="py-2" />
                @endforelse
            </x-ui.card>

            <x-ui.card title="Jam ramai">
                @if ($hours->isEmpty())
                    <x-ui.empty-state icon="clock" message="Belum ada transaksi." class="py-2" />
                @else
                    <table class="w-full text-sm">
                        <caption class="sr-only">Jumlah transaksi per jam</caption>
                        <tbody>
                            @foreach ($hours as $row)
                                <tr>
                                    <th scope="row" class="font-tabular w-14 py-1 text-left font-normal">{{ sprintf('%02d.00', $row->hour) }}</th>
                                    <td class="py-1"><span class="block h-3 rounded-full bg-primary" style="width: {{ max(4, round($row->orders / $maxHour * 100)) }}%"></span></td>
                                    <td class="font-tabular w-10 py-1 text-right">{{ $row->orders }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-ui.card>
        </div>
    </div>

    @if ($merchant && $ledger->isNotEmpty())
        <x-ui.card title="Mutasi saldo terakhir">
            <x-ui.table caption="Mutasi saldo merchant">
                <thead><tr><x-ui.th>Waktu</x-ui.th><x-ui.th>Jenis</x-ui.th><x-ui.th numeric>Nominal</x-ui.th><x-ui.th numeric>Saldo</x-ui.th></tr></thead>
                <tbody>
                    @foreach ($ledger as $entry)
                        <tr wire:key="l-{{ $entry->uuid }}">
                            <x-ui.td label="Waktu">{{ $entry->created_at->setTimezone(config('sanad.display_timezone'))->translatedFormat('j M, H.i') }}</x-ui.td>
                            <x-ui.td label="Jenis">{{ $ledgerLabels[$entry->type] }}</x-ui.td>
                            <x-ui.td label="Nominal" class="text-right"><span class="font-tabular">{{ $entry->amount > 0 ? '+' : '' }}{{ Rupiah::format($entry->amount) }}</span></x-ui.td>
                            <x-ui.td label="Saldo" class="text-right"><span class="font-tabular">{{ Rupiah::format($entry->balance_after) }}</span></x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        </x-ui.card>
    @endif

    @if ($canManage)
        <x-ui.modal name="ajukan-pencairan" title="Ajukan pencairan">
            <div class="flex flex-col gap-3">
                <p class="text-base">Bisa dicairkan {{ Rupiah::format((int) $available) }}.</p>
                <x-ui.input-rupiah label="Nominal" name="payout_amount" required wire:model="payout_amount" />
                <x-ui.select label="Cara" name="payout_method" :options="['cash' => 'Tunai', 'transfer' => 'Transfer']" wire:model="payout_method" />
                <x-ui.input label="Catatan" name="payout_note" wire:model="payout_note" />
            </div>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
                <x-ui.button wire:click="requestPayout" wire:loading.attr="disabled" wire:target="requestPayout">Kirim Pengajuan</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
