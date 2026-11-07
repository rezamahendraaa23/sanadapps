@php use App\Modules\Finance\Support\Rupiah; @endphp

<div class="flex flex-col gap-4 {{ $tab === 'belum' && $invoices->isNotEmpty() ? 'pb-20' : '' }}">
    <div role="tablist" aria-label="Jenis tagihan" class="grid grid-cols-2 gap-1 rounded-control bg-surface p-1">
        @foreach (['belum' => 'Belum dibayar', 'riwayat' => 'Riwayat'] as $key => $label)
            <x-ui.tab :selected="$tab === $key" wire:key="tab-{{ $key }}" wire:click="$set('tab', '{{ $key }}')">{{ $label }}</x-ui.tab>
        @endforeach
    </div>

    @forelse ($invoices as $invoice)
        <x-ui.card wire:key="tagihan-{{ $invoice->uuid }}" class="!p-0">
            <div class="flex items-stretch">
                @if ($tab === 'belum')
                    <label class="flex w-14 shrink-0 items-center justify-center">
                        <span class="sr-only">Pilih {{ $invoice->invoice_no }}</span>
                        <input type="checkbox" value="{{ $invoice->uuid }}" wire:model.live="selected" class="size-6 accent-primary">
                    </label>
                @endif
                <a href="{{ route('wali.invoices.show', $invoice) }}" wire:navigate class="min-w-0 flex-1 p-4 {{ $tab === 'belum' ? 'pl-0' : '' }}">
                    <div class="flex items-start justify-between gap-2">
                        <span class="min-w-0 font-semibold">{{ $invoice->period?->translatedFormat('F Y') ?? $invoice->invoice_no }}</span>
                        <x-ui.invoice-status :status="$invoice->status" class="shrink-0" />
                    </div>
                    <p class="text-sm text-text-muted">{{ $invoice->student?->name }}</p>
                    <div class="mt-1 flex flex-wrap items-end justify-between gap-x-2">
                        <span class="font-display font-tabular text-xl font-semibold whitespace-nowrap">{{ Rupiah::format($invoice->isOpen() ? $invoice->outstanding() : $invoice->total) }}</span>
                        <span class="text-sm text-text-muted">Jatuh tempo {{ $invoice->due_date->translatedFormat('j M Y') }}</span>
                    </div>
                </a>
            </div>
        </x-ui.card>
    @empty
        <x-ui.card>
            @if ($tab === 'belum')
                <x-ui.empty-state message="Semua tagihan sudah lunas." />
            @else
                <x-ui.empty-state icon="receipt-text" message="Belum ada riwayat tagihan." />
            @endif
        </x-ui.card>
    @endforelse

    @error('selected')
        <p role="alert" class="flex items-start gap-1 text-sm text-danger"><x-ui.icon name="circle-alert" size="sm" class="mt-0.5" />{{ $message }}</p>
    @enderror

    @if ($tab === 'belum' && $invoices->isNotEmpty())
        <div class="fixed inset-x-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-10 border-t border-border bg-surface">
            <div class="mx-auto flex w-full max-w-[480px] items-center justify-between gap-3 px-4 py-2">
                <div>
                    <p class="text-xs text-text-muted">Total dipilih</p>
                    <p class="font-tabular text-lg font-semibold" aria-live="polite">{{ Rupiah::format($selectedTotal) }}</p>
                </div>
                <x-ui.button :disabled="$selectedCount === 0" wire:click="pay">Bayar ({{ $selectedCount }})</x-ui.button>
            </div>
        </div>
    @endif
</div>
