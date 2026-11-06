import { useForm } from '@inertiajs/react';
import { AlarmClock, CheckCircle2, Coins, Download, FileClock } from 'lucide-react';
import type { FormEvent } from 'react';
import { rupiah } from '../../components/contract-form';
import { Button, EmptyState, Field, PageHeader, Panel, StatCard } from '../../components/ui';
import { BackendLayout } from '../../layouts/backend-layout';

type ClientReceivable = { client_id: number; client: string; invoices: number; billed: number; paid: number; outstanding: number; overdue: number; oldest_due: string | null };
type Totals = { billed: number; paid: number; outstanding: number; overdue: number };

export default function Receivables({ clients, totals, filters, urls }: {
    clients: ClientReceivable[];
    totals: Totals;
    filters: { search: string; from: string; until: string };
    urls: { export: string };
}) {
    const form = useForm(filters);

    function filter(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.get(window.location.pathname, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <BackendLayout title="Laporan piutang">
            <PageHeader title="Laporan piutang" eyebrow="KEUANGAN" description="Tagihan, pelunasan, dan piutang per klien, termasuk tiket yang sudah ditutup.">
                <a className="btn btn-secondary" href={urls.export}><Download size={16} strokeWidth={1.8} />Ekspor CSV</a>
            </PageHeader>
            <div className="stat-grid mb-5">
                <StatCard label="Total ditagih" value={rupiah(totals.billed)} icon={FileClock} tone="blue" />
                <StatCard label="Sudah dibayar" value={rupiah(totals.paid)} icon={CheckCircle2} />
                <StatCard label="Piutang" value={rupiah(totals.outstanding)} icon={Coins} tone="amber" />
                <StatCard label="Lewat jatuh tempo" value={rupiah(totals.overdue)} icon={AlarmClock} tone="red" />
            </div>
            <form onSubmit={filter} className="filter-bar flex-wrap">
                <Field label="Klien" error={form.errors.search}><input type="search" value={form.data.search} onChange={(event) => form.setData('search', event.target.value)} placeholder="Nama klien" /></Field>
                <Field label="Terbit dari" error={form.errors.from}><input type="date" value={form.data.from} onChange={(event) => form.setData('from', event.target.value)} /></Field>
                <Field label="Terbit sampai" error={form.errors.until}><input type="date" value={form.data.until} onChange={(event) => form.setData('until', event.target.value)} /></Field>
                <Button type="submit" variant="secondary" disabled={form.processing}>Terapkan filter</Button>
            </form>
            <Panel>
                {clients.length ? (
                    <div className="overflow-x-auto">
                        <table className="w-full"><thead><tr><th>Klien</th><th>Tagihan</th><th>Ditagih</th><th>Dibayar</th><th>Piutang</th><th>Lewat jatuh tempo</th><th>Jatuh tempo terlama</th></tr></thead>
                            <tbody>{clients.map((client) => <tr key={client.client_id}>
                                <td><strong>{client.client}</strong></td>
                                <td>{client.invoices}</td>
                                <td>{rupiah(client.billed)}</td>
                                <td>{rupiah(client.paid)}</td>
                                <td><strong>{rupiah(client.outstanding)}</strong></td>
                                <td className={client.overdue ? 'text-red-700' : ''}>{rupiah(client.overdue)}</td>
                                <td>{client.oldest_due ?? '—'}</td>
                            </tr>)}</tbody>
                        </table>
                    </div>
                ) : <EmptyState title="Belum ada data piutang" description="Tagihan muncul di sini setelah kontrak aktif." icon={Coins} />}
            </Panel>
        </BackendLayout>
    );
}
