<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>{!! \App\Support\Pdf\PrintStyles::inline() !!}</style>
    <style>@page { size: A4; margin: 12mm } html, body { background: white }</style>
</head>
<body class="bg-surface font-sans text-text">
    <h1 class="font-display text-2xl font-semibold">Jadwal Sholat {{ $start->translatedFormat('F Y') }}</h1>
    <p class="text-sm">{{ $profile->name }}{{ $profile->city ? ', '.$profile->city : '' }}</p>
    <table class="mt-3 w-full text-sm">
        <thead><tr class="border-b border-text text-left"><th class="py-1">Tanggal</th>@foreach (\App\Modules\Masjid\Models\PrayerTime::NAMES as $name)<th class="py-1">{{ $name }}</th>@endforeach</tr></thead>
        <tbody>
            @foreach ($rows as $row)
                <tr class="border-b border-border"><td class="py-0.5">{{ $row->date->translatedFormat('j D') }}</td>@foreach (array_keys(\App\Modules\Masjid\Models\PrayerTime::NAMES) as $prayer)<td class="font-tabular py-0.5">{{ str_replace(':', '.', $row->{$prayer}) }}</td>@endforeach</tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
