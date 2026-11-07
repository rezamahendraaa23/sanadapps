@php
    use App\Modules\Finance\Support\Rupiah;
    $valid = $payment->status === 'settlement';
@endphp

<x-layouts.base title="Periksa kwitansi">
    <main id="konten" class="pattern-geometric flex min-h-dvh items-center justify-center p-4">
        <div class="w-full max-w-[480px] rounded-card border border-border bg-surface p-6 shadow-sm">
            <h1 class="font-display text-2xl font-semibold">Periksa kwitansi</h1>
            @if ($valid)
                <x-ui.banner tone="success" class="mt-3">Kwitansi ini sah dan tercatat lunas.</x-ui.banner>
            @else
                <x-ui.banner tone="danger" class="mt-3">Pembayaran ini tidak berlaku atau sudah dikembalikan.</x-ui.banner>
            @endif
            <dl class="mt-4 grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-1 text-base">
                <dt class="text-text-muted">Nomor</dt><dd class="font-tabular text-right">{{ $payment->payment_no }}</dd>
                <dt class="text-text-muted">Nominal</dt><dd class="font-tabular text-right">{{ Rupiah::format($payment->amount) }}</dd>
                @if ($payment->paid_at)
                    <dt class="text-text-muted">Tanggal</dt><dd class="text-right">{{ $payment->paid_at->setTimezone(config('sanad.display_timezone'))->translatedFormat('j F Y') }}</dd>
                @endif
                <dt class="text-text-muted">Pesantren</dt><dd class="text-right">{{ app(\App\Support\SchoolIdentity::class)->displayName() }}</dd>
            </dl>
        </div>
    </main>
</x-layouts.base>
