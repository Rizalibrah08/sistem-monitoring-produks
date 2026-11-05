import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft, FileText } from 'lucide-react';
import type { BackendSharedProps } from '../../backend-types';
import { Badge, EmptyState, InfoBox, Panel, Progress } from '../../components/ui';
import { BackendLayout } from '../../layouts/backend-layout';

type TicketDetail = {
    id: number;
    code: string;
    product_name: string;
    client: string;
    client_pic: string;
    marketing_user: string;
    dosage_form: string;
    category: string;
    phase: string;
    phase_label: string;
    status: string;
    status_label: string;
    current_stage: string | null;
    created_by: string;
    created_at: string | null;
};
type ChecklistDocument = { id: number; name: string; scope: string; requirement: string; pic: string | null; both_phases: boolean; status: string; status_label: string };
type ChecklistPhase = { phase: string; label: string; progress: number; documents: ChecklistDocument[] };
type StageEntry = { name: string; started_at: string; due_at: string | null; finished_at: string | null };
type HistoryEntry = { id: number; event: string; label: string; actor: string; created_at: string | null };

const statusTones: Record<string, string> = { belum_ada: 'neutral', diunggah: 'blue', sedang_diverifikasi: 'amber', perlu_revisi: 'red', disetujui: 'green' };

export default function TicketShow({ ticket, checklist, stages, history }: { ticket: TicketDetail; checklist: ChecklistPhase[]; stages: StageEntry[]; history: HistoryEntry[] }) {
    const { routes } = usePage<BackendSharedProps>().props;

    return (
        <BackendLayout title={`${ticket.code} · ${ticket.product_name}`}>
            <Link href={routes.tickets} className="mb-3 inline-flex items-center gap-1 text-sm text-gray-500"><ArrowLeft size={14} />Daftar tiket</Link>
            <div className="ticket-heading mb-5">
                <p className="text-sm text-gray-500">{ticket.code}</p>
                <h1 className="text-2xl font-semibold">{ticket.product_name}</h1>
                <div className="mt-2 flex flex-wrap gap-2"><Badge tone="blue">{ticket.phase_label}</Badge><Badge>{ticket.current_stage ?? 'Belum ada tahap'}</Badge><Badge tone={ticket.status === 'aktif' ? 'blue' : ticket.status === 'selesai' ? 'green' : 'red'}>{ticket.status_label}</Badge></div>
            </div>
            <div className="two-column">
                <Panel title="Informasi produk">
                    <dl className="panel-body grid grid-cols-2 gap-3 text-sm">
                        <dt className="text-gray-500">Klien</dt><dd>{ticket.client}</dd>
                        <dt className="text-gray-500">PIC klien</dt><dd>{ticket.client_pic}</dd>
                        <dt className="text-gray-500">PIC Marketing</dt><dd>{ticket.marketing_user}</dd>
                        <dt className="text-gray-500">Sediaan</dt><dd>{ticket.dosage_form}</dd>
                        <dt className="text-gray-500">Kategori</dt><dd>{ticket.category}</dd>
                        <dt className="text-gray-500">Dibuat</dt><dd>{ticket.created_at} oleh {ticket.created_by}</dd>
                    </dl>
                </Panel>
                <Panel title="Tahapan">
                    <ol className="panel-body space-y-3">
                        {stages.map((stage, index) => <li key={`${stage.name}-${index}`}>
                            <strong>{stage.name}</strong>
                            <div className="text-xs text-gray-500">Mulai {stage.started_at}{stage.due_at ? ` · tenggat ${stage.due_at}` : ''}{stage.finished_at ? ` · selesai ${stage.finished_at}` : ' · berjalan'}</div>
                        </li>)}
                    </ol>
                </Panel>
            </div>
            <div className="mt-5 two-column">
                {checklist.map((phase) => (
                    <Panel key={phase.phase} title={`${phase.label} · ${phase.progress}%`}>
                        <div className="panel-body"><Progress value={phase.progress} showLabel={false} /></div>
                        {phase.documents.length ? (
                            <div className="overflow-x-auto">
                                <table className="w-full"><thead><tr><th>Dokumen</th><th>PIC</th><th>Status</th></tr></thead>
                                    <tbody>{phase.documents.map((document) => <tr key={document.id}>
                                        <td><strong>{document.name}</strong><div className="mt-1 flex flex-wrap gap-1"><Badge dot={false}>{document.requirement}</Badge><Badge dot={false}>{document.scope}</Badge>{document.both_phases && <Badge dot={false} tone="blue">Kedua fase</Badge>}</div></td>
                                        <td>{document.pic ?? '—'}</td>
                                        <td><Badge tone={statusTones[document.status]}>{document.status_label}</Badge></td>
                                    </tr>)}</tbody>
                                </table>
                            </div>
                        ) : <EmptyState title="Belum ada dokumen di fase ini." description="Checklist mengikuti jenis dokumen wajib dan aturan dokumen saat tiket dibuat." icon={FileText} />}
                    </Panel>
                ))}
            </div>
            <div className="mt-5"><InfoBox>Unggah dan verifikasi dokumen akan tersedia pada tahap berikutnya. Dokumen yang berlaku untuk kedua fase cukup disiapkan sekali.</InfoBox></div>
            <div className="mt-5">
                <Panel title="Riwayat">
                    <ul className="panel-body space-y-2 text-sm">
                        {history.map((entry) => <li key={entry.id}><strong>{entry.label}</strong> <span className="text-gray-500">· {entry.actor} · {entry.created_at}</span></li>)}
                    </ul>
                </Panel>
            </div>
        </BackendLayout>
    );
}
