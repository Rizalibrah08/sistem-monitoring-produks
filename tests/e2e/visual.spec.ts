import { expect, test } from '@playwright/test';

test('desktop and mobile screens have no client errors or horizontal overflow', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    const screens = [
        ['/', 'dashboard'],
        ['/tickets/PRD-2026-0012?role=ra&tab=documents', 'documents'],
        ['/inbox?role=marketing', 'inbox'],
        ['/login', 'login'],
    ];
    await page.setViewportSize({ width: 1440, height: 1024 });
    for (const [path, name] of screens) {
        await page.goto(path);
        await page.evaluate(async () => {
            await document.fonts.ready;
        });
        await page.screenshot({
            path: `.superpowers/sdd/frontend-health/screenshots/${name}-desktop.png`,
            fullPage: true,
        });
    }
    await page.setViewportSize({ width: 360, height: 800 });
    for (const [path, name] of [
        ['/portal/demo-token', 'portal'],
        ['/inbox?role=marketing', 'inbox'],
        ['/?role=manager', 'dashboard'],
    ]) {
        await page.goto(path);
        await page.evaluate(async () => {
            await document.fonts.ready;
        });
        expect(
            await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth),
            `horizontal overflow on ${path}`,
        ).toBe(true);
        await page.screenshot({
            path: `.superpowers/sdd/frontend-health/screenshots/${name}-mobile.png`,
            fullPage: true,
        });
        if (name === 'inbox') {
            await page.locator('.conversation-item').first().click();
            await expect(page.getByPlaceholder('Tulis pesan Anda...')).toBeVisible();
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
            await page.screenshot({
                path: '.superpowers/sdd/frontend-health/screenshots/inbox-chat-mobile.png',
                fullPage: true,
            });
        }
    }
    expect(errors).toEqual([]);
});
