@php
    use App\Modules\Finance\Support\Rupiah;
    $tz = config('sanad.display_timezone');
@endphp

<div class="mx-auto flex w-full max-w-[720px] flex-col gap-4">
    <x-ui.ticket-card>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="font-display text-2xl font-semibold font-tabular">{{ Rupiah::format($payment->amount) }}</p>
                <p class="text-sm text-text-muted">{{ ['cash' => 'Tunai', 'manual_transfer' => 'Transfer manual', 'midtrans' => 'Pembayaran online', 'wallet' => 'Dompet'][$payment->method] }}</p>
            </div>
            <x-ui.payment-status :status="$payment->status" :method="$payment->method" />
        </div>
        @if ($payment->isTopup())
            <p class="mt-3 text-sm">Top-up dompet {{ $payment->studentNames() }}</p>
        @endif
        <ul class="mt-3 text-sm">
            @foreach ($payment->invoices as $invoice)
                <li class="flex justify-between gap-3 py-0.5">
                    <a href="{{ route('staff.invoices.show', $invoice) }}" wire:navigate class="font-tabular text-primary hover:underline">{{ $invoice->invoice_no }}</a>
                    <span>{{ $invoice->student?->name }}</span>
                    <span class="font-tabular">{{ Rupiah::format($invoice->pivot->amount) }}</span>
                </li>
            @endforeach
        </ul>
        @if ($payment->unique_code)
            <p class="mt-2 text-sm text-text-muted">Kode unik {{ $payment->unique_code }} masuk kas terpisah.</p>
        @endif
        @if ($payment->reject_reason)
            <x-ui.banner tone="danger" class="mt-3">Ditolak: {{ $payment->reject_reason }}</x-ui.banner>
        @endif
        @if ($payment->refund_reason)
            <x-ui.banner tone="info" class="mt-3">Dikembalikan: {{ $payment->refund_reason }}</x-ui.banner>
        @endif
        <x-slot:proof>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="text-sm text-text-muted">
                    @if ($payment->paid_at)<p>Dibayar {{ $payment->paid_at->setTimezone($tz)->translatedFormat('j M Y, H.i') }}</p>@endif
                    @if ($payment->verifier)<p>Dicatat oleh {{ $payment->verifier->name }}</p>@endif
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($payment->proof_path)
                        <x-ui.button variant="secondary" :href="route('staff.payments.proof', $payment)" target="_blank">Lihat Bukti</x-ui.button>
                    @endif
                    @if (in_array($payment->status, ['settlement', 'refunded'], true))
                        <x-ui.button icon="file-down" :href="route('payments.receipt', $payment)">Unduh Kwitansi</x-ui.button>
                    @endif
                </div>
            </div>
        </x-slot:proof>
    </x-ui.ticket-card>

    @if ($payment->status === 'settlement' && auth()->user()->can('refund', $payment))
        <div class="flex justify-end">
            <x-ui.button variant="tertiary" class="!text-danger hover:!bg-danger-soft" x-data x-on:click="$dispatch('open-modal', 'kembalikan-dana')">Catat Pengembalian Dana</x-ui.button>
        </div>
    @endif

    <x-ui.modal name="kembalikan-dana" title="Catat pengembalian dana?">
        <div class="flex flex-col gap-3">
            <p>{{ Rupiah::format($payment->amount) }} tercatat keluar dari kas dan tagihannya kembali terbuka.</p>
            <x-ui.textarea label="Alasan" name="reason" required wire:model="reason" />
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button variant="danger" wire:click="refund" wire:loading.attr="disabled" wire:target="refund">Catat Pengembalian</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
