@php use App\Modules\Finance\Support\Rupiah; @endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>{!! \App\Support\Pdf\PrintStyles::inline() !!}</style>
    <style>
        @page { size: A4; margin: 12mm 12mm 16mm }
        html, body { background: var(--color-surface); -webkit-print-color-adjust: exact; print-color-adjust: exact }
        .hero { position: relative; overflow: hidden; border-radius: 6mm; background: var(--color-primary); color: var(--color-on-primary) }
        /* Pola titik halus dan lingkaran besar di header, dari token warna saja. */
        .hero::before { content: ''; position: absolute; inset: 0; background-image: radial-gradient(color-mix(in srgb, var(--color-on-primary) 16%, transparent) 1.2px, transparent 1.4px); background-size: 5mm 5mm; opacity: .7 }
        .hero::after { content: ''; position: absolute; right: -18mm; top: -22mm; width: 70mm; height: 70mm; border-radius: 50%; border: 9mm solid color-mix(in srgb, var(--color-accent-soft) 22%, transparent) }
        .hero > * { position: relative; z-index: 1 }
        .pill { display: inline-block; border-radius: 999px; padding: 1mm 3.5mm; font-size: 8.5pt; font-weight: 700; letter-spacing: .08em; text-transform: uppercase }
        /* Dua kolom bergaya menu: kategori boleh terpotong antar kolom, baris produk tidak. */
        .cols { column-count: 2; column-gap: 8mm; column-rule: 0.3mm dashed var(--color-border) }
        .cat { margin-top: 5mm }
        .cat:first-child { margin-top: 0 }
        .cat-head { display: flex; align-items: center; gap: 2.5mm; border-radius: 2.5mm; background: var(--color-primary-soft); padding: 1.6mm 3mm; break-after: avoid }
        .cat-head .dot { width: 2.4mm; height: 2.4mm; transform: rotate(45deg); border-radius: .5mm; background: var(--color-accent); flex-shrink: 0 }
        .row { break-inside: avoid; display: flex; align-items: baseline; gap: 1.5mm; padding: 1.5mm 1mm; border-bottom: 0.2mm solid color-mix(in srgb, var(--color-border) 70%, transparent); font-size: 9.5pt; line-height: 1.3 }
        .row .lead { flex: 1; min-width: 4mm; border-bottom: 0.35mm dotted var(--color-text-muted); opacity: .45; transform: translateY(-1mm) }
        .row .nm { max-width: 62%; font-weight: 600 }
        .row .pr { font-weight: 600; white-space: nowrap; color: var(--color-primary) }
        .row .pr.text-danger { color: var(--color-danger) }
        .row.soldout .nm, .row.soldout .pr { color: var(--color-text-muted); text-decoration: line-through }
        .sub { font-size: 7.5pt; white-space: nowrap; color: var(--color-text-muted); font-weight: 400 }
        .chip { display: inline-block; border-radius: 999px; padding: .2mm 1.8mm; font-size: 6.8pt; font-weight: 700; vertical-align: 1px; margin-left: 1mm }
        .foot { break-inside: avoid; margin-top: 6mm; border-top: 0.3mm solid var(--color-border); padding-top: 2.5mm; font-size: 8pt; color: var(--color-text-muted); display: flex; justify-content: space-between; gap: 4mm }
    </style>
</head>
<body class="font-sans text-text">
    <header class="hero px-8 py-7">
        <div class="flex items-center gap-4">
            @if ($logo)
                <img src="{{ $logo }}" alt="" class="size-14 shrink-0 rounded-full bg-surface object-contain p-1">
            @else
                <span class="font-display inline-flex size-14 shrink-0 items-center justify-center rounded-full bg-surface text-xl font-semibold text-primary">{{ mb_substr($merchant->name, 0, 1) }}</span>
            @endif
            <div class="min-w-0 flex-1">
                <p class="text-sm opacity-80">{{ $school }}</p>
                <p class="font-display text-3xl font-semibold leading-tight">{{ $merchant->name }}</p>
                @if ($merchant->location)<p class="text-sm opacity-80">{{ $merchant->location }}</p>@endif
            </div>
            <div class="shrink-0 text-right">
                <span class="pill bg-accent-soft text-accent">Daftar Harga</span>
                <p class="mt-2 text-sm opacity-90">Berlaku per {{ $date }}</p>
            </div>
        </div>
        <div class="mt-5 flex flex-wrap gap-2 text-sm">
            <span class="rounded-full bg-surface/15 px-3 py-1">{{ $count }} produk</span>
            @foreach (array_slice(array_keys($groups), 0, 6) as $category)
                <span class="rounded-full border border-surface/30 px-3 py-1">{{ $category }}</span>
            @endforeach
            @if (count($groups) > 6)<span class="rounded-full border border-surface/30 px-3 py-1">+{{ count($groups) - 6 }} kategori lainnya</span>@endif
            @if ($promoCount > 0)<span class="rounded-full bg-accent-soft px-3 py-1 font-semibold text-accent">{{ $promoCount }} produk sedang promo</span>@endif
        </div>
    </header>

    @if ($groups === [])
        <p class="mt-10 text-center text-text-muted">Belum ada produk aktif.</p>
    @else
        <main class="cols mt-6">
            @foreach ($groups as $category => $items)
                <section class="cat">
                    <div class="cat-head">
                        <span class="dot"></span>
                        <h2 class="font-display flex-1 text-base font-semibold text-primary">{{ $category }}</h2>
                        <span class="text-xs text-text-muted">{{ count($items) }} produk</span>
                    </div>
                    @foreach ($items as $item)
                        <div @class(['row', 'soldout' => $item['sold_out']])>
                            <span class="nm">
                                {{ $item['name'] }}
                                @if ($item['sold_out'])<span class="chip bg-text text-surface">Habis</span>@elseif ($item['promo_price'] !== null)<span class="chip bg-danger text-on-primary">Promo</span>@endif
                                @if ($item['bundle_note'])<br><span class="sub font-semibold text-accent" style="white-space: normal">{{ $item['bundle_note'] }}</span>@endif
                            </span>
                            <span class="lead"></span>
                            @if ($item['promo_price'] !== null && ! $item['sold_out'])
                                <span class="font-tabular sub line-through">{{ Rupiah::format($item['price']) }}</span>
                                <span class="pr font-tabular text-danger">{{ Rupiah::format($item['promo_price']) }}</span>
                            @else
                                <span class="pr font-tabular">{{ Rupiah::format($item['price']) }}</span>
                            @endif
                        </div>
                    @endforeach
                </section>
            @endforeach
        </main>
    @endif

    <footer class="foot">
        <span>{{ $merchant->name }}, {{ $school }}</span>
        <span>{{ $payments !== [] ? 'Bayar dengan '.implode(', ', $payments).'. ' : '' }}Harga dapat berubah sewaktu-waktu.</span>
    </footer>
</body>
</html>
