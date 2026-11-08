@once
<style>
    .d-tegas { background: var(--color-accent-soft) }
    .d-tegas .giant { position: absolute; right: -6mm; top: -16mm; font-family: 'Lalezar'; font-size: 230pt; line-height: 1; color: color-mix(in srgb, var(--color-primary) 12%, transparent); white-space: nowrap }
    .d-tegas .bar { position: absolute; left: 0; top: 0; bottom: 0; width: 8mm; background: var(--color-primary) }
    .d-tegas .main { position: absolute; left: 24mm; right: 22mm; top: 18mm; bottom: 14mm; display: flex; flex-direction: column }
    .d-tegas .chip { align-self: flex-start; padding: 1.4mm 5mm; border-radius: 99mm; background: var(--color-primary); color: var(--color-on-primary); font-size: 8.5pt; font-weight: 800; letter-spacing: 0.2em; text-transform: uppercase; transform: rotate(-2deg) }
    .d-tegas .title { margin-top: 3mm; font-family: 'Lalezar'; font-size: 60pt; line-height: 1.1; color: var(--color-primary); text-align: left; direction: rtl; align-self: flex-start }
    .d-tegas .sub { font-family: 'Marhey'; font-weight: 600; font-size: 15pt; color: var(--color-accent); align-self: flex-start }
    .d-tegas .school { margin-top: 1mm; font-size: 10pt; font-weight: 800; color: var(--color-text-muted) }
    .d-tegas .given { margin-top: 6mm }
    .d-tegas .name { font-family: var(--font-display); font-size: 38pt; line-height: 1; color: var(--color-text) }
    .d-tegas .text { margin-top: 3mm; max-width: 170mm }
    .d-tegas .grade { margin-top: 4mm; align-self: flex-start; background: var(--color-text); color: var(--color-surface); transform: rotate(-1.5deg) }
    .d-tegas .foot { margin-top: auto; display: flex; justify-content: space-between; align-items: flex-end }
</style>
@endonce
<div class="giant ar" aria-hidden="true">شَهَادَة</div>
<div class="bar"></div>
<div class="main">
    @include('academic.certificates.parts.logos', ['align' => 'left'])
    <span class="chip">{{ $latinTitle }}</span>
    <div class="title ar">شَهَادَة</div>
    <div class="sub ar">{{ $arabicTitle }}</div>
    <div class="school {{ $arabicName ? 'ar' : '' }}" @if ($arabicName) style="font-family: 'Marhey'; font-size: 13pt; align-self: flex-start" @endif>{{ $arabicName ?: $identity->displayName() }}</div>
    @include('academic.certificates.parts.body')
    <div class="foot">@include('academic.certificates.parts.foot')</div>
</div>
