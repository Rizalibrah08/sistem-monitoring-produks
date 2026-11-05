import { describe, expect, it } from 'vitest';
import { createFixtures } from '../../resources/js/mocks/fixtures';
import {
    activateContract,
    canSubmitTicket,
    closeTicketWithNie,
    openPreRegistration,
    reviewDocument,
    validateUpload,
    verifyInvoice,
    openRegistration,
} from '../../resources/js/features/workflow';

describe('document upload and version safety', () => {
    it('rejects an executable disguised with an unsupported extension', () => {
        expect(
            validateUpload(
                { name: 'dokumen.exe', size: 10, type: 'application/octet-stream' },
                { number: 'DOC/01', issuedAt: '2026-10-01' },
            ).file,
        ).toMatch(/format/i);
    });
    it('rejects files exceeding 20 MB', () => {
        expect(
            validateUpload(
                { name: 'coa.pdf', size: 20 * 1024 * 1024 + 1, type: 'application/pdf' },
                { number: 'COA/01', issuedAt: '2026-10-01' },
            ).file,
        ).toMatch(/20 MB/);
    });
    it('accepts exactly 20 MB and complete metadata', () => {
        expect(
            validateUpload(
                { name: 'coa.pdf', size: 20 * 1024 * 1024, type: 'application/pdf' },
                { number: 'COA/01', issuedAt: '2026-10-01' },
            ),
        ).toEqual({});
    });
    it('rejects a dangerous browser MIME type even when the filename ends in pdf', () => {
        expect(
            validateUpload(
                { name: 'coa.pdf', size: 20, type: 'application/x-msdownload' },
                { number: 'COA/01', issuedAt: '2026-10-01' },
            ).file,
        ).toEqual(expect.stringMatching(/format/i));
    });
    it('requests missing metadata and expiry only when applicable', () => {
        expect(
            validateUpload({ name: 'coa.pdf', size: 10, type: 'application/pdf' }, { number: '', issuedAt: '' }, true),
        ).toEqual({ number: expect.any(String), issuedAt: expect.any(String), expiresAt: expect.any(String) });
    });
    it('does not approve a document before required QA approval', () => {
        const ticket = createFixtures().tickets[0];
        const document = { ...ticket.documents[0], qaApproved: false, requiresQa: true };
        expect(() =>
            reviewDocument({ ...ticket, documents: [document] }, document.id, 'approve', '', ticket.revision),
        ).toThrow(/QA/);
    });
    it('requires a rejection reason and keeps the original ticket unchanged', () => {
        const ticket = createFixtures().tickets[0];
        expect(() => reviewDocument(ticket, ticket.documents[0].id, 'revise', ' ', ticket.revision)).toThrow(/alasan/i);
        expect(ticket.documents[0].status).toBe('Menunggu verifikasi');
    });
    it('refuses a stale revision instead of overwriting a newer change', () => {
        const ticket = createFixtures().tickets[0];
        expect(() => reviewDocument(ticket, ticket.documents[0].id, 'approve', '', ticket.revision - 1)).toThrow(
            /berubah/i,
        );
    });
    it('approves the selected version without deleting its history', () => {
        const ticket = createFixtures().tickets[0];
        const result = reviewDocument(ticket, ticket.documents[0].id, 'approve', '', ticket.revision);
        expect(result.documents[0].status).toBe('Disetujui');
        expect(result.documents[0].versions).toHaveLength(2);
        expect(ticket.documents[0].status).toBe('Menunggu verifikasi');
    });
    it('blocks submission when a conditional required item is missing', () => {
        const ticket = createFixtures().tickets[0];
        const documents: typeof ticket.documents = ticket.documents.map((doc) => ({
            ...doc,
            status: 'Disetujui' as const,
        }));
        documents[0] = { ...documents[0], conditional: true, required: true, status: 'Belum ada' };
        const result = canSubmitTicket({ ...ticket, documents });
        expect(result.allowed).toBe(false);
        expect(result.reasons.join(' ')).toContain(documents[0].name);
    });
});

describe('contract, payment and phase guards', () => {
    it('does not activate an unsigned contract', () => {
        const contract = createFixtures().contracts[0];
        expect(() => activateContract({ ...contract, status: 'Dikirim' })).toThrow(/tanda tangan/i);
    });
    it('activates a signed contract after Marketing confirmation', () => {
        expect(activateContract({ ...createFixtures().contracts[0], status: 'Ditandatangani' }).status).toBe('Aktif');
    });
    it('requires a reason when a transfer proof is rejected', () => {
        expect(() => verifyInvoice(createFixtures().invoices[0], 'reject', '')).toThrow(/alasan/i);
    });
    it('marks a verified invoice paid without changing its amount', () => {
        const invoice = createFixtures().invoices[0];
        const result = verifyInvoice(invoice, 'approve', '');
        expect(result.status).toBe('Lunas');
        expect(result.amount).toBe(15000000);
        expect(invoice.status).toBe('Menunggu verifikasi');
    });
    it('does not open registration solely because an unapproved result was uploaded', () => {
        const ticket = createFixtures().tickets[0];
        expect(() => openRegistration({ ...ticket, phase: 'Pra-registrasi', preResult: 'rejected' })).toThrow(
            /disetujui/i,
        );
    });
    it('opens registration with a signed MoU and accepted pre-registration result even when DP is unpaid', () => {
        const ticket = createFixtures().tickets[0];
        expect(
            openRegistration({
                ...ticket,
                phase: 'Pra-registrasi',
                mouSigned: true,
                dpPaid: false,
                preSubmitted: true,
                preResult: 'approved',
            }).phase,
        ).toBe('Registrasi');
    });
    it('keeps registration closed while the MoU is not signed', () => {
        const ticket = createFixtures().tickets[0];
        expect(() =>
            openRegistration({
                ...ticket,
                phase: 'Pra-registrasi',
                mouSigned: false,
                preSubmitted: true,
                preResult: 'approved',
            }),
        ).toThrow(/MoU/);
    });
    it('blocks submission until the MoU is signed', () => {
        const ticket = createFixtures().tickets[0];
        const result = canSubmitTicket({ ...ticket, mouSigned: false });
        expect(result.allowed).toBe(false);
        expect(result.reasons.join(' ')).toMatch(/MoU/);
    });
    it('does not block submission because the DP invoice is still unpaid', () => {
        const ticket = createFixtures().tickets[0];
        const documents = ticket.documents.map((doc) => ({
            ...doc,
            status: 'Disetujui' as const,
            validation: 'Lolos' as const,
            qaApproved: true,
        }));
        const result = canSubmitTicket({ ...ticket, mouSigned: true, dpPaid: false, documents });
        expect(result).toEqual({ allowed: true, reasons: [] });
    });
    it('opens pre-registration once Marketing confirms the signed MoU, regardless of DP', () => {
        const state = createFixtures();
        const ticket = state.tickets.find((item) => item.id === 'PRD-2026-0025')!;
        const contract = { ...state.contracts.find((item) => item.ticketId === ticket.id)!, status: 'Aktif' as const };
        const result = openPreRegistration({ ...ticket, dpPaid: false }, contract);
        expect(result).toMatchObject({ mouSigned: true, phase: 'Pra-registrasi', stage: 'Dokumen pra-registrasi' });
        expect(result.dpPaid).toBe(false);
    });
    it('does not open pre-registration from a contract that is unconfirmed or belongs to another ticket', () => {
        const state = createFixtures();
        const ticket = state.tickets.find((item) => item.id === 'PRD-2026-0025')!;
        const contract = state.contracts.find((item) => item.ticketId === ticket.id)!;
        expect(() => openPreRegistration(ticket, { ...contract, status: 'Ditandatangani' })).toThrow(/dikonfirmasi/i);
        expect(() => openPreRegistration(ticket, { ...contract, status: 'Aktif', ticketId: 'PRD-2026-0012' })).toThrow(
            /tiket/i,
        );
    });
    it('keeps registration blocked while a required pre-registration document needs revision', () => {
        const ticket = createFixtures().tickets[0];
        const documents = ticket.documents.map((doc) =>
            doc.phase === 'Pra-registrasi' ? { ...doc, status: 'Perlu revisi' as const } : doc,
        );
        expect(() =>
            openRegistration({
                ...ticket,
                phase: 'Pra-registrasi',
                preSubmitted: true,
                preResult: 'approved',
                documents,
            }),
        ).toThrow(/dokumen/i);
    });
    it('does not close a ticket with an empty NIE number', () => {
        const ticket = createFixtures().tickets[0];
        expect(() => closeTicketWithNie(ticket, '', '2026-10-21')).toThrow(/NIE/);
    });
    it('closes a submitted registration without deleting its documents', () => {
        const ticket = createFixtures().tickets[0];
        const result = closeTicketWithNie({ ...ticket, submitted: true }, 'TR262000123', '2026-10-21');
        expect(result.closed).toBe(true);
        expect(result).toMatchObject({ nie: 'TR262000123', nieDate: '2026-10-21' });
        expect(result.documents).toHaveLength(ticket.documents.length);
    });
});

describe('demo workflow consistency', () => {
    it('provides an active contract for every ticket with a signed MoU', () => {
        const state = createFixtures();
        const missing = state.tickets.filter(
            (ticket) =>
                ticket.mouSigned &&
                !state.contracts.some((contract) => contract.ticketId === ticket.id && contract.status === 'Aktif'),
        );
        expect(missing.map((ticket) => ticket.id)).toEqual([]);
    });
    it('keeps every ticket without a signed MoU in the initial phase', () => {
        const state = createFixtures();
        const leaked = state.tickets.filter((ticket) => !ticket.mouSigned && ticket.phase !== 'Awal');
        expect(leaked.map((ticket) => ticket.id)).toEqual([]);
    });
    it('demonstrates a ticket progressing in pre-registration while its DP is still unpaid', () => {
        const state = createFixtures();
        expect(
            state.tickets.some((ticket) => ticket.mouSigned && !ticket.dpPaid && ticket.phase === 'Pra-registrasi'),
        ).toBe(true);
    });
    it('provides a paid DP invoice for every ticket whose DP is marked paid', () => {
        const state = createFixtures();
        const missing = state.tickets.filter(
            (ticket) =>
                ticket.dpPaid &&
                !state.invoices.some(
                    (invoice) =>
                        invoice.ticketId === ticket.id && invoice.term.startsWith('DP') && invoice.status === 'Lunas',
                ),
        );
        expect(missing.map((ticket) => ticket.id)).toEqual([]);
    });
    it('provides an actionable QA queue instead of an entirely empty demonstration', () => {
        const state = createFixtures();
        const hasTask = state.tickets.some(
            (ticket) =>
                !ticket.closed &&
                !ticket.submitted &&
                ticket.documents.some(
                    (doc) => doc.requiresQa && !doc.qaApproved && doc.status === 'Menunggu verifikasi',
                ),
        );
        expect(hasTask).toBe(true);
    });
});
