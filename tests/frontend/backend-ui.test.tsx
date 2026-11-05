import { App, router } from '@inertiajs/react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import Login from '../../resources/js/Pages/Auth/Login';
import Dashboard from '../../resources/js/Pages/Backend/Dashboard';
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
