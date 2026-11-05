import { Link, useForm, usePage } from '@inertiajs/react';
import { Plus, Ticket as TicketIcon } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import type { BackendSharedProps, Paginated } from '../../backend-types';
import { BackendPagination } from '../../components/backend-pagination';
import { Badge, Button, EmptyState, Field, Modal, PageHeader, Panel, Progress, Select } from '../../components/ui';
import { BackendLayout } from '../../layouts/backend-layout';

type Option = { value: string; label: string };
type IdOption = { value: number; label: string };
type CreateOptions = { clients: IdOption[]; dosageForms: IdOption[]; categories: IdOption[] };
type TicketRow = {
    id: number;
    code: string;
    product_name: string;
    client: string;
    dosage_form: string;
    category: string;
    phase: string;
    phase_label: string;
    status: string;
    status_label: string;
    current_stage: string | null;
    progress: number;
    created_at: string | null;
    show_url: string;
};
type Filters = { search: string; phase: string; status: string };

export default function Tickets({ tickets, filters, phases, statuses, can, createOptions, urls }: { tickets: Paginated<TicketRow>; filters: Filters; phases: Option[]; statuses: Option[]; can: { create: boolean }; createOptions: CreateOptions | null; urls: { store: string; clients: string } }) {
    const { routes } = usePage<BackendSharedProps>().props;
    const search = useForm(filters);
    const [creating, setCreating] = useState(false);

    function filter(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        search.get(routes.tickets, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <BackendLayout title="Daftar tiket">
            <PageHeader title="Daftar tiket" eyebrow="RUANG KERJA" description="Satu tiket untuk setiap produk yang didaftarkan ke BPOM.">{can.create && <Button icon={Plus} onClick={() => setCreating(true)}>Buat tiket</Button>}</PageHeader>
            <form onSubmit={filter} className="filter-bar flex-wrap">
                <Field label="Cari tiket" error={search.errors.search}><input type="search" value={search.data.search} onChange={(event) => search.setData('search', event.target.value)} placeholder="Kode, produk, atau klien" /></Field>
                <Field label="Fase" error={search.errors.phase}><Select value={search.data.phase} onChange={(event) => search.setData('phase', event.target.value)}><option value="">Semua fase</option>{phases.map((phase) => <option key={phase.value} value={phase.value}>{phase.label}</option>)}</Select></Field>
                <Field label="Status" error={search.errors.status}><Select value={search.data.status} onChange={(event) => search.setData('status', event.target.value)}><option value="">Semua status</option>{statuses.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}</Select></Field>
                <Button type="submit" variant="secondary" disabled={search.processing}>Terapkan filter</Button>
            </form>
            <Panel>
                {tickets.data.length ? (
                    <div className="overflow-x-auto">
                        <table className="w-full"><thead><tr><th>Kode</th><th>Produk</th><th>Klien</th><th>Fase</th><th>Tahap</th><th>Kelengkapan</th><th>Status</th></tr></thead>
                            <tbody>{tickets.data.map((ticket) => <tr key={ticket.id}>
                                <td><Link href={ticket.show_url} className="font-semibold">{ticket.code}</Link><div className="text-xs text-gray-500">{ticket.created_at}</div></td>
                                <td><strong>{ticket.product_name}</strong><div className="text-xs text-gray-500">{ticket.dosage_form} · {ticket.category}</div></td>
                                <td>{ticket.client}</td>
                                <td>{ticket.phase_label}</td>
                                <td>{ticket.current_stage ?? '—'}</td>
                                <td><Progress value={ticket.progress} /></td>
                                <td><Badge tone={ticket.status === 'aktif' ? 'blue' : ticket.status === 'selesai' ? 'green' : 'red'}>{ticket.status_label}</Badge></td>
                            </tr>)}</tbody>
                        </table>
                    </div>
                ) : <EmptyState title="Belum ada tiket yang sesuai" description="Tiket baru dibuat oleh Marketing setelah klien mengajukan produk." icon={TicketIcon} />}
                <BackendPagination page={tickets} />
            </Panel>
            {creating && createOptions && <TicketCreator options={createOptions} storeUrl={urls.store} clientsUrl={urls.clients} onClose={() => setCreating(false)} />}
        </BackendLayout>
    );
}

function TicketCreator({ options, storeUrl, clientsUrl, onClose }: { options: CreateOptions; storeUrl: string; clientsUrl: string; onClose: () => void }) {
    const form = useForm({ client_id: '', product_name: '', dosage_form_id: '', category_id: '' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(storeUrl, { onSuccess: onClose });
    }

    return (
        <Modal title="Buat tiket" description="Kode tiket dan checklist dokumen kedua fase dibuat otomatis sesuai aturan yang berlaku hari ini." onClose={onClose}>
            <form onSubmit={submit} className="form-stack">
                <Field label="Klien" error={form.errors.client_id} hint={options.clients.length ? undefined : 'Belum ada klien yang Anda tangani.'}><Select value={form.data.client_id} onChange={(event) => form.setData('client_id', event.target.value)}><option value="">Pilih klien</option>{options.clients.map((client) => <option key={client.value} value={client.value}>{client.label}</option>)}</Select></Field>
                <p className="text-sm text-gray-500">Klien belum terdaftar? <Link href={clientsUrl} className="font-semibold">Tambah di Data klien</Link>.</p>
                <Field label="Nama produk" error={form.errors.product_name}><input required maxLength={150} value={form.data.product_name} onChange={(event) => form.setData('product_name', event.target.value)} /></Field>
                <Field label="Sediaan" error={form.errors.dosage_form_id}><Select value={form.data.dosage_form_id} onChange={(event) => form.setData('dosage_form_id', event.target.value)}><option value="">Pilih sediaan</option>{options.dosageForms.map((form) => <option key={form.value} value={form.value}>{form.label}</option>)}</Select></Field>
                <Field label="Kategori" error={form.errors.category_id}><Select value={form.data.category_id} onChange={(event) => form.setData('category_id', event.target.value)}><option value="">Pilih kategori</option>{options.categories.map((category) => <option key={category.value} value={category.value}>{category.label}</option>)}</Select></Field>
                <div className="form-footer"><Button variant="secondary" onClick={onClose} disabled={form.processing}>Batal</Button><Button type="submit" disabled={form.processing}>{form.processing ? 'Menyimpan...' : 'Simpan tiket'}</Button></div>
            </form>
        </Modal>
    );
}
