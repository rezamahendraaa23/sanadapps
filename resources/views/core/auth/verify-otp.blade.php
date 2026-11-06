<div
    class="flex flex-col gap-4"
    x-data="{
        wait: 60,
        timer: null,
        start() { this.wait = 60; clearInterval(this.timer); this.timer = setInterval(() => { if (this.wait > 0) this.wait--; }, 1000); },
    }"
    x-init="start()"
    x-on:otp-sent.window="start()"
>
    <div>
        <h1 class="font-display text-2xl font-semibold">Masukkan kode</h1>
        <p class="mt-1 text-base text-text-muted">Bila nomor {{ $maskedPhone }} terdaftar, kode 6 angka dikirim ke WhatsApp. Kode berlaku 5 menit.</p>
    </div>

    <form wire:submit="verify" class="flex flex-col gap-4">
        <x-ui.input label="Kode OTP" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus required class="font-tabular text-center text-2xl tracking-[0.5em]" wire:model="code" />
        <x-ui.button type="submit" class="w-full" wire:loading.attr="disabled" wire:target="verify">
            <span wire:loading.remove wire:target="verify">Masuk</span>
            <span wire:loading wire:target="verify">Memeriksa…</span>
        </x-ui.button>
    </form>

    <div class="flex flex-wrap items-center justify-between gap-2">
        <x-ui.button variant="tertiary" :href="route('login')">Ubah nomor</x-ui.button>
        <x-ui.button variant="tertiary" wire:click="resend" x-bind:disabled="wait > 0">
            <span x-show="wait > 0">Kirim ulang dalam <span class="font-tabular" x-text="wait"></span> detik</span>
            <span x-show="wait === 0" x-cloak>Kirim ulang kode</span>
        </x-ui.button>
    </div>
</div>
