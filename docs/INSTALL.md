# Cara Memasang SANAD

Pilih satu cara:

| Cara | Cocok untuk | Lama |
|---|---|---|
| **A. Skrip otomatis** | Server Ubuntu 22.04 atau 24.04 yang masih kosong (VPS) | 15 menit |
| **B. Docker** | Laptop atau server yang sudah ada Docker | 15 menit |

Sebelum mulai, siapkan **token GitHub** karena repo ini privat:
GitHub > foto profil > **Settings > Developer settings > Personal access tokens > Fine-grained tokens > Generate new token**. Pilih repo `sanadapps`, izin `Contents: Read-only`, lalu salin tokennya. Saat `git clone` meminta password, tempel token ini (bukan sandi GitHub).

---

## A. Skrip otomatis (server Ubuntu)

**1. Masuk ke server**

```bash
ssh root@IP-SERVER
```

Kalau penyedia memberi port SSH khusus: `ssh -p 20301 root@IP-SERVER`

**2. Ambil kode dan jalankan skrip**

```bash
apt update && apt install -y git
git clone https://github.com/rezamahendraaa23/sanadapps.git /var/www/sanad
cd /var/www/sanad
sudo bash scripts/install-ubuntu.sh
```

Skrip menanyakan tiga hal:

```
Nama pesantren: Pondok Pesantren Al-Mukhtar
Email admin pertama: admin@almukhtar.sch.id
Port web [80]:
```

Tekan Enter di pertanyaan port untuk memakai port 80. Kalau server memakai IPv4 bersama (NAT), isi port dari penyedia, misalnya `20302`.

**3. Catat hasilnya**

Di akhir muncul kotak seperti ini:

```
============================================================
 SANAD terpasang.
 Buka   : http://31.58.103.4
 Masuk  : tab Staf, email admin@almukhtar.sch.id
 Sandi awal: Xk29fLm0Qa7pZr4T   <- CATAT SEKARANG, hanya tampil sekali
============================================================
```

Buka alamat itu di browser, pilih tab **Staf**, lalu masuk. Selesai.

Skrip ini juga sudah memasang swap (bila RAM kecil), Nginx, antrean, dan penjadwal. Kalau terhenti di tengah, jalankan ulang perintah yang sama; data yang sudah ada tidak hilang.

---

## B. Docker

**1. Pasang Docker**

- Laptop Windows atau Mac: pasang [Docker Desktop](https://www.docker.com/products/docker-desktop/), lalu buka aplikasinya.
- Server Ubuntu: `curl -fsSL https://get.docker.com | sh`

**2. Ambil kode dan isi pengaturan**

```bash
git clone https://github.com/rezamahendraaa23/sanadapps.git sanad
cd sanad
cp .env.example .env
```

Buka `.env`, isi dua baris ini:

```env
SANAD_SCHOOL_NAME="Pondok Pesantren Al-Mukhtar"
SANAD_ADMIN_EMAIL=admin@almukhtar.sch.id
```

**3. Nyalakan**

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app npm install
docker compose exec app npm run build
docker compose exec app php artisan migrate --seed
```

Perintah terakhir menampilkan:

```
Admin admin@almukhtar.sch.id dibuat. Sandi awal: Xk29fLm0Qa7pZr4T
```

**Catat sandinya**, hanya tampil sekali.

**4. Buka**

- Aplikasi: http://localhost:8080 (pilih tab **Staf**)
- Kotak surat uji: http://localhost:8025

Mematikan: `docker compose down`. Menyalakan lagi: `docker compose up -d`.

---

## Setelah masuk pertama kali

1. Ganti sandi admin lewat menu akun di pojok kanan atas.
2. Ikuti daftar **Setup awal pesantren** di Dashboard: profil dan logo, modul, tahun ajaran, kelas dan asrama, impor santri dan wali, jenis tagihan.
3. Layar TV masjid: buka `http://alamat-server/display` di TV, lalu masukkan kode 6 angka di **Masjid & Layar TV > Layar TV > Pasang Layar**.

## Memperbarui ke versi terbaru

Server dengan skrip (cara A):

```bash
cd /var/www/sanad && git pull && sudo bash scripts/install-ubuntu.sh
```

Docker (cara B):

```bash
git pull
docker compose exec app composer install
docker compose exec app npm run build
docker compose exec app php artisan migrate
```

## Kalau ada masalah

| Gejala | Coba |
|---|---|
| Halaman tidak terbuka | Pastikan port yang dipakai memang dibuka penyedia server |
| Galat 500 | Lihat `storage/logs/laravel.log` |
| Tampilan tanpa warna | Jalankan ulang `npm run build` |
| PDF gagal diunduh | Chrome belum terpasang; jalankan ulang skrip |
| Skrip berhenti dengan tulisan GAGAL | Baca pesannya; rincian ada di `/var/log/sanad-install.log` |

Panduan operasional (cadangan, pemulihan, keamanan) ada di [RUNBOOK.md](RUNBOOK.md).
