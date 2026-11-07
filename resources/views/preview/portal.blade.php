@php
    [$title, $heroLabel, $heroValue, $heroNote] = [
        'wali' => ['Beranda Wali', 'Total yang harus dibayar', 'Rp 625.000', 'Contoh tagihan. Jatuh tempo 10 Nov 2026.'],
        'santri' => ['Beranda Santri', 'Berikutnya', '07.30', 'Contoh jadwal: pelajaran pertama.'],
        'ustadz' => ['Beranda Ustadz', 'Kelas berikutnya', '09.15', 'Contoh jadwal: Kelas Contoh A.'],
        'musyrif' => ['Beranda Musyrif', 'Izin menunggu', '3', 'Angka contoh.'],
    ][$role];
@endphp

<x-layouts.portal :title="$title" :role="$role" :home-url="route('preview.portal', $role)" :preview="true">
    @include('preview.banner')

    <x-ui.hero-card :label="$heroLabel" :value="$heroValue">
        {{ $heroNote }}
        <x-slot:action>
            <x-ui.button variant="on-primary" class="w-full" x-data x-on:click="$dispatch('toast', { message: 'Aksi utama berfungsi.', tone: 'success' })">
                {{ $role === 'wali' ? 'Bayar Sekarang' : 'Lihat rincian' }}
            </x-ui.button>
        </x-slot:action>
    </x-ui.hero-card>

    <x-ui.ticket-card>
        <h2 class="font-display text-lg font-semibold">Izin keluar contoh</h2>
        <p class="mt-1 text-sm text-text-muted">Santri Contoh 01. Kembali pukul 17.00.</p>
        <x-slot:proof>
            <x-ui.status-badge status="review" />
        </x-slot:proof>
    </x-ui.ticket-card>

    <x-ui.card title="Memuat kartu"><x-ui.skeleton /></x-ui.card>

    <x-ui.card title="Pengumuman">
        <x-ui.empty-state icon="megaphone" message="Belum ada pengumuman dari pesantren." class="py-2" />
    </x-ui.card>

    <nav aria-label="Pratinjau portal lain" class="flex flex-wrap gap-2">
        @foreach (['wali' => 'Wali', 'santri' => 'Santri', 'ustadz' => 'Ustadz', 'musyrif' => 'Musyrif'] as $key => $label)
            <x-ui.button variant="tertiary" :href="route('preview.portal', $key)" wire:navigate :aria-current="$key === $role ? 'page' : null">{{ $label }}</x-ui.button>
        @endforeach
        <x-ui.button variant="tertiary" :href="route('preview.gallery')" wire:navigate>Komponen</x-ui.button>
    </nav>
</x-layouts.portal>
