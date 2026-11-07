@php
    use App\Modules\Finance\Support\Rupiah;
    $canManage = auth()->user()->can('create', \App\Modules\Finance\Models\Invoice::class);
@endphp

<div>
    <x-ui.page-toolbar>
        <div class="grid w-full gap-3 md:grid-cols-4 lg:max-w-4xl">
            <x-ui.input label="Cari nomor, nama, atau NIS" name="search" type="search" wire:model.live.debounce.300ms="search" />
            <x-ui.select label="Status" name="status" placeholder="Semua status" :options="['unpaid' => 'Belum bayar', 'partial' => 'Sebagian', 'overdue' => 'Terlambat', 'paid' => 'Lunas', 'void' => 'Void']" wire:model.live="status" />
            <x-ui.select label="Kelas" name="classroom" placeholder="Semua kelas" :options="$classrooms" wire:model.live="classroom" />
            <x-ui.input label="Periode" name="period" type="month" wire:model.live="period" />
        </div>
        <div class="flex flex-wrap gap-2">
            <x-ui.button variant="secondary" icon="file-down" :href="route('staff.invoices.export', array_filter(['status' => $status, 'kelas' => $classroom, 'periode' => $period]))">Ekspor</x-ui.button>
            @if ($canManage)
                <x-ui.button variant="secondary" :href="route('staff.invoices.create')">Tagihan Manual</x-ui.button>
                <x-ui.button icon="plus" :href="route('staff.invoices.generate')">Generate Tagihan</x-ui.button>
            @endif
        </div>
    </x-ui.page-toolbar>

    <x-ui.card>
        @if ($invoices->isEmpty())
            @if ($hasAny)
                <x-ui.empty-state icon="search" message="Tidak ada tagihan yang cocok dengan filter ini.">
                    <x-slot:action><x-ui.button variant="secondary" wire:click="clearFilters">Hapus filter</x-ui.button></x-slot:action>
                </x-ui.empty-state>
            @else
                <x-ui.empty-state icon="receipt-text" message="Belum ada tagihan. Generate tagihan pertama setelah jenis dan tarifnya diatur." />
            @endif
        @else
            <x-ui.table caption="Daftar tagihan">
                <thead><tr><x-ui.th>Nomor</x-ui.th><x-ui.th>Santri</x-ui.th><x-ui.th>Periode</x-ui.th><x-ui.th numeric>Total</x-ui.th><x-ui.th numeric>Terbayar</x-ui.th><x-ui.th>Status</x-ui.th><x-ui.th>Jatuh tempo</x-ui.th></tr></thead>
                <tbody>
                    @foreach ($invoices as $invoice)
                        <tr wire:key="invoice-{{ $invoice->uuid }}" class="hover:bg-primary-soft">
                            <x-ui.td label="Nomor"><a href="{{ route('staff.invoices.show', $invoice) }}" class="font-tabular inline-flex min-h-11 items-center font-semibold text-primary hover:underline">{{ $invoice->invoice_no }}</a></x-ui.td>
                            <x-ui.td label="Santri">{{ $invoice->student?->name ?? 'Pendaftar' }}</x-ui.td>
                            <x-ui.td label="Periode">{{ $invoice->period?->translatedFormat('M Y') ?? '-' }}</x-ui.td>
                            <x-ui.td label="Total" numeric>{{ Rupiah::format($invoice->total) }}</x-ui.td>
                            <x-ui.td label="Terbayar" numeric>{{ Rupiah::format($invoice->paid_total) }}</x-ui.td>
                            <x-ui.td label="Status"><x-ui.invoice-status :status="$invoice->status" /></x-ui.td>
                            <x-ui.td label="Jatuh tempo">{{ $invoice->due_date->translatedFormat('j M Y') }}</x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
            <div class="mt-4">{{ $invoices->links() }}</div>
        @endif
    </x-ui.card>
</div>
