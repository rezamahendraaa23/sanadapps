@php use App\Modules\Finance\Support\Rupiah; @endphp

{{-- Terima pembayaran di loket: pilih santri, centang tagihan, terima uang. Ringkasan di kanan selalu terlihat. --}}
<form wire:submit="record" class="grid w-full gap-4 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start">
    <div class="flex min-w-0 flex-col gap-4">
        <x-ui.card>
            <h2 class="mb-3 flex items-center gap-2 text-base font-semibold"><span class="inline-flex size-7 items-center justify-center rounded-full bg-primary text-sm text-on-primary">1</span>Santri</h2>
            @if ($student)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-control bg-bg p-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="font-display inline-flex size-11 shrink-0 items-center justify-center rounded-full bg-primary-soft font-semibold text-primary">{{ mb_strtoupper(mb_substr($student->name, 0, 1)) }}</span>
                        <div class="min-w-0">
                            <p class="font-semibold break-words">{{ $student->name }}</p>
                            <p class="font-tabular text-sm text-text-muted">{{ $student->nis }}{{ $student->classroom ? ', '.$student->classroom->name : '' }}</p>
                        </div>
                    </div>
                    <x-ui.button variant="tertiary" icon="repeat" wire:click="clearStudent">Ganti Santri</x-ui.button>
                </div>
            @else
                <x-ui.input label="Cari nama atau NIS" name="search" type="search" autocomplete="off" placeholder="Ketik minimal 2 huruf" wire:model.live.debounce.250ms="search" />
                <div class="mt-2 flex flex-col">
                    @foreach ($results as $result)
                        <button type="button" wire:key="hasil-{{ $result->uuid }}" wire:click="pickStudent('{{ $result->uuid }}')" class="flex min-h-12 w-full items-center justify-between gap-2 rounded-control px-3 text-left hover:bg-primary-soft focus-visible:outline-2 focus-visible:outline-primary">
                            <span class="min-w-0"><span class="block font-semibold">{{ $result->name }}</span><span class="block text-sm text-text-muted">{{ $result->classroom?->name ?? 'Belum ada kelas' }}</span></span>
                            <span class="font-tabular text-sm text-text-muted">{{ $result->nis }}</span>
                        </button>
                    @endforeach
                    @if (mb_strlen(trim($search)) >= 2 && $results->isEmpty())
                        <p class="px-1 py-2 text-sm text-text-muted">Santri tidak ditemukan.</p>
                    @endif
                </div>
            @endif
        </x-ui.card>

        @if ($student)
            <x-ui.card>
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="flex items-center gap-2 text-base font-semibold"><span class="inline-flex size-7 items-center justify-center rounded-full bg-primary text-sm text-on-primary">2</span>Tagihan yang dibayar</h2>
                    @if ($invoices->count() > 1)
                        <x-ui.button variant="tertiary" wire:click="selectAll({{ count($selected) === $invoices->count() ? 'false' : 'true' }})">{{ count($selected) === $invoices->count() ? 'Kosongkan' : 'Pilih Semua' }}</x-ui.button>
                    @endif
                </div>
                @if ($invoices->isEmpty())
                    <x-ui.empty-state icon="circle-check" message="Santri ini tidak punya tagihan yang perlu dibayar." class="py-2" />
                @else
                    <fieldset class="flex flex-col gap-2">
                        <legend class="sr-only">Pilih tagihan yang dibayar</legend>
                        @foreach ($invoices as $invoice)
                            <label wire:key="pilih-{{ $invoice->uuid }}" class="relative flex min-h-14 cursor-pointer items-start gap-3 rounded-control border border-border p-3 has-checked:border-primary has-checked:bg-primary-soft">
                                <input type="checkbox" value="{{ $invoice->uuid }}" wire:model.live="selected" class="mt-1 size-5 shrink-0 accent-primary">
                                <span class="min-w-0 flex-1">
                                    <span class="block font-semibold break-words">{{ $invoice->items->pluck('description')->unique()->implode(', ') ?: $invoice->invoice_no }}</span>
                                    <span class="block text-sm text-text-muted">Jatuh tempo {{ $invoice->due_date->translatedFormat('j M Y') }}, <span class="font-tabular">{{ $invoice->invoice_no }}</span></span>
                                </span>
                                <span class="flex shrink-0 flex-col items-end gap-1">
                                    <span class="font-tabular font-semibold">{{ Rupiah::format($invoice->outstanding()) }}</span>
                                    <x-ui.invoice-status :status="$invoice->status" />
                                </span>
                            </label>
                        @endforeach
                        @error('selected')
                            <p role="alert" class="flex items-start gap-1 text-sm text-danger"><x-ui.icon name="circle-alert" size="sm" class="mt-0.5" />{{ $message }}</p>
                        @enderror
                    </fieldset>
                @endif
            </x-ui.card>
        @endif
    </div>

    {{-- 3. Ringkasan dan uang diterima --}}
    <x-ui.card class="lg:sticky lg:top-20">
        <h2 class="mb-3 flex items-center gap-2 text-base font-semibold"><span class="inline-flex size-7 items-center justify-center rounded-full bg-primary text-sm text-on-primary">3</span>Pembayaran</h2>
        @if (! $student || $invoices->isEmpty())
            <p class="text-sm text-text-muted">{{ $student ? 'Tidak ada tagihan untuk dibayar.' : 'Pilih santri dulu. Tagihannya akan muncul di sini untuk dicentang.' }}</p>
        @else
            <div class="flex flex-col gap-4">
                <div class="flex items-baseline justify-between gap-2">
                    <span class="text-sm text-text-muted">{{ count($selected) }} tagihan dipilih</span>
                    <span class="font-display font-tabular text-2xl font-semibold">{{ Rupiah::format($total) }}</span>
                </div>
                <x-ui.input-rupiah label="Nominal dibayar" name="amount" required help="Boleh kurang dari total bila jenis tagihannya boleh dicicil." wire:model.live.debounce.400ms="amount" />
                <div class="flex flex-col gap-2">
                    <x-ui.input-rupiah label="Uang diterima" name="received" required wire:model.live.debounce.400ms="received" />
                    @if ($quick !== [])
                        <div class="flex flex-wrap gap-2" role="group" aria-label="Nominal cepat">
                            @foreach ($quick as $i => $value)
                                <button type="button" wire:key="cepat-{{ $value }}" wire:click="quickReceived({{ $value }})" class="font-tabular min-h-11 rounded-full border border-border px-3 text-sm font-semibold hover:border-primary hover:bg-primary-soft">{{ $i === 0 ? 'Uang pas' : Rupiah::format($value) }}</button>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="rounded-control bg-accent-soft p-3 text-center" aria-live="polite">
                    <span class="block text-sm">Kembalian</span>
                    <span class="font-display font-tabular text-3xl font-semibold">{{ Rupiah::format($change) }}</span>
                </div>
                <x-ui.button type="submit" icon="check" class="w-full" wire:loading.attr="disabled" wire:target="record">
                    <span wire:loading.remove wire:target="record">Terima {{ Rupiah::format((int) preg_replace('/\D/', '', $amount)) }}</span>
                    <span wire:loading wire:target="record">Memproses</span>
                </x-ui.button>
                <p class="text-center text-xs text-text-muted">Setelah tersimpan, kwitansi bisa diunduh untuk dicetak.</p>
            </div>
        @endif
    </x-ui.card>
</form>
