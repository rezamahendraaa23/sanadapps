@once
<style>
    .d-kufi { background: var(--color-surface); display: flex }
    .d-kufi .side { position: relative; flex: none; width: 92mm; height: 210mm; background: var(--color-primary); color: var(--color-on-primary); display: flex; flex-direction: column; justify-content: space-between; padding: 14mm 12mm; box-sizing: border-box; overflow: hidden }
    .d-kufi .side .big { font-family: 'Reem Kufi'; font-weight: 700; font-size: 66pt; line-height: 1; text-align: right }
    .d-kufi .side .sub { font-family: 'Noto Kufi Arabic'; font-size: 12pt; font-weight: 600; text-align: right; color: var(--color-accent-soft) }
    .d-kufi .side .school { font-family: 'Noto Kufi Arabic'; font-size: 11pt; font-weight: 700; text-align: right }
    .d-kufi .side .ghost { position: absolute; left: -20mm; bottom: -26mm; width: 110mm; height: 110mm; color: var(--color-on-primary); opacity: 0.12 }
    .d-kufi .side .tag { position: relative; align-self: flex-start; padding: 1.2mm 4mm; border-radius: 99mm; background: var(--color-accent); color: var(--color-on-primary); font-size: 8pt; font-weight: 800; letter-spacing: 0.2em; text-transform: uppercase }
    .d-kufi .main { flex: 1; display: flex; flex-direction: column; padding: 18mm 16mm 12mm 16mm }
    .d-kufi .eyebrow { font-size: 9pt; font-weight: 800; letter-spacing: 0.3em; text-transform: uppercase; color: var(--color-accent) }
    .d-kufi .given { margin-top: 10mm }
    .d-kufi .name { margin-top: 2mm; font-family: var(--font-display); font-size: 28pt; color: var(--color-primary) }
    .d-kufi .text { margin-top: 4mm; max-width: 150mm }
    .d-kufi .grade { margin-top: 5mm; align-self: flex-start; background: var(--color-primary-soft); color: var(--color-primary) }
    .d-kufi .foot { margin-top: auto; display: flex; justify-content: space-between; align-items: flex-end }
</style>
@endonce
<aside class="side">
    <span class="tag">{{ $latinTitle }}</span>
    <div class="ghost">@include('academic.certificates.parts.star', ['stroke' => 1.6])</div>
    <div style="position: relative">
        <div class="big ar">شَهَادَة</div>
        <div class="sub ar">{{ $arabicTitle }}</div>
    </div>
    <div class="school {{ $arabicName ? 'ar' : '' }}" style="position: relative">{{ $arabicName ?: $identity->displayName() }}</div>
</aside>
<div class="main">
    @include('academic.certificates.parts.logos', ['align' => 'left'])
    <div class="eyebrow">{{ $identity->displayName() }}</div>
    @include('academic.certificates.parts.body')
    <div class="foot">@include('academic.certificates.parts.foot')</div>
</div>
