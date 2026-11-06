<div class="flex flex-col gap-4">
    <div>
        <h1 class="font-display text-2xl font-semibold">Verifikasi dua langkah</h1>
        <p class="mt-1 text-base text-text-muted">Masukkan 6 angka dari aplikasi autentikator, atau salah satu kode cadangan.</p>
    </div>
    <form wire:submit="verify" class="flex flex-col gap-4">
        <x-ui.input label="Kode" name="code" autocomplete="one-time-code" autofocus required wire:model="code" />
        <x-ui.button type="submit" class="w-full" wire:loading.attr="disabled" wire:target="verify">Masuk</x-ui.button>
    </form>
    <x-ui.button variant="tertiary" :href="route('login')">Kembali ke halaman masuk</x-ui.button>
</div>
