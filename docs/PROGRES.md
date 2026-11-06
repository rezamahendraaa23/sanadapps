# Progres SANAD

Diperbarui setiap ada langkah selesai.

- [x] **Tahap 0:** fondasi proyek, design tokens, komponen, layout
- [ ] **Tahap 1:** auth, role dan policy, profil pesantren, modul, integrasi, audit log, wizard setup, import santri/wali (sedang dikerjakan)
- [ ] **Tahap 2:** keuangan, tagihan, pembayaran tunai dan transfer manual, kwitansi PDF, tunggakan
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

Persiapan:

- [x] PostgreSQL 16 portabel di port 5433
- [x] Docker, CI, dan `.env` memakai PostgreSQL
- [x] Paket permission, Sanctum, Fortify, dan Excel terpasang
- [x] Konfigurasi PostgreSQL di-commit (test Tahap 0 belum memakai tabel; uji tabel mulai Tahap 1)

Fitur:

- [x] Tabel inti: `school_profile`, `modules`, `integrations`, `audit_logs`, `webhook_events`, pengguna dan role
- [x] Modul feature-flag: `EnsureModuleEnabled` dan aturan dependensi (layar SE-02 menyusul)
- [x] Masuk: OTP wali, NIS santri, email staf, lupa sandi (P-04, P-05, P-06); OTP lewat log sampai Fonnte di Tahap 3
- [x] Role dan policy data santri sesuai matriks PRD Sistem bagian 10 (izin modul lain ditambah di tahapnya)
- [ ] Profil dan branding (SE-01), integrasi (SE-03), audit log (SE-06)
- [ ] Data induk: tahun ajaran, kelas, asrama, santri, wali, ustadz (COR-03 sampai COR-09)
- [ ] Field custom, kenaikan kelas, pengumuman (COR-06, COR-10, COR-13)
- [ ] Import santri dan wali (COR-07, SET-03)
- [ ] Wizard setup dan checklist dashboard (SET-01)
- [ ] Topbar staf: pencarian, notifikasi, profil
- [ ] 2FA opsional: langkah masuk selesai, layar mengaktifkan belum (COR-11)
- [ ] Test policy, Delivery Gate, commit

## Belum teruji

- Docker dan CI baru lolos cek sintaks, belum pernah dijalankan
- Teks Arab belum dicoba di HP Android sungguhan
