import { defineConfig, devices } from '@playwright/test';
import { browserEnvironment } from './tests/e2e/environment';

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    reporter: 'list',
    globalSetup: './tests/e2e/setup.ts',
    use: { baseURL: 'http://127.0.0.1:8013', trace: 'retain-on-failure' },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8013 --no-reload',
        url: 'http://127.0.0.1:8013/up',
        env: browserEnvironment,
        reuseExistingServer: false,
        timeout: 30000,
    },
});
