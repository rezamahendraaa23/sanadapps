@once
<style>
    .d-tinta { background: color-mix(in srgb, var(--color-primary) 70%, black); color: var(--color-on-primary) }
    .d-tinta .glow { position: absolute; width: 150mm; height: 150mm; left: 73.5mm; top: -40mm; border-radius: 50%; background: radial-gradient(circle, color-mix(in srgb, var(--color-accent) 30%, transparent), transparent 70%) }
    .d-tinta .frame { position: absolute; inset: 10mm; border: 0.5mm solid var(--color-accent); border-radius: 3mm }
    .d-tinta .star { position: absolute; width: 70mm; height: 70mm; color: var(--color-accent); opacity: 0.18 }
    .d-tinta .inner { position: absolute; left: 34mm; right: 34mm; top: 18mm; bottom: 18mm; display: flex; flex-direction: column; align-items: center; text-align: center }
    .d-tinta .basmalah { font-family: 'Aref Ruqaa'; font-size: 15pt; color: var(--color-accent-soft) }
    .d-tinta .title { font-family: 'Aref Ruqaa'; font-weight: 700; font-size: 58pt; line-height: 1.1; color: var(--color-accent-soft) }
    .d-tinta .sub { font-family: 'Aref Ruqaa'; font-size: 15pt; color: var(--color-on-primary); opacity: 0.85 }
    .d-tinta .school { margin-top: 1mm; font-size: 9pt; font-weight: 800; letter-spacing: 0.3em; text-transform: uppercase; color: var(--color-accent-soft) }
    .d-tinta .given { margin-top: 5mm; opacity: 0.85 }
    .d-tinta .name { margin-top: 1mm; font-family: 'Amiri'; font-size: 32pt; color: var(--color-on-primary) }
    .d-tinta .grade { margin-top: 3mm; background: var(--color-accent-soft); color: var(--color-text) }
    .d-tinta .meta { color: var(--color-accent-soft) }
    .d-tinta .logo-plate .logos img { padding: 1mm 2mm; border-radius: 2mm; background: var(--color-surface) }
    .d-tinta .foot { margin-top: auto; width: 100%; display: flex; justify-content: space-between; align-items: flex-end; text-align: left }
</style>
@endonce
<div class="glow"></div>
<div class="frame"></div>
<div class="star" style="left: -22mm; bottom: -22mm">@include('academic.certificates.parts.star', ['stroke' => 1.5])</div>
<div class="star" style="right: -22mm; top: -22mm">@include('academic.certificates.parts.star', ['stroke' => 1.5])</div>
<div class="inner">
    <div class="logo-plate">@include('academic.certificates.parts.logos')</div>
    <div class="basmalah ar">بِسْمِ اللّٰهِ الرَّحْمٰنِ الرَّحِيْمِ</div>
    <div class="title ar">شَهَادَة</div>
    <div class="sub ar">{{ $arabicTitle }}</div>
    <div class="school {{ $arabicName ? 'ar' : '' }}" @if ($arabicName) style="font-family: 'Aref Ruqaa'; font-size: 14pt; letter-spacing: 0; text-transform: none" @endif>{{ $arabicName ?: $identity->displayName() }}</div>
    @include('academic.certificates.parts.body')
    <div class="foot">@include('academic.certificates.parts.foot')</div>
</div>
