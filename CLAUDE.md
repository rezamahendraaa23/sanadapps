# SANAD

Sistem informasi satu pesantren. Sumber kebenaran: `PRD-Sistem-Pesantren.md`, `PRD-UIUX-Pesantren.md`, `MASTER-PROMPT-Claude-Code.md`. Keputusan dan asumsi ada di `docs/ASSUMPTIONS.md`.

## Perintah di Windows

XAMPP masih PHP 8.2, jadi jangan pakai `php` bawaan. PHP 8.3 portabel ada di `%LOCALAPPDATA%\sanad-tools\php83\php.exe`.

- Test: `<php83> vendor/pestphp/pest/bin/pest`
- Format: `<php83> vendor/laravel/pint/builds/pint`
- E2E: set `PHP_BIN` ke path php83, lalu `npx playwright test`
- Server: `<php83> artisan serve --port=8010`
- Aset: `npm run build`
- Database: PostgreSQL 16 portabel, port 5433. Data di `%LOCALAPPDATA%\sanad-tools\pgdata`. Nyalakan dengan `pgsql\bin\pg_ctl -D pgdata -l pg.log start` dari folder itu. PostgreSQL 18 di port 5432 bukan milik proyek ini.

## Aturan tampilan

- Warna hanya lewat token di `resources/css/tokens.css`. Test menolak hex mentah di view.
- Komponen ada di `resources/views/components/ui`. Jangan membuat gaya satu kali per layar.
- Identitas pesantren selalu lewat `App\Support\SchoolIdentity`, tidak pernah ditulis di kode.
- Antarmuka berbahasa Indonesia, tanpa em dash, tanpa emoji.
