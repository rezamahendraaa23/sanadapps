@php
    use App\Modules\Wallet\Support\QrToken;
    use App\Support\Qr;
    use Illuminate\Support\Facades\Storage;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>{!! \App\Support\Pdf\PrintStyles::inline() !!}</style>
    @if ($brandCss = $identity->brandCss())
        <style>{!! $brandCss !!}</style>
    @endif
    <style>
        @page { size: {{ $format === 'printer' ? '85.6mm 54mm' : 'A4' }}; margin: {{ $format === 'printer' ? '0' : '10mm' }} }
        html, body { margin: 0; background: white; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

        .sheet { display: grid; grid-template-columns: 85.6mm 85.6mm; gap: 4mm; align-content: start; }
        .face { position: relative; box-sizing: border-box; width: 85.6mm; height: 54mm; overflow: hidden; break-inside: avoid; }
        .sheet .face { outline: 0.2mm dashed var(--color-border); border-radius: 3mm; }
        .printer .face { break-after: page; }
        .printer .face:last-child { break-after: auto; }

        /* Depan: poster. Nama jadi unsur utama, NIS jadi pola, foto seperti stiker. */
        .front { background: var(--color-primary); color: var(--color-on-primary); }
        .ghost { position: absolute; left: -2mm; top: 9mm; width: 120mm; font-family: var(--font-display); font-size: 40pt; font-weight: 700; line-height: 0.9; letter-spacing: -0.02em; color: transparent; -webkit-text-stroke: 0.25mm var(--color-on-primary); opacity: 0.16; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .brand { position: absolute; left: 4mm; top: 3.6mm; right: 36mm; display: flex; align-items: center; gap: 1.6mm; }
        .logo { flex: none; width: 6mm; height: 6mm; border-radius: 1.5mm; background: var(--color-surface); object-fit: contain; }
        .school { margin: 0; font-size: 6pt; font-weight: 800; line-height: 1.15; overflow: hidden; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }

        .who { position: absolute; left: 4mm; bottom: 4mm; width: 47mm; }
        .first { margin: 0; font-family: var(--font-display); font-size: 25pt; font-weight: 700; line-height: 0.92; letter-spacing: -0.02em; overflow-wrap: anywhere; }
        .first.long { font-size: 18pt; }
        .first.longer { font-size: 13pt; }
        .rest { margin: 0.6mm 0 0; font-size: 8pt; font-weight: 700; line-height: 1.1; overflow: hidden; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
        .meta { display: flex; flex-wrap: wrap; align-items: center; gap: 1.2mm; margin-top: 1.8mm; }
        .nis { padding: 0.5mm 2mm; border-radius: 99mm; background: var(--color-accent-soft); color: var(--color-text); font-size: 7.5pt; font-weight: 800; font-variant-numeric: tabular-nums; letter-spacing: 0.03em; }
        .fact { font-size: 6pt; font-weight: 700; opacity: 0.85; }

        .photo-wrap { position: absolute; right: 5mm; top: 6.5mm; width: 27mm; height: 35mm; padding: 1mm; border-radius: 2.6mm; background: var(--color-surface); transform: rotate(4deg); box-shadow: 0.8mm 1.2mm 0 rgb(0 0 0 / 0.25); }
        .photo { display: block; width: 100%; height: 100%; border-radius: 1.8mm; object-fit: cover; object-position: top; }
        .initial { display: flex; align-items: center; justify-content: center; background: var(--color-accent-soft); color: var(--color-primary); font-family: var(--font-display); font-size: 38pt; font-weight: 700; }
        .sticker { position: absolute; right: 2.4mm; bottom: 5.4mm; width: 13mm; height: 13mm; border-radius: 50%; background: var(--color-accent); color: var(--color-on-primary); display: flex; flex-direction: column; align-items: center; justify-content: center; transform: rotate(-12deg); text-align: center; line-height: 1; }
        .sticker b { font-family: var(--font-display); font-size: 11pt; font-weight: 700; }
        .sticker span { margin-top: 0.4mm; font-size: 4.5pt; font-weight: 800; }

        /* Belakang: tiket dengan sobekan di tengah. */
        .back { display: flex; background: var(--color-surface); color: var(--color-text); }
        .stub { position: relative; flex: none; width: 34mm; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1.4mm; background: var(--color-accent-soft); border-right: 0.35mm dashed var(--color-text-muted); }
        .stub:before, .stub:after { content: ''; position: absolute; right: -2.2mm; width: 4.4mm; height: 4.4mm; border-radius: 50%; background: white; }
        .stub:before { top: -2.2mm; }
        .stub:after { bottom: -2.2mm; }
        .code { box-sizing: border-box; width: 25mm; height: 25mm; padding: 1.4mm; border-radius: 2.4mm; background: var(--color-surface); }
        .code svg { display: block; width: 100%; height: 100%; }
        .stub p { margin: 0; padding: 0 2mm; text-align: center; font-size: 5.8pt; font-weight: 800; line-height: 1.2; }
        .side { flex: 1; min-width: 0; box-sizing: border-box; display: flex; flex-direction: column; padding: 3.2mm 3.6mm 2.8mm 4.4mm; }
        .side h3 { margin: 0 0 1.4mm; font-family: var(--font-display); font-size: 11pt; font-weight: 700; line-height: 1; color: var(--color-primary); }
        .rules { margin: 0; padding: 0; list-style: none; font-size: 5.9pt; font-weight: 600; line-height: 1.22; }
        .rules li { display: flex; gap: 1.2mm; margin-bottom: 0.9mm; }
        .rules i { flex: none; width: 3.2mm; height: 3.2mm; border-radius: 50%; background: var(--color-primary); color: var(--color-on-primary); font-style: normal; font-size: 5pt; font-weight: 800; display: flex; align-items: center; justify-content: center; }
        .stamp { flex: 1; min-height: 0; display: flex; flex-direction: column; justify-content: flex-end; font-size: 5.3pt; color: var(--color-text-muted); }
        .stamp i { display: block; margin-bottom: 0.5mm; border-bottom: 0.15mm solid var(--color-text-muted); }
        .return { flex: none; margin: 1mm 0 0; font-size: 5.3pt; line-height: 1.2; overflow: hidden; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
        .return b { font-weight: 800; }
    </style>
</head>
<body class="font-sans">
    <div class="{{ $format === 'printer' ? 'printer' : 'sheet' }}">
        @foreach ($cards as $card)
            @php
                $student = $card->student;
                $photo = $student->photo_path && Storage::disk('local')->exists($student->photo_path) ? 'data:'.Storage::disk('local')->mimeType($student->photo_path).';base64,'.base64_encode(Storage::disk('local')->get($student->photo_path)) : null;
                $words = preg_split('/\s+/u', trim($student->name)) ?: [$student->name];
                $first = $words[0];
                $rest = implode(' ', array_slice($words, 1));
                $length = mb_strlen($first);
            @endphp

            <article class="face front">
                <div class="ghost" aria-hidden="true">{{ $student->nis }} {{ $student->nis }}<br>{{ $student->nis }} {{ $student->nis }}</div>
                <div class="brand">
                    @if ($logoData)
                        <img src="{{ $logoData }}" alt="" class="logo">
                    @endif
                    <p class="school">{{ $identity->displayName() }}</p>
                </div>
                <div class="who">
                    <p @class(['first', 'long' => $length > 8 && $length <= 12, 'longer' => $length > 12])>{{ $first }}</p>
                    @if ($rest !== '')<p class="rest">{{ $rest }}</p>@endif
                    <div class="meta">
                        <span class="nis">{{ $student->nis }}</span>
                        @if (in_array('classroom', $fields, true) && $student->classroom)<span class="fact">{{ $student->classroom->name }}</span>@endif
                        @if (in_array('dormitory', $fields, true) && $student->dormitory)<span class="fact">{{ $student->dormitory->name }}</span>@endif
                        @if (in_array('birth', $fields, true) && ($student->birth_place || $student->birth_date))<span class="fact">{{ collect([$student->birth_place, $student->birth_date?->translatedFormat('j M Y')])->filter()->implode(', ') }}</span>@endif
                        @if (in_array('academic_year', $fields, true) && $yearName)<span class="fact">{{ $yearName }}</span>@endif
                    </div>
                </div>
                <div class="photo-wrap">
                    @if ($photo)
                        <img src="{{ $photo }}" alt="" class="photo">
                    @else
                        <span class="photo initial">{{ mb_strtoupper(mb_substr($student->name, 0, 1)) }}</span>
                    @endif
                </div>
                @if (in_array('entry_year', $fields, true) && $student->entry_date)
                    <div class="sticker"><b>'{{ $student->entry_date->format('y') }}</b><span>ANGKATAN</span></div>
                @endif
            </article>

            <article class="face back">
                <div class="stub">
                    <div class="code">{!! Qr::svg(QrToken::printed($card), 160) !!}</div>
                    <p>Pindai untuk absen dan gerbang</p>
                </div>
                <div class="side">
                    <h3>Jaga kartu ini</h3>
                    <ul class="rules">
                        <li><i>1</i>Kartu pribadi, jangan dipinjamkan.</li>
                        <li><i>2</i>Belanja dengan menempelkan kartu. QR ini tidak bisa untuk belanja.</li>
                        <li><i>3</i>Hilang? Blokir lewat aplikasi wali atau lapor bendahara.</li>
                    </ul>
                    <div class="stamp"><i></i>Pengesahan pesantren</div>
                    <p class="return"><b>Menemukan kartu ini?</b> Kembalikan ke {{ $identity->displayName() }}. {{ collect([$profile->address, $profile->phone])->filter()->implode(', ') }}</p>
                </div>
            </article>
        @endforeach
    </div>
</body>
</html>
