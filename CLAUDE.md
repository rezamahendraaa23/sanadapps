# SANAD

Sistem informasi satu pesantren. Sumber kebenaran: `PRD-Sistem-Pesantren.md`, `PRD-UIUX-Pesantren.md`, `PRD-Masjid-Display.md`, `MASTER-PROMPT-Claude-Code .md` (nama berkasnya memuat spasi). Keputusan dan asumsi ada di `docs/ASSUMPTIONS.md`.

## Perintah di Windows

XAMPP masih PHP 8.2, jadi jangan pakai `php` bawaan. PHP 8.3 portabel ada di `%LOCALAPPDATA%\sanad-tools\php83\php.exe`.

- Test: `<php83> vendor/pestphp/pest/bin/pest`
- Format: `<php83> vendor/laravel/pint/builds/pint`
- E2E: set `PHP_BIN` ke path php83, lalu `npx playwright test`
- Server: `<php83> artisan serve --port=8010`
- Aset: `npm run build`
- Setelah menarik perubahan: `<php83> artisan migrate` dan `<php83> artisan db:seed --class=RoleSeeder`, lalu nyalakan ulang server.
- Database: PostgreSQL 16 portabel, port 5433. Data di `%LOCALAPPDATA%\sanad-tools\pgdata`. Nyalakan dengan `pgsql\bin\pg_ctl -D pgdata -l pg.log start` dari folder itu. PostgreSQL 18 di port 5432 bukan milik proyek ini.

## Kecepatan di Windows

PHP 8.3 portabel memakai `opcache.file_cache` di `%LOCALAPPDATA%\sanad-tools\opcache` dan `opcache.revalidate_freq=2`, serta `DB_PERSISTENT=true` di `.env`. Kunjungan pertama setelah view berubah tetap lambat karena berkas baru dipindai Windows Defender (terukur sekitar 350 ms per berkas). Jangan menjalankan `view:clear` tanpa perlu.

## Aturan tampilan

- Warna hanya lewat token di `resources/css/tokens.css`. Test menolak hex mentah di view.
- Komponen ada di `resources/views/components/ui`. Jangan membuat gaya satu kali per layar.
- Identitas pesantren selalu lewat `App\Support\SchoolIdentity`, tidak pernah ditulis di kode.
- Perintah mematikan proses: pastikan dulu PID dan namanya, jangan pakai `/T`. `netstat` di Windows bisa melaporkan PID 0.
- Antarmuka berbahasa Indonesia, tanpa em dash, tanpa emoji.
