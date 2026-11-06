<div class="flex flex-col gap-4">
    <h1 class="font-display text-2xl font-semibold">Lupa sandi</h1>
    @if ($sent)
        <x-ui.banner tone="success">Bila email itu terdaftar, tautan untuk membuat sandi baru sudah terkirim. Periksa kotak masuk Anda.</x-ui.banner>
    @else
        <form wire:submit="send" class="flex flex-col gap-4">
            <x-ui.input label="Email akun" name="email" type="email" autocomplete="email" required wire:model="email" />
            <x-ui.button type="submit" class="w-full" wire:loading.attr="disabled" wire:target="send">Kirim Tautan</x-ui.button>
        </form>
    @endif
    <x-ui.button variant="tertiary" :href="route('login', ['sebagai' => 'staf'])">Kembali ke halaman masuk</x-ui.button>
</div>
