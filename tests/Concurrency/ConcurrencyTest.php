<?php

use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Uji proses sungguhan yang berebut baris yang sama. Berjalan di database terpisah, sebab data
 * harus benar-benar tersimpan agar terlihat oleh proses lain, dan tidak memakai RefreshDatabase.
 */
function concurrencyEnv(): array
{
    return [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'pgsql',
        'DB_DATABASE' => getenv('CONCURRENCY_DB') ?: 'sanad_concurrency',
        'CACHE_STORE' => 'array',
        'SESSION_DRIVER' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'SANAD_NOTIFICATIONS_OFF' => '1',
        'BCRYPT_ROUNDS' => '4',
    ];
}

function runWorker(string ...$arguments): string
{
    $process = new Process([(new PhpExecutableFinder)->find(), base_path('tests/Concurrency/worker.php'), ...$arguments], base_path(), concurrencyEnv(), null, 60);
    $process->mustRun();

    return trim($process->getOutput());
}

/**
 * @return list<string> Keluaran tiap pekerja.
 */
function race(string $mode, string $argument, int $workers = 6): array
{
    $startAt = (string) (microtime(true) + 3);
    $processes = [];

    foreach (range(1, $workers) as $number) {
        $process = new Process([(new PhpExecutableFinder)->find(), base_path('tests/Concurrency/worker.php'), $mode, $startAt, $argument], base_path(), concurrencyEnv(), null, 60);
        $process->start();
        $processes[] = $process;
    }

    return array_map(function (Process $process): string {
        $process->wait();
        expect($process->getExitCode())->toBe(0, $process->getErrorOutput());

        return trim($process->getOutput());
    }, $processes);
}

beforeEach(function () {
    $process = new Process([(new PhpExecutableFinder)->find(), base_path('artisan'), 'migrate:fresh', '--force'], base_path(), concurrencyEnv(), null, 120);
    $process->mustRun();

    $this->ids = json_decode(runWorker('seed'), true);
});

it('verifikasi bersamaan hanya melunasi sekali', function () {
    $results = race('settle', (string) $this->ids['payment']);

    expect(array_count_values($results)['applied'])->toBe(1)
        ->and(array_count_values($results)['skipped'])->toBe(5);

    $report = json_decode(runWorker('report', '0', (string) $this->ids['payment']), true);
    expect((int) $report['paid_total'])->toBe(100000)->and($report['cash_count'])->toBe(1)->and($report['status'])->toBe('settlement');
});

it('webhook kembar bersamaan hanya melunasi sekali', function () {
    $results = race('webhook', (string) $this->ids['payment']);

    expect(array_unique($results))->toBe(['200']);

    $report = json_decode(runWorker('report', '0', (string) $this->ids['payment']), true);
    expect((int) $report['paid_total'])->toBe(100000)->and($report['cash_count'])->toBe(1);
});

it('catat tunai bersamaan untuk tagihan yang sama hanya diterima sekali', function () {
    $results = race('cash', (string) $this->ids['cash_invoice']);

    expect(array_count_values($results)['paid'])->toBe(1)
        ->and(array_count_values($results)['rejected'])->toBe(5);

    $report = json_decode(runWorker('report', '0', (string) $this->ids['payment']), true);
    expect($report['cash_payments'])->toBe(1)->and((int) $report['cash_invoice_paid'])->toBe(50000);
});

it('generate bersamaan tidak menduplikasi dan nomor invoice tetap unik', function () {
    $results = race('generate', '0');

    expect(array_sum(array_map('intval', $results)))->toBe(1);

    $report = json_decode(runWorker('report', '0', (string) $this->ids['payment']), true);
    expect($report['generated_items'])->toBe(1)->and($report['generated_invoices'])->toBe(1)->and($report['invoice_numbers_unique'])->toBeTrue();
});

it('belanja bersamaan: saldo cukup untuk 3, enam proses, tepat 3 berhasil dan saldo tidak pernah minus', function () {
    $results = race('spend', (string) $this->ids['merchant']);

    expect(array_count_values($results)['paid'])->toBe(3)
        ->and(array_count_values($results)['insufficient'])->toBe(3);

    $report = json_decode(runWorker('report', '0', (string) $this->ids['payment']), true);
    expect((int) $report['spender_balance'])->toBe(0)->and($report['spender_orders'])->toBe(3)->and($report['ledger_differences'])->toBe(0);
});

it('tap ganda bersamaan dengan kunci yang sama hanya memotong sekali dan semua menerima hasil yang sama', function () {
    $results = race('tap', (string) $this->ids['merchant']);

    expect(array_unique($results))->toBe(['paid:80000']);

    $report = json_decode(runWorker('report', '0', (string) $this->ids['payment']), true);
    expect((int) $report['tap_balance'])->toBe(80000)->and($report['tap_orders'])->toBe(1)
        ->and((int) $report['merchant_balance'])->toBe(10000)->and($report['ledger_differences'])->toBe(0);
});
