@if ($student?->photo_path)
    <img src="{{ route('staff.gate.photo', $student) }}" alt="" class="{{ $class ?? 'size-14' }} shrink-0 rounded-control object-cover">
@else
    <span class="font-display {{ $class ?? 'size-14' }} flex shrink-0 items-center justify-center rounded-control bg-primary-soft text-xl font-semibold text-primary" aria-hidden="true">{{ mb_substr($student?->name ?? '?', 0, 1) }}</span>
@endif
