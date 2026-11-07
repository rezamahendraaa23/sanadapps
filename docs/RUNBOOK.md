# Runbook SANAD

Panduan operasional untuk admin teknis pesantren atau vendor yang memegang server. Setiap bagian bisa dibaca sendiri saat ada kejadian.

## 1. Proses yang harus selalu hidup

| Proses | Perintah | Kalau mati |
|---|---|---|
| Web (PHP-FPM + Nginx/Caddy) | sesuai `docker/` | Halaman tidak terbuka |
| Antrean (Horizon) | `php artisan horizon` | WA, kwitansi PDF, notifikasi tertunda. Data tidak hilang, akan terkirim setelah hidup lagi |
| Penjadwal | cron tiap menit: `* * * * * php artisan schedule:run` | Tagihan bulanan, auto-submit ujian, cadangan harian, dan sinkron pembayaran tidak jalan |

Cek cepat: `GET /up` harus 200. Antrean dilihat di `/horizon` (hanya pengguna dengan izin pengaturan).

Jadwal penting (WIB): tagihan dibuat 01.00, denda 00.30, rekonsiliasi dompet 01.00, cadangan 03.00, uji restore Ahad 04.00, auto-submit ujian tiap menit, sinkron pembayaran tertunda tiap 15 menit.

## 2. Cadangan dan pemulihan

Lapisan pertama adalah snapshot dan arsip WAL dari penyedia PostgreSQL (RPO 15 menit, PRD Sistem §11). Lapisan aplikasi di bawah ini melengkapinya dan dipakai untuk uji restore.

- Buat cadangan manual: `php artisan sanad:backup`. Hasilnya ada di `storage/app/backups/<waktu UTC>/`: `db.dump` (pg_dump format custom), `files.zip` (unggahan di storage/app/private dan public), dan `manifest.json` (jumlah baris per tabel dan checksum).
- Salinan keluar server: isi `SANAD_BACKUP_DISK` dengan nama disk object storage (misalnya R2). Tanpa itu cadangan hanya ada di server yang sama, dan itu belum memenuhi syarat pemulihan bencana.
- Retensi lokal: `SANAD_BACKUP_KEEP_DAYS` (bawaan 14). Cadangan terbaru tidak pernah dihapus.
- Isi cadangan adalah data pribadi anak dan data kesehatan. Simpan di bucket privat dengan enkripsi saat tersimpan, dan batasi siapa yang punya akses.
- `SANAD_PG_BIN` diisi folder `pg_dump`/`pg_restore` bila tidak ada di PATH. Versi alat harus sama atau lebih baru dari server PostgreSQL.

### Uji restore (UAT 11)

`php artisan sanad:backup-drill` memulihkan cadangan terbaru ke database sementara `sanad_drill_*`, mencocokkan jumlah baris setiap tabel dengan manifest, lalu menghapus database itu. Perintah ini berjalan otomatis tiap Ahad. Bila gagal, galatnya tercatat di log dengan judul "Uji restore". Pengguna database butuh hak `CREATEDB`.

### Memulihkan ke staging

```bash
createdb sanad_staging
php artisan sanad:restore --database=sanad_staging            # cadangan terbaru
php artisan sanad:restore 20261108-030000 --database=sanad_staging --files
```

Sebelum memulihkan, checksum `db.dump` diperiksa. Berkas yang rusak ditolak.

### Memulihkan produksi setelah insiden

1. `php artisan down` dan matikan Horizon serta cron, supaya tidak ada tulisan baru.
2. Pilih titik pulih. Utamakan point-in-time restore dari penyedia database, karena kehilangan datanya paling sedikit. Pakai `sanad:restore` hanya bila PITR tidak tersedia.
3. `php artisan sanad:restore <nama> --database=<db produksi> --force --files`. Ada konfirmasi sekali lagi.
4. `php artisan migrate --force`, lalu `php artisan up`, lalu hidupkan lagi Horizon dan cron.
5. Jalankan rekonsiliasi: halaman Rekonsiliasi pembayaran, lalu "Sinkron yang Menggantung" untuk mengambil status Midtrans yang terjadi selama sistem mati. Rekonsiliasi dompet jalan otomatis 01.00, atau jalankan lewat tinker bila perlu segera.
6. Umumkan ke bendahara rentang waktu yang hilang, supaya transaksi tunai di rentang itu dicatat ulang.

## 3. Pembayaran Midtrans

- Webhook masuk ke `/webhooks/midtrans`. URL ini didaftarkan di dashboard Midtrans dan harus HTTPS.
- Webhook yang terlewat tidak perlu dikirim ulang dari Midtrans. Penjadwal menanyakan status semua pembayaran tertunda tiap 15 menit. Untuk satu pembayaran tertentu, buka halaman Rekonsiliasi pembayaran lalu pilih "Sinkron" pada baris itu.
- Webhook ganda aman, karena pembayaran yang sudah lunas tidak diproses dua kali.
- Ganti server key di Pengaturan, lalu Integrasi. Kunci tersimpan terenkripsi dan tidak pernah tampil utuh.

## 4. WhatsApp (Fonnte)

- Pesan gagal terlihat di menu Notifikasi (bagian log kiriman) dan bisa dikirim ulang dengan tombol "Ulangi". Pekerjaan antrean yang gagal ada di Kesehatan Sistem, juga dengan tombol "Ulangi".
- Perangkat Fonnte terputus: pindai ulang QR di dashboard Fonnte. Pesan yang gagal selama terputus dikirim ulang dari log.
- Darurat (misalnya banjir pesan salah): set `SANAD_NOTIFICATIONS_OFF=true`, jalankan `php artisan config:cache`, lalu mulai ulang Horizon.

## 5. Rotasi kunci aplikasi (APP_KEY)

APP_KEY mengenkripsi rahasia QR kartu, data kesehatan, token layar, dan kunci integrasi.

1. Buat kunci baru: `php artisan key:generate --show`.
2. Isi `APP_PREVIOUS_KEYS` dengan kunci lama (pisahkan dengan koma bila lebih dari satu), lalu ganti `APP_KEY` dengan kunci baru.
3. `php artisan config:cache`, lalu mulai ulang PHP-FPM dan Horizon.
4. Data lama tetap terbaca lewat kunci lama. Kunci lama boleh dihapus dari `APP_PREVIOUS_KEYS` setelah semua data terenkripsi ditulis ulang. Sampai ada perintah enkripsi ulang, biarkan kunci lama tetap di sana.
5. Semua pengguna akan keluar dari sesinya. Lakukan di luar jam ujian dan jam kasir ramai.

Jangan pernah kehilangan APP_KEY. Tanpa kunci itu, kartu santri harus dicetak ulang dan data kesehatan tidak bisa dibaca. Simpan salinannya di pengelola rahasia, terpisah dari cadangan database.

## 6. Kartu, kasir, dan gerbang

- Kartu hilang: blokir dari aplikasi wali atau menu Kartu Santri, lalu terbitkan kartu baru. Kartu lama langsung ditolak di kasir dan gerbang.
- Reader USB tidak terbaca: pastikan kursor ada di kolom "Hasil pindai". Reader bekerja seperti keyboard.
- Saldo tidak cocok: rekonsiliasi dompet 01.00 mencatat selisih di log. Saldo hanya dibetulkan lewat penyesuaian bendahara, tidak pernah lewat database langsung (ledger tidak bisa diubah).

## 7. Layar TV masjid

- Layar menampilkan kode 6 angka: belum dipasangkan atau tokennya dicabut. Pasangkan ulang di menu Masjid, lalu Layar.
- Layar tetap jalan saat internet putus dengan jadwal tersimpan 60 hari. Tanda "Offline" muncul di pojok bawah.
- Jadwal geser: perbaiki di Jadwal Sholat (koreksi menit atau ubah per tanggal). TV mengambil perubahan dalam satu menit.

## 8. Uji beban

- Harian di mesin pengembang: `vendor/bin/pest --group=load`. Mengukur waktu proses server untuk 200 santri ujian dan 600 transaksi kasir, serta memastikan tidak ada jawaban hilang dan saldo konsisten.
- Beban serentak sungguhan, hanya di staging:
  ```bash
  php artisan sanad:load-prepare --students=200     # data BEBAN-*, sesi santri, perangkat kasir
  k6 run -e BASE=https://staging... -e DATA=storage/app/load.json scripts/load/exam.js
  k6 run -e BASE=https://staging... -e DATA=storage/app/load.json scripts/load/pos.js
  ```
  Target p95 di bawah 300 ms (PRD Sistem §11). Untuk headroom 5x, siapkan `--students=1000` lalu jalankan `-e VUS=1000`. Hapus `storage/app/load.json` setelah uji, karena berkas itu berisi kunci rahasia. Perintah ini menolak berjalan di production.

## 9. Keamanan

- Semua respons membawa CSP, X-Frame-Options, nosniff, dan Referrer-Policy. HSTS aktif otomatis lewat HTTPS. Pasang HTTPS di Cloudflare atau proxy, dan set `SESSION_SECURE_COOKIE=true`.
- Login dan OTP dibatasi percobaannya. 2FA tersedia untuk admin dan bendahara.
- Horizon hanya untuk pengguna dengan izin pengaturan.
