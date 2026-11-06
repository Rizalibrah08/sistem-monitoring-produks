import { App, router } from '@inertiajs/react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import Login from '../../resources/js/Pages/Auth/Login';
import Audit from '../../resources/js/Pages/Backend/Audit';
import Bpom from '../../resources/js/Pages/Backend/Bpom';
import ChecklistSync from '../../resources/js/Pages/Backend/ChecklistSync';
import Clients from '../../resources/js/Pages/Backend/Clients';
import Contracts from '../../resources/js/Pages/Backend/Contracts';
import Invoices from '../../resources/js/Pages/Backend/Invoices';
import Receivables from '../../resources/js/Pages/Backend/Receivables';
import Dashboard from '../../resources/js/Pages/Backend/Dashboard';
import DocumentRules from '../../resources/js/Pages/Backend/DocumentRules';
import Master from '../../resources/js/Pages/Backend/Master';
import Nie from '../../resources/js/Pages/Backend/Nie';
import Notifications from '../../resources/js/Pages/Backend/Notifications';
import Priorities from '../../resources/js/Pages/Backend/Priorities';
import Reports from '../../resources/js/Pages/Backend/Reports';
import Templates from '../../resources/js/Pages/Backend/Templates';
import TicketShow from '../../resources/js/Pages/Backend/TicketShow';
import UploadLink from '../../resources/js/Pages/Client/UploadLink';
import UploadLinkExpired from '../../resources/js/Pages/Client/UploadLinkExpired';
import Samples from '../../resources/js/Pages/Backend/Samples';
import Tickets from '../../resources/js/Pages/Backend/Tickets';
import Verification from '../../resources/js/Pages/Backend/Verification';
import { BackendLayout } from '../../resources/js/layouts/backend-layout';

function renderPage(children: ReactNode, overrides: Record<string, unknown> = {}) {
    return render(
        <App
            initialPage={{
                component: 'TestPage', url: '/login', version: null, rescuedProps: [], rememberedState: {}, flash: {},
                props: {
                    errors: {},
                    auth: { user: null, permissions: [] },
                    navigation: [],
                    flash: { status: null },
                    routes: { login: '/login', loginStore: '/login', logout: '/logout', dashboard: '/dashboard', users: '/users', usersStore: '/users', audit: '/audit' },
                    ...overrides,
                },
            }}
            initialComponent={() => <>{children}</>}
            resolveComponent={() => () => <>{children}</>}
        />,
    );
}

const staff = { id: 1, name: 'Petugas RA', email: 'ra@aisha.test', role: 'ra', role_label: 'Regulatory Affairs', is_active: true, unavailable: false };

describe('backend workspace', () => {
    it('submits credentials without a client-selected role and displays server validation errors', async () => {
        const user = userEvent.setup();
        const requests: unknown[] = [];
        vi.spyOn(router, 'post').mockImplementation((url, data, options) => {
            requests.push({ url, data });
            options?.onError?.({ email: 'Email atau kata sandi salah, atau akun tidak aktif.' });
        });
        renderPage(<Login />);

        await user.type(screen.getByLabelText('Email'), 'ra@aisha.test');
        await user.type(screen.getByLabelText('Kata sandi'), 'password');
        await user.click(screen.getByRole('button', { name: 'Masuk' }));

        expect(requests).toEqual([{ url: '/login', data: { email: 'ra@aisha.test', password: 'password' } }]);
        expect(screen.getByRole('alert')).toHaveTextContent('Email atau kata sandi salah');
        expect(screen.getByLabelText('Email')).toHaveValue('ra@aisha.test');
        expect(screen.queryByRole('combobox')).not.toBeInTheDocument();
    });

    it('renders the authenticated identity and only navigation supplied by the server', () => {
        renderPage(<BackendLayout title="Dasbor"><p>Konten staf</p></BackendLayout>, {
            auth: { user: { ...staff, name: '<script>tidak dijalankan</script>' }, permissions: ['dashboard.view'] },
            navigation: [{ label: 'Dasbor', href: '/dashboard' }],
        });

        expect(screen.getByText('<script>tidak dijalankan</script>')).toBeInTheDocument();
        expect(document.querySelector('script')).not.toBeInTheDocument();
        expect(screen.getByRole('link', { name: /^Dasbor$/ })).toHaveAttribute('href', '/dashboard');
        expect(screen.queryByRole('link', { name: 'Pengguna dan role' })).not.toBeInTheDocument();
        expect(screen.queryByLabelText('Pratinjau role')).not.toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Keluar' })).toBeInTheDocument();
    });

    it('shows statistics and tasks supplied by the server without demo data', () => {
        renderPage(<Dashboard
            stats={[{ key: 'late', label: 'Terlambat SLA', value: 2, detail: 'Tahap melewati tenggat' }, { key: 'prospects', label: 'Prospek baru', value: null, detail: 'Menunggu integrasi WhatsApp' }]}
            tasks={[{ kind: 'revisi', title: 'Revisi Master formula', detail: 'Komposisi salah.', ticket_code: 'PRD-2026-0001', due: null, url: '/tickets/5' }]}
            urls={{ tasks: '/tasks' }} />, {
            auth: { user: staff, permissions: ['dashboard.view'] },
            navigation: [{ label: 'Dasbor', href: '/dashboard' }],
        });

        expect(screen.getByRole('heading', { name: /^Dasbor$/ })).toBeInTheDocument();
        expect(screen.getByText('Terlambat SLA')).toBeInTheDocument();
        expect(screen.getByText('—')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /Revisi Master formula/ })).toHaveAttribute('href', '/tickets/5');
        expect(screen.queryByText('PRD-2026-0012')).not.toBeInTheDocument();
    });
});

const admin = { ...staff, id: 9, name: 'Admin Aisha', role: 'admin', role_label: 'Admin' };
const masterUrls = { dosageForms: '/master/dosage-forms', categories: '/master/categories', materials: '/master/materials', stages: '/master/stages', documentTypes: '/master/document-types', holidays: '/master/holidays' };
const documentOptions = {
    phases: [{ value: 'pra_registrasi', label: 'Pra-registrasi' }, { value: 'registrasi', label: 'Registrasi' }, { value: 'kedua_fase', label: 'Kedua fase' }],
    scopes: [{ value: 'tiket', label: 'Tiket' }, { value: 'klien', label: 'Klien' }, { value: 'bahan_baku', label: 'Bahan baku' }, { value: 'perusahaan', label: 'Perusahaan' }],
    requirements: [{ value: 'wajib', label: 'Wajib' }, { value: 'kondisional', label: 'Kondisional' }, { value: 'opsional', label: 'Opsional' }],
    pics: [{ value: 'marketing', label: 'Marketing' }, { value: 'rnd', label: 'R&D' }, { value: 'qa', label: 'QA/QC' }, { value: 'ra', label: 'Regulatory Affairs' }, { value: 'client', label: 'Klien' }],
    verifiers: [{ value: 'qa', label: 'QA/QC' }, { value: 'ra', label: 'Regulatory Affairs' }],
};
const phases = [
    { value: 'awal', label: 'Awal' },
    { value: 'pra_registrasi', label: 'Pra-registrasi' },
    { value: 'registrasi', label: 'Registrasi' },
    { value: 'kedua_fase', label: 'Kedua fase' },
];
const criterionTypes = [{ value: 'benefit', label: 'Benefit' }, { value: 'cost', label: 'Cost' }];
const masterProps = { dosageForms: [], categories: [], materials: [], stages: [], phases, documentTypes: [], documentOptions, criteriaQueues: [], criterionTypes, holidays: [], urls: masterUrls };
const adminProps = { auth: { user: admin, permissions: ['dashboard.view', 'master.manage'] }, routes: { master: '/master' } };

describe('master data', () => {
    it('lists dosage forms and categories with their status and adds a dosage form through the server', async () => {
        const user = userEvent.setup();
        const requests: unknown[] = [];
        vi.spyOn(router, 'post').mockImplementation((url, data, options) => {
            requests.push({ url, data });
            options?.onError?.({ name: 'Nama ini sudah ada di daftar.' });
        });
        renderPage(
            <Master
                {...masterProps}
                tab="forms"
                dosageForms={[{ id: 1, name: 'Kapsul', is_active: true, update_url: '/master/dosage-forms/1' }]}
                categories={[{ id: 2, name: 'Jamu', is_active: false, update_url: '/master/categories/2' }]}
            />,
            adminProps,
        );

        expect(screen.getByText('Kapsul')).toBeInTheDocument();
        expect(screen.getByText('Jamu')).toBeInTheDocument();
        expect(screen.getByText('Nonaktif')).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Tambah sediaan' }));
        await user.type(screen.getByLabelText('Nama sediaan'), 'Serbuk');
        await user.click(screen.getByRole('button', { name: 'Simpan' }));

        expect(requests).toEqual([{ url: '/master/dosage-forms', data: { name: 'Serbuk', is_active: true } }]);
        expect(screen.getByRole('alert')).toHaveTextContent('Nama ini sudah ada di daftar.');
        expect(screen.getByLabelText('Nama sediaan')).toHaveValue('Serbuk');
    });

    it('updates a material with its supplier and active status', async () => {
        const user = userEvent.setup();
        const requests: unknown[] = [];
        vi.spyOn(router, 'put').mockImplementation((url, data) => {
            requests.push({ url, data });
        });
        renderPage(
            <Master
                {...masterProps}
                tab="ingredients"
                materials={[{ id: 5, name: 'Jahe merah', supplier: null, is_active: true, update_url: '/master/materials/5' }]}
            />,
            adminProps,
        );

        expect(screen.getByRole('cell', { name: '—' })).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Ubah Jahe merah' }));
        await user.type(screen.getByLabelText('Pemasok'), 'PT Rempah Nusantara');
        await user.click(screen.getByLabelText('Aktif'));
        await user.click(screen.getByRole('button', { name: 'Simpan' }));

        expect(requests).toEqual([{ url: '/master/materials/5', data: { name: 'Jahe merah', supplier: 'PT Rempah Nusantara', is_active: false } }]);
    });

    it('asks for confirmation before deleting a holiday', async () => {
        const user = userEvent.setup();
        const deletions: string[] = [];
        vi.spyOn(router, 'delete').mockImplementation((url) => {
            deletions.push(String(url));
        });
        const confirm = vi.spyOn(window, 'confirm').mockReturnValueOnce(false).mockReturnValueOnce(true);
        renderPage(
            <Master
                {...masterProps}
                tab="holidays"
                holidays={[{ id: 3, date: '2026-12-25', name: 'Hari Raya Natal', update_url: '/master/holidays/3', delete_url: '/master/holidays/3' }]}
            />,
            adminProps,
        );

        expect(screen.getByText('25/12/2026')).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Hapus Hari Raya Natal' }));
        expect(deletions).toEqual([]);

        await user.click(screen.getByRole('button', { name: 'Hapus Hari Raya Natal' }));
        expect(confirm).toHaveBeenCalledTimes(2);
        expect(deletions).toEqual(['/master/holidays/3']);
    });

    it('shows stage SLA and clears it when a stage is changed to wait for an external party', async () => {
        const user = userEvent.setup();
        const requests: unknown[] = [];
        vi.spyOn(router, 'put').mockImplementation((url, data) => {
            requests.push({ url, data });
        });
        renderPage(
            <Master
                {...masterProps}
                tab="stages"
                stages={[
                    { id: 1, code: 'perbaikan_dokumen', name: 'Perbaikan dokumen', phase: 'kedua_fase', phase_label: 'Kedua fase', sort_order: 12, sla_days: 5, waits_for_external: false, is_active: true, update_url: '/master/stages/1' },
                    { id: 2, code: 'menunggu_bpom_registrasi', name: 'Menunggu hasil BPOM registrasi', phase: 'registrasi', phase_label: 'Registrasi', sort_order: 9, sla_days: null, waits_for_external: true, is_active: true, update_url: '/master/stages/2' },
                ]}
            />,
            adminProps,
        );

        expect(screen.getByText('5 hari kerja')).toBeInTheDocument();
        expect(screen.getByText('Menunggu pihak luar')).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Ubah Perbaikan dokumen' }));
        expect(screen.getByLabelText('SLA (hari kerja)')).toHaveValue(5);

        await user.click(screen.getByLabelText('Menunggu BPOM atau pihak luar'));
        expect(screen.getByLabelText('SLA (hari kerja)')).toBeDisabled();
        await user.click(screen.getByRole('button', { name: 'Simpan' }));

        expect(requests).toEqual([{
            url: '/master/stages/1',
            data: { name: 'Perbaikan dokumen', phase: 'kedua_fase', sort_order: '12', sla_days: '', waits_for_external: true, is_active: true },
        }]);
    });

    it('lists document types with their age limit and adds a client document without a content verifier', async () => {
        const user = userEvent.setup();
        const requests: unknown[] = [];
        vi.spyOn(router, 'post').mockImplementation((url, data) => {
            requests.push({ url, data });
        });
        renderPage(
            <Master
                {...masterProps}
                tab="documents"
                documentTypes={[{
                    id: 7, name: 'CoA produk jadi', phase: 'kedua_fase', phase_label: 'Kedua fase', scope: 'tiket', scope_label: 'Tiket',
                    requirement: 'wajib', requirement_label: 'Wajib', pic_role: 'qa', pic_label: 'QA/QC', verifier_role: 'qa', verifier_label: 'QA/QC',
                    requires_expiry_date: false, max_age_months: 6, is_active: true, update_url: '/master/document-types/7',
                }]}
            />,
            adminProps,
        );

        expect(screen.getByText('CoA produk jadi')).toBeInTheDocument();
        expect(screen.getByText('Maks. 6 bulan')).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Tambah jenis dokumen' }));
        await user.type(screen.getByLabelText('Nama jenis dokumen'), 'Surat kuasa');
        await user.selectOptions(screen.getByLabelText('Cakupan'), 'klien');
        await user.selectOptions(screen.getByLabelText('PIC'), 'client');
        await user.click(screen.getByRole('button', { name: 'Simpan' }));

        expect(requests).toEqual([{
            url: '/master/document-types',
            data: { name: 'Surat kuasa', phase: 'pra_registrasi', scope: 'klien', requirement: 'wajib', pic_role: 'client', verifier_role: '', requires_expiry_date: false, max_age_months: '', is_active: true },
        }]);
    });

    it('shows the AHP weights of each queue and only saves weights that add up to one', async () => {
        const user = userEvent.setup();
        const requests: unknown[] = [];
        vi.spyOn(router, 'put').mockImplementation((url, data) => {
            requests.push({ url, data });
        });
        renderPage(
            <Master
                {...masterProps}
                tab="weights"
                criteriaQueues={[{
                    queue: 'ra', label: 'Regulatory Affairs', update_url: '/master/criteria/ra',
                    criteria: [
                        { code: 'C1', name: 'Critical Ratio', weight: 0.048, type: 'cost' },
                        { code: 'C2', name: 'Sisa hari kerja ke tenggat BPOM', weight: 0.74, type: 'cost' },
                        { code: 'C3', name: 'Kelengkapan dokumen wajib', weight: 0.106, type: 'benefit' },
                        { code: 'C4', name: 'Umur tiket', weight: 0.106, type: 'benefit' },
                    ],
                }]}
            />,
            adminProps,
        );

        expect(screen.getByText('Antrean Regulatory Affairs')).toBeInTheDocument();
        expect(screen.getByText('0,740')).toBeInTheDocument();
        expect(screen.getByText('Total 1,000')).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Ubah bobot Regulatory Affairs' }));
        await user.clear(screen.getByLabelText('Bobot C1'));
        await user.type(screen.getByLabelText('Bobot C1'), '0.1');
        expect(screen.getByRole('status')).toHaveTextContent('Total 1,052');
        expect(screen.getByRole('button', { name: 'Simpan' })).toBeDisabled();

        await user.clear(screen.getByLabelText('Bobot C1'));
        await user.type(screen.getByLabelText('Bobot C1'), '0.048');
        await user.click(screen.getByRole('button', { name: 'Simpan' }));

        expect(requests).toEqual([{
            url: '/master/criteria/ra',
            data: { criteria: {
                C1: { weight: '0.048', type: 'cost' },
                C2: { weight: '0.74', type: 'cost' },
                C3: { weight: '0.106', type: 'benefit' },
                C4: { weight: '0.106', type: 'benefit' },
            } },
        }]);
    });
});

const regulatory = { ...staff, id: 4 };
const ruleProps = { auth: { user: regulatory, permissions: ['dashboard.view', 'document_rules.manage'] }, routes: { documentRules: '/document-rules' } };
const ruleOptions = { documentTypes: [{ value: 3, label: 'Uji cemaran mikroba' }], dosageForms: [{ value: 2, label: 'Sirup' }], categories: [{ value: 4, label: 'Jamu' }] };

describe('document rules', () => {
    it('lists rules with their scope and adds a rule that applies to every category of one dosage form', async () => {
        const user = userEvent.setup();
        const requests: unknown[] = [];
        vi.spyOn(router, 'post').mockImplementation((url, data) => {
            requests.push({ url, data });
        });
        renderPage(
            <DocumentRules
                rules={[{ id: 1, document_type_id: 3, document_type: 'Uji cemaran mikroba', dosage_form_id: 2, dosage_form: 'Sirup', category_id: null, category: null, effective_from: '2026-11-01', notes: null, is_active: true, update_url: '/document-rules/1' }]}
                options={ruleOptions}
                urls={{ store: '/document-rules' }}
            />,
            ruleProps,
        );

        expect(screen.getByRole('cell', { name: 'Semua kategori' })).toBeInTheDocument();
        expect(screen.getByText('01/11/2026')).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Tambah aturan' }));
        await user.selectOptions(screen.getByLabelText('Sediaan'), '2');
        await user.type(screen.getByLabelText('Berlaku mulai'), '2026-12-01');
        await user.type(screen.getByLabelText('Catatan'), 'Wajib untuk sirup.');
        await user.click(screen.getByRole('button', { name: 'Simpan' }));

        expect(requests).toEqual([{
            url: '/document-rules',
            data: { document_type_id: '3', dosage_form_id: '2', category_id: '', effective_from: '2026-12-01', notes: 'Wajib untuk sirup.', is_active: true },
        }]);
    });

    it('explains that conditional document types are needed before a rule can be added', () => {
        renderPage(<DocumentRules rules={[]} options={{ ...ruleOptions, documentTypes: [] }} urls={{ store: '/document-rules' }} />, ruleProps);

        expect(screen.getByRole('button', { name: 'Tambah aturan' })).toBeDisabled();
        expect(screen.getByText(/belum ada jenis dokumen kondisional/i)).toBeInTheDocument();
    });
});

const templateVariables = [{ name: 'nama_klien', description: 'Nama klien pemilik merek' }, { name: 'nomor_kontrak', description: 'Nomor kontrak (MoU)' }];
const templateKinds = [{ value: 'kontrak', label: 'Kontrak' }, { value: 'tagihan', label: 'Tagihan' }];
const contractTemplate = {
    id: 1, name: 'Kontrak Maklon', kind: 'kontrak', kind_label: 'Kontrak', is_active: true, current_version: 2, upload_url: '/templates/1/versions',
    versions: [
        { id: 12, version: 2, original_name: 'kontrak-v2.docx', size: 20480, placeholders: ['nama_klien', 'nomor_kontrak'], notes: 'Tambah nomor kontrak', uploaded_by: 'Admin Aisha', uploaded_at: '05/11/2026 10:00', download_url: '/templates/versions/12/download' },
        { id: 11, version: 1, original_name: 'kontrak.docx', size: 18432, placeholders: ['nama_klien'], notes: null, uploaded_by: 'Admin Aisha', uploaded_at: '01/11/2026 09:00', download_url: '/templates/versions/11/download' },
    ],
};

describe('document templates', () => {
    it('shows each template with its version history and the allowed variables', () => {
        renderPage(<Templates templates={[contractTemplate]} kinds={templateKinds} variables={templateVariables} urls={{ store: '/templates' }} />, adminProps);

        expect(screen.getByRole('heading', { name: 'Kontrak Maklon' })).toBeInTheDocument();
        expect(screen.getByText('Versi 2')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Unduh versi 1' })).toHaveAttribute('href', '/templates/versions/11/download');
        expect(screen.getByText('Tambah nomor kontrak')).toBeInTheDocument();
        expect(screen.getAllByText('${nomor_kontrak}').length).toBeGreaterThan(0);
        expect(screen.getByText('Nomor kontrak (MoU)')).toBeInTheDocument();
    });

    it('uploads a new template with its name, kind and Word file and shows the rejected placeholders', async () => {
        const user = userEvent.setup();
        const requests: Array<{ url: string; data: Record<string, unknown> }> = [];
        vi.spyOn(router, 'post').mockImplementation((url, data, options) => {
            requests.push({ url: String(url), data: data as Record<string, unknown> });
            options?.onError?.({ file: 'Penanda tidak dikenal: ${diskon}. Gunakan penanda dari daftar variabel yang diizinkan.' });
        });
        renderPage(<Templates templates={[]} kinds={templateKinds} variables={templateVariables} urls={{ store: '/templates' }} />, adminProps);

        await user.click(screen.getByRole('button', { name: 'Unggah templat' }));
        await user.type(screen.getByLabelText('Nama templat'), 'Tagihan termin');
        await user.selectOptions(screen.getByLabelText('Jenis templat'), 'tagihan');
        await user.upload(screen.getByLabelText('Berkas Word (.docx)'), new File(['isi'], 'tagihan.docx', { type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' }));
        await user.click(screen.getByRole('button', { name: 'Unggah' }));

        expect(requests).toHaveLength(1);
        expect(requests[0].url).toBe('/templates');
        expect(requests[0].data).toMatchObject({ name: 'Tagihan termin', kind: 'tagihan', notes: '' });
        expect((requests[0].data.file as File).name).toBe('tagihan.docx');
        expect(screen.getByRole('alert')).toHaveTextContent('Penanda tidak dikenal: ${diskon}');
    });

    it('uploads a new version of an existing template to its own address', async () => {
        const user = userEvent.setup();
        const requests: Array<{ url: string; data: Record<string, unknown> }> = [];
        vi.spyOn(router, 'post').mockImplementation((url, data) => {
            requests.push({ url: String(url), data: data as Record<string, unknown> });
        });
        renderPage(<Templates templates={[contractTemplate]} kinds={templateKinds} variables={templateVariables} urls={{ store: '/templates' }} />, adminProps);

        await user.click(screen.getByRole('button', { name: 'Unggah versi baru Kontrak Maklon' }));
        await user.upload(screen.getByLabelText('Berkas Word (.docx)'), new File(['isi'], 'kontrak-v3.docx'));
        await user.type(screen.getByLabelText('Catatan perubahan'), 'Klausul pembayaran Midtrans');
        await user.click(screen.getByRole('button', { name: 'Unggah' }));

        expect(requests[0].url).toBe('/templates/1/versions');
        expect(requests[0].data).toMatchObject({ notes: 'Klausul pembayaran Midtrans' });
        expect(requests[0].data).not.toHaveProperty('name');
    });
});

const marketingUser = { ...staff, id: 7, name: 'Rizal Marketing', role: 'marketing', role_label: 'Marketing' };
const clientProps = { auth: { user: marketingUser, permissions: ['dashboard.view', 'clients.manage'] }, routes: { clients: '/clients' } };
const clientPage = { data: [{ id: 3, name: 'Nusantara Herbal', company: 'CV Nusantara Herbal', pic_name: 'Nadia Putri', whatsapp: '6281234567890', email: 'nadia@nusantara.test', marketing_user_id: 7, marketing_user: 'Rizal Marketing', update_url: '/clients/3' }], current_page: 1, last_page: 1, total: 1, prev_page_url: null, next_page_url: null };

describe('clients', () => {
    it('lists clients with a readable WhatsApp number and their marketing person in charge', () => {
        renderPage(<Clients clients={clientPage} marketingStaff={[{ value: 7, label: 'Rizal Marketing' }]} filters={{ search: '' }} urls={{ store: '/clients' }} />, clientProps);

        expect(screen.getByText('Nusantara Herbal')).toBeInTheDocument();
        expect(screen.getByText('+62 812-3456-7890')).toBeInTheDocument();
        expect(screen.getByRole('cell', { name: 'Rizal Marketing' })).toBeInTheDocument();
    });

    it('adds a client and shows the duplicate WhatsApp error from the server', async () => {
        const user = userEvent.setup();
        const requests: unknown[] = [];
        vi.spyOn(router, 'post').mockImplementation((url, data, options) => {
            requests.push({ url, data });
            options?.onError?.({ whatsapp: 'Nomor WhatsApp ini sudah dipakai klien lain.' });
        });
        renderPage(<Clients clients={{ ...clientPage, data: [], total: 0 }} marketingStaff={[{ value: 7, label: 'Rizal Marketing' }]} filters={{ search: '' }} urls={{ store: '/clients' }} />, clientProps);

        await user.click(screen.getByRole('button', { name: 'Tambah klien' }));
        await user.type(screen.getByLabelText('Nama klien'), 'Bumi Sehat');
        await user.type(screen.getByLabelText('Nama PIC klien'), 'Sari');
        await user.type(screen.getByLabelText('Nomor WhatsApp'), '0812 3456 7890');
        await user.selectOptions(screen.getByLabelText('PIC Marketing'), '7');
        await user.click(screen.getByRole('button', { name: 'Simpan' }));

        expect(requests).toEqual([{ url: '/clients', data: { name: 'Bumi Sehat', company: '', pic_name: 'Sari', whatsapp: '0812 3456 7890', email: '', marketing_user_id: '7' } }]);
        expect(screen.getByRole('alert')).toHaveTextContent('Nomor WhatsApp ini sudah dipakai klien lain.');
    });
});

const ticketRow = { id: 5, code: 'PRD-2026-0001', product_name: 'Kapsul Jahe Merah', client: 'Nusantara Herbal', dosage_form: 'Kapsul', category: 'Jamu', phase: 'awal', phase_label: 'Awal', status: 'aktif', status_label: 'Aktif', current_stage: 'Pengembangan sampel', needs_review: true, is_late: true, progress: 50, created_at: '05/11/2026', show_url: '/tickets/5' };
const ticketListProps = {
    tickets: { data: [ticketRow], current_page: 1, last_page: 1, total: 1, prev_page_url: null, next_page_url: null },
    filters: { search: '', phase: '', status: '' },
    phases: [{ value: 'awal', label: 'Awal' }, { value: 'pra_registrasi', label: 'Pra-registrasi' }],
    statuses: [{ value: 'aktif', label: 'Aktif' }],
    urls: { store: '/tickets', clients: '/clients' },
};
const ticketRoutes = { routes: { tickets: '/tickets' } };
const noBpom = { can_manage: false, can_submit: false, can_reject: false, can_complete: false, registration_blockers: null, packaging_requested_on: null, packaging_uploaded_on: null, submissions: [], urls: { submit: '/tickets/5/bpom-submissions', open_registration: '/tickets/5/registration', reject: '/tickets/5/rejection', packaging: '/tickets/5/packaging', nie: '/tickets/5/nie' } };
const noLabTests = { can_manage: false, store_url: '/tickets/5/lab-tests', tests: [] };
const ticketUrls = { materials: '/tickets/5/materials', create_material: '/master/materials', upload_links: '/tickets/5/upload-links', samples: '/tickets/5/samples', resolve_reviews: '/tickets/5/reviews/resolve', contract_store: '/contracts' };
const noAccess = { manage_materials: false, create_material: false, manage_upload_links: false, record_sample: false, resolve_reviews: false, manage_contract: false, create_contract: false };
const noMaterials = { materials: [], materialOptions: null, can: noAccess, urls: ticketUrls, uploadLinks: null, samples: [], reviews: [], contract: null, contractOptions: null, invoices: [], bpom: noBpom, labTests: noLabTests };
const ticketDetail = { id: 5, code: 'PRD-2026-0001', product_name: 'Kapsul Jahe Merah', client: 'Nusantara Herbal', client_pic: 'Nadia', marketing_user: 'Rizal Marketing', dosage_form: 'Kapsul', category: 'Jamu', phase: 'awal', phase_label: 'Awal', status: 'aktif', status_label: 'Aktif', current_stage: 'Pengembangan sampel', created_by: 'Rizal Marketing', created_at: '05/11/2026 10:00' };
type ChecklistDocumentFixture = { id: number; name: string; material: string | null; scope: string; requirement: string; pic: string | null; both_phases: boolean; status: string; status_label: string; revision_reason: string | null; content_verified: boolean | null; requires_issued_at: boolean; requires_expires_at: boolean; can_upload: boolean; upload_url: string; current_version: { version: number; number: string; original_name: string; issued_at: string | null; expires_at: string | null; uploaded_at: string | null; download_url: string } | null };
function checklistDocument(overrides: Partial<ChecklistDocumentFixture> & { id: number; name: string }): ChecklistDocumentFixture {
    return { material: null, scope: 'Tiket', requirement: 'Wajib', pic: 'R&D', both_phases: false, status: 'belum_ada', status_label: 'Belum ada', revision_reason: null, content_verified: null, requires_issued_at: false, requires_expires_at: false, can_upload: false, upload_url: `/tickets/5/documents/${overrides.id}/versions`, current_version: null, ...overrides };
}

describe('tickets', () => {
    it('lists tickets with a link to their detail and hides creation from staff who cannot create', () => {
        renderPage(<Tickets {...ticketListProps} can={{ create: false }} createOptions={null} />, { ...ticketRoutes, auth: { user: staff, permissions: ['tickets.view'] } });

        expect(screen.getByRole('link', { name: 'PRD-2026-0001' })).toHaveAttribute('href', '/tickets/5');
        expect(screen.getByText('Kapsul Jahe Merah')).toBeInTheDocument();
        expect(screen.getByText('50%')).toBeInTheDocument();
        expect(screen.getByText('Perlu ditinjau')).toBeInTheDocument();
        expect(screen.getByText('Terlambat')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Buat tiket' })).not.toBeInTheDocument();
    });

    it('lets marketing create a ticket for one of their clients', async () => {
        const user = userEvent.setup();
        const requests: unknown[] = [];
        vi.spyOn(router, 'post').mockImplementation((url, data) => {
            requests.push({ url, data });
        });
        renderPage(
            <Tickets
                {...ticketListProps}
                can={{ create: true }}
                createOptions={{ clients: [{ value: 3, label: 'Nusantara Herbal' }], dosageForms: [{ value: 1, label: 'Kapsul' }], categories: [{ value: 2, label: 'Jamu' }] }}
            />,
            { ...ticketRoutes, auth: { user: marketingUser, permissions: ['tickets.view', 'tickets.create'] } },
        );

        await user.click(screen.getByRole('button', { name: 'Buat tiket' }));
        await user.selectOptions(screen.getByLabelText('Klien'), '3');
        await user.type(screen.getByLabelText('Nama produk'), 'Serbuk Temulawak');
        await user.selectOptions(screen.getByLabelText('Sediaan'), '1');
        await user.selectOptions(screen.getByLabelText('Kategori'), '2');
        await user.click(screen.getByRole('button', { name: 'Simpan tiket' }));

        expect(requests).toEqual([{ url: '/tickets', data: { client_id: '3', product_name: 'Serbuk Temulawak', dosage_form_id: '1', category_id: '2' } }]);
    });

    it('shows the checklist of each phase with its progress, the stages, and the history', () => {
        renderPage(
            <TicketShow
                ticket={{ id: 5, code: 'PRD-2026-0001', product_name: 'Kapsul Jahe Merah', client: 'Nusantara Herbal', client_pic: 'Nadia', marketing_user: 'Rizal Marketing', dosage_form: 'Kapsul', category: 'Jamu', phase: 'awal', phase_label: 'Awal', status: 'aktif', status_label: 'Aktif', current_stage: 'Pengembangan sampel', created_by: 'Rizal Marketing', created_at: '05/11/2026 10:00' }}
                checklist={[
                    { phase: 'pra_registrasi', label: 'Pra-registrasi', progress: 50, documents: [
                        checklistDocument({ id: 1, name: 'Master formula', pic: 'R&D' }),
                        checklistDocument({ id: 2, name: 'Surat kuasa', scope: 'Klien', pic: 'Klien', both_phases: true, status: 'disetujui', status_label: 'Disetujui' }),
                    ] },
                    { phase: 'registrasi', label: 'Registrasi', progress: 0, documents: [] },
                ]}
                stages={[{ name: 'Pengembangan sampel', started_at: '05/11/2026 10:00', due_at: null, finished_at: null }]}
                history={[{ id: 9, event: 'ticket.created', label: 'Tiket dibuat', actor: 'Rizal Marketing', created_at: '05/11/2026 10:00' }]}
                {...noMaterials}
            />,
            { ...ticketRoutes, auth: { user: marketingUser, permissions: ['tickets.view'] } },
        );

        expect(screen.getByRole('heading', { name: 'Kapsul Jahe Merah' })).toBeInTheDocument();
        expect(screen.getByText('PRD-2026-0001')).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Pra-registrasi · 50%' })).toBeInTheDocument();
        expect(screen.getByText('Master formula')).toBeInTheDocument();
        expect(screen.getByText('Kedua fase')).toBeInTheDocument();
        expect(screen.getByText('Belum ada dokumen di fase ini.')).toBeInTheDocument();
        expect(screen.getAllByText('Pengembangan sampel').length).toBeGreaterThan(0);
        expect(screen.getByText('Tiket dibuat')).toBeInTheDocument();
    });
});

describe('ticket documents', () => {
    it('shows the current version, the revision reason, and uploads a new version with its dates', async () => {
        const user = userEvent.setup();
        const requests: Array<{ url: string; data: Record<string, unknown> }> = [];
        vi.spyOn(router, 'post').mockImplementation((url, data) => {
            requests.push({ url: String(url), data: data as Record<string, unknown> });
        });
        renderPage(
            <TicketShow
                ticket={ticketDetail}
                checklist={[{ phase: 'pra_registrasi', label: 'Pra-registrasi', progress: 0, documents: [
                    checklistDocument({
                        id: 7, name: 'CoA produk jadi', pic: 'QA/QC', status: 'perlu_revisi', status_label: 'Perlu revisi', can_upload: true, requires_issued_at: true,
                        revision_reason: 'Umur dokumen melebihi 6 bulan sejak terbit (01/03/2026).',
                        current_version: { version: 1, number: 'COA-9', original_name: 'coa.pdf', issued_at: '01/03/2026', expires_at: null, uploaded_at: '05/11/2026 10:00', download_url: '/document-versions/3/download' },
                    }),
                ] }]}
                stages={[]}
                history={[]}
                {...noMaterials}
            />,
            { ...ticketRoutes, auth: { user: { ...staff, role: 'qa', role_label: 'QA/QC' }, permissions: ['tickets.view'] } },
        );

        expect(screen.getByText('Umur dokumen melebihi 6 bulan sejak terbit (01/03/2026).')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Unduh v1 CoA produk jadi' })).toHaveAttribute('href', '/document-versions/3/download');

        await user.click(screen.getByRole('button', { name: 'Unggah CoA produk jadi' }));
        await user.upload(screen.getByLabelText('Berkas dokumen'), new File(['isi'], 'coa-baru.pdf', { type: 'application/pdf' }));
        await user.type(screen.getByLabelText('Nomor dokumen'), 'COA-10');
        await user.type(screen.getByLabelText('Tanggal terbit'), '2026-10-20');
        expect(screen.queryByLabelText('Tanggal kedaluwarsa')).not.toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: 'Unggah versi baru' }));

        expect(requests).toHaveLength(1);
        expect(requests[0].url).toBe('/tickets/5/documents/7/versions');
        expect(requests[0].data).toMatchObject({ number: 'COA-10', issued_at: '2026-10-20', expires_at: '' });
        expect((requests[0].data.file as File).name).toBe('coa-baru.pdf');
    });

    it('hides the upload action from staff who are not the person in charge', () => {
        renderPage(
            <TicketShow ticket={ticketDetail} checklist={[{ phase: 'pra_registrasi', label: 'Pra-registrasi', progress: 0, documents: [checklistDocument({ id: 8, name: 'Master formula' })] }]} stages={[]} history={[]} {...noMaterials} />,
            { ...ticketRoutes, auth: { user: staff, permissions: ['tickets.view'] } },
        );

        expect(screen.queryByRole('button', { name: 'Unggah Master formula' })).not.toBeInTheDocument();
    });

    it('lets R&D choose the raw materials of a ticket and shows material documents per material', async () => {
        const user = userEvent.setup();
        const requests: Array<{ url: string; data: Record<string, unknown> }> = [];
        vi.spyOn(router, 'put').mockImplementation((url, data) => {
            requests.push({ url: String(url), data: data as Record<string, unknown> });
        });
        renderPage(
            <TicketShow
                ticket={ticketDetail}
                checklist={[{ phase: 'pra_registrasi', label: 'Pra-registrasi', progress: 0, documents: [checklistDocument({ id: 9, name: 'Asal peredaran bahan', material: 'Jahe merah', scope: 'Bahan baku' })] }]}
                stages={[]}
                history={[]}
                materials={[{ id: 1, name: 'Jahe merah', supplier: 'CV Rempah' }]}
                materialOptions={[{ value: 1, label: 'Jahe merah (CV Rempah)' }, { value: 2, label: 'Kunyit' }]}
                can={{ ...noAccess, manage_materials: true, create_material: true }}
                urls={ticketUrls}
                uploadLinks={null}
                samples={[]}
                reviews={[]}
                contract={null}
                contractOptions={null}
                invoices={[]}
                bpom={noBpom}
                labTests={noLabTests}
            />,
            { ...ticketRoutes, auth: { user: { ...staff, role: 'rnd', role_label: 'R&D' }, permissions: ['tickets.view', 'ticket_materials.manage', 'materials.create'] } },
        );

        expect(screen.getByText('Jahe merah · CV Rempah')).toBeInTheDocument();
        expect(screen.getByText('· Jahe merah')).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Atur bahan baku' }));
        expect(screen.getByRole('checkbox', { name: 'Jahe merah (CV Rempah)' })).toBeChecked();
        await user.click(screen.getByRole('checkbox', { name: 'Kunyit' }));
        await user.click(screen.getByRole('button', { name: 'Simpan 2 bahan' }));

        expect(requests).toEqual([{ url: '/tickets/5/materials', data: { material_ids: [1, 2] } }]);
    });
});

describe('client upload links', () => {
    it('lets marketing create a link for a phase, shows the new link once, and revokes an active link', async () => {
        const user = userEvent.setup();
        const posts: unknown[] = [];
        const deletes: unknown[] = [];
        vi.spyOn(router, 'post').mockImplementation((url, data) => {
            posts.push({ url, data });
        });
        vi.spyOn(router, 'delete').mockImplementation((url) => {
            deletes.push(url);
        });
        renderPage(
            <TicketShow
                ticket={ticketDetail}
                checklist={[]}
                stages={[]}
                history={[]}
                {...noMaterials}
                can={{ ...noAccess, manage_upload_links: true }}
                uploadLinks={{ default_days: 14, max_days: 60, active: [{ id: 3, phase_label: 'Pra-registrasi', expires_at: '19/11/2026 10:00', created_by: 'Rizal Marketing', last_used_at: null, revoke_url: '/tickets/5/upload-links/3' }] }}
            />,
            { ...ticketRoutes, flash: { status: null, upload_link: 'http://localhost/unggah/abc' }, auth: { user: marketingUser, permissions: ['tickets.view', 'upload_links.manage'] } },
        );

        expect(screen.getByLabelText('Tautan unggah baru')).toHaveTextContent('http://localhost/unggah/abc');
        expect(screen.getByText(/belum dipakai/)).toBeInTheDocument();

        await user.selectOptions(screen.getByLabelText('Fase'), 'registrasi');
        await user.clear(screen.getByLabelText('Berlaku (hari)'));
        await user.type(screen.getByLabelText('Berlaku (hari)'), '7');
        await user.click(screen.getByRole('button', { name: 'Buat tautan' }));
        await user.click(screen.getByRole('button', { name: 'Cabut tautan Pra-registrasi' }));

        expect(posts).toEqual([{ url: '/tickets/5/upload-links', data: { phase: 'registrasi', days: '7' } }]);
        expect(deletes).toEqual(['/tickets/5/upload-links/3']);
    });

    it('shows the client only their documents and uploads a new version without login', async () => {
        const user = userEvent.setup();
        const requests: Array<{ url: string; data: Record<string, unknown> }> = [];
        vi.spyOn(router, 'post').mockImplementation((url, data) => {
            requests.push({ url: String(url), data: data as Record<string, unknown> });
        });
        renderPage(
            <UploadLink
                ticket={{ code: 'PRD-2026-0001', product_name: 'Kapsul Jahe Merah', client: 'Nusantara Herbal', client_pic: 'Nadia', marketing_user: 'Rizal Marketing', phase_label: 'Pra-registrasi', is_active: true }}
                expires_at="19/11/2026 10:00"
                progress={50}
                contract={null}
                invoices={[]}
                documents={[
                    { id: 4, name: 'Surat kuasa', status: 'disetujui', status_label: 'Disetujui', revision_reason: null, requires_issued_at: false, requires_expires_at: false, can_upload: false, upload_url: '/unggah/abc/dokumen/4', current_version: { version: 1, original_name: 'kuasa.pdf', uploaded_at: '05/11/2026 10:00' } },
                    { id: 5, name: 'Sertifikat merek', status: 'perlu_revisi', status_label: 'Perlu revisi', revision_reason: 'Dokumen sudah kedaluwarsa pada 01/10/2026.', requires_issued_at: false, requires_expires_at: true, can_upload: true, upload_url: '/unggah/abc/dokumen/5', current_version: null },
                ]}
            />,
        );

        expect(screen.getByRole('heading', { name: 'Halo, Nadia.' })).toBeInTheDocument();
        expect(screen.getByText('Dokumen sudah kedaluwarsa pada 01/10/2026.')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Unggah Surat kuasa' })).not.toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Unggah Sertifikat merek' }));
        await user.upload(screen.getByLabelText('Berkas dokumen'), new File(['isi'], 'merek.pdf', { type: 'application/pdf' }));
        await user.type(screen.getByLabelText('Nomor dokumen'), 'IDM-01');
        await user.type(screen.getByLabelText('Tanggal kedaluwarsa'), '2030-01-01');
        await user.click(screen.getByRole('button', { name: 'Kirim dokumen' }));

        expect(requests).toHaveLength(1);
        expect(requests[0].url).toBe('/unggah/abc/dokumen/5');
        expect(requests[0].data).toMatchObject({ number: 'IDM-01', expires_at: '2030-01-01' });
    });

    it('explains an expired link without showing any ticket data', () => {
        renderPage(<UploadLinkExpired />);

        expect(screen.getByRole('heading', { name: 'Tautan tidak berlaku' })).toBeInTheDocument();
        expect(screen.queryByText(/PRD-/)).not.toBeInTheDocument();
    });
});

describe('sample approvals', () => {
    it('lists tickets with their latest client decision', () => {
        renderPage(
            <Samples tickets={{ data: [{ id: 5, code: 'PRD-2026-0001', product_name: 'Kapsul Jahe Merah', client: 'Nusantara Herbal', current_stage: 'Pengembangan sampel', revisions: 2, decision: 'perlu_revisi', decision_label: 'Perlu revisi', decided_at: '05/11/2026', show_url: '/tickets/5' }], current_page: 1, last_page: 1, total: 1, prev_page_url: null, next_page_url: null }} />,
            { ...ticketRoutes, auth: { user: marketingUser, permissions: ['samples.record'] } },
        );

        expect(screen.getByRole('link', { name: 'PRD-2026-0001' })).toHaveAttribute('href', '/tickets/5');
        expect(screen.getByText('Perlu revisi')).toBeInTheDocument();
    });

    it('lets marketing record a revision with the client note and shows the history', async () => {
        const user = userEvent.setup();
        const requests: unknown[] = [];
        vi.spyOn(router, 'post').mockImplementation((url, data) => {
            requests.push({ url, data });
        });
        renderPage(
            <TicketShow
                ticket={ticketDetail}
                checklist={[]}
                stages={[]}
                history={[]}
                {...noMaterials}
                can={{ ...noAccess, record_sample: true }}
                samples={[{ id: 1, revision: 1, decision: 'perlu_revisi', decision_label: 'Perlu revisi', note: 'Rasa terlalu pahit.', recorded_by: 'Rizal Marketing', recorded_at: '01/11/2026 09:00' }]}
            />,
            { ...ticketRoutes, auth: { user: marketingUser, permissions: ['tickets.view', 'samples.record'] } },
        );

        expect(screen.getByText('Rasa terlalu pahit.')).toBeInTheDocument();
        await user.type(screen.getByLabelText('Catatan klien'), 'Warna perlu lebih terang.');
        await user.click(screen.getByRole('button', { name: 'Perlu revisi' }));

        expect(requests).toEqual([{ url: '/tickets/5/samples', data: { decision: 'perlu_revisi', note: 'Warna perlu lebih terang.' } }]);
    });
});

describe('ticket reviews', () => {
    it('warns that a ticket needs review and lets regulatory affairs resolve it', async () => {
        const user = userEvent.setup();
        const requests: unknown[] = [];
        vi.spyOn(router, 'post').mockImplementation((url) => {
            requests.push(url);
        });
        renderPage(
            <TicketShow
                ticket={ticketDetail}
                checklist={[]}
                stages={[]}
                history={[]}
                {...noMaterials}
                can={{ ...noAccess, resolve_reviews: true }}
                reviews={[{ id: 1, trigger_label: 'Versi baru dokumen bersama', message: 'Dokumen bersama Surat kuasa mendapat versi 2.', flagged_at: '05/11/2026 10:00' }]}
            />,
            { ...ticketRoutes, auth: { user: staff, permissions: ['tickets.view', 'ticket_reviews.resolve'] } },
        );

        expect(screen.getByText('Dokumen bersama Surat kuasa mendapat versi 2.')).toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: 'Tandai sudah ditinjau' }));

        expect(requests).toEqual(['/tickets/5/reviews/resolve']);
    });
});

const contractDetail = {
    id: 3, number: 'KTR/2026/0001', status: 'ditandatangani', status_label: 'Ditandatangani', service_value: 30000000, dp_percent: 50, payment_due_days: 7,
    document_template_id: 2, template: 'Kontrak maklon', dp_amount: 15000000, settlement_amount: 15000000, is_generated: true, generation_error: null,
    signed_at: '06/11/2026 09:00', confirmed_at: null, files: { pdf: '/contracts/3/files/pdf', signed: '/contracts/3/files/signed' },
    urls: { update: '/contracts/3', send: '/contracts/3/send', signed: '/contracts/3/signed', confirm: '/contracts/3/confirm' },
};

describe('contracts', () => {
    it('creates a contract for an eligible ticket with the default fifty percent down payment', async () => {
        const user = userEvent.setup();
        const requests: unknown[] = [];
        vi.spyOn(router, 'post').mockImplementation((url, data) => {
            requests.push({ url, data });
        });
        renderPage(
            <Contracts
                contracts={{ data: [], current_page: 1, last_page: 1, total: 0, prev_page_url: null, next_page_url: null }}
                filters={{ status: '' }}
                statuses={[{ value: 'draf', label: 'Draf' }]}
                createOptions={{ tickets: [{ value: 5, label: 'PRD-2026-0001 · Kapsul Jahe Merah' }], templates: [{ value: 2, label: 'Kontrak maklon' }], defaults: { dp_percent: 50, payment_due_days: 7 } }}
                urls={{ store: '/contracts' }}
            />,
            { ...ticketRoutes, auth: { user: marketingUser, permissions: ['contracts.manage'] } },
        );

        await user.click(screen.getByRole('button', { name: 'Buat kontrak' }));
        await user.selectOptions(screen.getByLabelText('Tiket'), '5');
        await user.selectOptions(screen.getByLabelText('Templat kontrak'), '2');
        await user.type(screen.getByLabelText('Nilai jasa (Rp)'), '30000000');
        expect(screen.getByText('DP Rp15.000.000 · pelunasan Rp15.000.000')).toBeInTheDocument();
        await user.click(screen.getAllByRole('button', { name: 'Buat kontrak' }).at(-1)!);

        expect(requests).toEqual([{ url: '/contracts', data: { ticket_id: 5, document_template_id: 2, service_value: 30000000, dp_percent: 50, payment_due_days: 7 } }]);
    });

    it('lets marketing confirm a signed contract from the ticket page', async () => {
        const user = userEvent.setup();
        const requests: unknown[] = [];
        vi.spyOn(router, 'post').mockImplementation((url) => {
            requests.push(url);
        });
        renderPage(
            <TicketShow ticket={ticketDetail} checklist={[]} stages={[]} history={[]} {...noMaterials} can={{ ...noAccess, manage_contract: true }} contract={contractDetail} contractOptions={{ templates: [], defaults: { dp_percent: 50, payment_due_days: 7 } }} />,
            { ...ticketRoutes, auth: { user: marketingUser, permissions: ['tickets.view', 'contracts.manage'] } },
        );

        expect(screen.getByRole('heading', { name: 'Kontrak KTR/2026/0001' })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Bertanda tangan' })).toHaveAttribute('href', '/contracts/3/files/signed');
        await user.click(screen.getByRole('button', { name: 'Konfirmasi kontrak aktif' }));

        expect(requests).toEqual(['/contracts/3/confirm']);
    });

    it('lets the client download the contract and upload the signed copy', async () => {
        const user = userEvent.setup();
        const requests: Array<{ url: string; data: Record<string, unknown> }> = [];
        vi.spyOn(router, 'post').mockImplementation((url, data) => {
            requests.push({ url: String(url), data: data as Record<string, unknown> });
        });
        renderPage(
            <UploadLink
                ticket={{ code: 'PRD-2026-0001', product_name: 'Kapsul Jahe Merah', client: 'Nusantara Herbal', client_pic: 'Nadia', marketing_user: 'Rizal Marketing', phase_label: 'Pra-registrasi', is_active: true }}
                expires_at="19/11/2026 10:00"
                progress={0}
                documents={[]}
                invoices={[]}
                contract={{ number: 'KTR/2026/0001', status: 'dikirim', status_label: 'Dikirim', signed_at: null, can_sign: true, download_url: '/unggah/abc/kontrak', upload_url: '/unggah/abc/kontrak' }}
            />,
        );

        expect(screen.getByRole('link', { name: 'Unduh kontrak' })).toHaveAttribute('href', '/unggah/abc/kontrak');
        await user.upload(screen.getByLabelText('Kontrak bertanda tangan'), new File(['ttd'], 'kontrak-ttd.pdf', { type: 'application/pdf' }));
        await user.click(screen.getByRole('button', { name: 'Kirim kontrak bertanda tangan' }));

        expect(requests).toHaveLength(1);
        expect(requests[0].url).toBe('/unggah/abc/kontrak');
        expect((requests[0].data.file as File).name).toBe('kontrak-ttd.pdf');
    });
});

describe('invoices', () => {
    const invoice = { id: 9, number: 'INV/2026/0001', term_label: 'DP 50%', amount: 15000000, due_date: '01/11/2026', status: 'terbit', status_label: 'Belum dibayar', is_overdue: true, paid_at: null, payment_type: null, ticket_code: 'PRD-2026-0001', client: 'Nusantara Herbal', ticket_url: '/tickets/5' };

    it('shows finance the outstanding total and flags overdue invoices', () => {
        renderPage(
            <Invoices invoices={{ data: [invoice], current_page: 1, last_page: 1, total: 1, prev_page_url: null, next_page_url: null }} filters={{ search: '', status: '' }} statuses={[{ value: 'lunas', label: 'Lunas' }]} summary={{ outstanding: 15000000, unpaid_count: 1, overdue_count: 1 }} />,
            { ...ticketRoutes, auth: { user: { ...staff, role: 'finance', role_label: 'Finance' }, permissions: ['invoices.view'] } },
        );

        expect(screen.getAllByText('Rp15.000.000').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Lewat jatuh tempo').length).toBeGreaterThan(0);
        expect(screen.getByRole('link', { name: 'PRD-2026-0001' })).toHaveAttribute('href', '/tickets/5');
    });

    it('lets the client start a Midtrans payment for an unpaid invoice', async () => {
        const user = userEvent.setup();
        const requests: unknown[] = [];
        vi.spyOn(router, 'post').mockImplementation((url) => {
            requests.push(url);
        });
        renderPage(
            <UploadLink
                ticket={{ code: 'PRD-2026-0001', product_name: 'Kapsul Jahe Merah', client: 'Nusantara Herbal', client_pic: 'Nadia', marketing_user: 'Rizal Marketing', phase_label: 'Pra-registrasi', is_active: true }}
                expires_at="19/11/2026 10:00"
                progress={0}
                documents={[]}
                contract={null}
                invoices={[{ ...invoice, is_overdue: false, pay_url: '/unggah/abc/tagihan/9/bayar' }, { ...invoice, id: 10, number: 'INV/2026/0002', status: 'lunas', status_label: 'Lunas', is_overdue: false, paid_at: '02/11/2026 10:00', pay_url: null }]}
            />,
        );

        expect(screen.getAllByRole('button', { name: 'Bayar sekarang' })).toHaveLength(1);
        await user.click(screen.getByRole('button', { name: 'Bayar sekarang' }));

        expect(requests).toEqual(['/unggah/abc/tagihan/9/bayar']);
    });
});

describe('receivables', () => {
    it('summarises receivables per client and links the CSV export', () => {
        renderPage(
            <Receivables
                clients={[{ client_id: 1, client: 'Nusantara Herbal', invoices: 2, billed: 30000000, paid: 15000000, outstanding: 15000000, overdue: 15000000, oldest_due: '01/11/2026' }]}
                totals={{ billed: 30000000, paid: 15000000, outstanding: 15000000, overdue: 15000000 }}
                filters={{ search: '', from: '', until: '' }}
                urls={{ export: '/receivables/export' }}
            />,
            { ...ticketRoutes, auth: { user: { ...staff, role: 'finance', role_label: 'Finance' }, permissions: ['invoices.view'] } },
        );

        expect(screen.getByText('Nusantara Herbal')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Ekspor CSV' })).toHaveAttribute('href', '/receivables/export');
        expect(screen.getByText('01/11/2026')).toBeInTheDocument();
    });
});

describe('document verification', () => {
    const item = {
        id: 4, ticket_code: 'PRD-2026-0001', product_name: 'Kapsul Jahe Merah', ticket_url: '/tickets/5', name: 'CoA produk jadi', material: null, phase_label: 'Pra-registrasi', pic: 'QA/QC', content_verified: true,
        version: { version: 2, number: 'COA-10', issued_at: '20/10/2026', expires_at: null, uploaded_at: '05/11/2026 10:00', uploaded_by: 'Petugas QA', download_url: '/document-versions/7/download' },
        token: 'a'.repeat(64), review_url: '/ticket-documents/4/reviews/pemakaian',
    };

    it('lets regulatory affairs approve a document with the token of the loaded state', async () => {
        const user = userEvent.setup();
        const requests: unknown[] = [];
        vi.spyOn(router, 'post').mockImplementation((url, data) => {
            requests.push({ url, data });
        });
        renderPage(<Verification level="pemakaian" title="Verifikasi dokumen" description="Antrean RA" items={[item]} />, { ...ticketRoutes, auth: { user: staff, permissions: ['documents.approve_usage'] } });

        expect(screen.getByText('Isi terverifikasi QA')).toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: 'Setujui CoA produk jadi PRD-2026-0001' }));

        expect(requests).toEqual([{ url: '/ticket-documents/4/reviews/pemakaian', data: { decision: 'disetujui', note: '', token: 'a'.repeat(64) } }]);
    });

    it('requires a reason when returning a document for revision', async () => {
        const user = userEvent.setup();
        const requests: unknown[] = [];
        vi.spyOn(router, 'post').mockImplementation((url, data) => {
            requests.push({ url, data });
        });
        renderPage(<Verification level="pemakaian" title="Verifikasi dokumen" description="Antrean RA" items={[item]} />, { ...ticketRoutes, auth: { user: staff, permissions: ['documents.approve_usage'] } });

        await user.click(screen.getByRole('button', { name: 'Kembalikan CoA produk jadi PRD-2026-0001' }));
        await user.type(screen.getByLabelText('Alasan revisi'), 'Nomor batch tidak sesuai.');
        await user.click(screen.getByRole('button', { name: 'Kembalikan dokumen' }));

        expect(requests).toEqual([{ url: '/ticket-documents/4/reviews/pemakaian', data: { decision: 'perlu_revisi', note: 'Nomor batch tidak sesuai.', token: 'a'.repeat(64) } }]);
    });
});

describe('bpom', () => {
    it('lists submissions by nearest deadline and warns about a third additional data request', () => {
        renderPage(
            <Bpom submissions={[{ id: 1, ticket_code: 'PRD-2026-0001', product_name: 'Kapsul Jahe Merah', client: 'Nusantara Herbal', ticket_url: '/tickets/5', phase_label: 'Registrasi', submitted_on: '01/11/2026', pnbp: 3000000, status_label: 'Tambahan data TD3', deadline: '01/02/2027', deadline_passed: false, rejection_risk: true }]} />,
            { ...ticketRoutes, auth: { user: staff, permissions: ['bpom.manage'] } },
        );

        expect(screen.getByRole('link', { name: 'PRD-2026-0001' })).toHaveAttribute('href', '/tickets/5');
        expect(screen.getByText('Risiko ditolak (TD3)')).toBeInTheDocument();
    });

    it('shows the unmet registration requirements and records a submission', async () => {
        const user = userEvent.setup();
        const requests: unknown[] = [];
        vi.spyOn(router, 'post').mockImplementation((url, data) => {
            requests.push({ url, data });
        });
        renderPage(
            <TicketShow ticket={{ ...ticketDetail, phase: 'pra_registrasi', phase_label: 'Pra-registrasi' }} checklist={[]} stages={[]} history={[]} {...noMaterials}
                bpom={{ ...noBpom, can_manage: true, can_submit: true, registration_blockers: ['Pengajuan pra-registrasi belum dicatat.'] }} />,
            { ...ticketRoutes, auth: { user: staff, permissions: ['tickets.view', 'bpom.manage'] } },
        );

        expect(screen.getByText('Pengajuan pra-registrasi belum dicatat.')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Buka fase registrasi' })).toBeDisabled();
        await user.type(screen.getByLabelText('Tanggal pengajuan'), '2026-11-05');
        await user.type(screen.getByLabelText('PNBP (Rp)'), '3000000');
        await user.click(screen.getByRole('button', { name: 'Catat pengajuan pra-registrasi' }));

        expect(requests).toEqual([{ url: '/tickets/5/bpom-submissions', data: { submitted_on: '2026-11-05', pnbp: '3000000' } }]);
    });
});

describe('lab tests and NIE', () => {
    it('records a re-analysis after a non-conforming lab result', async () => {
        const user = userEvent.setup();
        const requests: unknown[] = [];
        vi.spyOn(router, 'post').mockImplementation((url, data) => {
            requests.push({ url, data });
        });
        renderPage(
            <TicketShow ticket={ticketDetail} checklist={[]} stages={[]} history={[]} {...noMaterials}
                labTests={{ can_manage: true, store_url: '/tickets/5/lab-tests', tests: [{ id: 1, attempt: 1, laboratory: 'Lab Uji Nusantara', sent_on: '01/10/2026', estimated_on: '15/10/2026', result_on: '14/10/2026', conforms: false, note: 'Cemaran mikroba melebihi batas.', file_url: '/lab-tests/1/file', result_url: '/lab-tests/1/result' }] }} />,
            { ...ticketRoutes, auth: { user: staff, permissions: ['tickets.view', 'lab_tests.manage'] } },
        );

        expect(screen.getByText('Cemaran mikroba melebihi batas.')).toBeInTheDocument();
        await user.type(screen.getByLabelText('Laboratorium'), 'Lab Uji Nusantara');
        await user.type(screen.getByLabelText('Tanggal kirim'), '2026-11-01');
        await user.type(screen.getByLabelText('Perkiraan selesai'), '2026-11-14');
        await user.click(screen.getByRole('button', { name: 'Catat analisis ulang' }));

        expect(requests).toEqual([{ url: '/tickets/5/lab-tests', data: { laboratory: 'Lab Uji Nusantara', sent_on: '2026-11-01', estimated_on: '2026-11-14' } }]);
    });

    it('records the NIE that closes the ticket', async () => {
        const user = userEvent.setup();
        const requests: unknown[] = [];
        vi.spyOn(router, 'post').mockImplementation((url, data) => {
            requests.push({ url, data });
        });
        renderPage(
            <TicketShow ticket={ticketDetail} checklist={[]} stages={[]} history={[]} {...noMaterials}
                bpom={{ ...noBpom, can_manage: true, can_complete: true, packaging_requested_on: '01/11/2026', packaging_uploaded_on: '03/11/2026' }} />,
            { ...ticketRoutes, auth: { user: staff, permissions: ['tickets.view', 'bpom.manage'] } },
        );

        await user.type(screen.getByLabelText('Nomor NIE'), 'TR263012345');
        await user.type(screen.getByLabelText('Tanggal terbit NIE'), '2026-11-05');
        await user.click(screen.getByRole('button', { name: 'Simpan NIE dan tutup tiket' }));

        expect(requests).toEqual([{ url: '/tickets/5/nie', data: { nie_number: 'TR263012345', nie_issued_on: '2026-11-05' } }]);
    });

    it('lists tickets awaiting the NIE with their packaging design status', () => {
        renderPage(<Nie tickets={[{ id: 5, code: 'PRD-2026-0001', product_name: 'Kapsul Jahe Merah', client: 'Nusantara Herbal', submitted_on: '01/10/2026', packaging_requested_on: '01/11/2026', packaging_uploaded_on: null, ticket_url: '/tickets/5' }]} />,
            { ...ticketRoutes, auth: { user: staff, permissions: ['bpom.manage'] } });

        expect(screen.getByText('Diminta 01/11/2026')).toBeInTheDocument();
    });
});

describe('notifications', () => {
    it('lists notifications with links to the related screen and marks all as read', async () => {
        const user = userEvent.setup();
        const requests: unknown[] = [];
        vi.spyOn(router, 'post').mockImplementation((url) => {
            requests.push(url);
        });
        renderPage(
            <Notifications
                notifications={{ data: [{ id: 'n1', kind: 'eskalasi_sla', title: 'Dokumen pra-registrasi melewati tenggat', body: 'PRD-2026-0001 · tertunda di pihak internal.', ticket_code: 'PRD-2026-0001', read: false, created_at: '06/11/2026 09:00', open_url: '/notifications/n1/open' }], current_page: 1, last_page: 1, total: 1, prev_page_url: null, next_page_url: null }}
                unread={1}
                urls={{ read_all: '/notifications/read' }}
            />,
            { ...ticketRoutes, routes: { ...ticketRoutes.routes, notifications: '/notifications' }, workspace: { search_available: false, whatsapp_connected: false, notifications_available: true, notifications_unread: 1 }, auth: { user: staff, permissions: [] } },
        );

        expect(screen.getByRole('link', { name: /Dokumen pra-registrasi melewati tenggat/ })).toHaveAttribute('href', '/notifications/n1/open');
        expect(screen.getByRole('link', { name: 'Buka notifikasi, 1 belum dibaca' })).toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: 'Tandai semua dibaca (1)' }));

        expect(requests).toEqual(['/notifications/read']);
    });
});

describe('priority queue', () => {
    it('shows the ranked tickets with their criteria values and reason', () => {
        renderPage(
            <Priorities queue="ra" queue_label="Regulatory Affairs" queues={null} computed_at="06/11/2026 10:00"
                criteria={[{ code: 'C1', name: 'Critical Ratio', weight: 0.048, type: 'Cost' }]}
                rows={[{ rank: 1, ticket_id: 5, code: 'PRD-2026-0001', product_name: 'Kapsul Jahe Merah', client: 'Nusantara Herbal', phase_label: 'Pra-registrasi', current_stage: 'Dokumen pra-registrasi', c1: -0.5, c2: 2, c3: 50, c4: 10, score: 0.8123, reason: 'Tenggat BPOM tinggal 2 hari kerja.', ticket_url: '/tickets/5' }]} />,
            { ...ticketRoutes, auth: { user: staff, permissions: ['priorities.view'] } },
        );

        expect(screen.getByRole('heading', { name: 'Antrean prioritas Regulatory Affairs' })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'PRD-2026-0001' })).toHaveAttribute('href', '/tickets/5');
        expect(screen.getByText('0.8123')).toBeInTheDocument();
        expect(screen.getByText('Tenggat BPOM tinggal 2 hari kerja.')).toBeInTheDocument();
    });
});

describe('duration report', () => {
    it('separates internal, client, and external time and links the export', () => {
        renderPage(
            <Reports stages={[{ stage: 'Kontrak (MoU)', sla_days: 1, count: 2, average: 1.5, p75: 2, internal: 1, client: 2, external: 0 }]}
                totals={{ internal: 1, client: 2, external: 0 }} filters={{ from: '2026-08-01', until: '2026-11-05' }} urls={{ export: '/reports/export?from=2026-08-01&until=2026-11-05' }} />,
            { ...ticketRoutes, auth: { user: staff, permissions: ['reports.view'] } },
        );

        expect(screen.getByText('Kontrak (MoU)')).toBeInTheDocument();
        expect(screen.getByText('1.5')).toHaveClass('text-red-700');
        expect(screen.getByRole('link', { name: 'Ekspor CSV' })).toHaveAttribute('href', '/reports/export?from=2026-08-01&until=2026-11-05');
    });
});

describe('audit log', () => {
    it('offers exports with the active filters and reports a broken hash chain', async () => {
        const user = userEvent.setup();
        const requests: unknown[] = [];
        vi.spyOn(router, 'post').mockImplementation((url) => {
            requests.push(url);
        });
        renderPage(
            <Audit entries={{ data: [], current_page: 1, last_page: 1, total: 0, prev_page_url: null, next_page_url: null }} actors={[]}
                filters={{ actor_id: '', event: '', ticket_code: 'PRD-2026-0001', date_from: '', date_to: '' }}
                urls={{ csv: '/audit/export/csv?ticket_code=PRD-2026-0001', pdf: '/audit/export/pdf?ticket_code=PRD-2026-0001', verify: '/audit/verify' }}
                integrity={{ checked: 12, valid: false, mismatches: [{ id: 7, event: 'ticket.created', created_at: '05/11/2026 10:00:00', reason: 'isi tidak sesuai hash' }], verified_at: '06/11/2026 09:00:00' }} />,
            { ...ticketRoutes, routes: { ...ticketRoutes.routes, audit: '/audit' }, auth: { user: admin, permissions: ['audit.view', 'users.manage'] } },
        );

        expect(screen.getByRole('link', { name: 'Ekspor CSV' })).toHaveAttribute('href', '/audit/export/csv?ticket_code=PRD-2026-0001');
        expect(screen.getByRole('link', { name: 'Ekspor PDF' })).toHaveAttribute('href', '/audit/export/pdf?ticket_code=PRD-2026-0001');
        expect(screen.getByText(/#7 ticket.created/)).toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: 'Verifikasi integritas' }));

        expect(requests).toEqual(['/audit/verify']);
    });
});

describe('checklist sync', () => {
    it('lists the differences of each ticket and syncs it', async () => {
        const user = userEvent.setup();
        const requests: unknown[] = [];
        vi.spyOn(router, 'post').mockImplementation((url) => {
            requests.push(url);
        });
        renderPage(
            <ChecklistSync tickets={[{ id: 5, code: 'PRD-2026-0001', product_name: 'Kapsul Jahe Merah', client: 'Nusantara Herbal', add: ['Uji stabilitas'], remove: ['Formulir lama'], ticket_url: '/tickets/5', sync_url: '/tickets/5/checklist-sync' }]} />,
            { ...ticketRoutes, auth: { user: staff, permissions: ['document_rules.manage'] } },
        );

        expect(screen.getByText('Uji stabilitas')).toBeInTheDocument();
        expect(screen.getByText('Formulir lama')).toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: 'Sinkronkan PRD-2026-0001' }));

        expect(requests).toEqual(['/tickets/5/checklist-sync']);
    });
});
