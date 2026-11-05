import { App, router } from '@inertiajs/react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import Login from '../../resources/js/Pages/Auth/Login';
import Clients from '../../resources/js/Pages/Backend/Clients';
import Dashboard from '../../resources/js/Pages/Backend/Dashboard';
import DocumentRules from '../../resources/js/Pages/Backend/DocumentRules';
import Master from '../../resources/js/Pages/Backend/Master';
import Templates from '../../resources/js/Pages/Backend/Templates';
import TicketShow from '../../resources/js/Pages/Backend/TicketShow';
import Tickets from '../../resources/js/Pages/Backend/Tickets';
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

    it('does not show demo tickets or fabricated operational statistics on the real dashboard', () => {
        renderPage(<Dashboard />, {
            auth: { user: staff, permissions: ['dashboard.view'] },
            navigation: [{ label: 'Dasbor', href: '/dashboard' }],
        });

        expect(screen.getByRole('heading', { name: /^Dasbor$/ })).toBeInTheDocument();
        expect(screen.getByText(/modul tiket dan dokumen belum/i)).toBeInTheDocument();
        expect(screen.queryByText('PRD-2026-0012')).not.toBeInTheDocument();
        expect(screen.queryByText('Kapsul sambiloto')).not.toBeInTheDocument();
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

const ticketRow = { id: 5, code: 'PRD-2026-0001', product_name: 'Kapsul Jahe Merah', client: 'Nusantara Herbal', dosage_form: 'Kapsul', category: 'Jamu', phase: 'awal', phase_label: 'Awal', status: 'aktif', status_label: 'Aktif', current_stage: 'Pengembangan sampel', progress: 50, created_at: '05/11/2026', show_url: '/tickets/5' };
const ticketListProps = {
    tickets: { data: [ticketRow], current_page: 1, last_page: 1, total: 1, prev_page_url: null, next_page_url: null },
    filters: { search: '', phase: '', status: '' },
    phases: [{ value: 'awal', label: 'Awal' }, { value: 'pra_registrasi', label: 'Pra-registrasi' }],
    statuses: [{ value: 'aktif', label: 'Aktif' }],
    urls: { store: '/tickets', clients: '/clients' },
};
const ticketRoutes = { routes: { tickets: '/tickets' } };
const ticketDetail = { id: 5, code: 'PRD-2026-0001', product_name: 'Kapsul Jahe Merah', client: 'Nusantara Herbal', client_pic: 'Nadia', marketing_user: 'Rizal Marketing', dosage_form: 'Kapsul', category: 'Jamu', phase: 'awal', phase_label: 'Awal', status: 'aktif', status_label: 'Aktif', current_stage: 'Pengembangan sampel', created_by: 'Rizal Marketing', created_at: '05/11/2026 10:00' };
type ChecklistDocumentFixture = { id: number; name: string; scope: string; requirement: string; pic: string | null; both_phases: boolean; status: string; status_label: string; revision_reason: string | null; requires_issued_at: boolean; requires_expires_at: boolean; can_upload: boolean; upload_url: string; current_version: { version: number; number: string; original_name: string; issued_at: string | null; expires_at: string | null; uploaded_at: string | null; download_url: string } | null };
function checklistDocument(overrides: Partial<ChecklistDocumentFixture> & { id: number; name: string }): ChecklistDocumentFixture {
    return { scope: 'Tiket', requirement: 'Wajib', pic: 'R&D', both_phases: false, status: 'belum_ada', status_label: 'Belum ada', revision_reason: null, requires_issued_at: false, requires_expires_at: false, can_upload: false, upload_url: `/tickets/5/documents/${overrides.id}/versions`, current_version: null, ...overrides };
}

describe('tickets', () => {
    it('lists tickets with a link to their detail and hides creation from staff who cannot create', () => {
        renderPage(<Tickets {...ticketListProps} can={{ create: false }} createOptions={null} />, { ...ticketRoutes, auth: { user: staff, permissions: ['tickets.view'] } });

        expect(screen.getByRole('link', { name: 'PRD-2026-0001' })).toHaveAttribute('href', '/tickets/5');
        expect(screen.getByText('Kapsul Jahe Merah')).toBeInTheDocument();
        expect(screen.getByText('50%')).toBeInTheDocument();
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
            <TicketShow ticket={ticketDetail} checklist={[{ phase: 'pra_registrasi', label: 'Pra-registrasi', progress: 0, documents: [checklistDocument({ id: 8, name: 'Master formula' })] }]} stages={[]} history={[]} />,
            { ...ticketRoutes, auth: { user: staff, permissions: ['tickets.view'] } },
        );

        expect(screen.queryByRole('button', { name: 'Unggah Master formula' })).not.toBeInTheDocument();
    });
});
