@php use App\Modules\Finance\Support\Rupiah; @endphp

<div class="flex flex-col gap-4">
    <x-ui.card title="Nominal top-up">
        <p class="mb-3 text-base">Untuk {{ $child->name }}. Saldo bertambah setelah pembayaran diterima.</p>
        <div class="mb-3 flex flex-wrap gap-2">
            @foreach ([50000 => '50 ribu', 100000 => '100 ribu', 200000 => '200 ribu'] as $chip => $label)
                <button type="button" wire:click="pickAmount({{ $chip }})" @class(['inline-flex min-h-11 items-center rounded-full border px-4 text-sm font-semibold', 'border-primary bg-primary-soft text-primary' => $value === $chip, 'border-border' => $value !== $chip])>{{ $label }}</button>
            @endforeach
        </div>
        <x-ui.input-rupiah label="Nominal" name="amount" required :help="'Minimal '.Rupiah::format($limits['topup_min']).', maksimal '.Rupiah::format($limits['topup_max']).'.'" wire:model.live.debounce.300ms="amount" />
    </x-ui.card>

    @if ($onlineEnabled)
        <x-ui.card title="Bayar online">
            <p class="text-base">Virtual Account, QRIS, e-wallet, atau minimarket. Saldo bertambah otomatis setelah bayar.</p>
            @if ($adminFee > 0)
                <p class="mt-2 text-sm text-text-muted">Ditambah biaya admin {{ Rupiah::format($adminFee) }}.</p>
            @endif
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
            <x-ui.empty-state icon="landmark" message="Top-up belum bisa dilakukan lewat aplikasi. Titipkan tunai ke bendahara pesantren." class="py-2" />
        </x-ui.card>
    @endif

    <x-ui.button variant="tertiary" :href="route('wali.wallet', ['anak' => $child->uuid])" wire:navigate>Kembali ke Dompet</x-ui.button>
</div>
