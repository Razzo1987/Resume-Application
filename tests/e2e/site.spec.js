// Sito pubblico nei browser reali: funzionalità, privacy (niente cookie né risorse esterne) e accessibilità.
import { expect, test } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/** Raccoglie errori di console, violazioni CSP e richieste verso altri domini. */
function watch(page, baseURL) {
    const problems = [];
    const origin = new URL(baseURL).host;

    page.on('console', (message) => {
        if (message.type() === 'error') {
            problems.push(`console: ${message.text()}`);
        }
    });
    page.on('pageerror', (error) => problems.push(`page error: ${error.message}`));
    page.on('request', (request) => {
        const url = new URL(request.url());

        if (url.protocol.startsWith('http') && url.host !== origin) {
            problems.push(`external request: ${request.url()}`);
        }
    });

    return problems;
}

test.describe('CV', () => {
    test('loads without errors, cookies or third-party requests', async ({ page, context, baseURL }) => {
        const problems = watch(page, baseURL);

        await page.goto('/it-IT');
        await expect(page.locator('h1')).toHaveText('John Doe');
        await page.waitForLoadState('networkidle');

        expect(problems).toEqual([]);
        expect(await context.cookies()).toEqual([]);
    });

    test('reveals email and phone only with JavaScript', async ({ page }) => {
        await page.goto('/it-IT');

        const email = page.locator('#about .js-cloak').first();
        await expect(email).toHaveText('john.doe@example.com');
        await expect(email).toHaveAttribute('href', 'mailto:john.doe@example.com');
        await expect(page.locator('.sidebar-actions .js-cloak')).toHaveAttribute('href', 'mailto:john.doe@example.com');
    });

    test('shows the icons from the SVG sprite', async ({ page }) => {
        await page.goto('/it-IT');

        const icon = page.locator('#about .section-title svg.icon');
        await expect(icon).toBeVisible();
        const box = await icon.boundingBox();
        expect(box.width).toBeGreaterThan(10);
    });

    test('navigates to a section from the menu', async ({ page, isMobile }) => {
        await page.goto('/it-IT');

        await page.locator('.site-nav a[href="#certifications"]').click();
        await expect(page).toHaveURL(/#certifications$/);
        await expect(page.locator('#certifications-title')).toBeInViewport();

        if (!isMobile) {
            await expect(page.locator('.site-nav a[href="#certifications"]')).toHaveAttribute('aria-current', 'true');
        }
    });

    test('shows the back-to-top button after scrolling', async ({ page }) => {
        await page.goto('/it-IT');

        await expect(page.locator('.back-to-top')).toBeHidden();
        // il puntatore sul contenuto: sopra la barra laterale la rotellina scorrerebbe solo quella
        await page.mouse.move(page.viewportSize().width - 50, 300);
        await page.mouse.wheel(0, 2500);
        await expect(page.locator('.back-to-top')).toBeVisible();
    });

    test('switches language to the same page', async ({ page }) => {
        await page.goto('/it-IT/progetti/data-catalog-e-lineage');

        await page.locator('.language-switcher a[hreflang="en-GB"]').click();
        await expect(page).toHaveURL(/\/en-GB\/projects\/data-catalog-and-lineage$/);
        await expect(page.locator('h1')).toHaveText('Data catalog and lineage');
    });

    test('downloads the CV with the standard file name', async ({ page }) => {
        const response = await page.request.get('/it-IT/cv');

        expect(response.status()).toBe(200);
        expect(response.headers()['content-type']).toBe('application/pdf');
        expect(response.headers()['content-disposition']).toContain('CV John Doe - IT.pdf');
    });

    test('prints a clean page', async ({ page }) => {
        await page.goto('/it-IT');
        await expect(page.locator('.js-print')).toBeVisible();

        await page.emulateMedia({ media: 'print' });

        await expect(page.locator('.site-nav')).toBeHidden();
        await expect(page.locator('.sidebar-actions')).toBeHidden();
        await expect(page.locator('#experience')).toBeVisible();
    });
});

test.describe('accessibility (axe, WCAG 2.1 AA)', () => {
    for (const path of ['/it-IT', '/en-GB/experience/data-governance-manager-acme-consulting', '/en-GB/projects/data-catalog-and-lineage', '/it-IT/blog', '/en-GB/blog/why-data-governance-pays-off', '/it-IT/pagina-inesistente']) {
        test(path, async ({ page }) => {
            await page.goto(path);
            // I temi con animazioni: si analizza la pagina a riposo (testo a opacità piena)
            await page.evaluate(() => Promise.all(document.getAnimations()
                .filter((animation) => animation.effect?.getComputedTiming().endTime !== Infinity)
                .map((animation) => animation.finished.catch(() => null))));

            const results = await new AxeBuilder({ page })
                .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
                .analyze();

            expect(results.violations.map((violation) => `${violation.id}: ${violation.nodes.map((node) => node.target.join(' ')).join(', ')}`)).toEqual([]);
        });
    }
});
