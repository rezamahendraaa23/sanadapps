import { defineConfig } from '@playwright/test';
import path from 'node:path';

const php = process.env.PHP_BIN || 'php';
export const E2E_CACHE = path.resolve('storage/framework/cache/e2e');
const port = process.env.E2E_PORT || 8010;

export default defineConfig({
    testDir: './e2e',
    globalSetup: './e2e/global-setup.js',
    timeout: 120_000,
    expect: { timeout: 10_000 },
    fullyParallel: false,
    workers: 1,
    reporter: 'list',
    use: {
        baseURL: `http://127.0.0.1:${port}`,
        channel: process.env.CI ? undefined : 'chrome',
        launchOptions: { slowMo: 150 },
    },
    webServer: {
        command: `"${php}" artisan serve --host=127.0.0.1 --port=${port}`,
        url: `http://127.0.0.1:${port}/up`,
        reuseExistingServer: false,
        timeout: 30_000,
        env: {
            DB_DATABASE: process.env.E2E_DB || 'sanad_e2e',
            LOG_CHANNEL: 'e2e',
            CACHE_STORE: 'file',
            CACHE_FILE_PATH: E2E_CACHE,
            SESSION_DRIVER: 'file',
            MAIL_MAILER: 'array',
            SANAD_FAKE_MIDTRANS: '1',
        },
    },
});
