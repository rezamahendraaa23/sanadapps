# Progres SANAD

Diperbarui setiap ada langkah selesai.

- [x] **Tahap 0:** fondasi proyek, design tokens, komponen, layout
- [x] **Tahap 1:** auth, role dan policy, profil pesantren, modul, integrasi, audit log, wizard setup, import santri/wali
- [x] **Tahap 2:** keuangan, tagihan, pembayaran tunai dan transfer manual, kwitansi PDF, tunggakan
- [x] **Tahap 3:** Midtrans dan notifikasi WhatsApp Fonnte (diuji dengan gateway tiruan, kunci sungguhan belum ada)
- [x] **Tahap 4:** kartu santri, dompet, kasir POS
- [x] **Tahap 5:** Mart & Warung
- [x] **Tahap 6:** akademik, jadwal, absensi, nilai dan rapor PDF
- [x] **Tahap 7:** perizinan dan pos gerbang
- [x] **Tahap 8:** tahfidz dan mutabaah
- [ ] **Tahap 9:** ujian online dan PWA (berikutnya)
- [ ] **Tahap 10:** absensi selfie dan wajah
- [ ] **Tahap 11:** PPDB
- [ ] **Tahap 12:** pelanggaran, kesehatan, analitik risiko, alumni, inventaris, piket, ekspor Dapodik/EMIS
- [ ] **Tahap 12b:** modul Masjid: jadwal sholat Kemenag, layar TV offline, konten dan persetujuan, pairing, aplikasi Android TV
- [ ] **Tahap 13:** hardening, uji beban dan keamanan, backup, seluruh UAT

## Rincian Tahap 8

- [x] Setoran ziyadah, murojaah, tahsin; surat dan ayat divalidasi dari 114 surat (THF-01, U-05)
- [x] Target per term dan progres di portal wali dan santri (THF-02)
- [x] Peta hafalan per juz, ringkasan PDF (THF-03)
- [x] Mutabaah: santri mencentang, musyrif memverifikasi massal, terkunci setelah diverifikasi (THF-04, U-12, S-07)
- [x] Rekap dan ekspor Excel (THF-05)
- [x] Test policy, Delivery Gate, commit

## Rincian Tahap 7

- [x] Pengajuan izin oleh wali atau musyrif, QR izin saat disetujui (LEV-01, LEV-03, W-11)
- [x] Persetujuan musyrif untuk asramanya, opsi dua tingkat, antrean di HP (LEV-02, U-10, KS-01)
- [x] Terlambat kembali: job 10 menit, kabar sekali ke wali dan musyrif (LEV-04)
- [x] Papan sedang di luar, terlambat naik ke atas, pembaruan 5 detik (GTE-01, K-03)
- [x] Pindai gerbang: keluar, masuk, ditolak dengan alasan, suara, tap dobel diabaikan (GTE-02, K-02)
- [x] Pencatatan manual masuk tinjauan musyrif, penjemput izin pulang (GTE-03, GTE-06, K-04)
- [x] Izin hari ini, riwayat tidak bisa diubah, ekspor (GTE-04, GTE-05, K-06)
- [x] Serah terima shift dan override darurat (GTE-07, GTE-08, K-05)
- [x] Izin aktif membuat absensi tercatat izin atau sakit, bukan alpa
- [x] Test policy, Delivery Gate, commit

## Rincian Tahap 6

- [x] Mapel, penugasan, komponen nilai, aturan absensi (ACD-01, ACD-02, GRD-01, ATT-05)
- [x] Jadwal per hari dengan deteksi bentrok ustadz, kelas, ruang; salin dari term lain (ACD-03, AC-01)
- [x] Absensi manual default hadir, draf tersimpan di HP saat putus koneksi (ATT-01, U-03)
- [x] Sesi asrama, sholat, kegiatan oleh musyrif (ATT-02, U-13)
- [x] Absensi kartu dan QR: terlambat otomatis, pindai ganda diabaikan, API perangkat (ATT-03)
- [x] Rekap kehadiran dan ekspor Excel serta PDF (ATT-04, AC-02)
- [x] Input nilai per mapel dan kelas, validasi 0 sampai maksimum (GRD-02, U-04)
- [x] Rapor: draf, predikat, KKM, terbit mengunci nilai, PDF dengan teks Arab (GRD-03, GRD-04, AC-03)
- [x] Portal ustadz, musyrif, wali (W-09, W-10), santri (S-02, S-08)
- [x] Notifikasi alpa, terlambat, rapor terbit
- [x] Test policy, Delivery Gate, commit

## Rincian Tahap 5

- [x] Beranda Mart & Warung: penjualan hari ini, kartu per merchant, stok menipis (F-10)
- [x] Hak akses: pengelola hanya merchant miliknya, kasir hanya berjualan, bendahara hanya melihat (POS-01)
- [x] Katalog: kategori, foto, barcode, ubah harga massal, impor xlsx, daftar harga PDF (POS-05, F-11)
- [x] Stok: masuk, keluar, opname; otomatis berkurang saat terjual, kembali saat void; tidak bisa minus (POS-06, F-12)
- [x] Aturan wali: batas per merchant dan kategori, larang merchant atau kategori (POS-08, W-15)
- [x] Laporan: omzet, komisi, margin, produk terlaris, jam ramai, per merchant dan gabungan, ekspor (POS-03, POS-11, F-13)
- [x] Pencairan: ajukan, setujui, bayar; saldo dan kas berubah saat dibayar (POS-04, POS-10)
- [x] Rekonsiliasi belanja dompet dengan penjualan merchant
- [x] Notifikasi stok menipis dan status pencairan
- [x] Test policy, Delivery Gate, commit

## Rincian Tahap 4

- [x] Dompet per santri: saldo tidak bisa negatif, buku besar tidak bisa diubah (WAL-01, WAL-03)
- [x] Top-up lewat Midtrans, transfer, dan tunai; saldo bertambah hanya setelah lunas (WAL-02, W-08)
- [x] Limit harian, limit per transaksi, blokir sementara oleh wali (WAL-04, W-07)
- [x] Penyesuaian wajib alasan dan tarik sisa saldo, tercatat di kas (WAL-05, F-09)
- [x] Rekonsiliasi harian dompet dan merchant, selisih memberi tahu admin
- [x] Kartu: terbitkan, blokir, ganti, cetak PDF, satu santri satu kartu aktif (CRD-01, CRD-04, A-07)
- [x] PIN hash, wajib di atas ambang, salah 3 kali terkunci 15 menit (CRD-03)
- [x] QR dinamis 30 detik sekali pakai; QR lama dan palsu ditolak (CRD-05, S-09)
- [x] Perangkat dengan kunci API, scope, cabut; log pindai dan ekspor (CRD-02, CRD-06, CRD-07)
- [x] Kasir: keranjang, barcode, kartu atau QR, keypad PIN, hasil besar, void (POS-02, POS-07, POS-12, K-01)
- [x] Struk digital di riwayat wali (POS-09)
- [x] Uji konkurensi: enam proses berebut saldo, tepat yang cukup berhasil; tap ganda hanya memotong sekali
- [x] Notifikasi top-up, saldo menipis, belanja (opsional), kartu diblokir
- [x] Test policy, Delivery Gate, commit

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
- [ ] Import saldo dompet awal (belum ada layar impor; sekarang lewat penyesuaian satu per satu)
- [ ] Tab lain di detail santri (mengikuti tahap modulnya)
- [ ] Status backup di Kesehatan Sistem (Tahap 13)
- [ ] Produksi: OPcache tanpa cek berkas, `artisan optimize` dan `icons:cache` saat deploy, ukur waktu layar (Tahap 13)
- [ ] Endpoint REST `/api/v1` data inti (belum ada klien yang memakainya)

## Belum teruji

- Docker dan CI baru lolos cek sintaks, belum pernah dijalankan
- Tampilan dan teks Arab belum dicoba di HP Android sungguhan
- Horizon terpasang dan terkonfigurasi, tetapi belum pernah dijalankan, karena butuh Redis dan `pcntl` (WSL2 atau Docker)
- Reader kartu USB dan kamera QR di kasir belum dicoba dengan alat sungguhan; pemindaian di uji browser diketik atau disimulasikan
- Midtrans dan Fonnte belum pernah dicoba dengan kunci sungguhan. Yang perlu dipastikan: kode kanal bayar (A43) dan nilai `state` webhook Fonnte (A46)
