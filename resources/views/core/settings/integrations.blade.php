<div class="mx-auto grid w-full max-w-[720px] gap-4">
    <x-ui.banner tone="info">Kunci yang tersimpan tidak pernah ditampilkan penuh. Kosongkan isian bila tidak ingin mengubahnya.</x-ui.banner>

    @if (app(\App\Modules\Core\Support\ModuleRegistry::class)->enabled('payment_manual'))
        <livewire:finance.settings.bankaccounts />
    @endif

    <x-ui.card title="Midtrans">
        <form wire:submit="saveMidtrans" class="flex flex-col gap-4">
            <x-ui.select label="Mode" name="midtrans_mode" required :options="['sandbox' => 'Sandbox (uji coba)', 'production' => 'Production']" wire:model="midtrans_mode" />
            <x-ui.input label="Server key" name="midtrans_server_key" type="password" autocomplete="off" :help="$serverKeyMask ? 'Tersimpan: '.$serverKeyMask : 'Belum diisi.'" wire:model="midtrans_server_key" />
            <x-ui.input label="Client key" name="midtrans_client_key" type="password" autocomplete="off" :help="$clientKeyMask ? 'Tersimpan: '.$clientKeyMask : 'Belum diisi.'" wire:model="midtrans_client_key" />
            <fieldset class="flex flex-col gap-1">
                <legend class="text-sm font-semibold">Kanal yang ditampilkan ke wali</legend>
                @foreach ($channels as $key => $channel)
                    <label class="flex min-h-11 items-center gap-3 text-base">
                        <input type="checkbox" value="{{ $key }}" wire:model="midtrans_channels" class="size-5 accent-primary">
                        {{ $channel['label'] }}
                    </label>
                @endforeach
                @error('midtrans_channels')
                    <p role="alert" class="flex items-start gap-1 text-sm text-danger"><x-ui.icon name="circle-alert" size="sm" class="mt-0.5" />{{ $message }}</p>
                @enderror
            </fieldset>
            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.select label="Biaya admin" name="midtrans_fee_mode" required :options="['absorbed' => 'Ditanggung pesantren', 'passed' => 'Dibebankan ke wali']" wire:model.live="midtrans_fee_mode" />
                @if ($midtrans_fee_mode === 'passed')
                    <x-ui.input-rupiah label="Biaya per transaksi" name="midtrans_admin_fee" wire:model="midtrans_admin_fee" />
                @endif
            </div>
            <div class="flex flex-col-reverse gap-2 md:flex-row md:justify-end">
                <x-ui.button variant="secondary" wire:click="testMidtrans" wire:loading.attr="disabled" wire:target="testMidtrans">Tes Koneksi</x-ui.button>
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="saveMidtrans">Simpan Midtrans</x-ui.button>
            </div>
            @if ($midtransTest)
                <p role="status" @class(['flex items-center gap-2 text-base', 'text-success' => $midtransTest['ok'], 'text-danger' => ! $midtransTest['ok']])>
                    <x-ui.icon :name="$midtransTest['ok'] ? 'circle-check' : 'circle-alert'" />{{ $midtransTest['message'] }}
                </p>
            @endif
        </form>

        <div class="mt-4 border-t border-border pt-4" x-data="{ copied: false }">
            <h3 class="text-base font-semibold">Pasang webhook di dashboard Midtrans</h3>
            <ol class="mt-2 list-decimal pl-5 text-base">
                <li>Masuk ke dashboard Midtrans, buka Settings lalu Configuration.</li>
                <li>Tempel alamat di bawah pada Payment Notification URL.</li>
                <li>Simpan, lalu kirim notifikasi uji dari dashboard.</li>
            </ol>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <code class="min-w-0 flex-1 break-all rounded-control bg-bg p-2 text-sm">{{ $webhookUrl }}</code>
                <x-ui.button variant="secondary" icon="copy" x-on:click="navigator.clipboard?.writeText(@js($webhookUrl)); copied = true; setTimeout(() => copied = false, 2000)"><span x-text="copied ? 'Tersalin' : 'Salin'">Salin</span></x-ui.button>
            </div>
        </div>
    </x-ui.card>

    <x-ui.card title="Fonnte (WhatsApp)">
        <form wire:submit="saveFonnte" class="flex flex-col gap-4">
            <x-ui.input label="Token" name="fonnte_token" type="password" autocomplete="off" :help="$tokenMask ? 'Tersimpan: '.$tokenMask : 'Belum diisi.'" wire:model="fonnte_token" />
            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.input label="Nomor pengirim" name="fonnte_sender" type="tel" inputmode="tel" wire:model="fonnte_sender" />
                <x-ui.input label="Batas pesan per jam" name="fonnte_hourly_limit" inputmode="numeric" required wire:model="fonnte_hourly_limit" />
            </div>
            <div class="flex flex-col-reverse gap-2 md:flex-row md:justify-end">
                <x-ui.button variant="secondary" wire:click="testFonnte" wire:loading.attr="disabled" wire:target="testFonnte">Tes Koneksi</x-ui.button>
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="saveFonnte">Simpan Fonnte</x-ui.button>
            </div>
        </form>

        <div class="mt-4 grid gap-3 border-t border-border pt-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
            <x-ui.input label="Kirim pesan uji ke nomor" name="fonnte_test_phone" type="tel" inputmode="tel" wire:model="fonnte_test_phone" />
            <x-ui.button variant="secondary" wire:click="testFonnteSend" wire:loading.attr="disabled" wire:target="testFonnteSend">Tes Kirim</x-ui.button>
        </div>
        @if ($fonnteTest)
            <p role="status" @class(['mt-3 flex items-center gap-2 text-base', 'text-success' => $fonnteTest['ok'], 'text-danger' => ! $fonnteTest['ok']])>
                <x-ui.icon :name="$fonnteTest['ok'] ? 'circle-check' : 'circle-alert'" />{{ $fonnteTest['message'] }}
            </p>
        @endif
        @if ($fonnteWebhookUrl)
            <div class="mt-4 border-t border-border pt-4" x-data="{ copied: false }">
                <h3 class="text-base font-semibold">Webhook status pesan</h3>
                <p class="text-sm text-text-muted">Tempel di pengaturan perangkat Fonnte agar status sampai tercatat. Alamat ini memuat kunci rahasia, jangan dibagikan.</p>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <code class="min-w-0 flex-1 break-all rounded-control bg-bg p-2 text-sm">{{ $fonnteWebhookUrl }}</code>
                    <x-ui.button variant="secondary" icon="copy" x-on:click="navigator.clipboard?.writeText(@js($fonnteWebhookUrl)); copied = true; setTimeout(() => copied = false, 2000)"><span x-text="copied ? 'Tersalin' : 'Salin'">Salin</span></x-ui.button>
                </div>
            </div>
        @endif
    </x-ui.card>
</div>
