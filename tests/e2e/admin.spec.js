// Admin panel in a real browser: accessibility of the pages with custom markup (dashboard, statistics, system...).
// Needs an administrator (see screenshots.spec.js): E2E_ADMIN_EMAIL, E2E_ADMIN_PASSWORD and E2E_ADMIN_PATH
// override the demo values. Runs on Chromium only: the admin is the same on every browser.
import { expect, test } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const admin = {
    email: process.env.E2E_ADMIN_EMAIL ?? 'demo@example.com',
    password: process.env.E2E_ADMIN_PASSWORD ?? 'demo-password',
    path: process.env.E2E_ADMIN_PATH ?? 'admin',
};

test.describe('admin accessibility (axe, WCAG 2.1 AA)', () => {
    // One login for all the pages: the login form is rate limited
    test.describe.configure({ mode: 'serial' });

    let session;

    test.beforeAll(async ({ browser }, testInfo) => {
        if (testInfo.project.name !== 'chromium') {
            return;
        }

        const context = await browser.newContext({ baseURL: testInfo.project.use.baseURL });
        const page = await context.newPage();
        await page.goto(`/${admin.path}/login`);
        await page.locator('input[type=email]').fill(admin.email);
        await page.locator('input[type=password]').fill(admin.password);
        await page.locator('button[type=submit]').click();
        await expect(page).toHaveURL(new RegExp(`/${admin.path}$`));
        session = await context.storageState();
        await context.close();
    });

    for (const path of ['', '/statistics', '/system', '/translations', '/profile-data', '/settings']) {
        test(path || '/', async ({ browser }, testInfo) => {
            test.skip(testInfo.project.name !== 'chromium', 'Chromium only');

            const context = await browser.newContext({ baseURL: testInfo.project.use.baseURL, storageState: session, reducedMotion: 'reduce' });
            const page = await context.newPage();
            await page.goto(`/${admin.path}${path}`, { waitUntil: 'networkidle' });
            await expect(page).toHaveURL(new RegExp(`/${admin.path}${path}$`));

            const results = await new AxeBuilder({ page })
                .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
                .analyze();
            await context.close();

            expect(results.violations.map((violation) => `${violation.id}: ${violation.nodes.map((node) => node.target.join(' ')).join(', ')}`)).toEqual([]);
        });
    }
});
