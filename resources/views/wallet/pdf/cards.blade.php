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
        .face { position: relative; box-sizing: border-box; width: 85.6mm; height: 54mm; overflow: hidden; background: var(--color-surface); color: var(--color-text); break-inside: avoid; }
        .sheet .face { outline: 0.2mm dashed var(--color-border); border-radius: 3mm; }
        .printer .face { break-after: page; }
        .printer .face:last-child { break-after: auto; }

        /* Depan: bentuk geometris besar memberi karakter, foto dan nama jadi pusat perhatian. */
        .front { background: var(--color-bg); }
        .blob { position: absolute; right: -19mm; top: -18mm; width: 58mm; height: 58mm; border-radius: 50%; background: var(--color-primary); }
        .dot { position: absolute; left: 47mm; bottom: -7mm; width: 16mm; height: 16mm; border-radius: 50%; background: var(--color-accent-soft); }

        .brand { position: absolute; left: 4mm; top: 3.5mm; width: 40mm; display: flex; align-items: center; gap: 1.5mm; }
        .logo { flex: none; width: 6.5mm; height: 6.5mm; border-radius: 1.6mm; background: var(--color-surface); object-fit: contain; }
        .school { min-width: 0; margin: 0; font-size: 6.5pt; font-weight: 800; line-height: 1.12; overflow: hidden; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }

        .who { position: absolute; left: 4mm; bottom: 4mm; width: 44.5mm; }
        .tag { display: inline-block; margin: 0 0 1.2mm; padding: 0.35mm 1.8mm; border-radius: 99mm; background: var(--color-text); color: var(--color-surface); font-size: 5.5pt; font-weight: 800; letter-spacing: 0.02em; }
        .name { margin: 0; font-family: var(--font-display); font-size: 17pt; font-weight: 700; line-height: 1.02; letter-spacing: -0.01em; overflow-wrap: anywhere; }
        .name.long { font-size: 12.5pt; line-height: 1.06; }
        .name.longer { font-size: 9.5pt; line-height: 1.08; }
        .nis { display: inline-block; margin: 1.5mm 0 0; padding: 0.5mm 2mm; border: 0.35mm solid var(--color-text); border-radius: 99mm; background: var(--color-surface); font-size: 8pt; font-weight: 800; font-variant-numeric: tabular-nums; letter-spacing: 0.04em; }
        .facts { margin-top: 1.2mm; font-size: 6.5pt; font-weight: 600; line-height: 1.25; }
        .facts div { overflow: hidden; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 1; }

        .photo { position: absolute; right: 4.5mm; top: 7.5mm; box-sizing: border-box; width: 28mm; height: 37mm; border: 0.5mm solid var(--color-text); border-radius: 3mm; background: var(--color-surface); box-shadow: 1.2mm 1.2mm 0 var(--color-text); object-fit: cover; object-position: top; }
        .initial { display: flex; align-items: center; justify-content: center; background: var(--color-accent-soft); font-family: var(--font-display); font-size: 40pt; font-weight: 700; }

        /* Belakang: panel warna untuk QR, sisi putih untuk ketentuan dan stempel asli. */
        .back { display: flex; }
        .panel { flex: none; width: 33mm; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1.5mm; background: var(--color-primary); color: var(--color-on-primary); }
        .code { box-sizing: border-box; width: 25mm; height: 25mm; padding: 1.5mm; border-radius: 2.5mm; background: var(--color-surface); }
        .code svg { display: block; width: 100%; height: 100%; }
        .panel p { margin: 0; padding: 0 2mm; text-align: center; font-size: 6pt; font-weight: 800; line-height: 1.2; }
        .panel small { display: block; font-size: 7pt; font-weight: 800; font-variant-numeric: tabular-nums; letter-spacing: 0.04em; }
        .side { flex: 1; min-width: 0; box-sizing: border-box; display: flex; flex-direction: column; padding: 3mm 3.5mm; }
        .side h3 { margin: 0 0 1mm; font-family: var(--font-display); font-size: 9pt; font-weight: 700; }
        .side ul { margin: 0; padding: 0; list-style: none; font-size: 6pt; font-weight: 600; line-height: 1.25; }
        .side li { margin-bottom: 0.7mm; }
        .stamp { flex: 1; min-height: 0; margin-top: 1mm; display: flex; flex-direction: column; justify-content: flex-end; font-size: 5.5pt; color: var(--color-text-muted); }
        .stamp i { display: block; margin-bottom: 0.5mm; border-bottom: 0.15mm solid var(--color-text-muted); }
        .return { margin-top: 1mm; font-size: 5.5pt; line-height: 1.2; overflow: hidden; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 3; }
        .return b { font-weight: 800; }
    </style>
</head>
<body class="font-sans">
    <div class="{{ $format === 'printer' ? 'printer' : 'sheet' }}">
        @foreach ($cards as $card)
            @php
                $student = $card->student;
                $photo = $student->photo_path && Storage::disk('local')->exists($student->photo_path) ? 'data:'.Storage::disk('local')->mimeType($student->photo_path).';base64,'.base64_encode(Storage::disk('local')->get($student->photo_path)) : null;
                $length = mb_strlen($student->name);
            @endphp

            <article class="face front">
                <div class="blob"></div>
                <div class="dot"></div>
                <div class="brand">
                    @if ($logoData)
                        <img src="{{ $logoData }}" alt="" class="logo">
                    @endif
                    <p class="school">{{ $identity->displayName() }}</p>
                </div>
                <div class="who">
                    <p class="tag">Kartu Santri{{ $yearName && in_array('academic_year', $fields, true) ? ' '.$yearName : '' }}</p>
                    <p @class(['name', 'long' => $length > 20 && $length <= 38, 'longer' => $length > 38])>{{ $student->name }}</p>
                    <p class="nis">{{ $student->nis }}</p>
                    <div class="facts">
                        @if (in_array('birth', $fields, true) && ($student->birth_place || $student->birth_date))
                            <div>{{ collect([$student->birth_place, $student->birth_date?->translatedFormat('j F Y')])->filter()->implode(', ') }}</div>
                        @endif
                        @if (in_array('entry_year', $fields, true) && $student->entry_date)
                            <div>Masuk {{ $student->entry_date->format('Y') }}</div>
                        @endif
                        @if (in_array('classroom', $fields, true) && $student->classroom)
                            <div>{{ str_contains(mb_strtolower($student->classroom->name), 'kelas') ? '' : 'Kelas ' }}{{ $student->classroom->name }}</div>
                        @endif
                        @if (in_array('dormitory', $fields, true) && $student->dormitory)
                            <div>{{ str_contains(mb_strtolower($student->dormitory->name), 'asrama') ? '' : 'Asrama ' }}{{ $student->dormitory->name }}</div>
                        @endif
                    </div>
                </div>
                @if ($photo)
                    <img src="{{ $photo }}" alt="" class="photo">
                @else
                    <span class="photo initial">{{ mb_strtoupper(mb_substr($student->name, 0, 1)) }}</span>
                @endif
            </article>

            <article class="face back">
                <div class="panel">
                    <div class="code">{!! Qr::svg(QrToken::printed($card), 160) !!}</div>
                    <p>Pindai untuk absen dan gerbang<small>{{ $student->nis }}</small></p>
                </div>
                <div class="side">
                    <h3>Jaga kartu ini</h3>
                    <ul>
                        <li>Kartu pribadi, jangan dipinjamkan.</li>
                        <li>Belanja dengan menempelkan kartu. QR ini tidak bisa untuk belanja.</li>
                        <li>Hilang? Blokir lewat aplikasi wali atau lapor bendahara.</li>
                        <li>Berlaku selama santri aktif.</li>
                    </ul>
                    <div class="stamp"><i></i>Pengesahan pesantren</div>
                    <p class="return"><b>Menemukan kartu ini?</b> Mohon kembalikan ke {{ $identity->displayName() }}. {{ collect([$profile->address, $profile->phone])->filter()->implode(', ') }}</p>
                </div>
            </article>
        @endforeach
    </div>
</body>
</html>
