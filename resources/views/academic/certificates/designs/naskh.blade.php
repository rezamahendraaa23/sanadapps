@once
<style>
    .d-naskh { background: color-mix(in srgb, var(--color-accent-soft) 45%, var(--color-surface)) }
    .d-naskh .rules { position: absolute; inset: 0; width: 297mm; height: 210mm; color: var(--color-accent) }
    .d-naskh .rose { position: absolute; width: 26mm; height: 26mm; color: var(--color-accent) }
    .d-naskh .inner { position: absolute; left: 34mm; right: 34mm; top: 18mm; bottom: 16mm; display: flex; flex-direction: column; align-items: center; text-align: center }
    .d-naskh .basmalah { font-family: 'Scheherazade New'; font-size: 15pt; color: var(--color-text) }
    .d-naskh .school { font-family: 'Scheherazade New'; font-weight: 700; font-size: 15pt; color: var(--color-primary) }
    .d-naskh .title { font-family: 'Scheherazade New'; font-weight: 700; font-size: 50pt; line-height: 1.15; color: var(--color-text) }
    .d-naskh .sub { font-family: 'Scheherazade New'; font-size: 15pt; color: var(--color-accent) }
    .d-naskh .name { margin-top: 1mm; font-family: 'Scheherazade New'; font-size: 30pt; color: var(--color-primary) }
    .d-naskh .grade { margin-top: 3mm; border-radius: 1mm; background: transparent; border-top: 0.4mm solid var(--color-accent); border-bottom: 0.4mm solid var(--color-accent); color: var(--color-text) }
    .d-naskh .foot { margin-top: auto; width: 100%; display: flex; justify-content: space-between; align-items: flex-end; text-align: left }
</style>
@endonce
<svg class="rules" viewBox="0 0 297 210" aria-hidden="true">
    <rect x="9" y="9" width="279" height="192" fill="none" stroke="currentColor" stroke-width="1.4"/>
    <rect x="12.5" y="12.5" width="272" height="185" fill="none" stroke="currentColor" stroke-width="0.4"/>
    <rect x="15" y="15" width="267" height="180" fill="none" stroke="var(--color-primary)" stroke-width="0.25" stroke-dasharray="1.2 1.2"/>
</svg>
@foreach (['left: 4mm; top: 4mm', 'right: 4mm; top: 4mm', 'left: 4mm; bottom: 4mm', 'right: 4mm; bottom: 4mm'] as $place)
    <svg class="rose" style="{{ $place }}" viewBox="0 0 100 100" aria-hidden="true">
        @foreach ([0, 45, 90, 135, 180, 225, 270, 315] as $angle)
            <ellipse cx="50" cy="28" rx="7" ry="20" fill="currentColor" opacity="0.85" transform="rotate({{ $angle }} 50 50)"/>
        @endforeach
        <circle cx="50" cy="50" r="10" fill="var(--color-primary)"/>
        <circle cx="50" cy="50" r="4" fill="currentColor"/>
    </svg>
@endforeach
<div class="inner">
    @include('academic.certificates.parts.logos')
    <div class="basmalah ar">بِسْمِ اللّٰهِ الرَّحْمٰنِ الرَّحِيْمِ</div>
    <div class="school {{ $arabicName ? 'ar' : '' }}">{{ $arabicName ?: $identity->displayName() }}</div>
    <div class="title ar">شَهَادَة</div>
    <div class="sub ar">{{ $arabicTitle }}</div>
    @include('academic.certificates.parts.body')
    <div class="foot">@include('academic.certificates.parts.foot')</div>
</div>
