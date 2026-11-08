# SANAD

Sistem informasi untuk satu pesantren: keuangan, dompet dan kartu santri, akademik, ujian online, perizinan, pos gerbang, dan PPDB. Dibangun dengan Laravel 13, Livewire 3, dan Tailwind 4.

## Memasang

Panduan lengkap langkah demi langkah, dengan Docker atau manual, ada di [docs/INSTALL.md](docs/INSTALL.md).

Ringkasnya, dengan Docker:

```bash
cp .env.example .env        # isi SANAD_SCHOOL_NAME dan SANAD_ADMIN_EMAIL
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app npm install
docker compose exec app npm run build
docker compose exec app php artisan migrate --seed   # catat sandi admin yang tampil
```

Aplikasi terbuka di http://localhost:8080 dan Mailpit di http://localhost:8025.

Halaman `/_pratinjau` menampilkan komponen dan layout dengan data contoh. Halaman ini hanya ada di lingkungan `local` dan `testing`.

## Test

```bash
vendor/bin/pest
npx playwright test
```

Keputusan dan asumsi proyek ada di [docs/ASSUMPTIONS.md](docs/ASSUMPTIONS.md). Panduan operasional server ada di [docs/RUNBOOK.md](docs/RUNBOOK.md).
