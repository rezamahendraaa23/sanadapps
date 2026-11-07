<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Layar Masjid</title>
    <link rel="stylesheet" href="{{ asset('tv/display.css') }}?v=1">
    @if ($brandCss = app(\App\Support\SchoolIdentity::class)->brandCss())
        <style>{!! $brandCss !!}</style>
    @endif
</head>
<body>
    <div id="layar"></div>
    <script>
        window.SANAD_DISPLAY = {
            api: {!! json_encode(url('/api/v1/display')) !!},
            pairHint: {!! json_encode(url('/panel/masjid/layar')) !!},
            preview: {!! json_encode($preview) !!}
        };
    </script>
    <script src="{{ asset('tv/display.js') }}?v=1"></script>
</body>
</html>
