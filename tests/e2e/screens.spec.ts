import { expect, test } from './preview-test';

test('every feature group mounts its intended screen for the appropriate preview role', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    const routes = [
        ['/priorities?role=ra', 'Antrean prioritas'],
        ['/clients?role=marketing', 'Data klien'],
        ['/prospects?role=marketing', 'Prospek dari chatbot'],
        ['/samples?role=marketing', 'Persetujuan sampel'],
        ['/contracts?role=marketing', 'Daftar kontrak'],
        ['/contracts/new?role=marketing', 'Buat kontrak'],
        ['/invoices?role=finance', 'Daftar tagihan'],
        ['/receivables?role=finance', 'Laporan piutang'],
        ['/verification?role=ra', 'Verifikasi dokumen'],
        ['/qa-review?role=qa', 'Verifikasi dokumen mutu'],
        ['/shared-documents?role=qa', 'Dokumen bersama'],
        ['/ingredients?role=rnd', 'Bahan baku tiket'],
        ['/sync-checklist?role=ra', 'Sinkronisasi checklist'],
        ['/bpom?role=ra', 'Pengajuan dan catatan BPOM'],
        ['/nie?role=ra', 'Input NIE'],
        ['/contacts?role=marketing', 'Kontak WhatsApp'],
        ['/compose?role=marketing', 'Chat baru'],
        ['/tasks?role=rnd', 'Tugas dokumen'],
        ['/escalations?role=manager', 'Daftar eskalasi'],
    ];
    for (const [route, heading] of routes) {
        await page.goto(route);
        await expect(page.getByRole('heading', { name: heading, exact: true })).toBeVisible();
    }
    expect(errors).toEqual([]);
});
