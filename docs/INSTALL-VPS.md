# Memasang SANAD di VPS Kecil (Uji Coba)

Panduan untuk VPS **Ubuntu 22.04, 1 vCPU, RAM 1 GB, disk 10 GB**, dengan IPv4 bersama (NAT): SSH lewat port khusus, dan layanan web memakai salah satu port publik yang dialokasikan. Cocok untuk uji coba dan belajar, **belum** untuk dipakai satu pesantren sungguhan.

Dalam panduan ini:

- `IP-PUBLIK` adalah alamat IPv4 publik VPS (contoh `31.58.103.4`)
- `PORT-SSH` adalah port SSH dari penyedia (contoh `20301`)
- `PORT-WEB` adalah salah satu port publik yang boleh dipakai layanan (contoh `20302`)

Semua perintah dijalankan sebagai `root` di VPS.

---

## Kenapa tanpa Docker

RAM 1 GB dan disk 10 GB terlalu sempit untuk menjalankan semua kontainer (PHP, PostgreSQL, Redis, MinIO, Mailpit, Chromium). Pasang langsung lebih hemat, dan beberapa bagian dibuat ringan:

- Antrean memakai database, bukan Redis, jadi Horizon tidak perlu jalan
- Hanya 2 pekerja PHP
- Ditambah swap 2 GB supaya proses build tidak kehabisan RAM

---

## 1. Masuk ke VPS

Dari laptop (PowerShell atau terminal):

```bash
ssh -p PORT-SSH root@IP-PUBLIK
```

## 2. Tambah swap 2 GB

Wajib di RAM 1 GB. Tanpa swap, `composer install` dan `npm run build` bisa berhenti di tengah jalan.

```bash
fallocate -l 2G /swapfile
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab
free -h
```

Baris `Swap:` harus menunjukkan `2.0Gi`.

## 3. Pasang paket

```bash
apt update && apt upgrade -y
apt install -y software-properties-common curl git unzip nginx

# PHP 8.3 (Ubuntu 22.04 bawaannya PHP 8.1, jadi pakai repo ondrej)
add-apt-repository -y ppa:ondrej/php
apt install -y php8.3-fpm php8.3-cli php8.3-pgsql php8.3-mbstring php8.3-intl \
  php8.3-gd php8.3-zip php8.3-bcmath php8.3-xml php8.3-curl

# PostgreSQL 16
install -d /usr/share/postgresql-common/pgdg
curl -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc https://www.postgresql.org/media/keys/ACCC4CF8.asc
echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt jammy-pgdg main" > /etc/apt/sources.list.d/pgdg.list
apt update && apt install -y postgresql-16

# Node.js 22
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt install -y nodejs

# Google Chrome, untuk membuat PDF
curl -fsSLo /tmp/chrome.deb https://dl.google.com/linux/direct/google-chrome-stable_current_amd64.deb
apt install -y /tmp/chrome.deb fonts-noto-core

# Composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
```

Cek versi: `php -v` harus 8.3, `node -v` harus 22, `google-chrome --version` harus muncul.

## 4. Buat database

Ganti `SANDI-DB` dengan sandi buatan sendiri.

```bash
sudo -u postgres psql -c "CREATE USER sanad WITH PASSWORD 'SANDI-DB';"
sudo -u postgres psql -c "CREATE DATABASE sanad OWNER sanad;"
```

## 5. Ambil kode

Repo bersifat privat, jadi GitHub meminta login. Buat token dulu:

1. Buka github.com > foto profil > **Settings > Developer settings > Personal access tokens > Fine-grained tokens > Generate new token**
2. **Repository access:** pilih hanya `sanadapps`. **Permissions:** `Contents: Read-only`
3. Salin tokennya (hanya tampil sekali)

Lalu di VPS:

```bash
cd /var/www
git clone https://github.com/rezamahendraaa23/sanadapps.git sanad
```

Saat diminta: **Username** isi nama akun GitHub, **Password** tempel token tadi (bukan sandi GitHub).

## 6. Pasang dan atur aplikasi

```bash
cd /var/www/sanad
composer install --no-dev --optimize-autoloader
PUPPETEER_SKIP_DOWNLOAD=1 npm ci
npm run build
cp .env.example .env
php artisan key:generate
```

`PUPPETEER_SKIP_DOWNLOAD=1` mencegah unduhan Chrome kedua (sekitar 200 MB), karena Chrome sudah dipasang di langkah 3.

Buka `.env` dengan `nano .env`, lalu ubah baris-baris ini:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=http://IP-PUBLIK:PORT-WEB

DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=sanad
DB_USERNAME=sanad
DB_PASSWORD=SANDI-DB

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database

BROWSERSHOT_CHROME_PATH=/usr/bin/google-chrome

SANAD_SCHOOL_NAME="Nama Pesantren"
SANAD_ADMIN_EMAIL=email-anda@contoh.com
```

Simpan dengan `Ctrl+O`, Enter, lalu keluar dengan `Ctrl+X`.

Siapkan database, akun admin, dan izin folder:

```bash
php artisan migrate --seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache
```

**Catat sandi admin** yang muncul di akhir `migrate --seed`. Sandi ini hanya tampil sekali.

## 7. Atur Nginx di PORT-WEB

Buat berkas `/etc/nginx/sites-available/sanad`:

```nginx
server {
    listen PORT-WEB;
    server_name _;
    root /var/www/sanad/public;
    index index.php;
    client_max_body_size 25M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }
}
```

Lalu aktifkan:

```bash
ln -s /etc/nginx/sites-available/sanad /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
```

Hemat RAM: batasi pekerja PHP. Di `/etc/php/8.3/fpm/pool.d/www.conf`, ubah:

```ini
pm = ondemand
pm.max_children = 2
```

Lalu `systemctl restart php8.3-fpm`.

Buka di browser: `http://IP-PUBLIK:PORT-WEB`, pilih tab **Staf**, dan masuk dengan akun admin.

## 8. Proses latar: antrean dan penjadwal

**Penjadwal** (tagihan bulanan, auto-submit ujian, dan lainnya). Jalankan `crontab -u www-data -e`, lalu tambahkan:

```cron
* * * * * cd /var/www/sanad && php artisan schedule:run >> /dev/null 2>&1
```

**Antrean** (WA, notifikasi, PDF). Buat berkas `/etc/systemd/system/sanad-queue.service`:

```ini
[Unit]
Description=SANAD queue worker
After=network.target postgresql.service

[Service]
User=www-data
WorkingDirectory=/var/www/sanad
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always

[Install]
WantedBy=multi-user.target
```

Lalu:

```bash
systemctl daemon-reload
systemctl enable --now sanad-queue
systemctl status sanad-queue
```

Status harus `active (running)`.

---

## Memperbarui ke versi terbaru

```bash
cd /var/www/sanad
git pull
composer install --no-dev --optimize-autoloader
PUPPETEER_SKIP_DOWNLOAD=1 npm ci
npm run build
php artisan migrate --force
php artisan db:seed --class=RoleSeeder --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache
systemctl restart php8.3-fpm sanad-queue
```

---

## Batasan VPS ini

| Hal | Dampak | Jalan keluar |
|---|---|---|
| Tanpa HTTPS (hanya `http://IP:port`) | Browser memblokir kamera, jadi scan QR, absen selfie, dan absen wajah tidak jalan. Tombol pasang aplikasi (PWA) di layar TV juga tidak muncul | Pakai Cloudflare Tunnel: gratis, memberi alamat `https://` tanpa perlu membuka port 443 |
| RAM 1 GB | Buat PDF dan build aset terasa lambat; bisa lambat bila dibuka banyak orang bersamaan | Cukup untuk uji coba beberapa orang. Untuk pesantren sungguhan minimal 2 vCPU dan RAM 4 GB |
| Disk 10 GB | Foto, berkas unggahan, dan cadangan cepat memenuhi disk | Pantau dengan `df -h`. Hapus cadangan lama |
| Email | Belum ada server email | Isi pengaturan `MAIL_*` di `.env` dengan SMTP (misalnya Gmail SMTP) bila perlu |

## Kalau ada masalah

| Gejala | Cek |
|---|---|
| Halaman tidak terbuka sama sekali | `systemctl status nginx`, dan pastikan port yang dipakai memang `PORT-WEB` dari penyedia |
| 502 Bad Gateway | `systemctl status php8.3-fpm` |
| Galat 500 | `tail -50 /var/www/sanad/storage/logs/laravel.log` |
| Tampilan tanpa warna | `npm run build` belum jalan atau gagal; ulangi dan lihat pesannya |
| PDF gagal diunduh | `google-chrome --version` dan isi `BROWSERSHOT_CHROME_PATH` |
| Build berhenti dengan pesan `Killed` | RAM habis; pastikan swap aktif (`free -h`) |
| Notifikasi tidak terkirim | `systemctl status sanad-queue` |
