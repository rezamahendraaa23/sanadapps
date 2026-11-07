<?php

namespace App\Modules\Core\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Cadangan tingkat aplikasi: dump PostgreSQL format custom, arsip berkas unggahan, dan manifest
 * berisi jumlah baris per tabel untuk memeriksa hasil restore. Lapisan ini melengkapi, bukan
 * menggantikan, snapshot dan arsip WAL dari penyedia database (PRD Sistem §11).
 */
class Backup
{
    /** Folder berkas yang ikut dicadangkan, relatif terhadap storage/app. */
    private const FILE_ROOTS = ['private', 'public'];

    public function directory(): string
    {
        return config('sanad.backup.path') ?: storage_path('app/backups');
    }

    /**
     * @return array{name: string, path: string, manifest: array<string, mixed>}
     */
    public function create(): array
    {
        $name = now()->utc()->format('Ymd-His');
        $path = $this->directory().DIRECTORY_SEPARATOR.$name;
        if (! is_dir($path) && ! mkdir($path, 0700, true) && ! is_dir($path)) {
            throw new RuntimeException('Folder cadangan tidak bisa dibuat: '.$path);
        }

        $counts = $this->rowCounts();
        $this->run([$this->bin('pg_dump'), ...$this->connectionArgs(), '--format=custom', '--no-owner', '--no-privileges', '--file='.$path.'/db.dump', config('database.connections.pgsql.database')]);
        $files = $this->zipFiles($path.'/files.zip');

        $manifest = [
            'created_at' => now()->utc()->toIso8601String(),
            'database' => config('database.connections.pgsql.database'),
            'tables' => $counts,
            'files' => $files,
            'sha256' => ['db.dump' => hash_file('sha256', $path.'/db.dump'), 'files.zip' => hash_file('sha256', $path.'/files.zip')],
        ];
        file_put_contents($path.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->copyOffsite($name, $path);

        return ['name' => $name, 'path' => $path, 'manifest' => $manifest];
    }

    /**
     * @return list<string> nama cadangan, terbaru dulu
     */
    public function list(): array
    {
        $names = array_map('basename', glob($this->directory().'/*', GLOB_ONLYDIR) ?: []);
        $names = array_values(array_filter($names, fn (string $name) => is_file($this->directory().'/'.$name.'/manifest.json')));
        rsort($names);

        return $names;
    }

    /**
     * @return array<string, mixed>
     */
    public function manifest(string $name): array
    {
        $file = $this->directory().'/'.basename($name).'/manifest.json';
        if (! is_file($file)) {
            throw new RuntimeException('Cadangan tidak ditemukan: '.$name);
        }

        return json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Pulihkan dump ke database tujuan. Berkas hanya diekstrak bila diminta.
     */
    public function restore(string $name, string $database, bool $withFiles = false): void
    {
        $path = $this->directory().'/'.basename($name);
        $manifest = $this->manifest($name);
        if (hash_file('sha256', $path.'/db.dump') !== $manifest['sha256']['db.dump']) {
            throw new RuntimeException('Berkas db.dump rusak: checksum tidak cocok.');
        }

        $this->run([$this->bin('pg_restore'), ...$this->connectionArgs(), '--clean', '--if-exists', '--no-owner', '--no-privileges', '--single-transaction', '--dbname='.$database, $path.'/db.dump']);

        if ($withFiles) {
            $zip = new ZipArchive;
            if ($zip->open($path.'/files.zip') !== true) {
                throw new RuntimeException('files.zip tidak bisa dibuka.');
            }
            $zip->extractTo(storage_path('app'));
            $zip->close();
        }
    }

    /**
     * Uji restore: pulihkan cadangan ke database sementara, bandingkan jumlah baris, lalu hapus.
     *
     * @return array{database: string, tables: int, mismatches: array<string, array{expected: int, actual: int}>}
     */
    public function drill(string $name): array
    {
        $manifest = $this->manifest($name);
        $database = 'sanad_drill_'.now()->utc()->format('YmdHis');
        $this->run([$this->bin('createdb'), ...$this->connectionArgs(), $database]);

        try {
            $this->restore($name, $database);
            $actual = $this->rowCounts($database);
        } finally {
            $this->run([$this->bin('dropdb'), ...$this->connectionArgs(), '--if-exists', $database]);
        }

        $mismatches = [];
        foreach ($manifest['tables'] as $table => $expected) {
            if (($actual[$table] ?? -1) !== $expected) {
                $mismatches[$table] = ['expected' => $expected, 'actual' => $actual[$table] ?? -1];
            }
        }

        return ['database' => $database, 'tables' => count($manifest['tables']), 'mismatches' => $mismatches];
    }

    /**
     * Hapus cadangan lokal yang lebih tua dari jumlah hari tertentu; selalu sisakan yang terbaru.
     *
     * @return list<string>
     */
    public function prune(int $days): array
    {
        $removed = [];
        $cutoff = now()->utc()->subDays($days)->format('Ymd-His');
        foreach (array_slice($this->list(), 1) as $name) {
            if ($name < $cutoff) {
                $this->deleteDirectory($this->directory().'/'.$name);
                $removed[] = $name;
            }
        }

        return $removed;
    }

    /**
     * @return array<string, int>
     */
    private function rowCounts(?string $database = null): array
    {
        $connection = $database ? $this->temporaryConnection($database) : DB::connection('pgsql');
        $tables = $connection->select("select tablename from pg_tables where schemaname = 'public' order by tablename");
        $counts = [];
        foreach ($tables as $row) {
            $counts[$row->tablename] = (int) $connection->selectOne('select count(*) as n from "'.str_replace('"', '""', $row->tablename).'"')->n;
        }
        if ($database) {
            DB::purge('backup_drill');
        }

        return $counts;
    }

    private function temporaryConnection(string $database): Connection
    {
        config(['database.connections.backup_drill' => [...config('database.connections.pgsql'), 'database' => $database, 'options' => []]]);

        return DB::connection('backup_drill');
    }

    private function zipFiles(string $target): int
    {
        $zip = new ZipArchive;
        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('files.zip tidak bisa dibuat.');
        }
        $count = 0;
        $base = storage_path('app');
        foreach (self::FILE_ROOTS as $root) {
            if (! is_dir($base.'/'.$root)) {
                continue;
            }
            $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base.'/'.$root, \FilesystemIterator::SKIP_DOTS));
            foreach ($items as $file) {
                if ($file->isFile()) {
                    $zip->addFile($file->getPathname(), str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1)));
                    $count++;
                }
            }
        }
        if ($count === 0) {
            $zip->addFromString('.kosong', '');
        }
        $zip->close();

        return $count;
    }

    private function copyOffsite(string $name, string $path): void
    {
        $disk = config('sanad.backup.disk');
        if (! $disk) {
            return;
        }
        foreach (['db.dump', 'files.zip', 'manifest.json'] as $file) {
            $stream = fopen($path.'/'.$file, 'rb');
            Storage::disk($disk)->writeStream('sanad-backups/'.$name.'/'.$file, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function connectionArgs(): array
    {
        $db = config('database.connections.pgsql');

        return ['--host='.$db['host'], '--port='.$db['port'], '--username='.$db['username'], '--no-password'];
    }

    private function bin(string $tool): string
    {
        $dir = config('sanad.backup.pg_bin');

        return $dir ? rtrim($dir, '/\\').DIRECTORY_SEPARATOR.$tool : $tool;
    }

    /**
     * @param  list<string>  $command
     */
    private function run(array $command): void
    {
        $process = new Process($command, null, ['PGPASSWORD' => (string) config('database.connections.pgsql.password')], null, 1800);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException(basename($command[0]).' gagal: '.trim($process->getErrorOutput() ?: $process->getOutput()));
        }
    }

    private function deleteDirectory(string $dir): void
    {
        foreach (glob($dir.'/*') ?: [] as $file) {
            is_dir($file) ? $this->deleteDirectory($file) : unlink($file);
        }
        rmdir($dir);
    }
}
