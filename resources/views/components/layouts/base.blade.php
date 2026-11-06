@props(['title' => null])

@inject('identity', \App\Support\SchoolIdentity::class)

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title ? $title.' · ' : '' }}{{ $identity->displayName() }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if ($brandCss = $identity->brandCss())
        <style>{!! $brandCss !!}</style>
    @endif
    @livewireStyles
</head>
<body class="min-h-dvh bg-bg text-base text-text antialiased">
    <a href="#konten" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[1000] focus:rounded-control focus:bg-surface focus:p-3 focus:text-primary">
        Lewati ke konten utama
    </a>
    {{ $slot }}
    <x-ui.toasts />
    @livewireScripts
</body>
</html>
