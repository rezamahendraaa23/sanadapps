import { execFileSync } from 'node:child_process';
import { existsSync, rmSync } from 'node:fs';

export const LOG_FILE = 'storage/logs/e2e.log';

export default function globalSetup() {
    const php = process.env.PHP_BIN || 'php';
    const env = {
        ...process.env,
        DB_DATABASE: process.env.E2E_DB || 'sanad_e2e',
        CACHE_STORE: 'array',
        LOG_CHANNEL: 'single',
    };
    const artisan = (...args) => execFileSync(php, ['artisan', ...args], { env, stdio: 'pipe' });

    if (existsSync(LOG_FILE)) rmSync(LOG_FILE);

    artisan('migrate:fresh', '--force');
    artisan('db:seed', '--class=Database\\Seeders\\E2eSeeder', '--force');
}
