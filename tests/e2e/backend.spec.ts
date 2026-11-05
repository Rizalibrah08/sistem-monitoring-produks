import { expect, test, type Page } from '@playwright/test';

test('real login is required and unprotected form submissions fail CSRF validation', async ({ page }) => {
    await page.goto('/dashboard');
    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByRole('button', { name: 'Masuk', exact: true })).toBeVisible();
    await expect(page.getByRole('combobox')).toHaveCount(0);

    const response = await page.request.post('/login', { data: { email: 'admin@backend.test', password: 'AishaBrowser123!' } });
    expect(response.status()).toBe(419);
});

test('admin creates a persisted user and reads the resulting audit entry', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await login(page, 'admin@backend.test');
    await createQualityUser(page, 'qa-audit@backend.test', 'QA Audit Browser');
    await page.reload();
    await expect(page.locator('tbody tr').filter({ hasText: 'qa-audit@backend.test' })).toBeVisible();
    await page.getByRole('navigation', { name: 'Navigasi utama' }).getByRole('link', { name: 'Log audit', exact: true }).click();
    await page.getByLabel('Jenis aksi', { exact: true }).fill('user.created');
    await page.getByRole('button', { name: 'Terapkan filter', exact: true }).click();
    await expect(page.locator('tbody')).toContainText('user.created');
    await page.locator('tbody tr').first().getByText('Lihat rincian', { exact: true }).click();
    await expect(page.locator('tbody')).toContainText('qa-audit@backend.test');
    await expect(page.getByLabel('Pratinjau role')).toHaveCount(0);
    expect(errors).toEqual([]);
});

test('a created QA account cannot access admin pages and logout ends the session', async ({ page }) => {
    await login(page, 'admin@backend.test');
    await createQualityUser(page, 'qa-access@backend.test', 'QA Access Browser');
    await page.getByRole('button', { name: 'Keluar', exact: true }).click();
    await login(page, 'qa-access@backend.test');
    await expect(page.getByRole('link', { name: 'Pengguna dan role', exact: true })).toHaveCount(0);
    const response = await page.goto('/users?role=admin');
    expect(response?.status()).toBe(403);
    await page.goto('/dashboard');
    await page.getByRole('button', { name: 'Keluar', exact: true }).click();
    await page.goto('/dashboard');
    await expect(page).toHaveURL(/\/login$/);
});

test('real login and authenticated navigation fit a 360px screen', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 800 });
    await page.goto('/login');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await login(page, 'admin@backend.test');
    await page.getByRole('button', { name: 'Buka navigasi', exact: true }).click();
    await page.getByRole('navigation', { name: 'Navigasi utama' }).getByRole('link', { name: 'Pengguna dan role', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Pengguna dan role', exact: true })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
});

async function login(page: Page, email: string) {
    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill(email);
    await page.getByLabel('Kata sandi', { exact: true }).fill('AishaBrowser123!');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Dasbor', exact: true })).toBeVisible();
}

async function createQualityUser(page: Page, email: string, name: string) {
    await page.getByRole('navigation', { name: 'Navigasi utama' }).getByRole('link', { name: 'Pengguna dan role', exact: true }).click();
    await page.getByRole('button', { name: 'Tambah pengguna', exact: true }).click();
    await page.getByLabel('Nama', { exact: true }).fill(name);
    await page.getByLabel('Email pengguna', { exact: true }).fill(email);
    await page.getByLabel('Role pengguna', { exact: true }).selectOption('qa');
    await page.getByLabel('Kata sandi pengguna', { exact: true }).fill('AishaBrowser123!');
    await page.getByLabel('Konfirmasi kata sandi', { exact: true }).fill('AishaBrowser123!');
    await page.getByRole('button', { name: 'Simpan pengguna', exact: true }).click();
    await expect(page.getByRole('dialog')).toHaveCount(0);
    await expect(page.locator('tbody tr').filter({ hasText: email })).toBeVisible();
}
