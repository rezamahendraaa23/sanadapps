<div class="flex flex-col gap-4">
    <x-ui.card>
        <x-ui.input label="Tanggal" name="date" type="date" wire:model.live="date" />
    </x-ui.card>

    <x-ui.card title="Menunggu verifikasi">
        @if ($logs->isNotEmpty())
            <x-slot:actions>
                <x-ui.button variant="secondary" wire:click="verifyAll" wire:confirm="Verifikasi semua amalan di tanggal ini?">Setujui Semua</x-ui.button>
            </x-slot:actions>
        @endif
        @forelse ($logs as $studentLogs)
            <fieldset wire:key="s-{{ $studentLogs->first()->student_id }}" class="border-b border-border py-2 last:border-b-0">
                <legend class="text-base font-semibold break-words">{{ $studentLogs->first()->student->name }}</legend>
                @foreach ($studentLogs as $log)
                    <label wire:key="l-{{ $log->uuid }}" class="flex min-h-11 items-center gap-3 text-base">
                        <input type="checkbox" value="{{ $log->uuid }}" wire:model.live="selected" class="size-5 accent-primary">{{ $log->item->name }}
                    </label>
                @endforeach
            </fieldset>
        @empty
            <x-ui.empty-state icon="circle-check" message="Tidak ada amalan yang perlu diverifikasi." class="py-2" />
        @endforelse
        @if ($selected !== [])
            <x-ui.button class="mt-3 w-full md:w-auto" wire:click="verifySelected" wire:loading.attr="disabled" wire:target="verifySelected">Setujui {{ count($selected) }} Amalan</x-ui.button>
        @endif
    </x-ui.card>
</div>
