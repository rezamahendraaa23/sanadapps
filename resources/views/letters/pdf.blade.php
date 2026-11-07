<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>{!! \App\Support\Pdf\PrintStyles::inline() !!}</style>
    <style>
        /* Kop dan footer diulang di setiap halaman lewat thead dan tfoot. */
        @page { size: A4; margin: 0 }
        html, body { margin: 0; background: white; color: black; -webkit-print-color-adjust: exact; print-color-adjust: exact }
        body { font-family: 'Times New Roman', Georgia, serif; font-size: 12pt; line-height: 1.55 }
        table.page { width: 210mm; border-collapse: collapse }
        .kop img, .foot img { display: block; width: 210mm }
        .kop-text { margin: 12mm 20mm 0; padding-bottom: 3mm; border-bottom: 3px double black; text-align: center }
        .kop-text h1 { margin: 0; font-size: 16pt }
        .kop-text p { margin: 1mm 0 0; font-size: 10pt }
        .content { padding: 8mm 22mm 10mm }
        .meta td { padding: 0 2mm 0 0; vertical-align: top }
        .body { margin-top: 6mm; white-space: pre-line; text-align: justify }
        .sign { width: 75mm; margin: 10mm 0 0 auto; text-align: center; break-inside: avoid }
        .sign .name { margin-top: 22mm; font-weight: 700; text-decoration: underline }
        .void { margin-bottom: 4mm; padding: 2mm; border: 2px solid black; text-align: center; font-weight: 700 }
    </style>
</head>
<body>
    <table class="page">
        <thead><tr><td class="kop">
            @if ($header)
                <img src="{{ $header }}" alt="">
            @else
                <div class="kop-text">
                    <h1>{{ $identity->displayName() }}</h1>
                    <p>{{ collect([$profile?->address, $profile?->phone, $profile?->email])->filter()->implode(' | ') }}</p>
                </div>
            @endif
        </td></tr></thead>
        <tfoot><tr><td class="foot">@if ($footer)<img src="{{ $footer }}" alt="">@endif</td></tr></tfoot>
        <tbody><tr><td class="content">
            @if ($letter->status === 'void')
                <div class="void">DIBATALKAN: {{ $letter->void_reason }}</div>
            @endif
            <div style="display: flex; justify-content: space-between; gap: 8mm">
                <table class="meta"><tbody>
                    <tr><td>Nomor</td><td>:</td><td>{{ $letter->letter_no }}</td></tr>
                    <tr><td>Lampiran</td><td>:</td><td>-</td></tr>
                    <tr><td>Perihal</td><td>:</td><td><b>{{ $letter->subject }}</b></td></tr>
                </tbody></table>
                <div style="white-space: nowrap">{{ $city ? $city.', ' : '' }}{{ $letter->issued_on->locale('id')->translatedFormat('j F Y') }}</div>
            </div>
            @if ($letter->recipient)
                <p style="margin-top: 6mm">Kepada Yth.<br><b>{{ $letter->recipient }}</b><br>di tempat</p>
            @endif
            <div class="body">{{ $letter->body }}</div>
            <div class="sign">
                <div>{{ $letter->signer_title }}</div>
                <div class="name">{{ $letter->signer_name }}</div>
            </div>
        </td></tr></tbody>
    </table>
</body>
</html>
