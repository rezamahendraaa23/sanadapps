<div class="mx-auto grid w-full max-w-[720px] gap-4">
    <x-ui.banner tone="info">Kunci yang tersimpan tidak pernah ditampilkan penuh. Kosongkan isian bila tidak ingin mengubahnya. Tes koneksi dan pengiriman aktif setelah integrasi dibangun di Tahap 3.</x-ui.banner>

    @if (app(\App\Modules\Core\Support\ModuleRegistry::class)->enabled('payment_manual'))
        <livewire:finance.settings.bankaccounts />
    @endif

    <x-ui.card title="Midtrans">
        <form wire:submit="saveMidtrans" class="flex flex-col gap-4">
            <x-ui.select label="Mode" name="midtrans_mode" required :options="['sandbox' => 'Sandbox (uji coba)', 'production' => 'Production']" wire:model="midtrans_mode" />
            <x-ui.input label="Server key" name="midtrans_server_key" type="password" autocomplete="off" :help="$serverKeyMask ? 'Tersimpan: '.$serverKeyMask : 'Belum diisi.'" wire:model="midtrans_server_key" />
            <x-ui.input label="Client key" name="midtrans_client_key" type="password" autocomplete="off" :help="$clientKeyMask ? 'Tersimpan: '.$clientKeyMask : 'Belum diisi.'" wire:model="midtrans_client_key" />
            <div class="flex justify-end">
                <x-ui.button type="submit" class="w-full md:w-auto" wire:loading.attr="disabled" wire:target="saveMidtrans">Simpan Midtrans</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card title="Fonnte (WhatsApp)">
        <form wire:submit="saveFonnte" class="flex flex-col gap-4">
            <x-ui.input label="Token" name="fonnte_token" type="password" autocomplete="off" :help="$tokenMask ? 'Tersimpan: '.$tokenMask : 'Belum diisi.'" wire:model="fonnte_token" />
            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.input label="Nomor pengirim" name="fonnte_sender" type="tel" inputmode="tel" wire:model="fonnte_sender" />
                <x-ui.input label="Batas pesan per jam" name="fonnte_hourly_limit" inputmode="numeric" required wire:model="fonnte_hourly_limit" />
            </div>
            <div class="flex justify-end">
                <x-ui.button type="submit" class="w-full md:w-auto" wire:loading.attr="disabled" wire:target="saveFonnte">Simpan Fonnte</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</div>
