@once
<style>
    .d-khatam { background: var(--color-surface) }
    .d-khatam .band { position: absolute; inset: 0; width: 297mm; height: 210mm; color: var(--color-primary) }
    .d-khatam .panel { position: absolute; left: 20mm; right: 20mm; top: 20mm; bottom: 20mm; background: var(--color-surface); border: 0.5mm solid var(--color-accent); display: flex; flex-direction: column; align-items: center; text-align: center; padding: 7mm 18mm 6mm }
    .d-khatam .seal { width: 18mm; height: 18mm; color: var(--color-accent) }
    .d-khatam .school { font-family: 'Amiri'; font-weight: 700; font-size: 14pt; color: var(--color-primary) }
    .d-khatam .title { font-family: 'Amiri'; font-weight: 700; font-size: 46pt; line-height: 1.2; color: var(--color-primary) }
    .d-khatam .sub { font-family: 'Amiri'; font-size: 14pt; color: var(--color-accent) }
    .d-khatam .rule { width: 60mm; height: 0.4mm; margin: 2mm 0; background: var(--color-accent) }
    .d-khatam .name { font-family: 'Amiri'; font-size: 28pt; margin-top: 1mm }
    .d-khatam .grade { margin-top: 3mm; border: 0.4mm solid var(--color-primary); color: var(--color-primary) }
    .d-khatam .foot { margin-top: auto; width: 100%; display: flex; justify-content: space-between; align-items: flex-end; text-align: left }
</style>
@endonce
<svg class="band" viewBox="0 0 297 210" aria-hidden="true">
    <defs>
        <pattern id="khatam-tile" width="14" height="14" patternUnits="userSpaceOnUse">
            <rect width="14" height="14" fill="var(--color-primary)"/>
            <path d="M7 1.2l1.7 1.7h2.4v2.4L12.8 7l-1.7 1.7v2.4H8.7L7 12.8 5.3 11.1H2.9V8.7L1.2 7l1.7-1.7V2.9h2.4z" fill="none" stroke="var(--color-accent)" stroke-width="0.45"/>
            <circle cx="7" cy="7" r="1.3" fill="var(--color-accent)"/>
        </pattern>
    </defs>
    <path fill-rule="evenodd" d="M0 0H297V210H0ZM16 16V194H281V16Z" fill="url(#khatam-tile)"/>
</svg>
<div class="panel">
    @if ($logos !== [])
        @include('academic.certificates.parts.logos')
    @else
        <div class="seal">@include('academic.certificates.parts.star', ['stroke' => 3])</div>
    @endif
    <div class="school {{ $arabicName ? 'ar' : '' }}">{{ $arabicName ?: $identity->displayName() }}</div>
    <div class="title ar">شَهَادَة</div>
    <div class="sub ar">{{ $arabicTitle }}</div>
    <div class="rule"></div>
    @include('academic.certificates.parts.body')
    <div class="foot">@include('academic.certificates.parts.foot')</div>
</div>
