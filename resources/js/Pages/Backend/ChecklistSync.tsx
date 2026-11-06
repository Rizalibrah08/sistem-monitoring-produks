import { Link, router } from '@inertiajs/react';
import { ClipboardCheck, RefreshCw } from 'lucide-react';
import { Badge, Button, EmptyState, PageHeader, Panel } from '../../components/ui';
import { BackendLayout } from '../../layouts/backend-layout';

export type ChecklistDifference = { id: number; code: string; product_name: string; client: string; add: string[]; remove: string[]; ticket_url: string; sync_url: string };

export function DifferenceList({ difference }: { difference: Pick<ChecklistDifference, 'add' | 'remove'> }) {
    return (
        <div className="space-y-1 text-sm">
            {difference.add.map((name) => <div key={`add-${name}`}><Badge tone="green" dot={false}>Tambah</Badge> {name}</div>)}
            {difference.remove.map((name) => <div key={`remove-${name}`}><Badge tone="amber" dot={false}>Lepas</Badge> {name}</div>)}
        </div>
    );
}

export default function ChecklistSync({ tickets }: { tickets: ChecklistDifference[] }) {
    return (
        <BackendLayout title="Sinkronisasi checklist">
            <PageHeader title="Sinkronisasi checklist" eyebrow="REGULATORY AFFAIRS" description="Tiket aktif yang checklist-nya berbeda dengan jenis dokumen dan aturan yang berlaku hari ini. Butir yang sudah berisi berkas dan fase yang sudah diajukan ke BPOM tidak diubah." />
            <Panel>
                {tickets.length ? (
                    <div className="overflow-x-auto">
                        <table className="w-full"><thead><tr><th>Tiket</th><th>Perubahan</th><th>Aksi</th></tr></thead>
                            <tbody>{tickets.map((ticket) => <tr key={ticket.id}>
                                <td><Link href={ticket.ticket_url} className="font-semibold">{ticket.code}</Link><div className="text-xs text-gray-500">{ticket.product_name} · {ticket.client}</div></td>
                                <td><DifferenceList difference={ticket} /></td>
                                <td><Button icon={RefreshCw} aria-label={`Sinkronkan ${ticket.code}`} onClick={() => router.post(ticket.sync_url, {}, { preserveScroll: true })}>Sinkronkan</Button></td>
                            </tr>)}</tbody>
                        </table>
                    </div>
                ) : <EmptyState title="Semua checklist sudah sesuai aturan terbaru" description="Tiket muncul di sini saat jenis dokumen atau aturan dokumen berubah." icon={ClipboardCheck} />}
            </Panel>
        </BackendLayout>
    );
}
