@once
<style>
    .d-nastaliq { background: var(--color-primary-soft) }
    .d-nastaliq .card { position: absolute; inset: 12mm; border-radius: 10mm; background: var(--color-surface); box-shadow: 0 2mm 0 color-mix(in srgb, var(--color-primary) 18%, transparent) }
    .d-nastaliq .blob { position: absolute; border-radius: 50%; background: color-mix(in srgb, var(--color-accent) 22%, transparent) }
    .d-nastaliq .inner { position: absolute; left: 34mm; right: 34mm; top: 20mm; bottom: 18mm; display: flex; flex-direction: column; align-items: center; text-align: center }
    .d-nastaliq .school { font-family: 'Lateef'; font-size: 17pt; color: var(--color-primary) }
    .d-nastaliq .title { margin-top: -2mm; font-family: 'Gulzar'; font-size: 46pt; line-height: 1.7; color: var(--color-primary) }
    .d-nastaliq .sub { margin-top: -4mm; font-family: 'Lateef'; font-size: 18pt; color: var(--color-accent) }
    .d-nastaliq .given { margin-top: 3mm }
    .d-nastaliq .name { margin-top: 1mm; font-family: var(--font-display); font-size: 30pt; color: var(--color-text) }
    .d-nastaliq .grade { margin-top: 3mm; background: var(--color-primary-soft); color: var(--color-primary) }
    .d-nastaliq .foot { margin-top: auto; width: 100%; display: flex; justify-content: space-between; align-items: flex-end; text-align: left }
</style>
@endonce
<div class="blob" style="width: 90mm; height: 90mm; left: -30mm; top: -30mm"></div>
<div class="blob" style="width: 70mm; height: 70mm; right: -20mm; bottom: -24mm"></div>
<div class="card"></div>
<div class="inner">
    @include('academic.certificates.parts.logos')
    <div class="school {{ $arabicName ? 'ar' : '' }}">{{ $arabicName ?: $identity->displayName() }}</div>
    <div class="title ar">شَهَادَة</div>
    <div class="sub ar">{{ $arabicTitle }}</div>
    @include('academic.certificates.parts.body')
    <div class="foot">@include('academic.certificates.parts.foot')</div>
</div>
