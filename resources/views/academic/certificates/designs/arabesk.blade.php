@once
<style>
    .d-arabesk { background: var(--color-surface) }
    .d-arabesk .vine { position: absolute; width: 50mm; height: 50mm; color: var(--color-primary) }
    .d-arabesk .line { position: absolute; inset: 12mm; border: 0.3mm solid color-mix(in srgb, var(--color-primary) 40%, transparent) }
    .d-arabesk .inner { position: absolute; left: 46mm; right: 46mm; top: 16mm; bottom: 16mm; display: flex; flex-direction: column; align-items: center; text-align: center }
    .d-arabesk .school { font-family: 'Amiri'; font-weight: 400; font-size: 14pt; color: var(--color-text-muted) }
    .d-arabesk .title { font-family: 'Katibeh'; font-size: 74pt; line-height: 1; color: var(--color-primary) }
    .d-arabesk .sub { font-family: 'Katibeh'; font-size: 22pt; line-height: 1.2; color: var(--color-accent) }
    .d-arabesk .given { margin-top: 4mm }
    .d-arabesk .name { margin-top: 1mm; font-family: 'Amiri'; font-size: 30pt; color: var(--color-text) }
    .d-arabesk .grade { margin-top: 3mm; background: var(--color-accent-soft); color: var(--color-text) }
    .d-arabesk .foot { margin-top: auto; width: 100%; display: flex; justify-content: space-between; align-items: flex-end; text-align: left }
</style>
@endonce
<div class="line"></div>
@foreach (['left: 0; top: 0' => '', 'right: 0; top: 0' => 'scale(-1, 1)', 'left: 0; bottom: 0' => 'scale(1, -1)', 'right: 0; bottom: 0' => 'scale(-1, -1)'] as $place => $flip)
    <svg class="vine" style="{{ $place }}; transform: {{ $flip ?: 'none' }}" viewBox="0 0 100 100" aria-hidden="true">
        <g fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
            <path d="M8 92C8 46 46 8 92 8"/>
            <path d="M8 70c10-4 16-12 16-22s-8-14-14-10-4 14 4 14"/>
            <path d="M70 8c-4 10-12 16-22 16S34 16 38 10s14-4 14 4"/>
            <path d="M24 48c10 4 18 2 24-6"/><path d="M48 24c4 10 2 18-6 24"/>
            <path d="M34 66c6-2 10-6 10-12"/><path d="M66 34c-2 6-6 10-12 10"/>
        </g>
        <g fill="var(--color-accent)">
            <path d="M8 8l6 3-3 3z"/><circle cx="24" cy="24" r="3.2"/><circle cx="44" cy="44" r="2.2"/>
            <path d="M14 30c-4 0-6-3-6-6 3 0 6 2 6 6z"/><path d="M30 14c0-4-3-6-6-6 0 3 2 6 6 6z"/>
        </g>
    </svg>
@endforeach
<div class="inner">
    @include('academic.certificates.parts.logos')
    <div class="school {{ $arabicName ? 'ar' : '' }}">{{ $arabicName ?: $identity->displayName() }}</div>
    <div class="title ar">شَهَادَة</div>
    <div class="sub ar">{{ $arabicTitle }}</div>
    @include('academic.certificates.parts.body')
    <div class="foot">@include('academic.certificates.parts.foot')</div>
</div>
