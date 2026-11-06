import { Link, useForm } from '@inertiajs/react';
import { AlarmClock, Coins, FileClock, Wallet } from 'lucide-react';
import type { FormEvent } from 'react';
import type { Paginated } from '../../backend-types';
import { BackendPagination } from '../../components/backend-pagination';
import { rupiah } from '../../components/contract-form';
import { Badge, Button, EmptyState, Field, PageHeader, Panel, Select, StatCard } from '../../components/ui';
import { BackendLayout } from '../../layouts/backend-layout';

export type InvoiceRow = {
    id: number;
    number: string;
    term_label: string;
    amount: number;
    due_date: string;
    status: string;
    status_label: string;
    is_overdue: boolean;
    paid_at: string | null;
    payment_type: string | null;
    ticket_code: string;
    client: string;
    ticket_url: string;
};

export const invoiceTones: Record<string, string> = { terbit: 'neutral', menunggu_pembayaran: 'amber', lunas: 'green' };

export function InvoiceStatus({ invoice }: { invoice: Pick<InvoiceRow, 'status' | 'status_label' | 'is_overdue'> }) {
    return <span className="inline-flex flex-wrap gap-1"><Badge tone={invoiceTones[invoice.status]}>{invoice.status_label}</Badge>{invoice.is_overdue && <Badge tone="red">Lewat jatuh tempo</Badge>}</span>;
}

export default function Invoices({ invoices, filters, statuses, summary }: {
    invoices: Paginated<InvoiceRow>;
    filters: { search: string; status: string };
    statuses: { value: string; label: string }[];
    summary: { outstanding: number; unpaid_count: number; overdue_count: number };
}) {
    const search = useForm({ search: filters.search, status: filters.status });

    function filter(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        search.get(window.location.pathname, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <BackendLayout title="Tagihan">
            <PageHeader title="Daftar tagihan" eyebrow="KEUANGAN" description="Tagihan DP dan pelunasan per kontrak. Pembayaran lewat Midtrans tercatat lunas secara otomatis." />
            <div className="stat-grid mb-5">
                <StatCard label="Piutang berjalan" value={rupiah(summary.outstanding)} icon={Coins} />
                <StatCard label="Tagihan belum lunas" value={String(summary.unpaid_count)} icon={FileClock} tone="amber" />
                <StatCard label="Lewat jatuh tempo" value={String(summary.overdue_count)} icon={AlarmClock} tone="red" />
            </div>
            <form onSubmit={filter} className="filter-bar flex-wrap">
                <Field label="Cari tagihan" error={search.errors.search}><input type="search" value={search.data.search} onChange={(event) => search.setData('search', event.target.value)} placeholder="Nomor tagihan, tiket, atau klien" /></Field>
                <Field label="Status" error={search.errors.status}><Select value={search.data.status} onChange={(event) => search.setData('status', event.target.value)}><option value="">Semua status</option>{statuses.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}</Select></Field>
                <Button type="submit" variant="secondary" disabled={search.processing}>Terapkan filter</Button>
            </form>
            <Panel>
                {invoices.data.length ? (
                    <div className="overflow-x-auto">
                        <table className="w-full"><thead><tr><th>Nomor tagihan</th><th>Klien</th><th>Tiket</th><th>Termin</th><th>Jumlah</th><th>Jatuh tempo</th><th>Status</th></tr></thead>
                            <tbody>{invoices.data.map((invoice) => <tr key={invoice.id}>
                                <td><strong>{invoice.number}</strong></td>
                                <td>{invoice.client}</td>
                                <td><Link href={invoice.ticket_url} className="font-semibold">{invoice.ticket_code}</Link></td>
                                <td>{invoice.term_label}</td>
                                <td>{rupiah(invoice.amount)}</td>
                                <td>{invoice.due_date}</td>
                                <td><InvoiceStatus invoice={invoice} />{invoice.paid_at && <div className="text-xs text-gray-500">{invoice.paid_at}{invoice.payment_type ? ` · ${invoice.payment_type}` : ''}</div>}</td>
                            </tr>)}</tbody>
                        </table>
                    </div>
                ) : <EmptyState title="Belum ada tagihan" description="Tagihan DP terbit otomatis saat kontrak dikonfirmasi aktif." icon={Wallet} />}
                <BackendPagination page={invoices} />
            </Panel>
        </BackendLayout>
    );
}
