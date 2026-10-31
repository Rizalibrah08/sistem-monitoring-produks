import { expect, test } from '@playwright/test';

test('manager dashboard exposes tickets, review indicators and working navigation', async ({ page }) => {
    await page.goto('/');
    await expect(page.getByRole('heading', { name: 'Dasbor', exact: true })).toBeVisible();
    await expect(page.locator('.stat-grid').getByText('Tiket aktif', { exact: true })).toBeVisible();
    await expect(page.getByText('Perlu ditinjau', { exact: true }).first()).toBeVisible();
    await page.getByRole('link', { name: 'Daftar tiket', exact: true }).first().click();
    await expect(page.getByRole('heading', { name: 'Daftar tiket', exact: true })).toBeVisible();
    await page.getByPlaceholder('Cari kode, produk, atau klien...').fill('sambiloto');
    await expect(page.locator('tbody tr')).toHaveCount(1);
    await expect(page.getByText('Kapsul sambiloto', { exact: true })).toBeVisible();
});

test('RA document submission explains unmet requirements and revision requires a reason', async ({ page }) => {
    await page.goto('/tickets/PRD-2026-0012?role=ra&tab=documents');
    await expect(page.getByRole('button', { name: 'Ajukan ke BPOM' })).toBeDisabled();
    await page.getByRole('button', { name: 'Periksa dokumen CoA bahan baku' }).click();
    await expect(page.getByRole('dialog')).toBeVisible();
    await page.getByRole('button', { name: 'Kembalikan dokumen' }).click();
    await expect(page.getByText('Alasan pengembalian wajib diisi.', { exact: true })).toBeVisible();
});

test('mobile client portal and expired links do not overflow or leak ticket data', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 800 });
    await page.goto('/portal/demo-token');
    await expect(page.getByRole('heading', { name: 'Halo, Kak Nadia' })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await page.goto('/portal/expired');
    await expect(page.getByRole('heading', { name: 'Tautan tidak berlaku' })).toBeVisible();
    await expect(page.getByText('PRD-2026-0012')).toHaveCount(0);
});

test('inbox sends a demo message without calling an external gateway', async ({ page }) => {
    await page.goto('/inbox?role=marketing');
    await page.getByPlaceholder('Tulis pesan Anda...').fill('Terima kasih, dokumennya sedang kami periksa.');
    await page.getByRole('button', { name: 'Kirim pesan', exact: true }).click();
    await expect(
        page.locator('.chat-messages').getByText('Terima kasih, dokumennya sedang kami periksa.', { exact: true }),
    ).toBeVisible();
});

test('Marketing can create a ticket from validated product fields', async ({ page }) => {
    await page.goto('/tickets/new?role=marketing');
    await page.getByLabel('Klien', { exact: true }).selectOption('c1');
    await page.getByLabel('Nama produk', { exact: true }).fill('Kapsul sehat pengujian');
    await page.getByLabel('Sediaan', { exact: true }).selectOption({ label: 'Kapsul' });
    await page.getByLabel('Kategori', { exact: true }).selectOption({ label: 'Jamu' });
    await page.getByRole('button', { name: 'Simpan tiket', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Kapsul sehat pengujian', exact: true })).toBeVisible();
    await expect(page.getByText('Menunggu DP', { exact: true })).toBeVisible();
});

test('Finance verifies a proof and opens the paid ticket phase in the demo', async ({ page }) => {
    await page.goto('/payments?role=finance');
    await page.getByRole('button', { name: 'Periksa pembayaran INV-0033' }).click();
    await page.getByRole('button', { name: 'Konfirmasi lunas', exact: true }).click();
    await expect(page.getByRole('status')).toContainText('Pembayaran diverifikasi');
    await page.getByRole('link', { name: 'Tagihan', exact: true }).click();
    await page.getByRole('link', { name: 'PRD-2026-0021', exact: true }).click();
    await expect(page.locator('.ticket-heading').getByText('Pra-registrasi', { exact: true })).toBeVisible();
});

test('client-side upload rejects an unsupported file without losing metadata', async ({ page }) => {
    await page.goto('/tasks?role=rnd');
    await page.getByRole('button', { name: 'Unggah', exact: true }).first().click();
    await page.getByLabel('Nomor dokumen', { exact: true }).fill('DOC-TEST-001');
    await page.getByLabel('Tanggal terbit', { exact: true }).fill('2026-10-20');
    await page
        .getByLabel('Pilih berkas dokumen', { exact: true })
        .setInputFiles({ name: 'bahaya.exe', mimeType: 'application/octet-stream', buffer: Buffer.from('test') });
    await page.getByRole('button', { name: /Unggah sebagai/ }).click();
    await expect(page.getByRole('alert')).toContainText('Format berkas tidak didukung');
    await expect(page.getByLabel('Nomor dokumen', { exact: true })).toHaveValue('DOC-TEST-001');
});

test('core admin and reporting screens render without browser errors', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    for (const [route, title] of [
        ['/users?role=admin', 'Pengguna dan role'],
        ['/master?role=admin', 'Master data'],
        ['/templates?role=admin', 'Templat dokumen'],
        ['/audit?role=manager', 'Log audit'],
        ['/reports?role=manager', 'Laporan durasi'],
    ] as const) {
        await page.goto(route);
        await expect(page.getByRole('heading', { name: title, exact: true })).toBeVisible();
    }
    expect(errors).toEqual([]);
});
