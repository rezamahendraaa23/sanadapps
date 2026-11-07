@php
    use App\Modules\Finance\Support\Rupiah;
    $invalid = collect($rows ?? [])->filter(fn ($row) => $row['errors'] !== []);
    $validRows = collect($rows ?? [])->filter(fn ($row) => $row['errors'] === []);
@endphp

<div class="mx-auto flex w-full max-w-[720px] flex-col gap-4">
    @if ($imported !== null)
        <x-ui.card>
            <x-ui.banner tone="success">{{ $imported }} tunggakan awal dibuat sebagai tagihan manual.</x-ui.banner>
            <div class="mt-4 flex flex-col gap-2 md:flex-row">
                <x-ui.button variant="secondary" wire:click="startOver">Impor Lagi</x-ui.button>
                <x-ui.button :href="route('staff.invoices.index')" wire:navigate>Lihat Tagihan</x-ui.button>
            </div>
        </x-ui.card>
    @elseif ($rows !== null)
        <x-ui.card title="Pratinjau">
            <p class="text-base">{{ $validRows->count() }} baris siap, total <strong class="font-tabular">{{ Rupiah::format($validRows->sum('amount')) }}</strong>. {{ $invalid->count() }} baris bermasalah.</p>
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
                    <x-ui.button wire:click="import" wire:loading.attr="disabled" wire:target="import">Impor {{ $validRows->count() }} Tunggakan</x-ui.button>
                @endif
            </div>
        </x-ui.card>
    @else
        <x-ui.card title="Langkah 1: unduh template">
            <p class="text-base">Satu baris untuk satu tunggakan lama sebelum memakai SANAD. Santrinya harus sudah terdaftar.</p>
            <x-ui.button variant="secondary" icon="file-down" class="mt-3" :href="route('staff.arrears.import.template')">Unduh Template</x-ui.button>
        </x-ui.card>
        <x-ui.card title="Langkah 2: unggah berkas">
            <form wire:submit="check" class="flex flex-col gap-3">
                <div class="flex flex-col gap-1">
                    <label for="field-file" class="text-sm font-semibold">Berkas Excel (.xlsx), maksimal 10 MB</label>
                    <input id="field-file" type="file" accept=".xlsx" wire:model="file" @error('file') aria-invalid="true" @enderror class="min-h-11 w-full min-w-0 text-sm">
                    @error('file')
                        <p role="alert" class="flex items-start gap-1 text-sm text-danger"><x-ui.icon name="circle-alert" size="sm" class="mt-0.5" />{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex justify-end">
                    <x-ui.button type="submit" class="w-full md:w-auto" wire:loading.attr="disabled" wire:target="check,file">Periksa Berkas</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif
</div>
