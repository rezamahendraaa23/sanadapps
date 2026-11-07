<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>{!! \App\Support\Pdf\PrintStyles::inline() !!}</style>
    <style>@page { size: A4; margin: 15mm } html, body { background: white }</style>
</head>
<body class="bg-surface font-sans text-text">
    <h1 class="font-display text-xl font-semibold">Rekap absensi {{ $classroom ?? 'semua kelas' }}</h1>
    <p class="text-sm text-text-muted">{{ $from }} sampai {{ $to }}</p>
    <table class="mt-4 w-full border-collapse text-sm">
        <thead><tr class="border-b-2 border-text text-left"><th class="py-1">NIS</th><th class="py-1">Santri</th><th class="py-1 text-right">Hadir</th><th class="py-1 text-right">Terlambat</th><th class="py-1 text-right">Izin</th><th class="py-1 text-right">Sakit</th><th class="py-1 text-right">Alpa</th><th class="py-1 text-right">%</th></tr></thead>
        <tbody>
            @foreach ($rows as $row)
                <tr class="border-b border-border"><td class="font-tabular py-1">{{ $row['student']->nis }}</td><td class="py-1">{{ $row['student']->name }}</td><td class="font-tabular py-1 text-right">{{ $row['hadir'] }}</td><td class="font-tabular py-1 text-right">{{ $row['terlambat'] }}</td><td class="font-tabular py-1 text-right">{{ $row['izin'] }}</td><td class="font-tabular py-1 text-right">{{ $row['sakit'] }}</td><td class="font-tabular py-1 text-right">{{ $row['alpa'] }}</td><td class="font-tabular py-1 text-right">{{ $row['percent'] }}</td></tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
