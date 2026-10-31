import { expect, test } from '@playwright/test';

test('Marketing search and direct ticket navigation do not reveal another assigned client', async ({ page }) => {
    await page.goto('/?role=marketing');
    await page.getByLabel('Pencarian global').fill('Karsa');
    await expect(page.getByText('Kapsul pasak bumi', { exact: true })).toHaveCount(0);
    await page.goto('/tickets/PRD-2026-0024?role=marketing');
    await expect(page.getByRole('heading', { name: 'Tiket tidak ditemukan', exact: true })).toBeVisible();
});

test('a rejected pre-registration result enables correction and blocks unchanged resubmission', async ({ page }) => {
    await page.goto('/bpom?role=ra&ticket=PRD-2026-0010');
    await page.getByRole('button', { name: 'Catat hasil pra-registrasi', exact: true }).click();
    await page.getByLabel('Keputusan BPOM', { exact: true }).selectOption('rejected');
    await page
        .getByLabel('Dokumen hasil pra-registrasi', { exact: true })
        .setInputFiles({ name: 'hasil.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-demo') });
    await page.getByRole('button', { name: 'Simpan hasil', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Simpan dan kunci versi', exact: true })).toBeDisabled();
    await page.getByRole('link', { name: 'Daftar tiket', exact: true }).click();
    await page.getByPlaceholder('Cari kode, produk, atau klien...').fill('meniran');
    await page.getByRole('link', { name: 'Buka PRD-2026-0010', exact: true }).click();
    await page.getByRole('button', { name: /^Dokumen/ }).click();
    await expect(
        page.locator('tr').filter({ hasText: 'Master formula' }).getByText('Perlu revisi', { exact: true }),
    ).toBeVisible();
});

test('BPOM notes remain attached to their ticket across selection and navigation', async ({ page }) => {
    await page.goto('/bpom?role=ra');
    await page.getByRole('button', { name: 'Tambah catatan', exact: true }).click();
    await page.getByLabel('Catatan BPOM', { exact: true }).fill('Catatan hanya untuk jahe merah');
    await page.getByRole('button', { name: 'Simpan catatan', exact: true }).click();
    await page.locator('.filter-bar select').selectOption('PRD-2026-0017');
    await expect(page.getByText('Catatan hanya untuk jahe merah', { exact: true })).toHaveCount(0);
    await page.getByRole('link', { name: 'Daftar tiket', exact: true }).click();
    await page.getByRole('link', { name: 'Pengajuan BPOM', exact: true }).click();
    await expect(page.getByText('Catatan hanya untuk jahe merah', { exact: true })).toBeVisible();
});

test('a saved contract draft can be resumed and sent without creating a duplicate', async ({ page }) => {
    await page.goto('/contracts/new?role=marketing&ticket=PRD-2026-0020');
    await page.getByLabel('Nilai jasa (Rp)', { exact: true }).fill('20000000');
    await page.getByRole('button', { name: 'Simpan draf', exact: true }).click();
    await page.getByRole('link', { name: 'Lanjutkan draf KTR/2026/0020', exact: true }).click();
    await expect(page.getByLabel('Nilai jasa (Rp)', { exact: true })).toHaveValue('20000000');
    await page.getByRole('button', { name: 'Pratinjau sebelum kirim', exact: true }).click();
    await page.getByRole('button', { name: 'Simulasikan pengiriman kontrak', exact: true }).click();
    const row = page.locator('tbody tr').filter({ hasText: 'KTR/2026/0020' });
    await expect(row).toHaveCount(1);
    await expect(row.getByText('Dikirim', { exact: true })).toBeVisible();
});

test('BPOM results reject executable files before changing the registration phase', async ({ page }) => {
    await page.goto('/bpom?role=ra&ticket=PRD-2026-0010');
    await page.getByRole('button', { name: 'Catat hasil pra-registrasi', exact: true }).click();
    await page
        .getByLabel('Dokumen hasil pra-registrasi', { exact: true })
        .setInputFiles({ name: 'hasil.exe', mimeType: 'application/x-msdownload', buffer: Buffer.from('unsafe') });
    await page.getByRole('button', { name: 'Simpan hasil', exact: true }).click();
    await expect(page.getByRole('dialog').getByRole('alert')).toContainText('Format');
});

test('starting a conversation preserves the selected recipient through sending', async ({ page }) => {
    await page.goto('/contacts?role=marketing');
    await page.locator('tr').filter({ hasText: 'Bumi Sehat' }).click();
    await page.getByRole('link', { name: 'Mulai percakapan', exact: true }).click();
    await expect(page.getByLabel('Kontak klien', { exact: true })).toHaveValue('c2');
    await page.getByRole('button', { name: 'Simulasikan kirim pesan', exact: true }).click();
    await expect(page.locator('.chat-header')).toContainText('Bima');
    await expect(page.locator('.chat-messages')).toContainText('Bima Pratama');
});

test('sample revision history survives switching tickets', async ({ page }) => {
    await page.goto('/samples?role=marketing&ticket=PRD-2026-0018');
    await page.getByLabel('Catatan klien', { exact: true }).fill('Catatan sampel rosela khusus');
    await page.getByRole('button', { name: 'Perlu revisi', exact: true }).click();
    await page.locator('.filter-bar select').selectOption('PRD-2026-0019');
    await page.locator('.filter-bar select').selectOption('PRD-2026-0018');
    await expect(page.getByText('Catatan sampel rosela khusus', { exact: true })).toBeVisible();
});

test('recorded NIE and its date remain visible after closing the ticket', async ({ page }) => {
    await page.goto('/nie?role=ra');
    await page.getByLabel('Nomor NIE', { exact: true }).fill('TR262099999');
    await page.getByLabel('Tanggal terbit NIE', { exact: true }).fill('2026-10-21');
    await page.getByRole('button', { name: 'Simpan dan tutup tiket', exact: true }).click();
    await expect(page.getByText('TR262099999', { exact: true })).toBeVisible();
});
