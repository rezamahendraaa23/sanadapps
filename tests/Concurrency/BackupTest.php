<?php

use App\Modules\Core\Support\Backup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * UAT 11: cadangan bisa dipulihkan dan datanya kembali. Memakai database terpisah tanpa
 * RefreshDatabase, sebab pg_dump hanya melihat data yang benar-benar tersimpan.
 */
function backupTestEnv(): array
{
    return ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => getenv('BACKUP_TEST_DB') ?: 'sanad_backup_test', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'];
}

beforeEach(function () {
    $bin = config('sanad.backup.pg_bin');
    if (! ($bin ? is_file(rtrim($bin, '/\\').'/pg_dump'.(PHP_OS_FAMILY === 'Windows' ? '.exe' : '')) : (new ExecutableFinder)->find('pg_dump'))) {
        $this->markTestSkipped('pg_dump tidak tersedia.');
    }

    // Database sendiri: test konkurensi juga menjalankan migrate:fresh dan bisa berjalan bersamaan.
    $database = backupTestEnv()['DB_DATABASE'];
    if (! DB::connection('pgsql')->selectOne('select 1 as ok from pg_database where datname = ?', [$database])) {
        DB::connection('pgsql')->statement('CREATE DATABASE "'.$database.'"');
    }
    (new Process([(new PhpExecutableFinder)->find(), base_path('artisan'), 'migrate:fresh', '--force'], base_path(), backupTestEnv(), null, 120))->mustRun();
    config(['database.connections.pgsql.database' => backupTestEnv()['DB_DATABASE'], 'sanad.backup.path' => sys_get_temp_dir().'/sanad-backup-test-'.getmypid(), 'sanad.backup.disk' => null]);
    DB::purge('pgsql');
});

afterEach(function () {
    DB::purge('pgsql');
    $dir = config('sanad.backup.path');
    if ($dir && is_dir($dir)) {
        (new Process(PHP_OS_FAMILY === 'Windows' ? ['cmd', '/c', 'rmdir', '/s', '/q', $dir] : ['rm', '-rf', $dir]))->run();
    }
});

it('cadangan berisi manifest, lulus uji restore, dan restore mengembalikan data yang berubah', function () {
    DB::table('modules')->insert(['uuid' => (string) Str::uuid(), 'module_key' => 'uji_cadangan', 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);

    $backup = app(Backup::class);
    $result = $backup->create();

    expect($result['manifest']['tables']['modules'])->toBe(DB::table('modules')->count())
        ->and(is_file($result['path'].'/db.dump'))->toBeTrue()
        ->and(is_file($result['path'].'/files.zip'))->toBeTrue()
        ->and($backup->list())->toBe([$result['name']]);

    expect($backup->drill($result['name'])['mismatches'])->toBe([]);

    DB::table('modules')->where('module_key', 'uji_cadangan')->delete();
    DB::purge('pgsql');
    $backup->restore($result['name'], backupTestEnv()['DB_DATABASE']);
    DB::purge('pgsql');

    expect(DB::table('modules')->where('module_key', 'uji_cadangan')->exists())->toBeTrue();
});

it('restore menolak berkas yang rusak', function () {
    $backup = app(Backup::class);
    $result = $backup->create();
    file_put_contents($result['path'].'/db.dump', 'rusak', FILE_APPEND);

    expect(fn () => $backup->restore($result['name'], 'sanad_tidak_dipakai'))->toThrow(RuntimeException::class, 'checksum');
});

it('perintah restore menolak menimpa database aktif tanpa --force', function () {
    app(Backup::class)->create();

    $this->artisan('sanad:restore', ['--database' => backupTestEnv()['DB_DATABASE']])->assertFailed();
});
