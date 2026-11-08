@once
<style>
    .d-mihrab { background: var(--color-bg) }
    .d-mihrab .frame { position: absolute; inset: 0; width: 297mm; height: 210mm; color: var(--color-accent) }
    .d-mihrab .corner { position: absolute; width: 22mm; height: 22mm; color: var(--color-primary); opacity: 0.55 }
    .d-mihrab .inner { position: absolute; left: 40mm; right: 40mm; top: 20mm; bottom: 15mm; display: flex; flex-direction: column; align-items: center; text-align: center }
    .d-mihrab .basmalah { font-family: 'Amiri'; font-size: 14pt; color: var(--color-primary) }
    .d-mihrab .school { margin-top: 2mm; font-family: 'Amiri'; font-size: 13pt; font-weight: 700 }
    .d-mihrab .title { margin-top: 3mm; font-family: 'Aref Ruqaa'; font-weight: 700; font-size: 52pt; line-height: 1.15; color: var(--color-primary) }
    .d-mihrab .sub { font-family: 'Amiri'; font-size: 15pt; color: var(--color-accent) }
    .d-mihrab .latin { margin-top: 1mm; font-size: 8.5pt; font-weight: 800; letter-spacing: 0.3em; text-transform: uppercase; color: var(--color-text-muted) }
    .d-mihrab .given { margin-top: 5mm }
    .d-mihrab .name { margin-top: 1mm; font-family: 'Amiri'; font-size: 30pt; color: var(--color-primary) }
    .d-mihrab .text { margin-top: 2mm }
    .d-mihrab .grade { margin-top: 3mm; background: var(--color-primary); color: var(--color-on-primary) }
    .d-mihrab .foot { margin-top: auto; width: 100%; display: flex; justify-content: space-between; align-items: flex-end; text-align: left }
</style>
@endonce
<svg class="frame" viewBox="0 0 297 210" aria-hidden="true">
    <rect x="6" y="6" width="285" height="198" fill="none" stroke="currentColor" stroke-width="0.6"/>
    <path d="M24 200V78Q24 32 148.5 10Q273 32 273 78V200Z" fill="var(--color-surface)" stroke="currentColor" stroke-width="1.2"/>
    <path d="M29 196V79Q29 37 148.5 16Q268 37 268 79V196Z" fill="none" stroke="var(--color-primary)" stroke-width="0.35"/>
</svg>
@foreach (['left: 9mm; top: 9mm', 'right: 9mm; top: 9mm'] as $place)
    <div class="corner" style="{{ $place }}">@include('academic.certificates.parts.star', ['stroke' => 2.2])</div>
@endforeach
<div class="inner">
    @include('academic.certificates.parts.logos')
    <div class="basmalah ar">بِسْمِ اللّٰهِ الرَّحْمٰنِ الرَّحِيْمِ</div>
    <div class="school {{ $arabicName ? 'ar' : '' }}">{{ $arabicName ?: $identity->displayName() }}</div>
    <div class="title ar">شَهَادَة</div>
    <div class="sub ar">{{ $arabicTitle }}</div>
    <div class="latin">{{ $latinTitle }}</div>
    @include('academic.certificates.parts.body')
    <div class="foot">@include('academic.certificates.parts.foot')</div>
</div>
