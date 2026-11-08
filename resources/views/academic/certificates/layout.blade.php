@php
    use App\Modules\Academic\Support\AcademicSettings;
    use App\Modules\Academic\Support\CertificateDesigns;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Sertifikat</title>
    <style>{!! \App\Support\Pdf\PrintStyles::inline() !!}</style>
    @if ($brandCss = $identity->brandCss())
        <style>{!! $brandCss !!}</style>
    @endif
    <style>{!! CertificateDesigns::fontFaces($design, $embed ?? true) !!}</style>
    <style>
        @page { size: A4 landscape; margin: 0 }
        html, body { margin: 0; background: white; -webkit-print-color-adjust: exact; print-color-adjust: exact }
        .sheet { position: relative; box-sizing: border-box; width: 297mm; height: 210mm; overflow: hidden; color: var(--color-text); break-after: page; font-family: var(--font-sans) }
        .sheet:last-child { break-after: auto }
        .ar { direction: rtl; unicode-bidi: isolate }
        .ink { color: var(--color-primary) }
        .gold { color: var(--color-accent) }
        /* Isi selalu di tengah antara kepala dan kaki, jadi tidak ada ruang kosong di tengah kertas. */
        .body { margin: auto 0; width: 100% }
        .given { font-size: 11pt; letter-spacing: 0.02em }
        .name { font-weight: 700; line-height: 1.1 }
        .text { font-size: 11.5pt; line-height: 1.6 }
        .grade { display: inline-block; padding: 1.4mm 6mm; border-radius: 99mm; font-size: 10.5pt; font-weight: 800 }
        .meta { font-size: 9pt; line-height: 1.5; color: var(--color-text-muted) }
        .sign { width: 66mm; text-align: center; font-size: 10pt; line-height: 1.5 }
        .sign .who { margin-top: 16mm; font-weight: 800; text-decoration: underline }
        .logos { display: flex; justify-content: center; align-items: center; gap: 5mm; margin-bottom: 2mm }
        .logos.left { justify-content: flex-start }
        .logos img { height: 14mm; max-width: 34mm; object-fit: contain }
        .orn { position: absolute; pointer-events: none }
    </style>
</head>
<body>
    @foreach ($results as $result)
        @php
            $vars = [
                'result' => $result,
                'student' => $result->student->name,
                'nis' => $result->student->nis,
                'phase' => $result->phase,
                'predicate' => AcademicSettings::predicate((float) $result->final_score),
                'score' => number_format((float) $result->final_score, 2, ',', '.'),
                'period' => $result->started_on->locale('id')->translatedFormat('j F Y').' sampai '.$result->ended_on->locale('id')->translatedFormat('j F Y'),
                'issued' => ($city ? $city.', ' : '').($result->certified_at ?? now())->setTimezone(config('sanad.display_timezone'))->locale('id')->translatedFormat('j F Y'),
                'arabicTitle' => $result->phase->certificate_title_ar ?: 'شَهَادَةُ إِتْمَامِ الْمَرْحَلَةِ',
                'latinTitle' => $result->phase->certificate_title ?: 'Sertifikat',
            ];
        @endphp
        <section class="sheet d-{{ $design }}">
            @include('academic.certificates.designs.'.$design, $vars)
        </section>
    @endforeach
</body>
</html>
