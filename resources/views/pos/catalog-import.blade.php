@php
    $invalid = collect($rows ?? [])->filter(fn ($row) => $row['errors'] !== []);
    $validRows = collect($rows ?? [])->filter(fn ($row) => $row['errors'] === []);
@endphp

<div class="mx-auto flex w-full max-w-[720px] flex-col gap-4">
    @include('pos.partials.merchant-nav')

    @if ($imported !== null)
        <x-ui.card>
            <x-ui.banner tone="success">{{ $imported['created'] }} produk baru, {{ $imported['updated'] }} produk diperbarui.</x-ui.banner>
            <div class="mt-4 flex flex-col gap-2 md:flex-row">
                <x-ui.button variant="secondary" wire:click="startOver">Impor Lagi</x-ui.button>
                <x-ui.button :href="route('staff.merchants.catalog', $merchant)" wire:navigate>Lihat Katalog</x-ui.button>
            </div>
        </x-ui.card>
    @elseif ($rows !== null)
        <x-ui.card title="Pratinjau">
            <p class="text-base">{{ $validRows->where('exists', false)->count() }} produk baru, {{ $validRows->where('exists', true)->count() }} diperbarui, {{ $invalid->count() }} baris bermasalah.</p>
            @if ($invalid->isNotEmpty())
                <ul class="mt-3 flex flex-col gap-2">
                    @foreach ($invalid as $row)
                        <li class="rounded-control bg-danger-soft p-3 text-sm"><strong>Baris {{ $row['row'] }}:</strong> {{ implode(' ', $row['errors']) }}</li>
                    @endforeach
                </ul>
            @endif
            <div class="mt-4 flex flex-col-reverse gap-2 md:flex-row md:justify-end">
                <x-ui.button variant="secondary" wire:click="startOver">Pilih Berkas Lain</x-ui.button>
                @if ($validRows->isNotEmpty())
                    <x-ui.button wire:click="import" wire:loading.attr="disabled" wire:target="import">Impor {{ $validRows->count() }} Produk</x-ui.button>
                @endif
            </div>
        </x-ui.card>
    @else
        <x-ui.card title="Langkah 1: unduh template">
            <p class="text-base">Satu baris untuk satu produk. Barcode yang sudah ada akan memperbarui produknya.</p>
            <x-ui.button variant="secondary" icon="file-down" class="mt-3" :href="route('staff.merchants.import.template')">Unduh Template</x-ui.button>
        </x-ui.card>
        <x-ui.card title="Langkah 2: unggah berkas">
            <form wire:submit="check" class="flex flex-col gap-3">
                <div class="flex flex-col gap-1">
                    <x-ui.file-drop label="Berkas Excel" name="file" accept=".xlsx" hint="Format .xlsx sesuai template, maksimal 10 MB" wire:model="file" />
                </div>
                <div class="flex justify-end">
                    <x-ui.button type="submit" class="w-full md:w-auto" wire:loading.attr="disabled" wire:target="check,file">Periksa Berkas</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif
</div>
