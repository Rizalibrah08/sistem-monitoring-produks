import { router, useForm, usePage } from '@inertiajs/react';
import { Download, ShieldCheck } from 'lucide-react';
import type { FormEvent } from 'react';
import type { AuditEntry, BackendSharedProps, Paginated } from '../../backend-types';
import { BackendPagination } from '../../components/backend-pagination';
import { Badge, Button, EmptyState, Field, InfoBox, PageHeader, Panel, Select } from '../../components/ui';
import { BackendLayout } from '../../layouts/backend-layout';

type Filters = { actor_id: string | number; event: string; ticket_code: string; date_from: string; date_to: string };
type Integrity = { checked: number; valid: boolean; mismatches: { id: number; event: string | null; created_at: string | null; reason: string }[]; verified_at: string };
type AuditUrls = { csv: string; pdf: string; verify: string | null };

export default function Audit({ entries, actors, filters, urls, integrity = null }: { entries: Paginated<AuditEntry>; actors: { id: number; name: string }[]; filters: Filters; urls?: AuditUrls; integrity?: Integrity | null }) {
    const { routes, errors } = usePage<BackendSharedProps & { errors: Record<string, string> }>().props;
    const form = useForm({ ...filters, actor_id: String(filters.actor_id ?? '') });

    function filter(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.get(routes.audit, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <BackendLayout title="Log audit">
            <PageHeader title="Log audit" description="Riwayat append-only dengan rantai SHA-256. Waktu ditampilkan dalam WIB.">
                {urls && <>
                    <a className="btn btn-secondary" href={urls.csv}><Download size={16} strokeWidth={1.8} />Ekspor CSV</a>
                    <a className="btn btn-secondary" href={urls.pdf}><Download size={16} strokeWidth={1.8} />Ekspor PDF</a>
                    {urls.verify && <Button icon={ShieldCheck} onClick={() => router.post(urls.verify!, {}, { preserveScroll: true })}>Verifikasi integritas</Button>}
                </>}
            </PageHeader>
            {errors.export && <div className="mb-5"><InfoBox warning>{errors.export}</InfoBox></div>}
            {integrity && <div className="mb-5"><InfoBox warning={!integrity.valid}>
                <strong>{integrity.valid ? `Rantai hash utuh: ${integrity.checked} entri diperiksa.` : `Ditemukan entri yang tidak cocok dari ${integrity.checked} entri.`}</strong> <span className="text-gray-500">({integrity.verified_at})</span>
                {integrity.mismatches.length > 0 && <ul className="mt-1 list-disc pl-5 text-sm">{integrity.mismatches.map((item) => <li key={item.id}>#{item.id} {item.event ?? ''} · {item.created_at} · {item.reason}</li>)}</ul>}
            </InfoBox></div>}
            <Panel className="mb-5 p-5">
                <form onSubmit={filter} className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <Field label="Pelaku" error={form.errors.actor_id}><Select value={form.data.actor_id} onChange={(event) => form.setData('actor_id', event.target.value)}><option value="">Semua pelaku</option>{actors.map((actor) => <option key={actor.id} value={actor.id}>{actor.name}</option>)}</Select></Field>
                    <Field label="Jenis aksi" error={form.errors.event}><input value={form.data.event} onChange={(event) => form.setData('event', event.target.value)} placeholder="Contoh: user.created" /></Field>
                    <Field label="Kode tiket" error={form.errors.ticket_code}><input value={form.data.ticket_code} onChange={(event) => form.setData('ticket_code', event.target.value)} /></Field>
                    <Field label="Dari tanggal" error={form.errors.date_from}><input type="date" value={form.data.date_from} onChange={(event) => form.setData('date_from', event.target.value)} /></Field>
                    <Field label="Sampai tanggal" error={form.errors.date_to}><input type="date" value={form.data.date_to} onChange={(event) => form.setData('date_to', event.target.value)} /></Field>
                    <div className="flex items-end"><Button type="submit" variant="secondary" disabled={form.processing}>Terapkan filter</Button></div>
                </form>
            </Panel>
            <Panel>
                {entries.data.length ? <div className="overflow-x-auto"><table className="w-full"><thead><tr><th>Waktu (WIB)</th><th>Pelaku</th><th>Aksi</th><th>Objek</th><th>Sumber</th><th>Rincian</th></tr></thead>
                    <tbody>{entries.data.map((entry) => <tr key={entry.id}>
                        <td className="whitespace-nowrap">{entry.created_at}</td>
                        <td><strong>{entry.actor_name ?? 'Sistem / anonim'}</strong><div className="text-xs text-gray-500">{entry.actor_role ?? '—'}</div></td>
                        <td>{entry.event ?? entry.description}</td>
                        <td>{entry.ticket_code ?? `${entry.subject_type?.split('\\').pop() ?? '—'}${entry.subject_id ? ` #${entry.subject_id}` : ''}`}</td>
                        <td><Badge tone="neutral">{entry.source}</Badge></td>
                        <td><details><summary className="cursor-pointer text-emerald-800">Lihat rincian</summary><div className="min-w-64 space-y-3 py-3 text-xs">
                            <div><strong>Sebelum</strong><pre className="whitespace-pre-wrap break-words">{JSON.stringify(entry.before, null, 2)}</pre></div>
                            <div><strong>Sesudah</strong><pre className="whitespace-pre-wrap break-words">{JSON.stringify(entry.after, null, 2)}</pre></div>
                            {entry.reason && <p>Alasan: {entry.reason}</p>}
                            <p>IP: {entry.ip_address ?? '—'}</p><p className="break-words">User agent: {entry.user_agent ?? '—'}</p>
                            <p className="break-all">Hash sebelumnya: {entry.prev_hash}</p><p className="break-all">Hash: {entry.hash}</p>
                        </div></details></td>
                    </tr>)}</tbody></table></div> : <EmptyState title="Belum ada aktivitas yang sesuai" description="Perubahan akun dan aktivitas akses akan tercatat secara otomatis." />}
                <BackendPagination page={entries} />
            </Panel>
        </BackendLayout>
    );
}
