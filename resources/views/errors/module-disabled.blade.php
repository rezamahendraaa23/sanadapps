<x-layouts.base title="Modul tidak aktif">
    <main id="konten" class="flex min-h-dvh items-center justify-center p-6">
        <x-ui.card class="w-full max-w-[480px]">
            <x-ui.empty-state icon="toggle-right" :message="$message">
                <x-slot:action>
                    <x-ui.button variant="secondary" :href="route('home')">Kembali ke beranda</x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
            <p class="text-center text-xs text-text-muted">Kode: MODULE_DISABLED</p>
        </x-ui.card>
    </main>
</x-layouts.base>
