import { Link } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { Badge, EmptyState, PageHeader, Panel } from '../../components/ui';
import { BackendLayout } from '../../layouts/backend-layout';

type Escalation = { id: number; ticket_code: string; product_name: string; client: string; stage: string; due_at: string | null; overdue_at: string; by_client: boolean; ticket_url: string };

export default function Escalations({ escalations }: { escalations: Escalation[] }) {
    return (
        <BackendLayout title="Eskalasi">
            <PageHeader title="Daftar eskalasi" eyebrow="PEMANTAUAN" description="Tahap tiket aktif yang melewati tenggat SLA. Keterlambatan karena klien dilaporkan terpisah dan tidak dieskalasikan." />
            <Panel>
                {escalations.length ? (
                    <div className="overflow-x-auto">
                        <table className="w-full"><thead><tr><th>Tiket</th><th>Tahap</th><th>Tenggat</th><th>Penyebab</th></tr></thead>
                            <tbody>{escalations.map((row) => <tr key={row.id}>
                                <td><Link href={row.ticket_url} className="font-semibold">{row.ticket_code}</Link><div className="text-xs text-gray-500">{row.product_name} · {row.client}</div></td>
                                <td>{row.stage}</td>
                                <td>{row.due_at}<div className="text-xs text-gray-500">terlambat sejak {row.overdue_at}</div></td>
                                <td><Badge tone={row.by_client ? 'amber' : 'red'}>{row.by_client ? 'Menunggu klien' : 'Internal'}</Badge></td>
                            </tr>)}</tbody>
                        </table>
                    </div>
                ) : <EmptyState title="Tidak ada tahap yang terlambat" description="Tahap yang melewati tenggat SLA akan muncul di sini." icon={Bell} />}
            </Panel>
        </BackendLayout>
    );
}
