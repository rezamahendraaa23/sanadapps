@php use App\Modules\Finance\Support\Rupiah; @endphp

<form wire:submit="record" class="mx-auto flex w-full max-w-[720px] flex-col gap-4">
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
        @endif
    </x-ui.card>

    @if ($student)
        <x-ui.card title="Tagihan">
            @if ($invoices->isEmpty())
                <x-ui.empty-state message="Santri ini tidak punya tagihan yang perlu dibayar." class="py-2" />
            @else
                <fieldset>
                    <legend class="sr-only">Pilih tagihan yang dibayar</legend>
                    @foreach ($invoices as $invoice)
                        <label wire:key="pilih-{{ $invoice->uuid }}" class="flex min-h-11 items-center gap-3 border-b border-border py-2 last:border-b-0">
                            <input type="checkbox" value="{{ $invoice->uuid }}" wire:model.live="selected" class="size-5 shrink-0 accent-primary">
                            <span class="min-w-0 flex-1">
                                <span class="font-tabular block text-sm text-text-muted">{{ $invoice->invoice_no }}, jatuh tempo {{ $invoice->due_date->translatedFormat('j M Y') }}</span>
                                <span class="font-tabular font-semibold">{{ Rupiah::format($invoice->outstanding()) }}</span>
                            </span>
                            <x-ui.invoice-status :status="$invoice->status" />
                        </label>
                    @endforeach
                    @error('selected')
                        <p role="alert" class="mt-2 flex items-start gap-1 text-sm text-danger"><x-ui.icon name="circle-alert" size="sm" class="mt-0.5" />{{ $message }}</p>
                    @enderror
                </fieldset>
            @endif
        </x-ui.card>

        @if ($invoices->isNotEmpty())
            <x-ui.card title="Pembayaran">
                <div class="flex flex-col gap-4">
                    <p class="text-base">Total dipilih: <strong class="font-tabular">{{ Rupiah::format($total) }}</strong></p>
                    <div class="grid gap-4 md:grid-cols-2">
                        <x-ui.input-rupiah label="Nominal dibayarkan" name="amount" required help="Bisa kurang dari total bila jenisnya boleh dicicil." wire:model.live="amount" />
                        <x-ui.input-rupiah label="Uang diterima" name="received" required wire:model.live="received" />
                    </div>
                    <p class="rounded-control bg-accent-soft p-3 text-center" aria-live="polite">
                        <span class="block text-sm">Kembalian</span>
                        <span class="font-display font-tabular text-2xl font-semibold">{{ Rupiah::format($change) }}</span>
                    </p>
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="record">
                        <span wire:loading.remove wire:target="record">Catat Pembayaran</span>
                        <span wire:loading wire:target="record">Memproses…</span>
                    </x-ui.button>
                </div>
            </x-ui.card>
        @endif
    @endif
</form>
