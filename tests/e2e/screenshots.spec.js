// Screenshot del sito e dell'admin con i dati dimostrativi, per il README e la pagina docs/SCREENSHOTS.md.
// Gira solo su richiesta, con un amministratore già creato:
//   php artisan make:filament-user --name=Demo --email=demo@example.com --password=demo-password
//   SCREENSHOTS=1 npx playwright test screenshots --project=chromium
// Le immagini finiscono in screenshots/ (ignorata da git); in CI sono un artefatto del workflow.
import { devices, expect, test } from '@playwright/test';

const dir = 'screenshots';
const admin = {
    email: process.env.E2E_ADMIN_EMAIL ?? 'demo@example.com',
    password: process.env.E2E_ADMIN_PASSWORD ?? 'demo-password',
    path: process.env.E2E_ADMIN_PATH ?? 'admin',
};

test.describe('screenshots', () => {
    test.skip(!process.env.SCREENSHOTS, 'solo con SCREENSHOTS=1');
    test.describe.configure({ mode: 'serial' });

    test.beforeEach(async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'chromium', 'solo Chromium');
        await page.emulateMedia({ reducedMotion: 'reduce' });
    });

    test('public site', async ({ page, browser }) => {
        await page.setViewportSize({ width: 1440, height: 900 });

        const shots = [
            ['cv-desktop', '/en-GB'],
            ['cv-desktop-it', '/it-IT'],
            ['project', '/en-GB/projects/data-catalog-and-lineage'],
            ['experience', '/en-GB/experience/data-governance-manager-acme-consulting'],
            ['blog', '/en-GB/blog'],
            ['article', '/en-GB/blog/why-data-governance-pays-off'],
        ];

        for (const [name, path] of shots) {
            await page.goto(path, { waitUntil: 'networkidle' });
            await page.screenshot({ path: `${dir}/${name}.png` });
        }

        await page.goto('/en-GB', { waitUntil: 'networkidle' });
        await page.screenshot({ path: `${dir}/cv-full-page.png`, fullPage: true });

        // Telefono: prima schermata e menu aperto
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
            await page.waitForTimeout(500); // widget caricati dopo la pagina
            await page.screenshot({ path: `${dir}/${name}.png` });
        }

        // Sistema: solo il riquadro degli aggiornamenti (il resto mostra i percorsi del server)
        await page.goto(`/${admin.path}/system`, { waitUntil: 'networkidle' });
        await page.locator('.fi-section').filter({ has: page.getByRole('button', { name: /Check now|Controlla ora/ }) })
            .screenshot({ path: `${dir}/admin-updates.png` });
    });
});
