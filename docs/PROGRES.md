# Progres SANAD

Diperbarui setiap ada langkah selesai.

- [x] **Tahap 0:** fondasi proyek, design tokens, komponen, layout
- [x] **Tahap 1:** auth, role dan policy, profil pesantren, modul, integrasi, audit log, wizard setup, import santri/wali
- [x] **Tahap 2:** keuangan, tagihan, pembayaran tunai dan transfer manual, kwitansi PDF, tunggakan
- [x] **Tahap 3:** Midtrans dan notifikasi WhatsApp Fonnte (diuji dengan gateway tiruan, kunci sungguhan belum ada)
- [ ] **Tahap 4:** kartu santri, dompet, kasir POS (berikutnya)
- [ ] **Tahap 5:** Mart & Warung
- [ ] **Tahap 6:** akademik, jadwal, absensi, nilai dan rapor PDF
- [ ] **Tahap 7:** perizinan dan pos gerbang
- [ ] **Tahap 8:** tahfidz dan mutabaah
- [ ] **Tahap 9:** ujian online dan PWA
- [ ] **Tahap 10:** absensi selfie dan wajah
- [ ] **Tahap 11:** PPDB
- [ ] **Tahap 12:** pelanggaran, kesehatan, analitik risiko, alumni, inventaris, piket, ekspor Dapodik/EMIS
- [ ] **Tahap 13:** hardening, uji beban dan keamanan, backup, seluruh UAT

## Rincian Tahap 3

- [x] Bayar online lewat Snap, biaya admin, kedaluwarsa, kanal yang ditampilkan (FIN-07, SE-03)
- [x] Webhook Midtrans: tanda tangan SHA-512, idempoten, status mundur diabaikan
- [x] Job sinkronisasi 15 menit dan layar rekonsiliasi; selisih nominal ditandai untuk ditinjau
- [x] Uji konkurensi multi-proses di PostgreSQL: webhook ganda, bayar serentak, tunai serentak
- [x] Fonnte: tes koneksi, tes kirim, webhook status (NTF-01)
- [x] Template per pemicu, variabel, pratinjau, aktif dan mati (NTF-02, SE-04)
- [x] Antrean dengan jeda acak dan batas per jam (NTF-03)
- [x] Log, ulang 3 kali lalu gagal, kirim ulang dari layar (NTF-04)
- [x] Preferensi wali, kategori kritis terkunci (NTF-05, W-13)
- [x] Notifikasi aplikasi dan lonceng portal (NTF-06, W-12)
- [x] OTP lewat Fonnte: antrean terenkripsi, kode tidak masuk log
- [x] Pengingat H-3, H0, H+3, H+7 dan pengingat massal dengan pratinjau (FIN-12, F-06)
- [x] Pengumuman lewat WhatsApp, langsung dan terjadwal (COR-13)
- [x] Tautan dari WhatsApp kembali ke halaman tujuan setelah masuk
- [x] Konfigurasi Horizon: prioritas antrean `otp`, `notifications`, `default`
- [x] Pindah halaman tanpa muat ulang, sorotan tab seketika, OPcache di PHP pengembangan
- [x] Test policy, Delivery Gate, commit

## Rincian Tahap 2

- [x] Jenis tagihan, tarif bertingkat, cek tarif efektif (FIN-01, FIN-02, F-01)
- [x] Penugasan per santri, diskon dan beasiswa (FIN-03, FIN-04)
- [x] Generate tagihan dengan pratinjau wajib, idempoten, terjadwal H-7 (FIN-05, F-02)
- [x] Tagihan manual, void wajib alasan, ekspor (FIN-06, FIN-16, F-03)
- [x] Transfer manual: rekening, kode unik, unggah bukti, unggah ulang (FIN-08, W-04, W-05, W-06)
- [x] Verifikasi transfer dengan pintasan keyboard (F-04)
- [x] Pembayaran tunai, kembalian, cicilan ke tagihan tertua (FIN-09, FIN-10, F-05)
- [x] Denda keterlambatan sekali saja (FIN-11)
- [x] Tunggakan dan impor tunggakan awal (FIN-12, F-06, SET-03)
- [x] Kwitansi PDF dengan QR dan teks Arab, halaman periksa publik (FIN-13)
- [x] Laporan kas dan pengeluaran (FIN-14, F-07)
- [x] Pengembalian dana tercatat (FIN-16)
- [x] Portal wali: beranda, tagihan, detail, bayar, status (W-01 sampai W-06)
- [x] Tab keuangan di detail santri, angka keuangan di dashboard
- [x] Test policy, Delivery Gate, commit

## Rincian Tahap 1

- [x] PostgreSQL 16 portabel di port 5433; Docker, CI, dan `.env` memakai PostgreSQL
- [x] Tabel inti dan data induk, audit log yang tidak bisa diubah
- [x] Modul feature-flag dan layar Modul (SE-02)
- [x] Masuk: OTP wali, NIS santri, email staf, lupa sandi (P-04, P-05, P-06)
- [x] Role dan policy data santri sesuai matriks PRD Sistem bagian 10
- [x] Profil dan branding (SE-01), integrasi (SE-03), audit log (SE-06)
- [x] Data induk: tahun ajaran, term, kelas, asrama, santri, wali, ustadz, pengguna (COR-03 sampai COR-11)
- [x] Field custom, kenaikan kelas, pengumuman (COR-06, COR-10, COR-13)
- [x] Import santri dan wali (COR-07, SET-03)
- [x] Checklist setup di dashboard (SET-01)
- [x] Topbar staf: pencarian, notifikasi, menu akun
- [x] 2FA opsional dan ganti sandi (COR-11)
- [x] Kesehatan Sistem (SET-02, SE-08)
- [x] Test policy, Delivery Gate, commit

## Dikerjakan sebagian, dilanjutkan nanti

Rincian alasannya ada di `docs/ASSUMPTIONS.md`.

- [ ] Layar tagihan di portal santri, bayar sebagian dari portal wali
- [ ] Import saldo dompet awal (Tahap 4)
- [ ] Tab lain di detail santri (mengikuti tahap modulnya)
- [ ] Status backup di Kesehatan Sistem (Tahap 13)
- [ ] Produksi: OPcache tanpa cek berkas, `artisan optimize` dan `icons:cache` saat deploy, ukur waktu layar (Tahap 13)
- [ ] Endpoint REST `/api/v1` data inti (belum ada klien yang memakainya)

## Belum teruji

- Docker dan CI baru lolos cek sintaks, belum pernah dijalankan
- Tampilan dan teks Arab belum dicoba di HP Android sungguhan
- Horizon terpasang dan terkonfigurasi, tetapi belum pernah dijalankan, karena butuh Redis dan `pcntl` (WSL2 atau Docker)
- Midtrans dan Fonnte belum pernah dicoba dengan kunci sungguhan. Yang perlu dipastikan: kode kanal bayar (A43) dan nilai `state` webhook Fonnte (A46)
