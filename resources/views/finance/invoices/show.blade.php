@php use App\Modules\Finance\Support\Rupiah; @endphp

<div class="mx-auto flex w-full max-w-[720px] flex-col gap-4">
    <x-ui.card>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                @if ($invoice->student)
                    <a href="{{ route('staff.students.show', $invoice->student) }}" class="text-lg font-semibold text-primary hover:underline">{{ $invoice->student->name }}</a>
                    <p class="text-sm text-text-muted">NIS {{ $invoice->student->nis }}{{ $invoice->student->classroom ? ' · '.$invoice->student->classroom->name : '' }}</p>
                @endif
                <p class="mt-1 text-sm text-text-muted">Jatuh tempo {{ $invoice->due_date->translatedFormat('j M Y') }}</p>
            </div>
            <x-ui.invoice-status :status="$invoice->status" />
        </div>

        @if ($invoice->status === 'void')
            <x-ui.banner tone="warning" class="mt-3">Dibatalkan: {{ $invoice->void_reason }}</x-ui.banner>
        @endif

        <x-ui.table caption="Rincian tagihan" class="mt-4">
            <thead><tr><x-ui.th>Rincian</x-ui.th><x-ui.th numeric>Harga</x-ui.th><x-ui.th numeric>Diskon</x-ui.th><x-ui.th numeric>Jumlah</x-ui.th></tr></thead>
            <tbody>
                @foreach ($invoice->items as $item)
                    <tr wire:key="item-{{ $item->id }}">
                        <x-ui.td label="Rincian">{{ $item->description }}</x-ui.td>
                        <x-ui.td label="Harga" numeric>{{ Rupiah::format($item->unit_price) }}</x-ui.td>
                        <x-ui.td label="Diskon" numeric>{{ $item->discount ? '-'.Rupiah::format($item->discount) : '-' }}</x-ui.td>
                        <x-ui.td label="Jumlah" numeric>{{ Rupiah::format($item->amount) }}</x-ui.td>
                    </tr>
                @endforeach
            </tbody>
        </x-ui.table>

        <dl class="mt-4 ml-auto grid max-w-xs grid-cols-2 gap-1 text-right text-base">
            <dt class="text-text-muted">Total</dt><dd class="font-tabular font-semibold">{{ Rupiah::format($invoice->total) }}</dd>
            <dt class="text-text-muted">Terbayar</dt><dd class="font-tabular">{{ Rupiah::format($invoice->paid_total) }}</dd>
            <dt class="text-text-muted">Sisa</dt><dd class="font-tabular font-semibold">{{ Rupiah::format($invoice->outstanding()) }}</dd>
        </dl>

        @if ($canVoid)
            <div class="mt-4 flex justify-end border-t border-border pt-4">
                <x-ui.button variant="tertiary" class="!text-danger hover:!bg-danger-soft" x-data x-on:click="$dispatch('open-modal', 'void-tagihan')">Batalkan Tagihan</x-ui.button>
            </div>
        @endif
    </x-ui.card>

    <x-ui.card title="Riwayat pembayaran">
        @forelse ($invoice->payments as $payment)
            <div wire:key="payment-{{ $payment->id }}" class="flex flex-wrap items-center justify-between gap-2 border-b border-border py-2 last:border-b-0">
                <div>
                    <a href="{{ route('staff.payments.show', $payment) }}" class="font-tabular inline-flex min-h-11 items-center font-semibold text-primary hover:underline">{{ $payment->payment_no }}</a>
                    <span class="font-tabular text-sm text-text-muted"> {{ Rupiah::format($payment->pivot->amount) }}</span>
                </div>
                <x-ui.payment-status :status="$payment->status" />
            </div>
        @empty
            <x-ui.empty-state icon="banknote" message="Belum ada pembayaran untuk tagihan ini." class="py-2" />
        @endforelse
    </x-ui.card>

    <x-ui.modal name="void-tagihan" title="Batalkan tagihan ini?">
        <div class="flex flex-col gap-3">
            <p>Tagihan tidak lagi dihitung sebagai tunggakan dan hilang dari daftar bayar wali. Catatannya tetap tersimpan.</p>
            <x-ui.textarea label="Alasan" name="reason" required wire:model="reason" />
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Kembali</x-ui.button>
            <x-ui.button variant="danger" wire:click="void" wire:loading.attr="disabled" wire:target="void">Batalkan Tagihan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
