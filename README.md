# SANAD

Sistem informasi untuk satu pesantren: keuangan, dompet dan kartu santri, akademik, ujian online, perizinan, pos gerbang, dan PPDB. Dibangun dengan Laravel 13, Livewire 3, dan Tailwind 4.

## Menjalankan dengan Docker

Butuh Docker Desktop.

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app npm install
docker compose exec app npm run build
```

Aplikasi terbuka di http://localhost:8080 dan Mailpit di http://localhost:8025.

## Menjalankan tanpa Docker

Butuh PHP 8.3 atau lebih baru dan Node 22 atau lebih baru.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
npm run build
php artisan serve --port=8010
```

Halaman `/_pratinjau` menampilkan komponen dan layout dengan data contoh. Halaman ini hanya ada di lingkungan `local` dan `testing`.

## Test

```bash
vendor/bin/pest
npx playwright test
```

Keputusan dan asumsi proyek ada di [docs/ASSUMPTIONS.md](docs/ASSUMPTIONS.md).
