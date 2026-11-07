<?php

namespace App\Console\Commands;

use App\Modules\Core\Support\Backup;
use Illuminate\Console\Command;
use Throwable;

class BackupRestore extends Command
{
    protected $signature = 'sanad:restore {name? : Nama cadangan, kosong berarti terbaru} {--database= : Database tujuan, mis. sanad_staging} {--files : Ekstrak juga berkas unggahan} {--force : Izinkan menimpa database yang sedang dipakai}';

    protected $description = 'Pulihkan cadangan ke database tujuan';

    public function handle(Backup $backup): int
    {
        $name = $this->argument('name') ?: ($backup->list()[0] ?? null);
        $database = $this->option('database');
        if (! $name || ! $database) {
            $this->error($name ? 'Isi --database tujuan.' : 'Belum ada cadangan.');

            return self::FAILURE;
        }

        $live = config('database.connections.pgsql.database');
        if ($database === $live && ! $this->option('force')) {
            $this->error('Tujuan sama dengan database yang sedang dipakai ('.$live.'). Pakai --force bila memang ingin menimpa.');

            return self::FAILURE;
        }
        if ($database === $live && ! $this->confirm('Semua data di '.$live.' akan diganti dengan cadangan '.$name.'. Lanjutkan?')) {
            return self::FAILURE;
        }

        try {
            $backup->restore($name, $database, (bool) $this->option('files'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Cadangan '.$name.' dipulihkan ke '.$database.'.');

        return self::SUCCESS;
    }
}
