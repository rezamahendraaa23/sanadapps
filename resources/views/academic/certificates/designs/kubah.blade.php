@once
<style>
    .d-kubah { background: linear-gradient(180deg, var(--color-surface) 0%, var(--color-surface) 60%, var(--color-primary-soft) 100%) }
    .d-kubah .skyline { position: absolute; left: 0; right: 0; bottom: 0; width: 297mm; height: 34mm; color: var(--color-primary) }
    .d-kubah .moon { position: absolute; right: 26mm; top: 18mm; width: 18mm; height: 18mm; color: var(--color-accent) }
    .d-kubah .inner { position: absolute; left: 30mm; right: 30mm; top: 14mm; bottom: 38mm; display: flex; flex-direction: column; align-items: center; text-align: center }
    .d-kubah .school { font-family: 'Amiri'; font-size: 13pt; color: var(--color-primary) }
    .d-kubah .title { font-family: 'Rakkas'; font-size: 54pt; line-height: 1.1; color: var(--color-primary) }
    .d-kubah .sub { font-family: 'Amiri'; font-size: 14pt; color: var(--color-accent) }
    .d-kubah .given { margin-top: 3mm }
    .d-kubah .name { font-family: 'Rakkas'; font-weight: 400; font-size: 30pt; color: var(--color-text) }
    .d-kubah .text { margin-top: 1mm }
    .d-kubah .grade { margin-top: 3mm; background: var(--color-accent); color: var(--color-on-primary) }
    .d-kubah .foot { margin-top: auto; width: 100%; display: flex; justify-content: space-between; align-items: flex-end; text-align: left }
    .d-kubah .foot .sign .who { margin-top: 12mm }
</style>
@endonce
<svg class="moon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M16 15.5A7 7 0 1 1 8.5 6a5.5 5.5 0 0 0 7.5 9.5z"/></svg>
<svg class="skyline" viewBox="0 0 297 48" preserveAspectRatio="none" aria-hidden="true">
    <g fill="currentColor">
        <rect x="0" y="34" width="297" height="14"/>
        <path d="M60 34V20a14 12 0 0 1 28 0v14z"/><path d="M74 4l1.6 3.2L74 10l-1.6-2.8z"/><rect x="73.4" y="7" width="1.2" height="5"/>
        <path d="M118 34V16a30 22 0 0 1 61 0v18z"/><path d="M148.5 0l2.2 4.4-2.2 3.6-2.2-3.6z"/><rect x="147.8" y="6" width="1.4" height="6"/>
        <path d="M209 34V20a14 12 0 0 1 28 0v14z"/><path d="M223 4l1.6 3.2L223 10l-1.6-2.8z"/><rect x="222.4" y="7" width="1.2" height="5"/>
        <rect x="100" y="8" width="5" height="26"/><path d="M99 8l3.5-7 3.5 7z"/>
        <rect x="192" y="8" width="5" height="26"/><path d="M191 8l3.5-7 3.5 7z"/>
        <rect x="30" y="18" width="4" height="16"/><path d="M29 18l3-6 3 6z"/>
        <rect x="263" y="18" width="4" height="16"/><path d="M262 18l3-6 3 6z"/>
    </g>
</svg>
<div class="inner">
    @include('academic.certificates.parts.logos')
    <div class="school {{ $arabicName ? 'ar' : '' }}">{{ $arabicName ?: $identity->displayName() }}</div>
    <div class="title ar">شَهَادَة</div>
    <div class="sub ar">{{ $arabicTitle }}</div>
    @include('academic.certificates.parts.body')
    <div class="foot">@include('academic.certificates.parts.foot')</div>
</div>
