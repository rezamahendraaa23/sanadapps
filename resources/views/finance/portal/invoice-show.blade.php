@php
    use App\Modules\Finance\Support\Rupiah;
    $openPayment = $invoice->payments->first(fn ($payment) => in_array($payment->status, ['pending', 'review', 'failed'], true));
@endphp

<div class="flex flex-col gap-4">
    <x-ui.ticket-card>
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <h2 class="font-display text-lg font-semibold">{{ $invoice->period?->translatedFormat('F Y') ?? 'Tagihan' }}</h2>
                <p class="text-sm text-text-muted">{{ $invoice->student?->name }}</p>
            </div>
            <x-ui.invoice-status :status="$invoice->status" class="shrink-0" />
        </div>
        <ul class="mt-3 flex flex-col gap-2 text-base">
            @foreach ($invoice->items as $item)
                <li>
                    <div class="flex justify-between gap-3"><span class="min-w-0">{{ $item->description }}</span><span class="font-tabular shrink-0">{{ Rupiah::format($item->qty * $item->unit_price) }}</span></div>
                    @if ($item->discount)
                        <div class="flex justify-between gap-3 text-sm text-success"><span>Diskon</span><span class="font-tabular">-{{ Rupiah::format($item->discount) }}</span></div>
                    @endif
                </li>
            @endforeach
        </ul>
        <x-slot:proof>
            <dl class="grid grid-cols-2 gap-y-1 text-base">
                <dt class="font-semibold">Total</dt><dd class="font-display font-tabular text-right text-xl font-semibold">{{ Rupiah::format($invoice->total) }}</dd>
                @if ($invoice->paid_total)
                    <dt class="text-text-muted">Terbayar</dt><dd class="font-tabular text-right">{{ Rupiah::format($invoice->paid_total) }}</dd>
                @endif
                <dt class="text-text-muted">Nomor</dt>
                <dd class="text-right" x-data="{ copied: false }">
                    <button type="button" class="font-tabular inline-flex min-h-11 items-center gap-1 text-primary" x-on:click="navigator.clipboard?.writeText(@js($invoice->invoice_no)); copied = true; setTimeout(() => copied = false, 2000)">
                        {{ $invoice->invoice_no }} <x-ui.icon name="copy" size="sm" /><span class="sr-only">Salin nomor</span>
                    </button>
                    <span x-show="copied" x-cloak class="block text-xs text-success" role="status">Tersalin</span>
                </dd>
                <dt class="text-text-muted">Jatuh tempo</dt><dd class="text-right">{{ $invoice->due_date->translatedFormat('j M Y') }}</dd>
            </dl>
        </x-slot:proof>
    </x-ui.ticket-card>

    @if ($invoice->status === 'void')
        <x-ui.banner tone="warning">Tagihan ini dibatalkan pesantren: {{ $invoice->void_reason }}</x-ui.banner>
    @elseif ($openPayment)
        <x-ui.button :href="route('wali.payments.show', $openPayment)" wire:navigate class="w-full">Lihat Status Pembayaran</x-ui.button>
    @elseif ($invoice->isOpen())
        <x-ui.button :href="route('wali.checkout', ['tagihan' => $invoice->uuid])" wire:navigate class="w-full">Bayar Sekarang</x-ui.button>
    @endif

    @php($settled = $invoice->payments->where('status', 'settlement'))
    @if ($settled->isNotEmpty())
        <x-ui.card title="Kwitansi">
            @foreach ($settled as $payment)
                <div wire:key="kw-{{ $payment->uuid }}" class="flex flex-wrap items-center justify-between gap-2 border-b border-border py-2 last:border-b-0">
                    <span class="font-tabular">{{ $payment->payment_no }}</span>
                    <x-ui.button variant="secondary" icon="file-down" :href="route('payments.receipt', $payment)">Unduh<span class="sr-only"> kwitansi {{ $payment->payment_no }}</span></x-ui.button>
                </div>
            @endforeach
        </x-ui.card>
    @endif
</div>
