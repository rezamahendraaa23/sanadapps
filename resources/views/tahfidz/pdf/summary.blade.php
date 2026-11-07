@php $identity = app(\App\Support\SchoolIdentity::class); @endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>{!! \App\Support\Pdf\PrintStyles::inline() !!}</style>
    @if ($brandCss = $identity->brandCss())
        <style>{!! $brandCss !!}</style>
    @endif
    <style>@page { size: A4; margin: 15mm } html, body { background: white }</style>
</head>
<body class="bg-surface font-sans text-text">
    <p class="font-display text-lg font-semibold">{{ $identity->displayName() }}</p>
    <h1 class="font-display mt-2 text-2xl font-semibold">Ringkasan hafalan</h1>
    <p class="text-base">{{ $student->name }}, NIS {{ $student->nis }}{{ $student->classroom ? ', '.$student->classroom->name : '' }}</p>

    <h2 class="font-display mt-5 text-base font-semibold">Peta juz</h2>
    <table class="mt-2 w-full border-collapse text-sm">
        @foreach ($juzMap->chunk(10) as $row)
            <tr>
                @foreach ($row as $juz)
                    <td class="border border-border p-1 text-center"><span class="font-semibold">{{ $juz['juz'] }}</span><br>{{ $juz['percent'] }}%</td>
                @endforeach
            </tr>
        @endforeach
    </table>

    <h2 class="font-display mt-5 text-base font-semibold">Surat yang sudah selesai</h2>
    @if ($memorized->isEmpty())
        <p class="text-sm">Belum ada.</p>
    @else
        <p dir="rtl" lang="ar" class="font-arabic text-right text-lg leading-[2]">{{ $memorized->pluck('name_ar')->join('، ') }}</p>
        <p class="text-sm">{{ $memorized->pluck('name_latin')->join(', ') }}</p>
    @endif

    <h2 class="font-display mt-5 text-base font-semibold">Setoran terakhir</h2>
    <table class="mt-2 w-full border-collapse text-sm">
        <thead><tr class="border-b-2 border-text text-left"><th class="py-1">Tanggal</th><th class="py-1">Jenis</th><th class="py-1">Surat dan ayat</th><th class="py-1">Predikat</th></tr></thead>
        <tbody>
            @foreach ($history as $record)
                <tr class="border-b border-border"><td class="py-1">{{ $record->record_date->translatedFormat('j M Y') }}</td><td class="py-1">{{ \App\Modules\Tahfidz\Models\TahfidzRecord::TYPES[$record->type] }}</td><td class="py-1">{{ $record->surahFrom->name_latin }} {{ $record->ayah_from }} sampai {{ $record->surah_from === $record->surah_to ? '' : $record->surahTo->name_latin.' ' }}{{ $record->ayah_to }}</td><td class="py-1">{{ \App\Modules\Tahfidz\Models\TahfidzRecord::GRADES[$record->grade] }}</td></tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
