import { Link } from '@inertiajs/react';
import { Package } from 'lucide-react';
import { rupiah } from '../../components/contract-form';
import { Badge, EmptyState, PageHeader, Panel } from '../../components/ui';
import { BackendLayout } from '../../layouts/backend-layout';

type SubmissionRow = {
    id: number;
    ticket_code: string;
    product_name: string;
    client: string;
    ticket_url: string;
    phase_label: string;
    submitted_on: string;
    pnbp: number;
    status_label: string;
    deadline: string | null;
    deadline_passed: boolean;
    rejection_risk: boolean;
};

export default function Bpom({ submissions }: { submissions: SubmissionRow[] }) {
    return (
        <BackendLayout title="Pengajuan BPOM">
            <PageHeader title="Pengajuan dan catatan BPOM" eyebrow="REGULATORY AFFAIRS" description="Pengajuan yang menunggu hasil, tambahan data yang belum dipenuhi, dan tenggat registrasi, diurutkan dari tenggat terdekat." />
            <Panel>
                {submissions.length ? (
                    <div className="overflow-x-auto">
                        <table className="w-full"><thead><tr><th>Tiket</th><th>Fase</th><th>Tanggal pengajuan</th><th>PNBP</th><th>Tenggat</th><th>Status</th></tr></thead>
                            <tbody>{submissions.map((submission) => <tr key={submission.id}>
                                <td><Link href={submission.ticket_url} className="font-semibold">{submission.ticket_code}</Link><div className="text-xs text-gray-500">{submission.product_name} · {submission.client}</div></td>
                                <td>{submission.phase_label}</td>
                                <td>{submission.submitted_on}</td>
                                <td>{rupiah(submission.pnbp)}</td>
                                <td className={submission.deadline_passed ? 'text-red-700' : ''}>{submission.deadline ?? '—'}</td>
                                <td><span className="inline-flex flex-wrap gap-1"><Badge tone="blue">{submission.status_label}</Badge>{submission.rejection_risk && <Badge tone="red">Risiko ditolak (TD3)</Badge>}</span></td>
                            </tr>)}</tbody>
                        </table>
                    </div>
                ) : <EmptyState title="Belum ada pengajuan BPOM" description="Pengajuan dicatat dari detail tiket setelah semua dokumen fase disetujui." icon={Package} />}
            </Panel>
        </BackendLayout>
    );
}
