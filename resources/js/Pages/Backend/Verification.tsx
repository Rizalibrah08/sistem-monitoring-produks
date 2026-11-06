import { Link, router, useForm, usePage } from '@inertiajs/react';
import { Check, Download, FileCheck, RotateCcw } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Badge, Button, EmptyState, Field, InfoBox, Modal, PageHeader, Panel } from '../../components/ui';
import { BackendLayout } from '../../layouts/backend-layout';

type QueueItem = {
    id: number;
    ticket_code: string;
    product_name: string;
    ticket_url: string;
    name: string;
    material: string | null;
    phase_label: string;
    pic: string | null;
    content_verified: boolean | null;
    version: { version: number; number: string; issued_at: string | null; expires_at: string | null; uploaded_at: string | null; uploaded_by: string; download_url: string };
    token: string;
    review_url: string;
};

export default function Verification({ level, title, description, items }: { level: string; title: string; description: string; items: QueueItem[] }) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const [returning, setReturning] = useState<QueueItem | null>(null);

    function approve(item: QueueItem) {
        router.post(item.review_url, { decision: 'disetujui', note: '', token: item.token }, { preserveScroll: true });
    }

    return (
        <BackendLayout title={title}>
            <PageHeader title={title} eyebrow="VERIFIKASI" description={description} />
            {errors.token && <div className="mb-5"><InfoBox warning>{errors.token}</InfoBox></div>}
            <Panel>
                {items.length ? (
                    <div className="overflow-x-auto">
                        <table className="w-full"><thead><tr><th>Tiket</th><th>Dokumen</th><th>Versi</th><th>PIC</th><th>Aksi</th></tr></thead>
                            <tbody>{items.map((item) => <tr key={item.id}>
                                <td><Link href={item.ticket_url} className="font-semibold">{item.ticket_code}</Link><div className="text-xs text-gray-500">{item.product_name}</div></td>
                                <td>
                                    <strong>{item.name}</strong>{item.material && <span className="text-gray-500"> · {item.material}</span>}
                                    <div className="mt-1 flex flex-wrap gap-1"><Badge dot={false}>{item.phase_label}</Badge>{level === 'pemakaian' && item.content_verified && <Badge tone="green">Isi terverifikasi QA</Badge>}</div>
                                </td>
                                <td>
                                    v{item.version.version} · No. {item.version.number}
                                    <div className="text-xs text-gray-500">{item.version.issued_at ? `terbit ${item.version.issued_at} · ` : ''}{item.version.expires_at ? `berlaku s.d. ${item.version.expires_at} · ` : ''}{item.version.uploaded_by}, {item.version.uploaded_at}</div>
                                </td>
                                <td>{item.pic ?? '—'}</td>
                                <td><div className="flex flex-wrap gap-1">
                                    <a className="btn btn-ghost" href={item.version.download_url} aria-label={`Unduh ${item.name} ${item.ticket_code}`}><Download size={16} strokeWidth={1.8} />Unduh</a>
                                    <Button icon={Check} aria-label={`Setujui ${item.name} ${item.ticket_code}`} onClick={() => approve(item)}>Setujui</Button>
                                    <Button variant="secondary" icon={RotateCcw} aria-label={`Kembalikan ${item.name} ${item.ticket_code}`} onClick={() => setReturning(item)}>Perlu revisi</Button>
                                </div></td>
                            </tr>)}</tbody>
                        </table>
                    </div>
                ) : <EmptyState title="Tidak ada dokumen yang menunggu" description="Dokumen muncul di sini setelah diunggah dan lolos validasi otomatis." icon={FileCheck} />}
            </Panel>
            {returning && <ReturnDialog key={returning.id} item={returning} onClose={() => setReturning(null)} />}
        </BackendLayout>
    );
}

function ReturnDialog({ item, onClose }: { item: QueueItem; onClose: () => void }) {
    const form = useForm({ decision: 'perlu_revisi', note: '', token: item.token });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(item.review_url, { preserveScroll: true, onSuccess: onClose });
    }

    return (
        <Modal title={`Kembalikan ${item.name}`} description={`${item.ticket_code} · versi ${item.version.version}. PIC dokumen perlu mengunggah versi baru.`} onClose={onClose}>
            <form onSubmit={submit} className="form-stack">
                <Field label="Alasan revisi" error={form.errors.note ?? form.errors.token}><textarea required maxLength={2000} value={form.data.note} onChange={(event) => form.setData('note', event.target.value)} /></Field>
                <div className="form-footer"><Button variant="secondary" onClick={onClose} disabled={form.processing}>Batal</Button><Button type="submit" disabled={form.processing}>Kembalikan dokumen</Button></div>
            </form>
        </Modal>
    );
}
