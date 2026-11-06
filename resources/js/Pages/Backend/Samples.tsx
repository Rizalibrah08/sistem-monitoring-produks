import { Link } from '@inertiajs/react';
import { FlaskConical } from 'lucide-react';
import type { Paginated } from '../../backend-types';
import { BackendPagination } from '../../components/backend-pagination';
import { Badge, EmptyState, PageHeader, Panel } from '../../components/ui';
import { BackendLayout } from '../../layouts/backend-layout';

type SampleRow = {
    id: number;
    code: string;
    product_name: string;
    client: string;
    current_stage: string | null;
    revisions: number;
    decision: string | null;
    decision_label: string;
    decided_at: string | null;
    show_url: string;
};

export const sampleTones: Record<string, string> = { disetujui: 'green', perlu_revisi: 'amber' };

export default function Samples({ tickets }: { tickets: Paginated<SampleRow> }) {
    return (
        <BackendLayout title="Persetujuan sampel">
            <PageHeader title="Persetujuan sampel" eyebrow="PENGEMBANGAN PRODUK" description="Catat keputusan klien agar setiap revisi sampel dapat ditelusuri." />
            <Panel>
                {tickets.data.length ? (
                    <div className="overflow-x-auto">
                        <table className="w-full"><thead><tr><th>Tiket</th><th>Produk</th><th>Klien</th><th>Tahap</th><th>Revisi</th><th>Keputusan klien</th></tr></thead>
                            <tbody>{tickets.data.map((ticket) => <tr key={ticket.id}>
                                <td><Link href={ticket.show_url} className="font-semibold">{ticket.code}</Link></td>
                                <td><strong>{ticket.product_name}</strong></td>
                                <td>{ticket.client}</td>
                                <td>{ticket.current_stage ?? '—'}</td>
                                <td>{ticket.revisions}</td>
                                <td><Badge tone={ticket.decision ? sampleTones[ticket.decision] : 'neutral'}>{ticket.decision_label}</Badge>{ticket.decided_at && <div className="text-xs text-gray-500">{ticket.decided_at}</div>}</td>
                            </tr>)}</tbody>
                        </table>
                    </div>
                ) : <EmptyState title="Belum ada sampel untuk ditinjau" description="Tiket aktif di fase awal muncul di sini sampai sampelnya disetujui klien." icon={FlaskConical} />}
                <BackendPagination page={tickets} />
            </Panel>
        </BackendLayout>
    );
}
