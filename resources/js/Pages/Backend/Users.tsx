import { useForm, usePage } from '@inertiajs/react';
import { Plus, Users as UsersIcon } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import type { BackendSharedProps, Paginated, StaffUser } from '../../backend-types';
import { BackendPagination } from '../../components/backend-pagination';
import { Badge, Button, EmptyState, Field, Modal, PageHeader, Panel, Select } from '../../components/ui';
import { BackendLayout } from '../../layouts/backend-layout';
import type { Role } from '../../types';

type RoleOption = { value: Role; label: string };
type Filters = { search: string; role: string; status: string };

export default function Users({ users, roles, filters }: { users: Paginated<StaffUser>; roles: RoleOption[]; filters: Filters }) {
    const { routes } = usePage<BackendSharedProps>().props;
    const search = useForm(filters);
    const [editor, setEditor] = useState<{ user: StaffUser | null } | null>(null);

    function filter(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        search.get(routes.users, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <BackendLayout title="Pengguna dan role">
            <PageHeader title="Pengguna dan role" description="Kelola akun staf, hak akses, status aktif, dan jadwal cuti."><Button icon={Plus} onClick={() => setEditor({ user: null })}>Tambah pengguna</Button></PageHeader>
            <form onSubmit={filter} className="filter-bar flex-wrap">
                <Field label="Cari pengguna" error={search.errors.search}><input type="search" value={search.data.search} onChange={(event) => search.setData('search', event.target.value)} placeholder="Nama atau email" /></Field>
                <Field label="Role" error={search.errors.role}><Select value={search.data.role} onChange={(event) => search.setData('role', event.target.value)}><option value="">Semua role</option>{roles.map((role) => <option key={role.value} value={role.value}>{role.label}</option>)}</Select></Field>
                <Field label="Status" error={search.errors.status}><Select value={search.data.status} onChange={(event) => search.setData('status', event.target.value)}><option value="">Semua status</option><option value="active">Aktif</option><option value="inactive">Nonaktif</option><option value="unavailable">Sedang cuti</option></Select></Field>
                <Button type="submit" variant="secondary" disabled={search.processing}>Terapkan filter</Button>
            </form>
            <Panel>
                {users.data.length ? (
                    <div className="overflow-x-auto">
                        <table className="w-full"><thead><tr><th>Pengguna</th><th>Role</th><th>Status</th><th>Cuti</th><th>Terakhir masuk</th><th>Aksi</th></tr></thead>
                            <tbody>{users.data.map((user) => <tr key={user.id}>
                                <td><strong>{user.name}</strong><div className="text-xs text-gray-500">{user.email}</div></td>
                                <td>{user.role_label ?? 'Belum ditetapkan'}</td>
                                <td><Badge tone={user.is_active ? 'green' : 'neutral'}>{user.is_active ? 'Aktif' : 'Nonaktif'}</Badge></td>
                                <td>{user.unavailable_from ? `${formatDate(user.unavailable_from)} – ${formatDate(user.unavailable_until)}` : '—'}{user.unavailable && <div><Badge tone="amber">Sedang cuti</Badge></div>}</td>
                                <td>{user.last_login_at ?? 'Belum pernah'}</td>
                                <td><Button variant="ghost" aria-label={`Ubah ${user.name}`} onClick={() => setEditor({ user })}>Ubah</Button></td>
                            </tr>)}</tbody>
                        </table>
                    </div>
                ) : <EmptyState title="Tidak ada pengguna yang sesuai" description="Ubah filter atau tambahkan akun staf baru." icon={UsersIcon} />}
                <BackendPagination page={users} />
            </Panel>
            {editor && <UserEditor key={editor.user?.id ?? 'new'} user={editor.user} roles={roles} onClose={() => setEditor(null)} />}
        </BackendLayout>
    );
}

function UserEditor({ user, roles, onClose }: { user: StaffUser | null; roles: RoleOption[]; onClose: () => void }) {
    const { auth, routes } = usePage<BackendSharedProps>().props;
    const form = useForm({ name: user?.name ?? '', email: user?.email ?? '', role: user?.role ?? 'marketing', is_active: user?.is_active ?? true, unavailable_from: user?.unavailable_from ?? '', unavailable_until: user?.unavailable_until ?? '', password: '', password_confirmation: '' });
    const ownAccount = user?.id === auth.user?.id;

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const options = { onSuccess: onClose, onFinish: () => form.reset('password', 'password_confirmation') };
        if (user) form.put(user.update_url, options);
        else form.post(routes.usersStore, options);
    }

    return (
        <Modal title={user ? 'Ubah pengguna' : 'Tambah pengguna'} description="Klien tidak memiliki akun internal. Setiap perubahan dicatat dalam audit." onClose={onClose}>
            <form onSubmit={submit} className="form-stack">
                <Field label="Nama" error={form.errors.name}><input autoFocus required maxLength={255} value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></Field>
                <Field label="Email pengguna" error={form.errors.email}><input type="email" autoComplete="off" required maxLength={255} value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} /></Field>
                <Field label="Role pengguna" error={form.errors.role}><Select disabled={ownAccount} value={form.data.role} onChange={(event) => form.setData('role', event.target.value as Role)}>{roles.map((role) => <option value={role.value} key={role.value}>{role.label}</option>)}</Select></Field>
                <Field label={user ? 'Kata sandi baru (opsional)' : 'Kata sandi pengguna'} error={form.errors.password} hint="Minimal 12 karakter, berisi huruf dan angka; maksimal 72 byte."><input type="password" autoComplete="new-password" required={!user} value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} /></Field>
                <Field label="Konfirmasi kata sandi" error={form.errors.password_confirmation}><input type="password" autoComplete="new-password" required={Boolean(form.data.password)} value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} /></Field>
                <Field label="Mulai cuti" error={form.errors.unavailable_from}><input type="date" value={form.data.unavailable_from} onChange={(event) => form.setData('unavailable_from', event.target.value)} /></Field>
                <Field label="Akhir cuti" error={form.errors.unavailable_until}><input type="date" value={form.data.unavailable_until} onChange={(event) => form.setData('unavailable_until', event.target.value)} /></Field>
                <label className="flex items-center gap-2"><input type="checkbox" checked={form.data.is_active} disabled={ownAccount} onChange={(event) => form.setData('is_active', event.target.checked)} />Akun aktif</label>
                {form.errors.is_active && <p role="alert" className="text-red-700">{form.errors.is_active}</p>}
                {ownAccount && <p className="text-xs text-gray-500">Role dan status akun sendiri tidak dapat diubah.</p>}
                <div className="form-footer"><Button variant="secondary" onClick={onClose} disabled={form.processing}>Batal</Button><Button type="submit" disabled={form.processing}>{form.processing ? 'Menyimpan...' : 'Simpan pengguna'}</Button></div>
            </form>
        </Modal>
    );
}

function formatDate(value: string | null) {
    return value ? value.split('-').reverse().join('/') : '—';
}
