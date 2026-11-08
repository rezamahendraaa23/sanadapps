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
- [x] **Tahap 9:** ujian online dan PWA
- [x] **Tahap 10:** absensi wajah dan persetujuan wali (selfie dihapus atas keputusan pemilik)
- [x] **Tahap 11:** PPDB
- [x] **Tahap 12:** pelanggaran, kesehatan, analitik risiko, alumni, inventaris, piket, ekspor Dapodik/EMIS
- [x] **Tahap 12b:** modul Masjid: jadwal sholat Kemenag, layar TV offline, konten dan persetujuan, pairing, aplikasi Android TV
- [ ] **Tahap 13:** hardening, uji beban dan keamanan, backup, seluruh UAT (pekerjaan kode selesai, menunggu uji lapangan di bawah)
- [x] **Tambahan pemilik 8 November:** POS lanjutan, modul Surat, kurikulum berbasis fase, konten TV fleksibel

## Rincian Tahap 13

- [x] Header keamanan dan CSP, HSTS lewat HTTPS, Horizon hanya pengelola pengaturan
- [x] Uji akses silang (wali A ke data wali B, ustadz dan musyrif lain) di tagihan, kwitansi, rapor, ujian, PPDB, absensi
- [x] Cadangan harian (`sanad:backup`), restore aman (`sanad:restore`), uji restore mingguan otomatis (`sanad:backup-drill`)
- [x] Uji beban satu proses 200 santri: autosave ujian p95 sekitar 8 ms, belanja kartu p95 di bawah 100 ms, tidak ada jawaban hilang, saldo cocok
- [ ] Uji beban serentak di staging dengan k6 (`scripts/load`), **belum dijalankan, butuh server staging**
- [x] Runbook (`docs/RUNBOOK.md`) dan pemetaan 16 skenario UAT ke bukti test (`docs/UAT.md`)
- [ ] Uji lapangan: Midtrans sandbox, HP santri sungguhan, kamera wajah, Fonnte, APK Android TV

## Rincian tambahan pemilik

- [x] Kasir: desain baru, chip kategori, keranjang gaya struk, omzet hari ini
- [x] POS: shift kasir dengan hitung uang, penjualan tunai untuk bukan santri, pemasok dan pembelian dengan utang, daftar perlu belanja, kedaluwarsa, promo, retur sebagian, struk thermal 58 dan 80 mm
- [x] Surat: template dengan isian otomatis, kop dan footer diunggah, nomor berurutan per tahun, arsip dan pembatalan, PDF, peran tata usaha
- [x] Kurikulum fase: fase berdurasi sendiri, rombongan, nilai selama masa fase, keputusan lulus, sertifikat bernomor, rapor fase, naik fase
- [x] Layar TV: nuansa Islami, konten layar penuh atau bagian pengumuman, YouTube, pemotong gambar sesuai layar, tombol pasang aplikasi, status perintah
- [x] Perangkat pemindai pindah ke Pengaturan dan tidak memuat layar TV
- [ ] Portal wali dan santri belum menampilkan rapor fase dan sertifikat (baru dari panel staf)
- [ ] Sidebar per modul: menunggu keputusan pemilik
- [ ] CCTV Jumatan: menunggu merek dan cara kamera menyiarkan

## Rincian Tahap 12b

- [x] Jadwal sholat metode Kemenag dengan golden test 4 kota sepanjang 2026, selisih maksimal 1 menit (MSJ-PRY-01 sampai 14)
- [x] Koreksi, ubah manual per tanggal, iqamah, Jumat, Ramadan, tanggal hijriah dengan penetapan manual
- [x] Pembanding dari API jadwal resmi dan impor manual, cadangan ke hitungan sendiri
- [x] Layar TV offline-first: pairing kode, token yang bisa dicabut, mode adzan dan iqamah dari jadwal tersimpan, jam dari server (MSJ-DSP, MSJ-DEV)
- [x] Konten dengan persetujuan, darurat, ayat terverifikasi, pengumuman SANAD tayang di TV
- [x] Privasi layar: nama santri hanya dengan persetujuan wali, manifest tanpa data sensitif
- [x] Sambungan ke SANAD: jadwal berjangkar, sesi absensi sholat otomatis, kartu sholat berikutnya di portal santri
- [ ] Aplikasi Android TV: kode ada, **belum dibangun dan belum dicoba di TV**
- [x] Test policy, Delivery Gate, commit

## Rincian Tahap 12

- [x] Pelanggaran: aturan, poin per tahun ajaran, ambang sanksi sekali, opsi poin terlambat kembali (DSC-01 sampai DSC-03, LEV-04)
- [x] Kesehatan: terenkripsi, detail hanya UKS dan tercatat, ringkasan untuk admin, musyrif, wali (HLT-01, HLT-02)
- [x] Skor risiko harian dengan alasan dan bobot yang bisa diubah (ANL-01)
- [x] Alumni otomatis dari kelulusan, cari, ubah, ekspor (ALM-01)
- [x] Inventaris dengan mutasi yang tidak bisa dihapus (INV-01)
- [x] Piket mingguan, penugasan, tukar, tampil di portal (PKT-01)
- [x] Ekspor kolom umum untuk Dapodik dan EMIS (EXP-01, template resmi belum dicocokkan)
- [x] Test policy, Delivery Gate, commit

## Rincian Tahap 11

- [x] Periode, kuota, biaya, tahap berbobot 100%, form tutup otomatis (PPD-01, PD-01)
- [x] Wizard publik dari HP tanpa akun dengan OTP, draf tiap langkah (PPD-02, P-02)
- [x] Unggah dokumen dengan pengecilan di HP, pratinjau, periksa per dokumen (PPD-03, PD-03)
- [x] Tagihan biaya daftar, status sudah bayar saat lunas (PPD-04)
- [x] Seleksi berbobot, nilai ujian online masuk otomatis, peringkat, kuota terkunci (PPD-05, EXM-12)
- [x] Pengumuman sekarang atau terjadwal, keputusan tersembunyi sebelum diumumkan (PPD-06)
- [x] Daftar ulang satu klik: santri, wali, dompet, tagihan uang masuk (PPD-07)
- [x] Cek status publik dengan nomor dan OTP (PPD-08, P-03)
- [x] Test policy, Delivery Gate, commit

## Rincian Tahap 10

- [x] Mode absen wajah per pesantren dan per sesi (ATT-05, SE-07)
- [x] Absen selfie sempat dibangun lalu dihapus atas keputusan pemilik (ATT-06)
- [x] Antrean tinjauan dengan terima massal, alasan tolak, pintasan H dan T (U-14)
- [x] Wajah di balik `FaceMatcher`: ambang atas dan bawah, liveness 3 kali lalu cadangan (ATT-07)
- [x] Persetujuan wali digital dan kertas, cabut menghapus data saat itu juga, enrol terblokir tanpa persetujuan (ATT-08, W-14, U-15)
- [x] API perangkat untuk tablet (`/device/attendance/face`)
- [x] Foto bukti hanya untuk pengabsen sesi, setiap akses tercatat di audit
- [x] Pengenal wajah sungguhan lewat face-api di browser (Jalur C), model disimpan di server sendiri
- [ ] Uji coba di lokasi dengan santri, kamera, dan cahaya sungguhan: **belum dilakukan**
- [x] Test policy, Delivery Gate, commit

## Rincian Tahap 9

- [x] Bank soal lima jenis, gambar, teks Arab RTL, impor per baris (EXM-01, EXM-02, U-06)
- [x] Pembuat ujian 4 langkah, acak per tag, kode akses, validasi sebelum terbit (EXM-03, U-07)
- [x] Acak soal dan opsi per santri, tersimpan di attempt (EXM-04)
- [x] Halaman ujian HP: timer server, nomor soal, tandai ragu, tinjau (EXM-05, S-03 sampai S-06)
- [x] Autosave IndexedDB, sinkron idempoten `client_seq`, buka ulang saat putus lewat service worker (EXM-06)
- [x] Batas waktu dari server, auto-submit tiap menit, ambil alih perangkat (EXM-07, EXM-08)
- [x] Monitor live 5 detik dengan jumlah pindah layar (EXM-09, U-08)
- [x] Koreksi otomatis dan esai, analisis butir, kirim ke buku nilai (EXM-10, U-09, AC-04)
- [x] Uji konkurensi: mulai bersamaan satu attempt, sinkron bersamaan menyimpan seq terbesar
- [x] Test policy, Delivery Gate, commit

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
- Ujian belum diuji beban ratusan santri bersamaan (Tahap 13) dan belum dicoba di HP Android sungguhan
- Absen wajah baru diuji pada foto contoh, belum pada kamera dan santri sungguhan, dan butuh tinjauan hukum UU PDP sebelum diaktifkan. Pos otomatis tanpa tombol belum teruji dengan wajah bergerak. Kamera wajah baru diuji dengan kamera tiruan Chrome, belum di perangkat sungguhan
- Satu E2E Tahap 4 sekali menggantung lebih dari 10 detik pada satu pindah halaman, tanpa jejak di log dan tidak muncul lagi di uji penuh berikutnya. Penyebabnya belum diketahui
- Layar TV baru diuji di Chrome desktop, belum di Android TV, belum uji 72 jam, belum dilihat dari jarak 8 meter
- Midtrans dan Fonnte belum pernah dicoba dengan kunci sungguhan. Yang perlu dipastikan: kode kanal bayar (A43) dan nilai `state` webhook Fonnte (A46)
