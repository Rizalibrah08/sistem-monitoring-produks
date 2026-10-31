import { createContext, useContext, useRef, useState, type ReactNode } from 'react';
import { createFixtures } from './fixtures';
import type { DemoState, Role, Ticket } from '../types';

type PreviewContext = {
    data: DemoState;
    role: Role;
    setRole: (role: Role) => void;
    update: (recipe: (state: DemoState) => DemoState, message?: string, objectId?: string) => boolean;
    updateTicket: (id: string, recipe: (ticket: Ticket) => Ticket, message?: string) => boolean;
    notify: (message: string, error?: boolean) => void;
    toast: { message: string; error: boolean } | null;
};
const Context = createContext<PreviewContext | null>(null);

export function PreviewProvider({ initialRole = 'manager', children }: { initialRole?: Role; children: ReactNode }) {
    const [data, setData] = useState(createFixtures);
    const [role, setRole] = useState<Role>(initialRole);
    const [toast, setToast] = useState<PreviewContext['toast']>(null);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
    function notify(message: string, error = false) {
        if (timer.current) clearTimeout(timer.current);
        setToast({ message, error });
        timer.current = setTimeout(() => setToast(null), 4500);
    }
    function update(
        recipe: (state: DemoState) => DemoState,
        message = 'Perubahan demo berhasil disimpan.',
        objectId = 'Pratinjau',
    ) {
        try {
            const result = recipe(data);
            setData({
                ...result,
                audit: [
                    {
                        id: crypto.randomUUID(),
                        time: '21/10/2026 10.30',
                        actor: role,
                        action: message,
                        object: objectId,
                        change: 'Perubahan data demo',
                        source: 'Web demo',
                    },
                    ...result.audit,
                ],
            });
            notify(message);
            return true;
        } catch (error) {
            notify(error instanceof Error ? error.message : 'Perubahan tidak dapat disimpan.', true);
            return false;
        }
    }
    function updateTicket(id: string, recipe: (ticket: Ticket) => Ticket, message?: string) {
        return update(
            (state) => ({
                ...state,
                tickets: state.tickets.map((ticket) => (ticket.id === id ? recipe(ticket) : ticket)),
            }),
            message,
            id,
        );
    }
    const clientIds = new Set(
        data.clients.filter((client) => client.assignedTo === 'marketing').map((client) => client.id),
    );
    const ticketIds = new Set(
        data.tickets.filter((ticket) => clientIds.has(ticket.clientId)).map((ticket) => ticket.id),
    );
    const visibleData =
        role === 'marketing'
            ? {
                  ...data,
                  clients: data.clients.filter((client) => clientIds.has(client.id)),
                  tickets: data.tickets.filter((ticket) => clientIds.has(ticket.clientId)),
                  contracts: data.contracts.filter((contract) => clientIds.has(contract.clientId)),
                  invoices: data.invoices.filter((invoice) => clientIds.has(invoice.clientId)),
                  bpomNotes: data.bpomNotes.filter((note) => ticketIds.has(note.ticketId)),
                  conversations: data.conversations.filter(
                      (conversation) => !conversation.clientId || clientIds.has(conversation.clientId),
                  ),
              }
            : data;
    return (
        <Context.Provider value={{ data: visibleData, role, setRole, update, updateTicket, notify, toast }}>
            {children}
        </Context.Provider>
    );
}

export function usePreview() {
    const context = useContext(Context);
    if (!context) throw new Error('PreviewProvider is required.');
    return context;
}
