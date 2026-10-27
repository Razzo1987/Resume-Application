import { defineConfig, devices } from '@playwright/test';

/**
 * End-to-end tests of the public site (tests/e2e).
 * They need a database with the demo data and compiled assets:
 *   php artisan migrate:fresh --seed && php artisan db:seed --class=DemoSeeder && npm run build
 * Then: npx playwright test (starts "php artisan serve" on port 8001 by itself).
 * With E2E_BASE_URL an already running site is tested (e.g. Apache).
 * With E2E_THEME=name another theme is tested (copied to themes/ by scripts/build-theme.sh):
 * the general suite runs with that theme active, plus the theme's tests in themes/{name}/tests/e2e.
 */
const baseURL = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8001';
const theme = process.env.E2E_THEME;
const root = process.cwd().replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

export default defineConfig({
    testDir: theme ? '.' : 'tests/e2e',
    // with a theme: the general suite + the theme's one (absolute paths, so no other tests/e2e is picked up)
    testMatch: theme
        ? [new RegExp(`^${root}/tests/e2e/.*\\.spec\\.js$`), new RegExp(`^${root}/themes/${theme}/tests/e2e/.*\\.spec\\.js$`)]
        : '**/*.spec.js',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? [['github'], ['list'], ['html', { open: 'never' }]] : 'list',
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
            env: theme ? { ...process.env, RESUME_THEME: theme } : undefined,
        },
});
