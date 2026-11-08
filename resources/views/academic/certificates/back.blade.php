{{-- Halaman belakang: hasil ujian fase. Huruf judul mengikuti khat desain depan. --}}
@once
<style>
    .back { background: var(--color-surface); padding: 16mm 22mm 14mm; display: flex; flex-direction: column }
    .back .edge { position: absolute; inset: 8mm; border: 0.4mm solid color-mix(in srgb, var(--color-primary) 45%, transparent); border-radius: 3mm }
    .back .head { position: relative; display: flex; justify-content: space-between; align-items: flex-end; gap: 10mm; padding-bottom: 4mm; border-bottom: 0.6mm solid var(--color-primary) }
    .back .head .ar-title { font-size: 30pt; line-height: 1.3; color: var(--color-primary) }
    .back .head .latin { font-size: 9pt; font-weight: 800; letter-spacing: 0.25em; text-transform: uppercase; color: var(--color-accent) }
    .back .head h2 { margin: 1mm 0 0; font-family: var(--font-display); font-size: 18pt; color: var(--color-text) }
    .back .info { position: relative; display: grid; grid-template-columns: repeat(4, auto); justify-content: start; column-gap: 12mm; margin-top: 4mm; font-size: 10pt }
    .back .info dt { color: var(--color-text-muted); font-size: 8.5pt }
    .back .info dd { margin: 0; font-weight: 700 }
    .back table { position: relative; width: 100%; margin-top: 5mm; border-collapse: collapse; font-size: 10.5pt }
    .back th { padding: 2mm 3mm; text-align: left; font-size: 8.5pt; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-text-muted); border-bottom: 0.3mm solid var(--color-border) }
    .back td { padding: 2.2mm 3mm; border-bottom: 0.2mm solid var(--color-border) }
    .back td.num, .back th.num { text-align: right; font-variant-numeric: tabular-nums }
    .back tr:nth-child(even) td { background: color-mix(in srgb, var(--color-primary-soft) 55%, transparent) }
    .back .summary { position: relative; margin-top: auto; display: flex; justify-content: space-between; align-items: flex-end; gap: 10mm }
    .back .score { display: flex; gap: 6mm; align-items: center; padding: 4mm 6mm; border-radius: 3mm; background: var(--color-primary); color: var(--color-on-primary) }
    .back .score b { font-family: var(--font-display); font-size: 26pt; line-height: 1 }
    .back .score span { font-size: 9.5pt; line-height: 1.4 }
</style>
@endonce
<div class="edge"></div>
<div class="head">
    <div>
        <div class="latin">Hasil ujian</div>
        <h2>{{ $phase->name }}, {{ $phase->curriculum->name }}</h2>
    </div>
    <div class="ar ar-title" style="font-family: '{{ $titleFont }}'">كَشْفُ الدَّرَجَاتِ</div>
</div>
<dl class="info">
    <div><dt>Nama</dt><dd>{{ $student }}</dd></div>
    <div><dt>NIS</dt><dd>{{ $nis }}</dd></div>
    <div><dt>Masa fase</dt><dd>{{ $period }}</dd></div>
    <div><dt>Nomor sertifikat</dt><dd>{{ $result->certificate_no ?? '-' }}</dd></div>
</dl>
<table>
    <thead><tr><th style="width: 10mm">No</th><th>Mata pelajaran</th><th>Predikat</th><th class="num">Nilai</th></tr></thead>
    <tbody>
        @forelse ($result->scores as $row)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $row['name'] }}</td>
                <td>{{ \App\Modules\Academic\Support\AcademicSettings::predicate((float) $row['score']) }}</td>
                <td class="num">{{ number_format((float) $row['score'], 2, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="4">Belum ada nilai.</td></tr>
        @endforelse
    </tbody>
</table>
<div class="summary">
    <div class="score">
        <b>{{ $score }}</b>
        <span>Nilai akhir, predikat {{ $predicate }}<br>Batas lulus {{ rtrim(rtrim(number_format($phase->min_score, 2, ',', '.'), '0'), ',') }}</span>
    </div>
    <div class="sign">
        <div>{{ $issued }}</div>
        <div>{{ $signerTitle }}</div>
        <div class="who">{{ $signerName ?: ' ' }}</div>
    </div>
</div>
