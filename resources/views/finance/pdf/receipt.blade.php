@php
    use App\Modules\Finance\Support\Rupiah;
    $tz = config('sanad.display_timezone');
    $methodLabel = ['cash' => 'Tunai', 'manual_transfer' => 'Transfer manual', 'midtrans' => 'Pembayaran online', 'wallet' => 'Dompet'][$payment->method];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>{!! \App\Support\Pdf\PrintStyles::inline() !!}</style>
    @if ($brandCss = $identity->brandCss())
        <style>{!! $brandCss !!}</style>
    @endif
    <style>@page { size: A5; margin: 0 } html, body { background: white }</style>
</head>
<body class="bg-surface font-sans text-text">
    <div class="p-8">
        <header class="rule-double flex items-center gap-3 pb-3">
            @if ($logoData)
                <img src="{{ $logoData }}" alt="" class="size-12 object-contain">
            @else
                <span class="flex size-12 items-center justify-center rounded-full bg-primary-soft font-display text-xl font-semibold text-primary">{{ mb_strtoupper(mb_substr($identity->displayName(), 0, 1)) }}</span>
            @endif
            <div class="min-w-0">
                <p class="font-display text-lg font-semibold">{{ $identity->displayName() }}</p>
                @if ($profile->address)<p class="text-xs text-text-muted">{{ $profile->address }}</p>@endif
                @if ($profile->phone || $profile->email)<p class="text-xs text-text-muted">{{ collect([$profile->phone, $profile->email])->filter()->join(' · ') }}</p>@endif
            </div>
        </header>

        <p dir="rtl" lang="ar" class="font-arabic mt-3 text-center text-[1.375rem] leading-[2]">بِسْمِ ٱللَّهِ ٱلرَّحْمَٰنِ ٱلرَّحِيمِ</p>

        <div class="mt-2 flex items-start justify-between gap-4">
            <div>
                <h1 class="font-display text-2xl font-semibold">Kwitansi</h1>
                <p class="font-tabular text-sm text-text-muted">{{ $payment->payment_no }}</p>
            </div>
            @if ($payment->status === 'settlement')
                <x-ui.stamp>Lunas</x-ui.stamp>
            @else
                <span class="rounded-control border border-danger px-3 py-1 text-sm font-semibold text-danger">Dikembalikan</span>
            @endif
        </div>

        <dl class="mt-4 grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1 text-sm">
            <dt class="text-text-muted">Santri</dt><dd class="text-right font-semibold">{{ $students }}</dd>
            <dt class="text-text-muted">Tanggal bayar</dt><dd class="text-right">{{ $payment->paid_at?->setTimezone($tz)->translatedFormat('j F Y, H.i') }}</dd>
            <dt class="text-text-muted">Cara bayar</dt><dd class="text-right">{{ $methodLabel }}</dd>
        </dl>

        <table class="mt-4 w-full border-collapse text-sm">
            <thead>
                <tr class="border-b border-text-muted text-left">
                    <th class="py-1 font-semibold">Rincian</th>
                    <th class="py-1 text-right font-semibold">Nominal</th>
                </tr>
            </thead>
            <tbody>
                @if ($payment->isTopup())
                    <tr><td class="py-1">Top-up dompet</td><td class="font-tabular py-1 text-right">{{ Rupiah::format($payment->amount) }}</td></tr>
                @endif
                @foreach ($payment->invoices as $invoice)
                    @foreach ($invoice->items as $item)
                        <tr class="border-b border-border">
                            <td class="py-1">{{ $item->description }}@if ($item->discount)<span class="block text-xs text-text-muted">Diskon {{ Rupiah::format($item->discount) }}</span>@endif</td>
                            <td class="font-tabular py-1 text-right">{{ Rupiah::format($item->amount) }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
            <tfoot>
                @if ($payment->amount !== $payment->invoices->sum(fn ($invoice) => $invoice->items->sum('amount')))
                    <tr><td class="pt-2 text-text-muted">Total tagihan</td><td class="font-tabular pt-2 text-right">{{ Rupiah::format($payment->invoices->sum(fn ($invoice) => $invoice->items->sum('amount'))) }}</td></tr>
                @endif
                <tr><td class="pt-2 text-base font-semibold">Dibayar</td><td class="font-display font-tabular pt-2 text-right text-xl font-semibold">{{ Rupiah::format($payment->amount) }}</td></tr>
            </tfoot>
        </table>

        <div class="mt-6 flex items-end justify-between gap-4 border-t-2 border-dashed border-border pt-4">
            <div class="text-xs text-text-muted">
                <p>Pindai kode untuk memeriksa keaslian kwitansi.</p>
                <p class="mt-1">Dicetak {{ now($tz)->translatedFormat('j F Y, H.i') }}</p>
            </div>
            <div class="size-24 shrink-0">{!! $qr !!}</div>
        </div>
    </div>
</body>
</html>
