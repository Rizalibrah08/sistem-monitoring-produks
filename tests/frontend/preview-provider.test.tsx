import { act, renderHook } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it } from 'vitest';
import { PreviewProvider, usePreview } from '../../resources/js/mocks/preview-provider';

describe('preview scope and ticket-specific activity', () => {
    it('keeps other Marketing clients out of every visible data collection without deleting their data', () => {
        const { result } = renderHook(() => usePreview(), {
            wrapper: ({ children }: { children: ReactNode }) => (
                <PreviewProvider initialRole="marketing">{children}</PreviewProvider>
            ),
        });
        expect(result.current.data.clients.some((client) => client.id === 'c5')).toBe(false);
        expect(result.current.data.tickets.some((ticket) => ticket.clientId === 'c5')).toBe(false);
        expect(result.current.data.contracts.some((contract) => contract.clientId === 'c5')).toBe(false);
        act(() => result.current.setRole('manager'));
        expect(result.current.data.clients.some((client) => client.id === 'c5')).toBe(true);
    });
    it('attaches a ticket mutation to that ticket rather than every ticket history', () => {
        const { result } = renderHook(() => usePreview(), {
            wrapper: ({ children }: { children: ReactNode }) => <PreviewProvider>{children}</PreviewProvider>,
        });
        act(() => {
            result.current.updateTicket(
                'PRD-2026-0012',
                (ticket) => ({ ...ticket, needsReview: false }),
                'Dokumen diperiksa.',
            );
        });
        expect(result.current.data.audit[0].object).toBe('PRD-2026-0012');
    });
});
