# Progres SANAD

Diperbarui setiap ada langkah selesai.

- [x] **Tahap 0:** fondasi proyek, design tokens, komponen, layout
- [x] **Tahap 1:** auth, role dan policy, profil pesantren, modul, integrasi, audit log, wizard setup, import santri/wali
- [ ] **Tahap 2:** keuangan, tagihan, pembayaran tunai dan transfer manual, kwitansi PDF, tunggakan (berikutnya)
- [ ] **Tahap 3:** Midtrans dan notifikasi WhatsApp Fonnte
- [ ] **Tahap 4:** kartu santri, dompet, kasir POS
- [ ] **Tahap 5:** Mart & Warung
- [ ] **Tahap 6:** akademik, jadwal, absensi, nilai dan rapor PDF
- [ ] **Tahap 7:** perizinan dan pos gerbang
- [ ] **Tahap 8:** tahfidz dan mutabaah
- [ ] **Tahap 9:** ujian online dan PWA
- [ ] **Tahap 10:** absensi selfie dan wajah
- [ ] **Tahap 11:** PPDB
- [ ] **Tahap 12:** pelanggaran, kesehatan, analitik risiko, alumni, inventaris, piket, ekspor Dapodik/EMIS
- [ ] **Tahap 13:** hardening, uji beban dan keamanan, backup, seluruh UAT

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

## Dikerjakan sebagian di Tahap 1, dilanjutkan nanti

Rincian alasannya ada di `docs/ASSUMPTIONS.md`.

- [ ] OTP WhatsApp sungguhan (Tahap 3; sekarang kode hanya masuk log dan ditolak di produksi)
- [ ] Tes koneksi Midtrans dan Fonnte, URL webhook, kanal bayar (Tahap 3)
- [ ] Kirim pengumuman lewat WhatsApp (Tahap 3)
- [ ] Rekening transfer manual di layar Integrasi (Tahap 2)
- [ ] Langkah ke-7 checklist setup: jenis tagihan (Tahap 2)
- [ ] Pencarian nomor invoice di topbar (Tahap 2)
- [ ] Import tunggakan awal (Tahap 2) dan saldo dompet awal (Tahap 4)
- [ ] Tab lain di detail santri (mengikuti tahap modulnya)
- [ ] Status backup di Kesehatan Sistem (Tahap 13)
- [ ] Endpoint REST `/api/v1` data inti (belum ada klien yang memakainya)

## Belum teruji

- Docker dan CI baru lolos cek sintaks, belum pernah dijalankan
- Tampilan dan teks Arab belum dicoba di HP Android sungguhan
- Horizon belum dipasang, karena butuh WSL2 atau Docker
