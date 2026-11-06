<x-layouts.base title="Pratinjau komponen">
    <main id="konten" tabindex="-1" class="mx-auto flex w-full max-w-[1280px] flex-col gap-8 p-4 outline-none md:p-8">
        <div>
            <x-ui.logo :tagline="true" :size="48" />
            <h1 class="font-display mt-4 text-2xl font-semibold">Pratinjau komponen</h1>
        </div>
        @include('preview.banner')

        <nav aria-label="Pratinjau layout" class="flex flex-wrap gap-2">
            <x-ui.button variant="secondary" :href="route('preview.staff')">Layout staf</x-ui.button>
            @foreach (['wali' => 'Portal wali', 'santri' => 'Portal santri', 'ustadz' => 'Portal ustadz', 'musyrif' => 'Portal musyrif'] as $role => $label)
                <x-ui.button variant="secondary" :href="route('preview.portal', $role)">{{ $label }}</x-ui.button>
            @endforeach
        </nav>

        <section class="flex flex-col gap-4">
            <x-ui.section-heading>Tipografi</x-ui.section-heading>
            <p class="font-display text-3xl font-semibold">Fredoka untuk judul dan angka utama</p>
            <p class="max-w-prose text-base">Nunito dipakai untuk teks bacaan dan tabel. Angka uang memakai tabular: <span class="font-tabular">Rp 1.250.000</span> dan <span class="font-tabular">Rp 625.000</span>.</p>
            <div class="flex flex-col gap-1">
                <p class="text-sm text-text-muted">Amiri, font Arab utama</p>
                <p dir="rtl"><x-ui.arabic class="text-[1.75rem]">بِسْمِ ٱللَّهِ ٱلرَّحْمَٰنِ ٱلرَّحِيمِ</x-ui.arabic></p>
                <p class="text-sm text-text-muted">Noto Naskh Arabic, font Arab cadangan</p>
                <p dir="rtl"><span dir="auto" lang="ar" class="font-arabic-fallback text-[1.375rem] leading-[2]">ٱلْحَمْدُ لِلَّهِ رَبِّ ٱلْعَٰلَمِينَ</span></p>
            </div>
        </section>

        <section class="flex flex-col gap-4">
            <x-ui.section-heading>Tombol</x-ui.section-heading>
            <div class="flex flex-wrap gap-3">
                <x-ui.button icon="banknote" x-data x-on:click="$dispatch('toast', { message: 'Tombol primer berfungsi.', tone: 'success' })">Bayar Sekarang</x-ui.button>
                <x-ui.button variant="secondary" x-data x-on:click="$dispatch('open-modal', 'contoh-konfirmasi')">Buka dialog</x-ui.button>
                <x-ui.button variant="tertiary" x-data x-on:click="$dispatch('toast', { message: 'Pesan informasi contoh.', tone: 'info' })">Tampilkan pesan</x-ui.button>
                <x-ui.button variant="danger" x-data x-on:click="$dispatch('toast', { message: 'Pesan kesalahan contoh.', tone: 'danger' })">Tampilkan galat</x-ui.button>
                <x-ui.button loading>Simpan</x-ui.button>
                <x-ui.button disabled>Tidak tersedia</x-ui.button>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2">
            <div class="flex min-w-0 flex-col gap-4">
                <x-ui.section-heading>Input</x-ui.section-heading>
                <x-ui.input label="Nomor WhatsApp" name="phone" type="tel" inputmode="tel" required help="Kode OTP dikirim ke nomor ini." />
                <x-ui.input label="NIS" name="nis" error="NIS ini sudah dipakai santri lain. Periksa kembali nomornya." />
                <x-ui.input-rupiah label="Nominal top-up" name="amount" :value="50000" help="Minimal Rp 10.000." />
            </div>
            <div class="flex min-w-0 flex-col gap-4">
                <x-ui.section-heading>Kartu hero</x-ui.section-heading>
                <x-ui.hero-card label="Total yang harus dibayar" value="Rp 625.000">
                    Contoh tagihan. Jatuh tempo 10 Nov 2026.
                    <x-slot:action>
                        <x-ui.button variant="on-primary" x-data x-on:click="$dispatch('toast', { message: 'Aksi pada kartu hero berfungsi.', tone: 'success' })">Bayar Sekarang</x-ui.button>
                    </x-slot:action>
                </x-ui.hero-card>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2">
            <div class="flex min-w-0 flex-col gap-4">
                <x-ui.section-heading>Kartu tiket</x-ui.section-heading>
                <x-ui.ticket-card>
                    <h3 class="font-display text-lg font-semibold">Kwitansi contoh</h3>
                    <dl class="mt-3 grid grid-cols-2 gap-y-1 text-sm">
                        <dt class="text-text-muted">Santri</dt><dd class="text-right">Santri Contoh 01</dd>
                        <dt class="text-text-muted">Nomor</dt><dd class="text-right">CONTOH-001</dd>
                        <dt class="text-text-muted">Total</dt><dd class="font-tabular text-right font-semibold">Rp 625.000</dd>
                    </dl>
                    <x-slot:proof>
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm text-text-muted">Rincian di atas, bukti di bawah takik.</p>
                            <x-ui.stamp>Lunas</x-ui.stamp>
                        </div>
                    </x-slot:proof>
                </x-ui.ticket-card>
            </div>
            <div class="flex min-w-0 flex-col gap-4">
                <x-ui.section-heading>Badge status</x-ui.section-heading>
                <div class="flex flex-wrap gap-2">
                    @foreach (['paid', 'unpaid', 'partial', 'overdue', 'review', 'void', 'present', 'excused', 'sick', 'absent', 'late', 'active', 'blocked'] as $status)
                        <x-ui.status-badge :status="$status" />
                    @endforeach
                </div>
            </div>
        </section>

        <section class="flex flex-col gap-4">
            <x-ui.section-heading>Tabel</x-ui.section-heading>
            <x-ui.card><livewire:preview.sample-table /></x-ui.card>
        </section>

        <section class="grid gap-4 md:grid-cols-3">
            <x-ui.card title="Skeleton"><x-ui.skeleton /></x-ui.card>
            <x-ui.card title="Kosong">
                <x-ui.empty-state message="Semua tagihan sudah lunas." class="py-2" />
            </x-ui.card>
            <x-ui.card title="Banner">
                <div class="flex flex-col gap-2">
                    <x-ui.banner tone="danger">Koneksi terputus. Jawaban tetap aman di HP.</x-ui.banner>
                    <x-ui.banner tone="success">Absensi tersimpan.</x-ui.banner>
                </div>
            </x-ui.card>
        </section>
    </main>

    <x-ui.modal name="contoh-konfirmasi" title="Blokir kartu contoh?">
        <p>Kartu akan diblokir seketika. Saldo tetap aman di dompet.</p>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button variant="danger" x-on:click="open = false; $dispatch('toast', { message: 'Contoh selesai. Tidak ada kartu yang diblokir.', tone: 'success' })">Blokir Kartu</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</x-layouts.base>
