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
        @page { size: A4; margin: 10mm }
        html, body { background: white }
        .card-print { width: 85.6mm; height: 54mm; break-inside: avoid; }
        .card-print svg { width: 22mm; height: 22mm; }
    </style>
</head>
<body class="bg-surface font-sans text-text">
    <div class="flex flex-wrap gap-4">
        @foreach ($cards as $card)
            <article class="card-print flex flex-col overflow-hidden rounded-card border border-border">
                <header class="flex items-center gap-2 bg-primary px-3 py-2 text-on-primary">
                    @if ($logoData)
                        <img src="{{ $logoData }}" alt="" class="size-7 rounded-full bg-surface object-contain">
                    @endif
                    <p class="font-display truncate text-sm font-semibold">{{ $identity->displayName() }}</p>
                </header>
                <div class="flex flex-1 items-center gap-3 p-3">
                    @php($photo = $card->student->photo_path && Storage::disk('local')->exists($card->student->photo_path) ? 'data:'.Storage::disk('local')->mimeType($card->student->photo_path).';base64,'.base64_encode(Storage::disk('local')->get($card->student->photo_path)) : null)
                    @if ($photo)
                        <img src="{{ $photo }}" alt="" class="h-[26mm] w-[20mm] rounded-control object-cover">
                    @else
                        <span class="font-display flex h-[26mm] w-[20mm] items-center justify-center rounded-control bg-primary-soft text-2xl font-semibold text-primary">{{ mb_substr($card->student->name, 0, 1) }}</span>
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="font-display text-base leading-tight font-semibold">{{ $card->student->name }}</p>
                        <p class="font-tabular mt-1 text-xs">NIS {{ $card->student->nis }}</p>
                        @if ($card->student->classroom)<p class="text-xs text-text-muted">{{ $card->student->classroom->name }}</p>@endif
                    </div>
                    <div class="shrink-0">{!! Qr::svg(QrToken::printed($card), 120) !!}</div>
                </div>
            </article>
        @endforeach
    </div>
</body>
</html>
