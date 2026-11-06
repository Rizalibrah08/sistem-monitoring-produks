import { Link } from '@inertiajs/react';
import { ClipboardCheck } from 'lucide-react';
import { Badge, EmptyState, PageHeader, Panel } from '../../components/ui';
import { BackendLayout } from '../../layouts/backend-layout';

export type TaskItem = { kind: string; title: string; detail: string; ticket_code: string; due: string | null; url: string };

const kindLabels: Record<string, string> = { unggah: 'Unggah', revisi: 'Revisi', verifikasi: 'Verifikasi', bpom: 'BPOM', kontrak: 'Kontrak', sampel: 'Sampel' };
const kindTones: Record<string, string> = { revisi: 'red', bpom: 'amber', verifikasi: 'blue', kontrak: 'green' };

export function TaskRows({ tasks }: { tasks: TaskItem[] }) {
    return (
        <ul className="divide-y">
            {tasks.map((task, index) => <li key={`${task.ticket_code}-${task.title}-${index}`} className="panel-body">
                <Link href={task.url} className="block">
                    <div className="flex flex-wrap items-center gap-2"><Badge tone={kindTones[task.kind]} dot={false}>{kindLabels[task.kind] ?? task.kind}</Badge><strong>{task.title}</strong></div>
                    <p className="text-sm text-gray-500">{task.ticket_code} · {task.detail}{task.due ? ` · tenggat ${task.due}` : ''}</p>
                </Link>
            </li>)}
        </ul>
    );
}

export default function Tasks({ tasks }: { tasks: TaskItem[] }) {
    return (
        <BackendLayout title="Tugas dokumen">
            <PageHeader title="Tugas dokumen" eyebrow="RUANG KERJA" description="Dokumen yang perlu Anda siapkan, periksa, atau tanggapi." />
            <Panel>
                {tasks.length ? <TaskRows tasks={tasks} /> : <EmptyState title="Tidak ada tugas dokumen" description="Tugas baru muncul saat dokumen perlu diunggah, direvisi, atau diverifikasi." icon={ClipboardCheck} />}
            </Panel>
        </BackendLayout>
    );
}
