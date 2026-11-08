# Cara Memasang SANAD

Panduan singkat untuk memasang SANAD di laptop (untuk mencoba) atau di server (untuk dipakai pesantren). Pilih salah satu jalur:

- **Jalur A, Docker:** paling mudah. Semua kebutuhan (PHP, PostgreSQL, Redis, Chromium untuk PDF) sudah disiapkan.
- **Jalur B, manual:** bila tidak bisa memakai Docker.

Setelah terpasang, lanjut ke bagian **Setelah terpasang** di bawah.

Untuk VPS kecil (RAM 1 GB, IPv4 bersama dengan port khusus), ikuti [INSTALL-VPS.md](INSTALL-VPS.md).

---

## Jalur A: Docker

### 1. Siapkan

Pasang [Docker Desktop](https://www.docker.com/products/docker-desktop/) (Windows atau Mac) atau Docker Engine (Linux), lalu ambil kodenya:

```bash
git clone https://github.com/rezamahendraaa23/sanadapps.git
cd sanadapps
cp .env.example .env
```

### 2. Isi `.env`

Buka `.env`, lalu ubah minimal baris ini:

```env
SANAD_SCHOOL_NAME="Nama Pesantren Anda"
SANAD_ADMIN_EMAIL=admin@pesantren-anda.sch.id
```

`SANAD_ADMIN_EMAIL` adalah email akun admin pertama. Sandinya dibuat otomatis di langkah 4.

### 3. Nyalakan

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app npm install
docker compose exec app npm run build
```

### 4. Siapkan database dan akun admin

```bash
docker compose exec app php artisan migrate --seed
```

Di akhir perintah ini muncul baris seperti:

```
Admin admin@pesantren-anda.sch.id dibuat. Sandi awal: xxxxxxxx
```

**Catat sandinya sekarang.** Sandi ini hanya tampil sekali. Setelah masuk pertama kali, segera ganti lewat menu akun di pojok kanan atas.

### 5. Buka

- Aplikasi: http://localhost:8080
- Kotak surat uji (email keluar tertangkap di sini): http://localhost:8025

Pilih tab **Staf** di halaman masuk, lalu pakai email admin dan sandi tadi.

---

## Jalur B: Manual

### 1. Pasang kebutuhan

| Kebutuhan | Versi | Catatan |
|---|---|---|
| PHP | 8.3 atau lebih baru | Ekstensi: `pdo_pgsql`, `pgsql`, `mbstring`, `intl`, `gd`, `zip`, `bcmath`, `redis` |
| Composer | 2.x | |
| Node.js | 22 atau lebih baru | Untuk membangun tampilan |
| PostgreSQL | 16 | Database utama |
| Redis | 7 | Antrean pesan (WA, notifikasi). Untuk mencoba di laptop boleh dilewati, lihat catatan di langkah 3 |
| Chromium atau Google Chrome | terbaru | Untuk membuat PDF (kwitansi, rapor, syahadah, jadwal sholat) |

### 2. Buat database

Masuk ke PostgreSQL, lalu:

```sql
CREATE USER sanad WITH PASSWORD 'ganti-sandi-ini';
CREATE DATABASE sanad OWNER sanad;
```

### 3. Ambil kode dan isi `.env`

```bash
git clone https://github.com/rezamahendraaa23/sanadapps.git
cd sanadapps
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Ubah baris berikut di `.env`:

```env
APP_URL=http://localhost:8010

DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=sanad
DB_USERNAME=sanad
DB_PASSWORD=ganti-sandi-ini

SANAD_SCHOOL_NAME="Nama Pesantren Anda"
SANAD_ADMIN_EMAIL=admin@pesantren-anda.sch.id

# Lokasi Chrome atau Chromium. Contoh Windows:
# BROWSERSHOT_CHROME_PATH="C:\Program Files\Google\Chrome\Application\chrome.exe"
BROWSERSHOT_CHROME_PATH=/usr/bin/chromium
```

Kalau tidak memakai Redis (hanya untuk mencoba), ganti juga:

```env
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
```

### 4. Siapkan database, tampilan, dan akun admin

```bash
php artisan migrate --seed
npm run build
```

Catat sandi admin yang tampil di akhir `migrate --seed`. Sandi ini hanya tampil sekali.

### 5. Jalankan

```bash
php artisan serve --port=8010
```

Buka http://localhost:8010, pilih tab **Staf**, lalu masuk dengan akun admin.

---

## Setelah terpasang

### Isi data awal

Dashboard menampilkan daftar **Setup awal pesantren**. Ikuti urutannya:

1. Isi profil dan logo pesantren
2. Pilih modul yang dipakai (matikan yang tidak perlu)
3. Atur integrasi (Midtrans, WhatsApp Fonnte), bisa menyusul
4. Buat tahun ajaran aktif
5. Buat kelas dan asrama
6. Impor santri dan wali dari Excel
7. Atur jenis tagihan dan tarif

### Proses yang harus selalu hidup di server

Untuk dipakai sungguhan, dua proses ini wajib jalan terus. Tanpa keduanya, tagihan bulanan, pengingat WA, dan auto-submit ujian tidak berjalan.

```bash
# Antrean pesan (WA, notifikasi, PDF)
php artisan horizon

# Penjadwal: pasang di cron, berjalan tiap menit
* * * * * cd /path/ke/sanadapps && php artisan schedule:run >> /dev/null 2>&1
```

Dengan Docker, jalankan perintah yang sama lewat `docker compose exec app ...`.

### Layar TV masjid

1. Di TV atau laptop yang menempel ke TV, buka `http://alamat-server/display`. Muncul kode 6 angka.
2. Di panel admin: **Ruang kerja Masjid & Layar TV > Layar TV > Pasang Layar**, masukkan kode itu.

### Setelah memperbarui kode

Setiap kali menarik versi baru:

```bash
git pull
composer install
npm install
npm run build
php artisan migrate
php artisan db:seed --class=RoleSeeder
```

Lalu nyalakan ulang server dan Horizon. Layar TV cukup di-refresh sekali.

---

## Kalau ada masalah

| Gejala | Penyebab umum | Solusi |
|---|---|---|
| Halaman putih atau galat 500 | `.env` belum lengkap atau `APP_KEY` kosong | Jalankan `php artisan key:generate`, cek `storage/logs/laravel.log` |
| Tampilan berantakan tanpa warna | Aset belum dibangun | `npm run build` |
| Unduh PDF gagal | Chrome atau Chromium tidak ditemukan | Isi `BROWSERSHOT_CHROME_PATH` dengan lokasi yang benar |
| Sandi admin terlewat | Hanya tampil sekali | Kosongkan tabel `users`, lalu jalankan `php artisan db:seed` lagi |
| WA atau notifikasi tidak terkirim | Horizon tidak jalan | Nyalakan `php artisan horizon` |
| Tagihan bulanan tidak muncul | Penjadwal tidak jalan | Pasang cron `schedule:run` |

Panduan operasional lebih lengkap (cadangan, pemulihan, rotasi kunci, keamanan) ada di [RUNBOOK.md](RUNBOOK.md).
