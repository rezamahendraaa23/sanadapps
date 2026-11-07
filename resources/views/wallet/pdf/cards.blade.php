@php
    use App\Modules\Wallet\Support\QrToken;
    use App\Support\Qr;
    use Illuminate\Support\Facades\Storage;

    // Bintang delapan penjuru, motif yang sama dengan latar halaman masuk SANAD.
    $star = 'M12 0l3.1 5.5 6.4-2 -2 6.4L25 13l-5.5 3.1 2 6.4-6.4-2L12 26l-3.1-5.5-6.4 2 2-6.4L-1 13l5.5-3.1-2-6.4 6.4 2z';
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
        .face { position: relative; box-sizing: border-box; width: 85.6mm; height: 54mm; overflow: hidden; display: flex; flex-direction: column; background: var(--color-surface); color: var(--color-text); break-inside: avoid; }
        .sheet .face { outline: 0.2mm dashed var(--color-border); border-radius: 3mm; }
        .printer .face { break-after: page; }
        .printer .face:last-child { break-after: auto; }

        .band { position: relative; flex: none; overflow: hidden; background: var(--color-primary); color: var(--color-on-primary); }
        .band > svg { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0.1; }
        .band > div { position: relative; }
        .ribbon { flex: none; height: 1mm; background: var(--color-accent); }

        .head { display: flex; align-items: center; gap: 2mm; height: 12mm; padding: 0 3.5mm; }
        .logo { flex: none; width: 8mm; height: 8mm; border-radius: 50%; background: var(--color-surface); object-fit: contain; }
        .school { min-width: 0; flex: 1; font-family: var(--font-display); font-size: 9.5pt; font-weight: 600; line-height: 1.1; overflow: hidden; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
        .kind { flex: none; text-align: right; font-size: 6pt; font-weight: 700; line-height: 1.25; }
        .kind span { display: block; font-size: 5.5pt; font-weight: 500; }

        .body { flex: 1; min-height: 0; display: flex; align-items: center; gap: 2.5mm; padding: 0 3.5mm; }
        .photo { flex: none; width: 21mm; height: 27mm; border-radius: 1.5mm; object-fit: cover; object-position: top; box-shadow: 0 0 0 0.35mm var(--color-accent); }
        .initial { display: flex; align-items: center; justify-content: center; background: var(--color-primary-soft); color: var(--color-primary); font-family: var(--font-display); font-size: 26pt; font-weight: 600; }
        .data { flex: 1; min-width: 0; }
        .name { font-family: var(--font-display); font-size: 11.5pt; font-weight: 600; line-height: 1.12; color: var(--color-primary); overflow-wrap: anywhere; }
        .name.long { font-size: 9pt; }
        .name.longer { font-size: 7.5pt; }
        .nis { margin-top: 1mm; font-size: 8.5pt; font-weight: 700; font-variant-numeric: tabular-nums; letter-spacing: 0.02em; }
        .facts { margin-top: 1mm; font-size: 6.5pt; line-height: 1.3; }
        .facts div { overflow: hidden; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
        .facts b { font-weight: 600; color: var(--color-text-muted); }
        .code { flex: none; width: 18.5mm; text-align: center; }
        .code svg { display: block; width: 18.5mm; height: 18.5mm; }
        .code p { margin: 0.6mm 0 0; font-size: 5pt; line-height: 1.15; color: var(--color-text-muted); }

        .back-band { height: 6mm; padding: 0 3.5mm; display: flex; align-items: center; justify-content: space-between; font-size: 6.5pt; font-weight: 700; }
        .back { flex: 1; min-height: 0; display: flex; gap: 3mm; padding: 2.5mm 3.5mm; }
        .rules { flex: 1; min-width: 0; }
        .rules h3 { margin: 0 0 0.8mm; font-size: 6.5pt; font-weight: 700; color: var(--color-primary); }
        .rules ol { list-style: decimal; margin: 0; padding-left: 3mm; font-size: 6pt; line-height: 1.28; }
        .rules li { margin-bottom: 0.5mm; }
        .sign { flex: none; width: 26mm; display: flex; flex-direction: column; justify-content: space-between; text-align: center; font-size: 5.5pt; color: var(--color-text-muted); }
        .sign i { display: block; height: 17mm; border-bottom: 0.15mm solid var(--color-text-muted); }
        .return { flex: none; padding: 1.3mm 3.5mm; background: var(--color-primary-soft); font-size: 5.5pt; line-height: 1.25; }
        .return b { display: block; font-size: 6pt; color: var(--color-primary); }
    </style>
</head>
<body class="font-sans">
    <div class="{{ $format === 'printer' ? 'printer' : 'sheet' }}">
        @foreach ($cards as $card)
            @php
                $student = $card->student;
                $photo = $student->photo_path && Storage::disk('local')->exists($student->photo_path) ? 'data:'.Storage::disk('local')->mimeType($student->photo_path).';base64,'.base64_encode(Storage::disk('local')->get($student->photo_path)) : null;
            @endphp

            <article class="face">
                <div class="band">
                    <svg aria-hidden="true"><defs><pattern id="motif-{{ $loop->index }}a" width="30" height="30" patternUnits="userSpaceOnUse"><path d="{{ $star }}" fill="currentColor" transform="translate(3 2)"/></pattern></defs><rect width="100%" height="100%" fill="url(#motif-{{ $loop->index }}a)"/></svg>
                    <div class="head">
                        @if ($logoData)
                            <img src="{{ $logoData }}" alt="" class="logo">
                        @endif
                        <p class="school">{{ $identity->displayName() }}</p>
                        <p class="kind">KARTU SANTRI @if ($yearName)<span>{{ $yearName }}</span>@endif</p>
                    </div>
                </div>
                <div class="ribbon"></div>
                <div class="body">
                    @if ($photo)
                        <img src="{{ $photo }}" alt="" class="photo">
                    @else
                        <span class="photo initial">{{ mb_strtoupper(mb_substr($student->name, 0, 1)) }}</span>
                    @endif
                    <div class="data">
                        <p @class(['name', 'long' => mb_strlen($student->name) > 26 && mb_strlen($student->name) <= 45, 'longer' => mb_strlen($student->name) > 45])>{{ $student->name }}</p>
                        <p class="nis">{{ $student->nis }}</p>
                        <div class="facts">
                            @if ($student->classroom)<div>@unless (str_contains(mb_strtolower($student->classroom->name), 'kelas'))<b>Kelas</b> @endunless{{ $student->classroom->name }}</div>@endif
                            @if ($student->dormitory)<div>@unless (str_contains(mb_strtolower($student->dormitory->name), 'asrama'))<b>Asrama</b> @endunless{{ $student->dormitory->name }}</div>@endif
                        </div>
                    </div>
                    <div class="code">
                        {!! Qr::svg(QrToken::printed($card), 140) !!}
                        <p>Pindai untuk absen dan gerbang</p>
                    </div>
                </div>
            </article>

            <article class="face">
                <div class="band">
                    <svg aria-hidden="true"><defs><pattern id="motif-{{ $loop->index }}b" width="30" height="30" patternUnits="userSpaceOnUse"><path d="{{ $star }}" fill="currentColor" transform="translate(3 2)"/></pattern></defs><rect width="100%" height="100%" fill="url(#motif-{{ $loop->index }}b)"/></svg>
                    <div class="back-band"><span>{{ $student->nis }}</span><span>Kartu pribadi, jangan dipinjamkan</span></div>
                </div>
                <div class="ribbon"></div>
                <div class="back">
                    <div class="rules">
                        <h3>Ketentuan kartu</h3>
                        <ol>
                            <li>Kartu ini identitas santri dan alat absen di pesantren.</li>
                            <li>Belanja di kasir dengan menempelkan kartu. QR di kartu ini tidak bisa dipakai belanja.</li>
                            <li>Kartu hilang? Blokir lewat aplikasi wali atau lapor bendahara.</li>
                            <li>Berlaku selama santri berstatus aktif.</li>
                        </ol>
                    </div>
                    <div class="sign"><span>Pengesahan pesantren</span><i></i></div>
                </div>
                <div class="return">
                    <b>Menemukan kartu ini? Mohon kembalikan ke {{ $identity->displayName() }}</b>
                    {{ collect([$profile->address, $profile->phone, $profile->email])->filter()->implode(', ') }}
                </div>
            </article>
        @endforeach
    </div>
</body>
</html>
