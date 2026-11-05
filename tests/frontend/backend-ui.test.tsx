import { App, router } from '@inertiajs/react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import Login from '../../resources/js/Pages/Auth/Login';
import Dashboard from '../../resources/js/Pages/Backend/Dashboard';
import Master from '../../resources/js/Pages/Backend/Master';
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
const masterUrls = { dosageForms: '/master/dosage-forms', categories: '/master/categories', materials: '/master/materials', stages: '/master/stages', holidays: '/master/holidays' };
const phases = [
    { value: 'awal', label: 'Awal' },
    { value: 'pra_registrasi', label: 'Pra-registrasi' },
    { value: 'registrasi', label: 'Registrasi' },
    { value: 'kedua_fase', label: 'Kedua fase' },
];
const masterProps = { dosageForms: [], categories: [], materials: [], stages: [], phases, holidays: [], urls: masterUrls };
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
});
