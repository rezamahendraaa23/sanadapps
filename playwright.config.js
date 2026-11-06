import { defineConfig } from '@playwright/test';

const php = process.env.PHP_BIN || 'php';
const port = process.env.E2E_PORT || 8010;

export default defineConfig({
    testDir: './e2e',
    globalSetup: './e2e/global-setup.js',
    timeout: 30_000,
    fullyParallel: false,
    workers: 1,
    reporter: 'list',
    use: {
        baseURL: `http://127.0.0.1:${port}`,
        channel: process.env.CI ? undefined : 'chrome',
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
            SESSION_DRIVER: 'file',
            MAIL_MAILER: 'array',
        },
    },
});
