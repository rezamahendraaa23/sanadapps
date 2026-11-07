@php use App\Modules\Finance\Support\Rupiah; @endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>{!! \App\Support\Pdf\PrintStyles::inline() !!}</style>
    <style>@page { size: A4; margin: 15mm } html, body { background: white }</style>
</head>
<body class="bg-surface font-sans text-text">
    <h1 class="font-display text-2xl font-semibold">Daftar harga {{ $merchant->name }}</h1>
    <table class="mt-4 w-full border-collapse text-sm">
        <thead><tr class="border-b-2 border-border text-left"><th class="py-1">Produk</th><th class="py-1">Barcode</th><th class="py-1 text-right">Harga</th></tr></thead>
        <tbody>
            @foreach ($products as $product)
                <tr class="border-b border-border"><td class="py-1">{{ $product->name }}</td><td class="font-tabular py-1">{{ $product->barcode }}</td><td class="font-tabular py-1 text-right">{{ Rupiah::format($product->price) }}</td></tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
