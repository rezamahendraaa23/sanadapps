@once
<style>
    .d-girih { background: var(--color-surface); display: flex }
    .d-girih .band { flex: none; width: 46mm; height: 210mm }
    .d-girih .main { flex: 1; display: flex; flex-direction: column; padding: 16mm 20mm 12mm 18mm }
    .d-girih .top { display: flex; justify-content: space-between; align-items: flex-start; gap: 8mm }
    .d-girih .school { font-family: 'El Messiri'; font-weight: 700; font-size: 12pt; color: var(--color-primary) }
    .d-girih .title { font-family: 'El Messiri'; font-weight: 700; font-size: 48pt; line-height: 1.1; color: var(--color-primary); text-align: right }
    .d-girih .sub { font-family: 'El Messiri'; font-size: 13pt; color: var(--color-accent); text-align: right }
    .d-girih .latin { font-family: 'El Messiri'; font-size: 11pt; font-weight: 700; letter-spacing: 0.25em; text-transform: uppercase; color: var(--color-text-muted) }
    .d-girih .given { margin-top: 8mm }
    .d-girih .name { margin-top: 1mm; font-family: 'El Messiri'; font-size: 32pt; color: var(--color-text) }
    .d-girih .name:after { content: ''; display: block; width: 40mm; height: 1mm; margin-top: 2mm; background: var(--color-accent) }
    .d-girih .text { margin-top: 3mm }
    .d-girih .grade { margin-top: 4mm; align-self: flex-start; background: var(--color-primary); color: var(--color-on-primary) }
    .d-girih .foot { margin-top: auto; display: flex; justify-content: space-between; align-items: flex-end }
</style>
@endonce
<svg class="band" viewBox="0 0 46 210" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
    <defs>
        <pattern id="girih-tile" width="23" height="23" patternUnits="userSpaceOnUse">
            <rect width="23" height="23" fill="var(--color-primary)"/>
            <g fill="none" stroke="var(--color-accent)" stroke-width="0.5">
                <path d="M11.5 2l3 6.5 6.5 3-6.5 3-3 6.5-3-6.5-6.5-3 6.5-3z"/>
                <path d="M0 0l8.5 8.5M23 0l-8.5 8.5M0 23l8.5-8.5M23 23l-8.5-8.5"/>
                <circle cx="11.5" cy="11.5" r="2.4"/>
            </g>
        </pattern>
    </defs>
    <rect width="46" height="210" fill="url(#girih-tile)"/>
    <rect x="40" width="6" height="210" fill="var(--color-accent)"/>
</svg>
<div class="main">
    <div class="top">
        <div>
            @include('academic.certificates.parts.logos', ['align' => 'left'])
            <div class="latin">{{ $latinTitle }}</div>
            <div class="school" style="margin-top: 2mm">{{ $identity->displayName() }}</div>
        </div>
        <div>
            <div class="title ar">شَهَادَة</div>
            <div class="sub ar">{{ $arabicTitle }}</div>
            @if ($arabicName)<div class="sub ar" style="color: var(--color-primary)">{{ $arabicName }}</div>@endif
        </div>
    </div>
    @include('academic.certificates.parts.body')
    <div class="foot">@include('academic.certificates.parts.foot')</div>
</div>
