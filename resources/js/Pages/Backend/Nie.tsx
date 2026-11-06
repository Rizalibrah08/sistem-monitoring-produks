import { Link } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { Badge, EmptyState, PageHeader, Panel } from '../../components/ui';
import { BackendLayout } from '../../layouts/backend-layout';

type AwaitingTicket = {
    id: number;
    code: string;
    product_name: string;
    client: string;
    submitted_on: string | null;
    packaging_requested_on: string | null;
    packaging_uploaded_on: string | null;
    ticket_url: string;
};

export default function Nie({ tickets }: { tickets: AwaitingTicket[] }) {
    return (
        <BackendLayout title="Input NIE">
            <PageHeader title="Input NIE" eyebrow="REGULATORY AFFAIRS" description="Tiket yang registrasinya sudah diajukan. Catat desain kemasan dan NIE dari detail tiket; NIE menutup tiket dan menerbitkan tagihan pelunasan." />
            <Panel>
                {tickets.length ? (
                    <div className="overflow-x-auto">
                        <table className="w-full"><thead><tr><th>Tiket</th><th>Klien</th><th>Registrasi diajukan</th><th>Desain kemasan</th></tr></thead>
                            <tbody>{tickets.map((ticket) => <tr key={ticket.id}>
                                <td><Link href={ticket.ticket_url} className="font-semibold">{ticket.code}</Link><div className="text-xs text-gray-500">{ticket.product_name}</div></td>
                                <td>{ticket.client}</td>
                                <td>{ticket.submitted_on ?? '—'}</td>
                                <td>{ticket.packaging_uploaded_on
                                    ? <Badge tone="green">Diunggah {ticket.packaging_uploaded_on}</Badge>
                                    : ticket.packaging_requested_on ? <Badge tone="amber">Diminta {ticket.packaging_requested_on}</Badge> : <Badge>Belum diminta</Badge>}</td>
                            </tr>)}</tbody>
                        </table>
                    </div>
                ) : <EmptyState title="Belum ada tiket yang siap menerima NIE" description="Tiket muncul di sini setelah pengajuan registrasi dicatat." icon={ShieldCheck} />}
            </Panel>
        </BackendLayout>
    );
}
