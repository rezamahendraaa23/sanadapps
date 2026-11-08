<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>{!! \App\Support\Pdf\PrintStyles::inline() !!}</style>
    @if ($brandCss = $identity->brandCss())
        <style>{!! $brandCss !!}</style>
    @endif
    <style>
        @page { size: A4; margin: 16mm 18mm }
        html, body { background: white }
        .page { break-after: page }
        .page:last-child { break-after: auto }
        .head { display: flex; align-items: center; gap: 4mm; padding-bottom: 3mm; border-bottom: 0.6mm solid var(--color-primary) }
        .head img { width: 14mm; height: 14mm; object-fit: contain }
        table.scores { width: 100%; margin-top: 5mm; border-collapse: collapse }
        table.scores th, table.scores td { padding: 2mm 3mm; border-bottom: 0.2mm solid var(--color-border); text-align: left }
        table.scores th:last-child, table.scores td:last-child { text-align: right }
        .final { margin-top: 5mm; display: flex; justify-content: space-between; align-items: center; padding: 4mm; border-radius: 3mm; background: var(--color-primary-soft) }
        .sign { width: 70mm; margin: 14mm 0 0 auto; text-align: center }
        .sign .who { margin-top: 18mm; font-weight: 700; text-decoration: underline }
    </style>
</head>
<body class="font-sans text-text">
    @foreach ($results as $result)
        <section class="page">
            <div class="head">
                @if ($logo)<img src="{{ $logo }}" alt="">@endif
                <div>
                    <div class="font-display text-xl font-semibold">Rapor {{ $result->phase->name }}</div>
                    <div class="text-sm">{{ $identity->displayName() }}, {{ $result->phase->curriculum->name }}</div>
                </div>
            </div>
            <table class="mt-4 text-sm"><tbody>
                <tr><td class="pr-4">Nama</td><td>: <b>{{ $result->student->name }}</b></td></tr>
                <tr><td class="pr-4">NIS</td><td>: {{ $result->student->nis }}</td></tr>
                <tr><td class="pr-4">Rombongan</td><td>: {{ $result->classroom?->name ?? '-' }}</td></tr>
                <tr><td class="pr-4">Masa fase</td><td>: {{ $result->started_on->locale('id')->translatedFormat('j F Y') }} sampai {{ $result->ended_on->locale('id')->translatedFormat('j F Y') }}</td></tr>
            </tbody></table>
            <table class="scores text-sm">
                <thead><tr><th>Mata pelajaran</th><th>Predikat</th><th>Nilai</th></tr></thead>
                <tbody>
                    @forelse ($result->scores as $row)
                        <tr><td>{{ $row['name'] }}</td><td>{{ \App\Modules\Academic\Support\AcademicSettings::predicate((float) $row['score']) }}</td><td class="font-tabular">{{ number_format((float) $row['score'], 2, ',', '.') }}</td></tr>
                    @empty
                        <tr><td colspan="3">Belum ada nilai selama masa fase.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="final">
                <div><div class="text-sm">Nilai akhir fase</div><div class="font-display font-tabular text-2xl font-semibold">{{ $result->final_score === null ? '-' : number_format((float) $result->final_score, 2, ',', '.') }}</div></div>
                <div class="text-right"><div class="text-sm">Keputusan</div><div class="text-lg font-semibold">{{ \App\Modules\Academic\Models\PhaseResult::LABELS[$result->status] }}</div><div class="text-sm">Batas lulus {{ number_format($result->phase->min_score, 0) }}</div></div>
            </div>
            @if ($result->note)
                <p class="mt-4 text-sm">Catatan: {{ $result->note }}</p>
            @endif
            <div class="sign text-sm">
                <div>{{ $signerTitle }}</div>
                <div class="who">{{ $signerName ?: ' ' }}</div>
            </div>
        </section>
    @endforeach
</body>
</html>
