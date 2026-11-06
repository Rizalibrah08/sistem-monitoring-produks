import { Link, useForm } from '@inertiajs/react';
import { FileSignature, Plus } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import type { Paginated } from '../../backend-types';
import { BackendPagination } from '../../components/backend-pagination';
import { ContractForm, rupiah, type ContractDefaults, type ContractOption } from '../../components/contract-form';
import { Badge, Button, EmptyState, Field, Modal, PageHeader, Panel, Select } from '../../components/ui';
import { BackendLayout } from '../../layouts/backend-layout';

type ContractRow = {
    id: number;
    number: string;
    ticket_code: string;
    product_name: string;
    client: string;
    service_value: number;
    dp_percent: number;
    status: string;
    status_label: string;
    show_url: string;
};

export const contractTones: Record<string, string> = { draf: 'neutral', dikirim: 'blue', ditandatangani: 'amber', aktif: 'green' };

export default function Contracts({ contracts, filters, statuses, createOptions, urls }: {
    contracts: Paginated<ContractRow>;
    filters: { status: string };
    statuses: { value: string; label: string }[];
    createOptions: { tickets: ContractOption[]; templates: ContractOption[]; defaults: ContractDefaults };
    urls: { store: string };
}) {
    const [creating, setCreating] = useState(false);
    const filter = useForm({ status: filters.status });

    function applyFilter(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        filter.get(window.location.pathname, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <BackendLayout title="Kontrak">
            <PageHeader title="Daftar kontrak" eyebrow="OPERASIONAL" description="Kontrak (MoU) untuk setiap pendaftaran produk; MoU yang dikonfirmasi membuka fase pra-registrasi.">
                <Button icon={Plus} onClick={() => setCreating(true)}>Buat kontrak</Button>
            </PageHeader>
            <form onSubmit={applyFilter} className="filter-bar flex-wrap">
                <Field label="Status"><Select value={filter.data.status} onChange={(event) => filter.setData('status', event.target.value)}><option value="">Semua status</option>{statuses.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}</Select></Field>
                <Button type="submit" variant="secondary">Terapkan filter</Button>
            </form>
            <Panel>
                {contracts.data.length ? (
                    <div className="overflow-x-auto">
                        <table className="w-full"><thead><tr><th>Nomor kontrak</th><th>Tiket</th><th>Klien</th><th>Nilai jasa</th><th>Status</th></tr></thead>
                            <tbody>{contracts.data.map((contract) => <tr key={contract.id}>
                                <td><strong>{contract.number}</strong></td>
                                <td><Link href={contract.show_url} className="font-semibold">{contract.ticket_code}</Link><div className="text-xs text-gray-500">{contract.product_name}</div></td>
                                <td>{contract.client}</td>
                                <td>{rupiah(contract.service_value)}<div className="text-xs text-gray-500">DP {contract.dp_percent}%</div></td>
                                <td><Badge tone={contractTones[contract.status]}>{contract.status_label}</Badge></td>
                            </tr>)}</tbody>
                        </table>
                    </div>
                ) : <EmptyState title="Belum ada kontrak" description="Kontrak dibuat setelah sampel disetujui klien." icon={FileSignature} />}
                <BackendPagination page={contracts} />
            </Panel>
            {creating && <Modal title="Buat kontrak" description="Berkas Word dan PDF dibuat otomatis dari templat sebagai pratinjau." onClose={() => setCreating(false)}>
                <ContractForm url={urls.store} tickets={createOptions.tickets} templates={createOptions.templates} submitLabel="Buat kontrak" onCancel={() => setCreating(false)}
                    initial={{ ticket_id: '', document_template_id: '', service_value: '', ...createOptions.defaults }} />
            </Modal>}
        </BackendLayout>
    );
}
