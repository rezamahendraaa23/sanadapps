<div class="flex flex-col gap-4">
    <x-ui.card title="Pola mingguan">
        <x-slot:actions><x-ui.button icon="plus" x-data x-on:click="$dispatch('open-modal', 'tambah-piket')">Tambah Piket</x-ui.button></x-slot:actions>
        @forelse ($rosters as $roster)
            <p wire:key="p-{{ $roster->uuid }}" class="border-b border-border py-2 text-base last:border-b-0 break-words">{{ $days[$roster->day_of_week] }}, <span class="font-tabular">{{ str_replace(':', '.', substr((string) $roster->start_time, 0, 5)) }} sampai {{ str_replace(':', '.', substr((string) $roster->end_time, 0, 5)) }}</span>: <strong>{{ $roster->name }}</strong>{{ $roster->location ? ', '.$roster->location : '' }}</p>
        @empty
            <x-ui.empty-state icon="calendar-clock" message="Belum ada pola piket." class="py-2" />
        @endforelse
    </x-ui.card>

    @if ($rosterOptions !== [])
        <x-ui.card title="Tugaskan">
            <form wire:submit="assign" class="flex flex-col gap-3">
                <div class="grid gap-3 md:grid-cols-2">
                    <x-ui.select label="Piket" name="roster_id" required placeholder="Pilih" :options="$rosterOptions" wire:model="roster_id" />
                    <x-ui.input label="Tanggal" name="duty_date" type="date" required wire:model="duty_date" />
                </div>
                @if ($personName)
                    <p class="flex flex-wrap items-center justify-between gap-2 rounded-control bg-primary-soft p-3 text-base"><strong class="min-w-0 break-words">{{ $personName }}</strong><button type="button" wire:click="$set('person', '')" class="inline-flex min-h-11 items-center text-sm font-semibold text-primary underline">Ganti</button></p>
                @else
                    <x-ui.input label="Cari santri atau ustadz" name="search" type="search" autocomplete="off" wire:model.live.debounce.250ms="search" />
                    @foreach ($people as $option)
                        <button type="button" wire:key="o-{{ $option['value'] }}" wire:click="choosePerson('{{ $option['value'] }}')" class="flex min-h-11 w-full items-center justify-between gap-2 rounded-control px-2 text-left hover:bg-primary-soft"><span class="font-semibold break-words">{{ $option['name'] }}</span><span class="shrink-0 text-sm text-text-muted">{{ $option['kind'] }}</span></button>
                    @endforeach
                    @error('person')<p role="alert" class="text-sm text-danger">{{ $message }}</p>@enderror
                @endif
                <x-ui.button type="submit" variant="secondary" class="self-start" wire:loading.attr="disabled" wire:target="assign">Tugaskan</x-ui.button>
            </form>
        </x-ui.card>
    @endif

    <x-ui.card title="Jadwal mendatang">
        @error('swap')<x-ui.banner tone="danger" class="mb-2" role="alert">{{ $message }}</x-ui.banner>@enderror
        @if ($assignments->isEmpty())
            <x-ui.empty-state icon="calendar-clock" message="Belum ada penugasan." class="py-2" />
        @else
            @foreach ($assignments as $assignment)
                <div wire:key="d-{{ $assignment->uuid }}" class="flex flex-wrap items-center justify-between gap-2 border-b border-border py-1 last:border-b-0">
                    <label class="flex min-h-11 min-w-0 flex-1 items-center gap-3">
                        <input type="checkbox" value="{{ $assignment->uuid }}" wire:model.live="swap" class="size-5 shrink-0 accent-primary" aria-label="Pilih untuk ditukar: {{ $assignment->assigneeName() }}">
                        <span class="min-w-0 text-base break-words">{{ $assignment->assigneeName() }} <span class="text-sm text-text-muted">{{ $assignment->roster->name }}, {{ $assignment->duty_date->translatedFormat('j M') }}</span></span>
                    </label>
                    @if ($assignment->status === 'scheduled')
                        <x-ui.button variant="tertiary" wire:click="markDone('{{ $assignment->uuid }}')">Selesai<span class="sr-only"> {{ $assignment->assigneeName() }}</span></x-ui.button>
                    @else
                        <span class="text-sm font-semibold text-success">Selesai</span>
                    @endif
                </div>
            @endforeach
            @if (count($swap) === 2)
                <x-ui.button class="mt-3" wire:click="swapSelected" wire:loading.attr="disabled" wire:target="swapSelected">Tukar Piket</x-ui.button>
            @endif
        @endif
    </x-ui.card>

    <x-ui.modal name="tambah-piket" title="Tambah piket">
        <div class="flex flex-col gap-3">
            <x-ui.input label="Nama piket" name="name" required wire:model="name" />
            <x-ui.select label="Hari" name="day_of_week" required :options="$days" wire:model="day_of_week" />
            <div class="grid grid-cols-2 gap-3">
                <x-ui.input label="Jam mulai" name="start_time" type="time" required wire:model="start_time" />
                <x-ui.input label="Jam selesai" name="end_time" type="time" required wire:model="end_time" />
            </div>
            <x-ui.input label="Lokasi" name="location" wire:model="location" />
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button wire:click="saveRoster" wire:loading.attr="disabled" wire:target="saveRoster">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
