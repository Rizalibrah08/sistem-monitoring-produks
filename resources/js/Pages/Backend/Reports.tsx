import { useForm } from '@inertiajs/react';
import { Clock3, Download, Hourglass, Users, Building2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button, EmptyState, Field, PageHeader, Panel, StatCard } from '../../components/ui';
import { BackendLayout } from '../../layouts/backend-layout';

type StageDuration = { stage: string; sla_days: number | null; count: number; average: number; p75: number; internal: number; client: number; external: number };

export default function Reports({ stages, totals, filters, urls }: {
    stages: StageDuration[];
    totals: { internal: number; client: number; external: number };
    filters: { from: string; until: string };
    urls: { export: string };
}) {
    const form = useForm(filters);

    function filter(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.get(window.location.pathname, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <BackendLayout title="Laporan durasi">
            <PageHeader title="Laporan durasi" eyebrow="PEMANTAUAN" description="Durasi tahap yang selesai pada periode ini dalam hari kerja, dengan waktu internal, menunggu klien, dan menunggu pihak luar dipisahkan.">
                <a className="btn btn-secondary" href={urls.export}><Download size={16} strokeWidth={1.8} />Ekspor CSV</a>
            </PageHeader>
            <form onSubmit={filter} className="filter-bar flex-wrap">
                <Field label="Selesai dari" error={form.errors.from}><input type="date" value={form.data.from} onChange={(event) => form.setData('from', event.target.value)} /></Field>
                <Field label="Selesai sampai" error={form.errors.until}><input type="date" value={form.data.until} onChange={(event) => form.setData('until', event.target.value)} /></Field>
                <Button type="submit" variant="secondary" disabled={form.processing}>Terapkan periode</Button>
            </form>
            <div className="stat-grid mb-5">
                <StatCard label="Waktu internal" value={`${totals.internal} hari`} icon={Users} />
                <StatCard label="Menunggu klien" value={`${totals.client} hari`} icon={Hourglass} tone="amber" />
                <StatCard label="Menunggu pihak luar" value={`${totals.external} hari`} icon={Building2} tone="blue" />
            </div>
            <Panel>
                {stages.length ? (
                    <div className="overflow-x-auto">
                        <table className="w-full"><thead><tr><th>Tahap</th><th>SLA</th><th>Jumlah</th><th>Rata-rata</th><th>Persentil ke-75</th><th>Internal</th><th>Menunggu klien</th><th>Pihak luar</th></tr></thead>
                            <tbody>{stages.map((row) => <tr key={row.stage}>
                                <td><strong>{row.stage}</strong></td>
                                <td>{row.sla_days ?? '—'}</td>
                                <td>{row.count}</td>
                                <td className={row.sla_days !== null && row.average > row.sla_days ? 'text-red-700' : ''}>{row.average}</td>
                                <td>{row.p75}</td>
                                <td>{row.internal}</td>
                                <td>{row.client}</td>
                                <td>{row.external}</td>
                            </tr>)}</tbody>
                        </table>
                    </div>
                ) : <EmptyState title="Belum ada tahap yang selesai pada periode ini" description="Ubah periode untuk melihat durasi tahap." icon={Clock3} />}
            </Panel>
        </BackendLayout>
    );
}
