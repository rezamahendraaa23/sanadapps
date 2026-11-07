<form wire:submit="save" class="mx-auto flex w-full max-w-[720px] flex-col gap-4">
    <x-ui.card title="Santri">
        @if ($student)
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-base font-semibold">{{ $student->name }} <span class="font-tabular text-sm font-normal text-text-muted">{{ $student->nis }}</span></p>
                <x-ui.button variant="tertiary" wire:click="clearStudent">Ganti</x-ui.button>
            </div>
        @else
            <x-ui.input label="Cari nama atau NIS" name="search" type="search" autocomplete="off" wire:model.live.debounce.250ms="search" />
            @foreach ($results as $result)
                <button type="button" wire:key="hasil-{{ $result->uuid }}" wire:click="pickStudent('{{ $result->uuid }}')" class="mt-1 flex min-h-11 w-full items-center justify-between rounded-control px-2 text-left hover:bg-primary-soft">
                    <span class="font-semibold">{{ $result->name }}</span><span class="font-tabular text-sm text-text-muted">{{ $result->nis }}</span>
                </button>
            @endforeach
            @if (mb_strlen(trim($search)) >= 2 && $results->isEmpty())
                <p class="mt-2 text-sm text-text-muted">Tidak ada santri aktif yang cocok.</p>
            @endif
        @endif
    </x-ui.card>

    <x-ui.card title="Rincian">
        <div class="flex flex-col gap-4">
            @foreach ($items as $index => $item)
                <div wire:key="baris-{{ $index }}" class="grid gap-3 border-b border-border pb-4 md:grid-cols-3">
                    <x-ui.input label="Rincian" :name="'items.'.$index.'.description'" required wire:model="items.{{ $index }}.description" />
                    <x-ui.select label="Jenis" :name="'items.'.$index.'.fee_type_id'" placeholder="Tanpa jenis" :options="$feeTypes" wire:model="items.{{ $index }}.fee_type_id" />
                    <x-ui.input-rupiah label="Nominal" :name="'items.'.$index.'.amount'" required wire:model="items.{{ $index }}.amount" />
                    @if (count($items) > 1)
                        <div class="md:col-span-3">
                            <x-ui.button variant="tertiary" wire:click="removeItem({{ $index }})">Hapus baris<span class="sr-only"> {{ $index + 1 }}</span></x-ui.button>
                        </div>
                    @endif
                </div>
            @endforeach
            <x-ui.button variant="secondary" icon="plus" class="self-start" wire:click="addItem">Tambah Baris</x-ui.button>
            <x-ui.input label="Jatuh tempo" name="due_date" type="date" required wire:model="due_date" />
        </div>
    </x-ui.card>

    <div class="flex flex-col-reverse gap-2 md:flex-row md:justify-end">
        <x-ui.button variant="secondary" :href="route('staff.invoices.index')" wire:navigate>Batal</x-ui.button>
        <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">Buat Tagihan</x-ui.button>
    </div>
</form>
