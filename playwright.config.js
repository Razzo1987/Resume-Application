import { defineConfig, devices } from '@playwright/test';

/**
 * Test end-to-end del sito pubblico (tests/e2e).
 * Servono database con i dati demo e asset compilati:
 *   php artisan migrate:fresh --seed && php artisan db:seed --class=DemoSeeder && npm run build
 * Poi: npx playwright test (avvia da solo "php artisan serve" sulla porta 8001).
 * Con E2E_BASE_URL si prova un sito già avviato (es. Apache).
 */
const baseURL = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8001';

export default defineConfig({
    testDir: 'tests/e2e',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? [['github'], ['list']] : 'list',
    use: {
        baseURL,
        trace: 'retain-on-failure',
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
        { name: 'firefox', use: { ...devices['Desktop Firefox'] } },
        { name: 'webkit', use: { ...devices['Desktop Safari'] } },
        { name: 'mobile', use: { ...devices['Pixel 7'] } },
    ],
    webServer: process.env.E2E_BASE_URL
        ? undefined
        : {
            command: 'php artisan serve --host=127.0.0.1 --port=8001',
            url: `${baseURL}/robots.txt`,
            reuseExistingServer: !process.env.CI,
            timeout: 60_000,
        },
});
