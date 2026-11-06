import { Link, router } from '@inertiajs/react';
import { Activity } from 'lucide-react';
import { EmptyState, Field, PageHeader, Panel, Select } from '../../components/ui';
import { BackendLayout } from '../../layouts/backend-layout';

type PriorityRow = {
    rank: number;
    ticket_id: number;
    code: string;
    product_name: string;
    client: string;
    phase_label: string;
    current_stage: string | null;
    c1: number;
    c2: number;
    c3: number;
    c4: number;
    score: number;
    reason: string;
    ticket_url: string;
};
type CriterionInfo = { code: string; name: string; weight: number; type: string };

export default function Priorities({ queue, queue_label, queues, criteria, rows, computed_at }: {
    queue: string;
    queue_label: string;
    queues: { value: string; label: string }[] | null;
    criteria: CriterionInfo[];
    rows: PriorityRow[];
    computed_at: string;
}) {
    return (
        <BackendLayout title="Antrean prioritas">
            <PageHeader title={`Antrean prioritas ${queue_label}`} eyebrow="RUANG KERJA" description={`Urutan AHP–TOPSIS, dihitung ulang ${computed_at}. Tiket yang menunggu BPOM tidak masuk antrean.`} />
            {queues && <div className="filter-bar"><Field label="Antrean"><Select value={queue} onChange={(event) => router.get(window.location.pathname, { queue: event.target.value }, { preserveScroll: true, replace: true })}>{queues.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</Select></Field></div>}
            <Panel>
                <p className="panel-body text-xs text-gray-500">{criteria.map((criterion) => `${criterion.code} ${criterion.name} (${criterion.type}, bobot ${criterion.weight})`).join(' · ')}</p>
                {rows.length ? (
                    <div className="overflow-x-auto">
                        <table className="w-full"><thead><tr><th>Peringkat</th><th>Tiket</th><th>C1</th><th>C2</th><th>C3</th><th>C4</th><th>Skor</th></tr></thead>
                            <tbody>{rows.map((row) => <tr key={row.ticket_id}>
                                <td><strong>{row.rank}</strong></td>
                                <td><Link href={row.ticket_url} className="font-semibold">{row.code}</Link><div className="text-xs text-gray-500">{row.product_name} · {row.client} · {row.current_stage ?? row.phase_label}</div><div className="text-xs">{row.reason}</div></td>
                                <td className={row.c1 < 0 ? 'text-red-700' : ''}>{row.c1.toFixed(2)}</td>
                                <td>{row.c2}</td>
                                <td>{row.c3}%</td>
                                <td>{row.c4}</td>
                                <td><strong>{row.score.toFixed(4)}</strong></td>
                            </tr>)}</tbody>
                        </table>
                    </div>
                ) : <EmptyState title="Belum ada antrean prioritas" description="Tiket aktif yang tidak sedang menunggu BPOM akan diurutkan di sini." icon={Activity} />}
            </Panel>
        </BackendLayout>
    );
}
