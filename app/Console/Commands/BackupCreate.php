<?php

namespace App\Console\Commands;

use App\Modules\Core\Support\Backup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class BackupCreate extends Command
{
    protected $signature = 'sanad:backup {--prune : Hapus cadangan lokal yang lebih tua dari SANAD_BACKUP_KEEP_DAYS}';

    protected $description = 'Buat cadangan database dan berkas unggahan';

    public function handle(Backup $backup): int
    {
        try {
            $result = $backup->create();
        } catch (Throwable $e) {
            Log::error('Cadangan gagal', ['error' => $e->getMessage()]);
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Cadangan '.$result['name'].' dibuat: '.count($result['manifest']['tables']).' tabel, '.$result['manifest']['files'].' berkas.');
        if ($this->option('prune')) {
            $removed = $backup->prune((int) config('sanad.backup.keep_days'));
            $this->line(count($removed).' cadangan lama dihapus.');
        }

        return self::SUCCESS;
    }
}
