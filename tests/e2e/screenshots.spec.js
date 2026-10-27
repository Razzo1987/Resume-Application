// Screenshots of the site and the admin with the demo data, for the README and the docs/SCREENSHOTS.md page.
// Runs only on demand, with an administrator already created:
//   php artisan make:filament-user --name=Demo --email=demo@example.com --password=demo-password
//   php artisan db:seed --class=DemoStatsSeeder   (demo statistics)
//   SCREENSHOTS=1 npx playwright test screenshots --project=chromium
// The images go to screenshots/ (ignored by git); in CI they are a workflow artifact.
import { devices, expect, test } from '@playwright/test';

const dir = 'screenshots';
const admin = {
    email: process.env.E2E_ADMIN_EMAIL ?? 'demo@example.com',
    password: process.env.E2E_ADMIN_PASSWORD ?? 'demo-password',
    path: process.env.E2E_ADMIN_PATH ?? 'admin',
};

test.describe('screenshots', () => {
    test.skip(!process.env.SCREENSHOTS, 'only with SCREENSHOTS=1');
    test.describe.configure({ mode: 'serial' });

    test.beforeEach(async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'chromium', 'Chromium only');
        await page.emulateMedia({ reducedMotion: 'reduce' });
    });

    test('public site', async ({ page, browser }) => {
        await page.setViewportSize({ width: 1440, height: 900 });

        const shots = [
            ['cv-desktop', '/en-GB'],
            ['cv-desktop-it', '/it-IT'],
            ['project', '/en-GB/projects/booking-portal'],
            ['experience', '/en-GB/experience/tech-lead-brightline-software'],
            ['blog', '/en-GB/blog'],
            ['article', '/en-GB/blog/three-rules-for-an-accessible-website'],
        ];

        for (const [name, path] of shots) {
            await page.goto(path, { waitUntil: 'networkidle' });
            await page.screenshot({ path: `${dir}/${name}.png` });
        }

        await page.goto('/en-GB', { waitUntil: 'networkidle' });
        await page.screenshot({ path: `${dir}/cv-full-page.png`, fullPage: true });

        // Phone: first screen and open menu
        const phone = await browser.newContext({ ...devices['Pixel 7'], reducedMotion: 'reduce' });
        const mobile = await phone.newPage();
        await mobile.goto('/en-GB', { waitUntil: 'networkidle' });
        await mobile.screenshot({ path: `${dir}/cv-mobile.png` });
        await mobile.evaluate(() => window.scrollTo(0, document.querySelector('#experience')?.offsetTop ?? 0));
        await mobile.waitForTimeout(300);
        await mobile.screenshot({ path: `${dir}/cv-mobile-experience.png` });
        await phone.close();
    });

    test('admin panel', async ({ page }) => {
        await page.setViewportSize({ width: 1440, height: 900 });
        await page.goto(`/${admin.path}/login`);
        await page.locator('input[type=email]').fill(admin.email);
        await page.locator('input[type=password]').fill(admin.password);
        await page.locator('button[type=submit]').click();
        await expect(page).toHaveURL(new RegExp(`/${admin.path}$`));

        const shots = [
            ['admin-dashboard', ''],
            ['admin-experience', '/experiences/1/edit'],
            ['admin-cv-layout', '/page-sections'],
            ['admin-translations', '/translations'],
            ['admin-settings', '/settings'],
            ['admin-themes', '/themes'],
        ];

        for (const [name, path] of shots) {
            await page.goto(`/${admin.path}${path}`, { waitUntil: 'networkidle' });
            await page.waitForTimeout(500); // widgets loaded after the page
            await page.screenshot({ path: `${dir}/${name}.png` });
        }

        // Statistics (with DemoStatsSeeder): the top part and the map
        await page.goto(`/${admin.path}/statistics`, { waitUntil: 'networkidle' });
        // locally the site sees 127.0.0.1: the note about private addresses is not part of the screenshot
        await page.addStyleTag({ content: '.resume-stats-proxy-note { display: none !important; }' });
        await page.waitForTimeout(1000);
        await page.screenshot({ path: `${dir}/admin-statistics.png` });
        // the top bar stays fixed and would cover the box
        await page.addStyleTag({ content: '.fi-topbar { display: none !important; }' });
        await page.locator('.resume-stats-map').locator('xpath=ancestor::section[1]').screenshot({ path: `${dir}/admin-statistics-map.png` });

        // System: only the updates box (the rest shows the server paths)
        await page.goto(`/${admin.path}/system`, { waitUntil: 'networkidle' });
        await page.locator('.fi-section').filter({ has: page.getByRole('button', { name: /Check now|Controlla ora/ }) })
            .screenshot({ path: `${dir}/admin-updates.png` });
    });
});
