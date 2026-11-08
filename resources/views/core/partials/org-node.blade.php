@php
    $position = $node['position'];
    $tone = ['bg-primary text-on-primary', 'bg-accent-soft text-accent', 'bg-primary-soft text-primary', 'bg-info-soft text-info'][min($depth, 3)];
    $ring = ['ring-primary', 'ring-accent', 'ring-primary-border', 'ring-info'][min($depth, 3)];
@endphp
<li wire:key="org-{{ $position->uuid }}">
    <article class="group relative z-10 mt-7 flex w-52 flex-col items-center rounded-[1.25rem] border border-border bg-surface px-3 pt-9 pb-2 text-center shadow-sm transition-shadow hover:shadow-md">
        <span @class(['absolute -top-7 inline-flex size-14 items-center justify-center overflow-hidden rounded-full font-display text-lg font-semibold ring-4 ring-offset-2 ring-offset-surface', $tone, $ring])>
            @if ($position->photo_path)
                <img src="{{ route('staff.org.photo', $position) }}" alt="" class="size-full object-cover">
            @else
                {{ $position->initials() }}
            @endif
        </span>
        <span @class(['rounded-full px-3 py-0.5 text-xs font-semibold', $depth === 0 ? 'bg-primary text-on-primary' : 'bg-bg text-text'])>{{ $position->title }}</span>
        @if ($position->person_name)
            <span class="mt-1.5 text-base leading-snug font-semibold break-words">{{ $position->person_name }}</span>
        @else
            <span class="mt-1.5 text-sm text-text-muted italic">Belum diisi</span>
        @endif
        @if ($position->note)
            <span class="text-xs text-text-muted">{{ $position->note }}</span>
        @endif
        <span class="mt-1 flex items-center justify-center gap-0.5">
            <button type="button" wire:click="add('{{ $position->uuid }}')" class="inline-flex size-11 items-center justify-center rounded-full text-primary hover:bg-primary-soft" aria-label="Tambah bawahan {{ $position->title }}"><x-ui.icon name="plus" size="sm" /></button>
            <button type="button" wire:click="edit('{{ $position->uuid }}')" class="inline-flex size-11 items-center justify-center rounded-full text-text-muted hover:bg-bg" aria-label="Ubah {{ $position->title }}"><x-ui.icon name="pencil" size="sm" /></button>
            <button type="button" wire:click="move('{{ $position->uuid }}', -1)" class="inline-flex size-11 items-center justify-center rounded-full text-text-muted hover:bg-bg" aria-label="Geser {{ $position->title }} ke kiri"><x-ui.icon name="arrow-left" size="sm" /></button>
            <button type="button" wire:click="remove('{{ $position->uuid }}')" wire:confirm="Hapus {{ $position->title }}? Bawahannya pindah ke atasan." class="inline-flex size-11 items-center justify-center rounded-full text-danger hover:bg-danger-soft" aria-label="Hapus {{ $position->title }}"><x-ui.icon name="trash-2" size="sm" /></button>
        </span>
    </article>
    @if ($node['children'] !== [])
        <ul>
            @foreach ($node['children'] as $child)
                @include('core.partials.org-node', ['node' => $child, 'depth' => $depth + 1])
            @endforeach
        </ul>
    @endif
</li>
