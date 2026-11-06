<x-layouts.staff title="Dashboard" :home-url="route('preview.staff')" :preview="true" breadcrumb="Pratinjau layout staf">
    <x-slot:actions>
        <x-ui.button variant="tertiary" :href="route('preview.gallery')">Komponen</x-ui.button>
    </x-slot:actions>

    @include('preview.banner')

    <div class="grid gap-4 min-[80rem]:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <div class="flex min-w-0 flex-col gap-4">
            <x-ui.section-heading>Perlu ditinjau</x-ui.section-heading>
            <x-ui.card><livewire:preview.sample-table /></x-ui.card>
        </div>
        <div class="flex min-w-0 flex-col gap-4">
            <x-ui.hero-card label="Terbayar bulan ini" value="Rp 18.750.000">Angka contoh.</x-ui.hero-card>
            <x-ui.card title="Menunggu verifikasi">
                <x-ui.empty-state message="Tidak ada bukti transfer yang menunggu. Bukti baru dari wali muncul di sini." class="py-2" />
            </x-ui.card>
        </div>
    </div>
</x-layouts.staff>
