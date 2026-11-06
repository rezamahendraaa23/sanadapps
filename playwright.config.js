import { defineConfig } from '@playwright/test';

const php = process.env.PHP_BIN || 'php';
const port = process.env.E2E_PORT || 8010;

export default defineConfig({
    testDir: './e2e',
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
        reuseExistingServer: !process.env.CI,
        timeout: 30_000,
    },
});
