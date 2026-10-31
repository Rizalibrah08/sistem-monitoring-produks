import { Link } from '@inertiajs/react';
import { ArrowUpRight, FlaskConical, Pill } from 'lucide-react';
import { Avatar, Badge, EmptyState, Progress } from '../../components/ui';
import { usePreview } from '../../mocks/preview-provider';
import { ticketProgress, ticketStatus } from '../../lib/utils';
import type { Ticket } from '../../types';

export function TicketTable({ tickets, compact = false }: { tickets: Ticket[]; compact?: boolean }) {
    const { data } = usePreview();
    if (!tickets.length)
        return (
            <EmptyState
                title="Tidak ada tiket yang sesuai"
                description="Ubah pencarian atau filter untuk melihat tiket lainnya."
            />
        );
    return (
        <div className="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Produk & tiket</th>
                        <th>Klien</th>
                        <th>Fase</th>
                        {!compact && <th>Tahap berjalan</th>}
                        <th>Progres</th>
                        <th>Status</th>
                        <th />
                    </tr>
                </thead>
                <tbody>
                    {tickets.map((ticket) => {
                        const client = data.clients.find((item) => item.id === ticket.clientId)!;
                        return (
                            <tr key={ticket.id}>
                                <td>
                                    <Link href={`/tickets/${ticket.id}`} preserveState className="product-cell">
                                        <span
                                            className={`product-icon ${ticket.form === 'Kapsul' ? 'product-mint' : 'product-sand'}`}
                                        >
                                            {ticket.form === 'Kapsul' ? (
                                                <Pill size={17} strokeWidth={1.5} />
                                            ) : (
                                                <FlaskConical size={17} strokeWidth={1.5} />
                                            )}
                                        </span>
                                        <span>
                                            <strong>{ticket.product}</strong>
                                            <small>{ticket.id}</small>
                                        </span>
                                    </Link>
                                </td>
                                <td>
                                    <span className="client-cell">
                                        <Avatar initials={client.initials} color={client.color} small />
                                        <span>{client.name}</span>
                                    </span>
                                </td>
                                <td>
                                    <Badge
                                        tone={
                                            ticket.phase === 'Registrasi'
                                                ? 'blue'
                                                : ticket.phase === 'Pra-registrasi'
                                                  ? 'green'
                                                  : 'neutral'
                                        }
                                        dot={false}
                                    >
                                        {ticket.phase}
                                    </Badge>
                                </td>
                                {!compact && <td className="muted-cell">{ticket.stage}</td>}
                                <td>
                                    <Progress value={ticketProgress(ticket)} />
                                </td>
                                <td>
                                    <Badge>{ticketStatus(ticket)}</Badge>
                                </td>
                                <td>
                                    <Link
                                        href={`/tickets/${ticket.id}`}
                                        preserveState
                                        className="icon-button"
                                        aria-label={`Buka ${ticket.id}`}
                                    >
                                        <ArrowUpRight size={16} />
                                    </Link>
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
