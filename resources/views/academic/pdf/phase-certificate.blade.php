<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>{!! \App\Support\Pdf\PrintStyles::inline() !!}</style>
    @if ($brandCss = $identity->brandCss())
        <style>{!! $brandCss !!}</style>
    @endif
    <style>
        @page { size: A4 landscape; margin: 0 }
        html, body { margin: 0; background: white; -webkit-print-color-adjust: exact; print-color-adjust: exact }
        .sheet { position: relative; box-sizing: border-box; width: 297mm; height: 210mm; overflow: hidden; padding: 16mm 22mm; color: var(--color-text); break-after: page }
        .sheet:last-child { break-after: auto }
        .frame { position: absolute; inset: 8mm; border: 0.6mm solid var(--color-primary); border-radius: 4mm }
        .frame:after { content: ''; position: absolute; inset: 2mm; border: 0.25mm solid var(--color-accent); border-radius: 2.5mm }
        .star { position: absolute; width: 56mm; height: 56mm; color: var(--color-primary); opacity: 0.12 }
        .star.tl { left: -14mm; top: -14mm }
        .star.br { right: -14mm; bottom: -14mm }
        .inner { position: relative; height: 100%; display: flex; flex-direction: column; align-items: center; text-align: center }
        .brand { display: flex; align-items: center; gap: 3mm; font-size: 11pt; font-weight: 700 }
        .brand img { width: 12mm; height: 12mm; object-fit: contain }
        .kicker { margin-top: 10mm; font-size: 10pt; font-weight: 800; letter-spacing: 0.35em; text-transform: uppercase; color: var(--color-accent) }
        h1 { margin: 2mm 0 0; font-family: var(--font-display); font-size: 40pt; font-weight: 700; line-height: 1; color: var(--color-primary) }
        .given { margin-top: 8mm; font-size: 11pt }
        .name { margin-top: 3mm; padding: 0 10mm 2mm; border-bottom: 0.5mm solid var(--color-accent); font-family: var(--font-display); font-size: 30pt; font-weight: 700 }
        .text { max-width: 190mm; margin-top: 6mm; font-size: 12pt; line-height: 1.6 }
        .grade { display: inline-block; margin-top: 4mm; padding: 1.5mm 6mm; border-radius: 99mm; background: var(--color-primary); color: var(--color-on-primary); font-weight: 800; font-size: 11pt }
        .foot { margin-top: auto; width: 100%; display: flex; justify-content: space-between; align-items: flex-end; font-size: 10pt }
        .number { text-align: left; color: var(--color-text-muted) }
        .sign { width: 70mm; text-align: center }
        .sign .who { margin-top: 18mm; font-weight: 800; text-decoration: underline }
    </style>
</head>
<body class="font-sans">
    @foreach ($results as $result)
        <section class="sheet">
            <div class="frame"></div>
            @foreach (['tl', 'br'] as $corner)
                <svg class="star {{ $corner }}" viewBox="0 0 100 100" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="3" d="M17.5 17.5h65v65h-65zM50 4L96 50 50 96 4 50z"/><circle cx="50" cy="50" r="15" fill="none" stroke="currentColor" stroke-width="3"/></svg>
            @endforeach
            <div class="inner">
                <div class="brand">
                    @if ($logo)<img src="{{ $logo }}" alt="">@endif
                    <span>{{ $identity->displayName() }}</span>
                </div>
                <div class="kicker">{{ $result->phase->certificate_title ?: 'Sertifikat' }}</div>
                <h1>Sertifikat</h1>
                <div class="given">diberikan kepada</div>
                <div class="name">{{ $result->student->name }}</div>
                <div class="text">
                    telah menyelesaikan <b>{{ $result->phase->name }}</b> pada {{ $result->phase->curriculum->name }}
                    sejak {{ $result->started_on->locale('id')->translatedFormat('j F Y') }} sampai {{ $result->ended_on->locale('id')->translatedFormat('j F Y') }}.
                </div>
                <div class="grade">Predikat {{ \App\Modules\Academic\Support\AcademicSettings::predicate((float) $result->final_score) }}, nilai {{ number_format((float) $result->final_score, 2, ',', '.') }}</div>
                <div class="foot">
                    <div class="number">Nomor {{ $result->certificate_no }}<br>NIS {{ $result->student->nis }}</div>
                    <div class="sign">
                        <div>{{ $city ? $city.', ' : '' }}{{ $result->certified_at?->setTimezone(config('sanad.display_timezone'))->locale('id')->translatedFormat('j F Y') }}</div>
                        <div>{{ $signerTitle }}</div>
                        <div class="who">{{ $signerName ?: ' ' }}</div>
                    </div>
                </div>
            </div>
        </section>
    @endforeach
</body>
</html>
