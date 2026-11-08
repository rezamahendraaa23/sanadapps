@php
    use App\Modules\Masjid\Models\PrayerTime;
    // Lima waktu wajib ditebalkan; waktu pendukung lebih redup agar mata langsung ke yang utama.
    $main = ['fajr', 'dhuhr', 'asr', 'maghrib', 'isha'];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>{!! \App\Support\Pdf\PrintStyles::inline() !!}</style>
    <style>
        @page { size: A4; margin: 9mm 10mm 9mm }
        html, body { background: var(--color-surface); -webkit-print-color-adjust: exact; print-color-adjust: exact }
        .arch { position: relative; overflow: hidden; border-radius: 40mm 40mm 5mm 5mm; background: var(--color-primary); color: var(--color-on-primary); text-align: center; padding: 7mm 14mm 4.5mm }
        .arch .pattern { position: absolute; inset: 0; color: var(--color-on-primary); opacity: .07 }
        .arch .inner { position: absolute; inset: 3.2mm; border: 0.4mm solid color-mix(in srgb, var(--color-accent-soft) 55%, transparent); border-radius: 37mm 37mm 3mm 3mm; pointer-events: none }
        .arch > .content { position: relative }
        .eyebrow { display: inline-flex; align-items: center; gap: 2mm; border-radius: 999px; background: var(--color-accent-soft); color: var(--color-accent); padding: .8mm 3.5mm; font-size: 8pt; font-weight: 700; letter-spacing: .14em; text-transform: uppercase }
        .star { width: 3mm; height: 3mm; color: var(--color-accent) }
        table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 9.6pt; line-height: 1.25 }
        thead th { padding: 2mm 1mm 1.6mm; font-size: 8pt; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--color-text-muted); border-bottom: 0.5mm solid var(--color-primary) }
        thead th.main { color: var(--color-primary); font-size: 9pt }
        tbody td { padding: .6mm 1mm; text-align: center; border-bottom: 0.2mm solid var(--color-border) }
        tbody tr:nth-child(even) td { background: color-mix(in srgb, var(--color-bg) 70%, transparent) }
        td.side { color: var(--color-text-muted); font-size: 8.8pt }
        td.main { font-weight: 700; font-size: 10.4pt }
        td.date { text-align: left; white-space: nowrap }
        td.date b { display: inline-block; width: 7mm; font-size: 11pt }
        td.hijri { text-align: left; color: var(--color-text-muted); font-size: 8.2pt; white-space: nowrap }
        tr.friday td { background: var(--color-accent-soft) !important }
        tr.friday td.date span { color: var(--color-accent); font-weight: 700 }
        .sw { display: inline-block; width: 3.2mm; height: 3.2mm; border-radius: 1mm; vertical-align: -0.5mm; margin-right: 1mm }
    </style>
</head>
<body class="font-sans text-text">
    <header class="arch">
        {{-- Pola khatam (bintang delapan) dari garis tipis, warnanya ikut token. --}}
        <svg class="pattern" aria-hidden="true" width="100%" height="100%">
            <defs>
                <pattern id="khatam" width="44" height="44" patternUnits="userSpaceOnUse">
                    <g fill="none" stroke="currentColor" stroke-width="1.4">
                        <rect x="10" y="10" width="24" height="24" />
                        <rect x="10" y="10" width="24" height="24" transform="rotate(45 22 22)" />
                        <circle cx="22" cy="22" r="5" />
                        <path d="M0 0 L6 6 M44 0 L38 6 M0 44 L6 38 M44 44 L38 38" />
                    </g>
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#khatam)" />
        </svg>
        <div class="inner"></div>
        <div class="content">
            <span class="eyebrow">
                <svg class="star" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 1l3 5.5 6-1-1 6L23 12l-3 .5 1 6-6-1L12 23l-3-5.5-6 1 1-6L1 12l3-.5-1-6 6 1z" /></svg>
                Jadwal Sholat
            </span>
            <p class="font-display mt-1.5 font-semibold" style="font-size: 28pt; line-height: 1.1">{{ $start->translatedFormat('F Y') }}</p>
            @if ($hijriRange)<p class="mt-1 text-base opacity-90">{{ $hijriRange }}</p>@endif
            <p class="mt-2 text-sm font-semibold">{{ $profile->name ?: $school }}</p>
            @if ($profile->city)<p class="text-xs opacity-80">{{ $profile->city }}</p>@endif
        </div>
    </header>

    <table class="mt-3">
        <thead>
            <tr>
                <th style="text-align: left">Tanggal</th>
                <th style="text-align: left">Hijriah</th>
                @foreach (PrayerTime::NAMES as $key => $name)
                    <th @class(['main' => in_array($key, $main, true)])>{{ $name }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @php
                    $date = $row->date->toDateString();
                    $isFriday = $row->date->dayOfWeekIso === 5;
                    $day = $hijri[$date] ?? null;
                @endphp
                <tr @class(['friday' => $isFriday])>
                    <td class="date font-tabular"><b>{{ $row->date->format('j') }}</b><span>{{ $row->date->translatedFormat('l') }}</span></td>
                    <td class="hijri font-tabular">{{ $day ? $day['day'].' '.\App\Modules\Masjid\Support\HijriDate::MONTHS[$day['month'] - 1] : '' }}</td>
                    @foreach (array_keys(PrayerTime::NAMES) as $prayer)
                        <td @class(['font-tabular', 'main' => in_array($prayer, $main, true), 'side' => ! in_array($prayer, $main, true)])>{{ str_replace(':', '.', $row->{$prayer}) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="10" class="py-8 text-text-muted">Jadwal bulan ini belum dihitung.</td></tr>
            @endforelse
        </tbody>
    </table>

    <footer class="mt-2 flex items-start justify-between gap-4 text-text-muted" style="break-inside: avoid; font-size: 7.5pt">
        <p><span class="sw bg-accent-soft"></span>Hari Jumat, Dzuhur diganti sholat Jumat. <b class="text-text">Tebal</b>: lima waktu wajib.</p>
        <p class="text-right">Sudut Subuh {{ str_replace('.', ',', (string) (float) $profile->fajr_angle) }}°, Isya {{ str_replace('.', ',', (string) (float) $profile->isha_angle) }}°, Ashar {{ $profile->asr_method === 'hanafi' ? 'Hanafi' : 'jumhur' }}, Imsak {{ $profile->imsak_minutes }} menit sebelum Subuh.<br>Tanggal hijriah perkiraan, dapat berbeda satu hari menurut rukyat.</p>
    </footer>
</body>
</html>
