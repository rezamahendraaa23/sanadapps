@php $tz = config('sanad.display_timezone'); @endphp

<div class="flex flex-col gap-4"
    x-data
    x-on:keydown.window="
        if ($event.target.closest('input, textarea, select, [role=dialog]') || $event.ctrlKey || $event.metaKey || $event.altKey) return;
        const first = $el.querySelector('[data-review]');
        if (! first) return;
        if ($event.key === 'h') first.querySelector('[data-approve]').click();
        if ($event.key === 't') first.querySelector('[data-reject]').click();
    "
>
    @if ($records->isEmpty())
        <x-ui.card><x-ui.empty-state icon="circle-check" message="Tidak ada absensi yang menunggu tinjauan." class="py-2" /></x-ui.card>
    @else
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-base"><strong class="font-tabular">{{ $records->count() }}</strong> menunggu. <span class="hidden text-sm text-text-muted lg:inline">Tekan H untuk hadir, T untuk tolak pada baris teratas.</span></p>
            @if ($selected !== [])
                <x-ui.button wire:click="approveSelected" wire:loading.attr="disabled" wire:target="approveSelected">Terima {{ count($selected) }} Terpilih</x-ui.button>
            @endif
        </div>

        @foreach ($records as $record)
            <x-ui.card wire:key="t-{{ $record->uuid }}" data-review>
                <div class="grid gap-3 md:grid-cols-[220px_1fr]">
                    @if ($record->evidence_path)
                        <img src="{{ route('attendance.evidence', $record) }}" alt="Foto absensi {{ $record->student->name }}" loading="lazy" class="aspect-[3/4] w-full max-w-[220px] rounded-control bg-bg object-cover">
                    @else
                        <div class="flex aspect-[3/4] w-full max-w-[220px] items-center justify-center rounded-control bg-bg text-sm text-text-muted">Foto sudah terhapus</div>
                    @endif
                    <div class="flex min-w-0 flex-col gap-1">
                        <label class="flex min-h-11 items-center gap-3">
                            <input type="checkbox" value="{{ $record->uuid }}" wire:model.live="selected" class="size-5 shrink-0 accent-primary" aria-label="Pilih {{ $record->student->name }}">
                            <span class="min-w-0 text-lg font-semibold break-words">{{ $record->student->name }}</span>
                        </label>
                        <p class="text-sm text-text-muted break-words">{{ $record->session->title }}, {{ $record->scanned_at?->setTimezone($tz)->translatedFormat('j M, H.i') }}</p>
                        <p class="text-base font-semibold">Kecocokan <span class="font-tabular">{{ number_format((float) $record->match_score, 2, ',', '') }}</span>, perlu konfirmasi</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <x-ui.button data-approve wire:click="approve('{{ $record->uuid }}')" wire:loading.attr="disabled" wire:target="approve">Hadir<span class="sr-only"> {{ $record->student->name }}</span></x-ui.button>
                            <x-ui.button data-reject variant="secondary" wire:click="askReject('{{ $record->uuid }}')" wire:loading.attr="disabled" wire:target="askReject">Tolak<span class="sr-only"> {{ $record->student->name }}</span></x-ui.button>
                        </div>
                    </div>
                </div>
            </x-ui.card>
        @endforeach
    @endif

    <x-ui.modal name="tolak-absensi" title="Tolak absensi">
        <div class="flex flex-col gap-1">
            <label for="field-reason" class="text-sm font-semibold">Alasan</label>
            <textarea id="field-reason" rows="3" wire:model="reason" class="w-full rounded-control border border-text-muted bg-surface p-3 text-base"></textarea>
            @error('reason')<p role="alert" class="text-sm text-danger">{{ $message }}</p>@enderror
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button variant="danger" wire:click="reject" wire:loading.attr="disabled" wire:target="reject">Tolak Absensi</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
