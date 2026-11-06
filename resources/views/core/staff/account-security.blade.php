<div class="mx-auto flex w-full max-w-[720px] flex-col gap-4">
    <x-ui.card title="Verifikasi dua langkah">
        @if ($enabled)
            <p class="flex items-center gap-2 text-base"><x-ui.icon name="circle-check" class="text-success" />Aktif. Saat masuk, Anda diminta kode dari aplikasi autentikator.</p>
            @if ($recovery)
                <x-ui.banner tone="warning" class="mt-3">Simpan kode cadangan ini di tempat aman. Kode ini hanya tampil sekarang.</x-ui.banner>
                <ul class="font-tabular mt-3 grid gap-1 text-sm md:grid-cols-2">
                    @foreach ($recovery as $code_item)
                        <li class="break-all">{{ $code_item }}</li>
                    @endforeach
                </ul>
            @endif
            <x-ui.button variant="secondary" class="mt-4" wire:click="disable" wire:confirm="Matikan verifikasi dua langkah? Akun hanya dilindungi sandi.">Matikan</x-ui.button>
        @elseif ($pending)
            <p class="text-base">Pindai kode ini dengan aplikasi autentikator, lalu masukkan 6 angka yang muncul.</p>
            <div class="mt-3 inline-block rounded-control bg-on-primary p-3" data-qr>{!! $qr !!}</div>
            <form wire:submit="confirm" class="mt-3 flex flex-col gap-3 md:max-w-xs">
                <x-ui.input label="Kode dari aplikasi" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required wire:model="code" />
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="confirm">Aktifkan</x-ui.button>
            </form>
        @else
            <p class="text-base">Tambahan perlindungan untuk akun yang memegang data keuangan dan pengaturan. Butuh aplikasi autentikator di HP.</p>
            <x-ui.button class="mt-3" wire:click="enable" wire:loading.attr="disabled" wire:target="enable">Siapkan Verifikasi Dua Langkah</x-ui.button>
        @endif
    </x-ui.card>

    <x-ui.card title="Ganti sandi">
        <form wire:submit="changePassword" class="flex flex-col gap-4">
            <x-ui.input-password label="Sandi sekarang" name="current_password" wire:model="current_password" />
            <x-ui.input-password label="Sandi baru" name="password" autocomplete="new-password" help="Minimal 8 karakter." wire:model="password" />
            <x-ui.input-password label="Ulangi sandi baru" name="password_confirmation" autocomplete="new-password" wire:model="password_confirmation" />
            <div class="flex justify-end">
                <x-ui.button type="submit" class="w-full md:w-auto" wire:loading.attr="disabled" wire:target="changePassword">Simpan Sandi</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</div>
