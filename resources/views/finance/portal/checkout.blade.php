@php
    use App\Modules\Finance\Support\Rupiah;
    $total = $invoices->sum(fn ($invoice) => $invoice->outstanding());
@endphp

<div class="flex flex-col gap-4">
    @if ($invoices->isEmpty())
        <x-ui.card>
            <x-ui.empty-state message="Tidak ada tagihan yang perlu dibayar.">
                <x-slot:action><x-ui.button variant="secondary" :href="route('wali.invoices')" wire:navigate>Kembali ke Tagihan</x-ui.button></x-slot:action>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.card title="Ringkasan">
            <ul class="flex flex-col gap-1 text-base">
                @foreach ($invoices as $invoice)
                    <li class="flex justify-between gap-3">
                        <span class="min-w-0">{{ $invoice->period?->translatedFormat('F Y') ?? $invoice->invoice_no }}<span class="block text-sm text-text-muted">{{ $invoice->student?->name }}</span></span>
                        <span class="font-tabular shrink-0">{{ Rupiah::format($invoice->outstanding()) }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="rule-double mt-3 pb-1"></p>
            <div class="mt-2 flex items-end justify-between gap-3">
                <span class="font-semibold">Total</span>
                <span class="font-display font-tabular text-2xl font-semibold">{{ Rupiah::format($total) }}</span>
            </div>
        </x-ui.card>

        @if ($onlineEnabled)
            <x-ui.card title="Bayar online">
                <p class="text-base">Virtual Account, QRIS, e-wallet, atau minimarket. Status lunas otomatis setelah bayar.</p>
                @if ($adminFee > 0)
                    <dl class="mt-2 grid grid-cols-2 gap-y-1 text-base">
                        <dt class="text-text-muted">Biaya admin</dt><dd class="font-tabular text-right">{{ Rupiah::format($adminFee) }}</dd>
                        <dt class="font-semibold">Total bayar</dt><dd class="font-tabular text-right font-semibold">{{ Rupiah::format($total + $adminFee) }}</dd>
                    </dl>
                @endif
                @error('online')
                    <p role="alert" class="mt-2 flex items-start gap-1 text-sm text-danger"><x-ui.icon name="circle-alert" size="sm" class="mt-0.5" />{{ $message }}</p>
                @enderror
                <x-ui.button class="mt-4 w-full" wire:click="payOnline" wire:loading.attr="disabled" wire:target="payOnline">
                    <span wire:loading.remove wire:target="payOnline">Bayar Online</span>
                    <span wire:loading wire:target="payOnline">Menyiapkan…</span>
                </x-ui.button>
            </x-ui.card>
        @endif

        @if ($manualEnabled && $accounts->isNotEmpty())
            <x-ui.card title="Transfer manual">
                <fieldset class="flex flex-col gap-2">
                    <legend class="mb-1 text-sm">Pilih rekening tujuan</legend>
                    @foreach ($accounts as $account)
                        <label wire:key="rek-{{ $account->uuid }}" class="flex min-h-12 items-center gap-3 rounded-control border border-border p-3 has-checked:border-primary has-checked:bg-primary-soft">
                            <input type="radio" name="bank_account" value="{{ $account->uuid }}" wire:model="bank_account" class="size-5 accent-primary">
                            <span class="min-w-0"><span class="block font-semibold">{{ $account->bank_name }}</span><span class="block text-sm text-text-muted">a.n. {{ $account->account_holder }}</span></span>
                        </label>
                    @endforeach
                </fieldset>
                @error('bank_account')
                    <p role="alert" class="mt-2 flex items-start gap-1 text-sm text-danger"><x-ui.icon name="circle-alert" size="sm" class="mt-0.5" />{{ $message }}</p>
                @enderror
                <x-ui.button :variant="$onlineEnabled ? 'secondary' : 'primary'" class="mt-4 w-full" wire:click="payByTransfer" wire:loading.attr="disabled" wire:target="payByTransfer">
                    <span wire:loading.remove wire:target="payByTransfer">Lanjut Transfer</span>
                    <span wire:loading wire:target="payByTransfer">Menyiapkan…</span>
                </x-ui.button>
            </x-ui.card>
        @elseif (! $onlineEnabled)
            <x-ui.card>
                <x-ui.empty-state icon="landmark" message="Pembayaran belum bisa dilakukan lewat aplikasi. Hubungi bendahara pesantren." class="py-2" />
            </x-ui.card>
        @endif
    @endif
</div>
