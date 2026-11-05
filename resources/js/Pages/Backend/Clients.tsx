import { useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Users as UsersIcon } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import type { BackendSharedProps, Paginated } from '../../backend-types';
import { BackendPagination } from '../../components/backend-pagination';
import { Button, EmptyState, Field, Modal, PageHeader, Panel, Select } from '../../components/ui';
import { BackendLayout } from '../../layouts/backend-layout';

type Option = { value: number; label: string };
type ClientRecord = {
    id: number;
    name: string;
    company: string | null;
    pic_name: string;
    whatsapp: string;
    email: string | null;
    marketing_user_id: number;
    marketing_user: string;
    update_url: string;
};

export default function Clients({ clients, marketingStaff, filters, urls }: { clients: Paginated<ClientRecord>; marketingStaff: Option[]; filters: { search: string }; urls: { store: string } }) {
    const { routes } = usePage<BackendSharedProps>().props;
    const search = useForm(filters);
    const [editor, setEditor] = useState<{ client: ClientRecord | null } | null>(null);

    function filter(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        search.get(routes.clients, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <BackendLayout title="Data klien">
            <PageHeader title="Data klien" eyebrow="OPERASIONAL" description="Pemilik merek beserta kontak PIC. Nomor WhatsApp dipakai chatbot dan inbox untuk mengenali klien."><Button icon={Plus} onClick={() => setEditor({ client: null })}>Tambah klien</Button></PageHeader>
            <form onSubmit={filter} className="filter-bar flex-wrap">
                <Field label="Cari klien" error={search.errors.search}><input type="search" value={search.data.search} onChange={(event) => search.setData('search', event.target.value)} placeholder="Nama, perusahaan, PIC, atau nomor" /></Field>
                <Button type="submit" variant="secondary" disabled={search.processing}>Cari</Button>
            </form>
            <Panel>
                {clients.data.length ? (
                    <div className="overflow-x-auto">
                        <table className="w-full"><thead><tr><th>Klien</th><th>PIC klien</th><th>WhatsApp</th><th>Email</th><th>PIC Marketing</th><th>Aksi</th></tr></thead>
                            <tbody>{clients.data.map((client) => <tr key={client.id}>
                                <td><strong>{client.name}</strong>{client.company && <div className="text-xs text-gray-500">{client.company}</div>}</td>
                                <td>{client.pic_name}</td>
                                <td>{formatWhatsapp(client.whatsapp)}</td>
                                <td>{client.email ?? '—'}</td>
                                <td>{client.marketing_user}</td>
                                <td><Button variant="ghost" icon={Pencil} aria-label={`Ubah ${client.name}`} onClick={() => setEditor({ client })}>Ubah</Button></td>
                            </tr>)}</tbody>
                        </table>
                    </div>
                ) : <EmptyState title="Belum ada klien yang sesuai" description="Tambahkan klien baru atau ubah kata kunci pencarian." icon={UsersIcon} />}
                <BackendPagination page={clients} />
            </Panel>
            {editor && <ClientEditor key={editor.client?.id ?? 'new'} client={editor.client} marketingStaff={marketingStaff} storeUrl={urls.store} onClose={() => setEditor(null)} />}
        </BackendLayout>
    );
}

function ClientEditor({ client, marketingStaff, storeUrl, onClose }: { client: ClientRecord | null; marketingStaff: Option[]; storeUrl: string; onClose: () => void }) {
    const { auth } = usePage<BackendSharedProps>().props;
    const form = useForm({
        name: client?.name ?? '',
        company: client?.company ?? '',
        pic_name: client?.pic_name ?? '',
        whatsapp: client ? `0${client.whatsapp.slice(2)}` : '',
        email: client?.email ?? '',
        marketing_user_id: String(client?.marketing_user_id ?? (auth.user?.role === 'marketing' ? auth.user.id : '')),
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (client) form.put(client.update_url, { preserveScroll: true, onSuccess: onClose });
        else form.post(storeUrl, { preserveScroll: true, onSuccess: onClose });
    }

    return (
        <Modal title={client ? 'Ubah klien' : 'Tambah klien'} description="Setiap perubahan data klien, termasuk nomor WhatsApp, dicatat dalam audit." onClose={onClose}>
            <form onSubmit={submit} className="form-stack">
                <Field label="Nama klien" error={form.errors.name}><input autoFocus required maxLength={150} value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} placeholder="Nama merek atau pemilik merek" /></Field>
                <Field label="Perusahaan" error={form.errors.company} hint="Opsional."><input maxLength={150} value={form.data.company} onChange={(event) => form.setData('company', event.target.value)} /></Field>
                <Field label="Nama PIC klien" error={form.errors.pic_name}><input required maxLength={150} value={form.data.pic_name} onChange={(event) => form.setData('pic_name', event.target.value)} /></Field>
                <Field label="Nomor WhatsApp" error={form.errors.whatsapp} hint="Nomor Indonesia, misalnya 0812xxxxxxxx. Satu nomor hanya untuk satu klien."><input type="tel" required inputMode="tel" value={form.data.whatsapp} onChange={(event) => form.setData('whatsapp', event.target.value)} /></Field>
                <Field label="Email" error={form.errors.email} hint="Opsional."><input type="email" maxLength={255} value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} /></Field>
                <Field label="PIC Marketing" error={form.errors.marketing_user_id}><Select value={form.data.marketing_user_id} onChange={(event) => form.setData('marketing_user_id', event.target.value)}><option value="">Pilih PIC Marketing</option>{marketingStaff.map((staff) => <option key={staff.value} value={staff.value}>{staff.label}</option>)}</Select></Field>
                <div className="form-footer"><Button variant="secondary" onClick={onClose} disabled={form.processing}>Batal</Button><Button type="submit" disabled={form.processing}>{form.processing ? 'Menyimpan...' : 'Simpan'}</Button></div>
            </form>
        </Modal>
    );
}

function formatWhatsapp(number: string) {
    const local = number.startsWith('62') ? number.slice(2) : number;
    return `+62 ${local.slice(0, 3)}-${local.slice(3, 7)}-${local.slice(7)}`;
}
