<div class="flex flex-col gap-4">
    <h1 class="font-display text-2xl font-semibold">Buat sandi baru</h1>
    <form wire:submit="save" class="flex flex-col gap-4">
        <x-ui.input label="Email" name="email" type="email" autocomplete="username" required wire:model="email" />
        <x-ui.input-password label="Sandi baru" name="password" autocomplete="new-password" help="Minimal 8 karakter." wire:model="password" />
        <x-ui.input-password label="Ulangi sandi baru" name="password_confirmation" autocomplete="new-password" wire:model="password_confirmation" />
        <x-ui.button type="submit" class="w-full" wire:loading.attr="disabled" wire:target="save">Simpan Sandi</x-ui.button>
    </form>
</div>
