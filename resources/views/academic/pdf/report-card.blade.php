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
    <header class="rule-double flex items-center gap-3 pb-3">
        @if ($logoData)
            <img src="{{ $logoData }}" alt="" class="size-14 object-contain">
        @endif
        <div class="min-w-0">
            <p class="font-display text-xl font-semibold">{{ $identity->displayName() }}</p>
            @if ($profile->address)<p class="text-xs text-text-muted">{{ $profile->address }}</p>@endif
        </div>
    </header>

    <p dir="rtl" lang="ar" class="font-arabic mt-3 text-center text-[1.375rem] leading-[2]">بِسْمِ ٱللَّهِ ٱلرَّحْمَٰنِ ٱلرَّحِيمِ</p>

    <h1 class="font-display mt-2 text-center text-2xl font-semibold">Rapor {{ $card->term->name }}</h1>
    @if ($card->status !== 'published')
        <p class="text-center text-sm font-semibold text-danger">DRAF, belum diterbitkan</p>
    @endif

    <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-1 text-sm">
        <div class="flex gap-2"><dt class="w-24 text-text-muted">Nama</dt><dd class="font-semibold">{{ $card->student->name }}</dd></div>
        <div class="flex gap-2"><dt class="w-24 text-text-muted">Tahun ajaran</dt><dd>{{ $card->term->academicYear?->name }}</dd></div>
        <div class="flex gap-2"><dt class="w-24 text-text-muted">NIS</dt><dd class="font-tabular">{{ $card->student->nis }}</dd></div>
        <div class="flex gap-2"><dt class="w-24 text-text-muted">Kelas</dt><dd>{{ $card->classroom?->name }}</dd></div>
    </dl>

    <table class="mt-4 w-full border-collapse text-sm">
        <thead>
            <tr class="border-b-2 border-text text-left">
                <th class="py-1">Mata pelajaran</th><th class="py-1 text-right">KKM</th><th class="py-1 text-right">Nilai</th><th class="py-1 text-center">Predikat</th><th class="py-1">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($card->items->sortBy('subject.name') as $item)
                <tr class="border-b border-border">
                    <td class="py-1.5">{{ $item->subject->name }}</td>
                    <td class="font-tabular py-1.5 text-right">{{ $item->subject->kkm }}</td>
                    <td class="font-tabular py-1.5 text-right font-semibold">{{ rtrim(rtrim(number_format($item->final_score, 2, ',', '.'), '0'), ',') }}</td>
                    <td class="py-1.5 text-center">{{ $item->predicate }}</td>
                    <td class="py-1.5">{{ $item->remark }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2 class="font-display mt-5 text-base font-semibold">Kehadiran</h2>
    <p class="text-sm">
        Hadir {{ $attendance['hadir'] ?? 0 }}, terlambat {{ $attendance['terlambat'] ?? 0 }}, izin {{ $attendance['izin'] ?? 0 }}, sakit {{ $attendance['sakit'] ?? 0 }}, alpa {{ $attendance['alpa'] ?? 0 }}.
    </p>

    @if ($card->homeroom_note)
        <h2 class="font-display mt-4 text-base font-semibold">Catatan wali kelas</h2>
        <p class="text-sm">{{ $card->homeroom_note }}</p>
    @endif

    <div class="mt-10 flex justify-end text-sm">
        <div class="w-56 text-center">
            <p>Wali kelas</p>
            <p class="mt-16 border-t border-text pt-1 font-semibold">{{ $card->classroom?->homeroomTeacher?->name ?? ' ' }}</p>
        </div>
    </div>
</body>
</html>
