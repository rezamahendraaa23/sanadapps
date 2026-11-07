<?php

namespace App\Console\Commands;

use App\Modules\Core\Support\Backup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class BackupDrill extends Command
{
    protected $signature = 'sanad:backup-drill {name? : Nama cadangan, kosong berarti terbaru}';

    protected $description = 'Uji restore ke database sementara lalu cocokkan jumlah baris';

    public function handle(Backup $backup): int
    {
        $name = $this->argument('name') ?: ($backup->list()[0] ?? null);
        if (! $name) {
            $this->error('Belum ada cadangan.');

            return self::FAILURE;
        }

        try {
            $result = $backup->drill($name);
        } catch (Throwable $e) {
            Log::error('Uji restore gagal', ['backup' => $name, 'error' => $e->getMessage()]);
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($result['mismatches'] !== []) {
            Log::error('Uji restore: jumlah baris berbeda', ['backup' => $name, 'mismatches' => $result['mismatches']]);
            foreach ($result['mismatches'] as $table => $row) {
                $this->error($table.': cadangan '.$row['expected'].', hasil restore '.$row['actual']);
            }

            return self::FAILURE;
        }

        $this->info('Uji restore '.$name.' lulus: '.$result['tables'].' tabel cocok.');

        return self::SUCCESS;
    }
}
