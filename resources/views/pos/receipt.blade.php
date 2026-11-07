@php use App\Modules\Finance\Support\Rupiah; @endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk {{ $order->receipt_no }}</title>
    <style>
        /* Printer thermal: lebar kertas 58 atau 80 mm, tinta hitam saja. */
        @page { size: {{ $width }}mm auto; margin: 0 }
        * { box-sizing: border-box }
        html, body { margin: 0; background: white; color: black }
        body { width: {{ $width }}mm; padding: 3mm {{ $width === 58 ? '2.5' : '4' }}mm 6mm; font-family: ui-monospace, 'Consolas', 'Courier New', monospace; font-size: {{ $width === 58 ? '10.5' : '12' }}px; line-height: 1.35 }
        .c { text-align: center }
        .b { font-weight: 700 }
        .big { font-size: 1.35em }
        .row { display: flex; justify-content: space-between; gap: 2mm }
        .row span:last-child { text-align: right; white-space: nowrap }
        hr { border: 0; border-top: 1px dashed black; margin: 2mm 0 }
        .item { margin-bottom: 1mm }
        .muted { opacity: 0.75 }
        .bar { display: flex; gap: 2mm; justify-content: center; margin: 4mm 0 }
        .bar a, .bar button { font: inherit; padding: 2mm 3mm; border: 1px solid black; border-radius: 2mm; background: white; color: black; text-decoration: none; cursor: pointer }
        @media print { .bar { display: none } }
    </style>
</head>
<body>
    <div class="bar">
        <button type="button" onclick="window.print()">Cetak</button>
        <a href="?lebar={{ $width === 58 ? 80 : 58 }}">Kertas {{ $width === 58 ? 80 : 58 }} mm</a>
    </div>

    <div class="c b big">{{ $order->merchant->name }}</div>
    <div class="c">{{ $identity->displayName() }}</div>
    @if ($order->merchant->location)
        <div class="c muted">{{ $order->merchant->location }}</div>
    @endif
    <hr>
    <div class="row"><span>{{ $order->receipt_no }}</span><span>{{ $order->created_at->setTimezone(config('sanad.display_timezone'))->format('d/m/y H.i') }}</span></div>
    <div class="row"><span>Kasir</span><span>{{ $order->cashier?->name ?? 'Perangkat' }}</span></div>
    <div class="row"><span>Pembeli</span><span>{{ $order->buyerName() }}</span></div>
    <hr>
    @foreach ($order->items as $item)
        <div class="item">
            <div>{{ $item->name }}</div>
            <div class="row"><span>{{ $item->qty }} x {{ number_format($item->unit_price, 0, ',', '.') }}</span><span>{{ number_format($item->unit_price * $item->qty, 0, ',', '.') }}</span></div>
            @if ($item->discount > 0)
                <div class="row muted"><span>Promo</span><span>-{{ number_format($item->discount, 0, ',', '.') }}</span></div>
            @endif
            @if ($item->returned_qty > 0)
                <div class="row muted"><span>Diretur {{ $item->returned_qty }}</span><span></span></div>
            @endif
        </div>
    @endforeach
    <hr>
    @if ($order->discount > 0)
        <div class="row"><span>Hemat</span><span>{{ Rupiah::format($order->discount) }}</span></div>
    @endif
    <div class="row b big"><span>TOTAL</span><span>{{ Rupiah::format($order->total) }}</span></div>
    @if ($order->isCash())
        <div class="row"><span>Tunai</span><span>{{ Rupiah::format((int) $order->paid_amount) }}</span></div>
        <div class="row"><span>Kembali</span><span>{{ Rupiah::format((int) $order->change_amount) }}</span></div>
    @else
        <div class="row"><span>{{ $order->scan_method === 'qr' ? 'QR santri' : 'Kartu santri' }}</span><span>{{ Rupiah::format($order->total) }}</span></div>
        @if ($order->walletTransaction)
            <div class="row"><span>Sisa saldo</span><span>{{ Rupiah::format($order->walletTransaction->balance_after) }}</span></div>
        @endif
    @endif
    @if ($order->returned_amount > 0)
        <div class="row"><span>Dikembalikan</span><span>{{ Rupiah::format($order->returned_amount) }}</span></div>
    @endif
    @if ($order->status === 'void')
        <hr>
        <div class="c b">DIBATALKAN</div>
    @endif
    <hr>
    <div class="c">Terima kasih</div>

    <script>
        if (! location.search.includes('tanpa-cetak')) {
            window.addEventListener('load', () => setTimeout(() => window.print(), 300));
        }
    </script>
</body>
</html>
