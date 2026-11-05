import type { Contract, Invoice, Ticket } from '../types';

export type UploadMetadata = { number: string; issuedAt: string; expiresAt?: string };
export function validateUpload(
    file: Pick<File, 'name' | 'size' | 'type'> | null,
    metadata: UploadMetadata,
    requiresExpiry = false,
): Record<string, string> {
    const errors: Record<string, string> = {};
    if (!file) errors.file = 'Pilih berkas yang ingin diunggah.';
    else if (!/\.(pdf|jpe?g|png|docx|xlsx)$/i.test(file.name))
        errors.file = 'Format berkas tidak didukung. Gunakan PDF, JPG, PNG, DOCX, atau XLSX.';
    else if (
        file.type &&
        ![
            'application/pdf',
            'image/jpeg',
            'image/png',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ].includes(file.type)
    )
        errors.file = 'Format isi berkas yang dilaporkan browser tidak didukung.';
    else if (file.size > 20 * 1024 * 1024) errors.file = 'Ukuran berkas maksimal 20 MB.';
    else if (file.size === 0) errors.file = 'Berkas kosong tidak dapat diunggah.';
    if (!metadata.number.trim()) errors.number = 'Nomor dokumen wajib diisi.';
    if (!metadata.issuedAt) errors.issuedAt = 'Tanggal terbit wajib diisi.';
    if (requiresExpiry && !metadata.expiresAt) errors.expiresAt = 'Tanggal kedaluwarsa wajib diisi.';
    return errors;
}

export function canSubmitTicket(ticket: Ticket): { allowed: boolean; reasons: string[] } {
    const reasons: string[] = [];
    if (ticket.closed) reasons.push('Tiket sudah ditutup.');
    if (!ticket.mouSigned) reasons.push('Kontrak (MoU) belum ditandatangani.');
    if (ticket.phase === 'Awal') reasons.push('Fase pra-registrasi belum dibuka.');
    if (ticket.submitted) reasons.push('Pengajuan fase ini sudah dicatat dan versi dokumen dikunci.');
    ticket.documents
        .filter((doc) => doc.required && (doc.phase === ticket.phase || doc.phase === 'Kedua fase'))
        .forEach((doc) => {
            if (doc.status !== 'Disetujui' || doc.validation !== 'Lolos' || (doc.requiresQa && !doc.qaApproved))
                reasons.push(`${doc.name} belum memenuhi persyaratan.`);
        });
    return { allowed: reasons.length === 0, reasons };
}

export function reviewDocument(
    ticket: Ticket,
    documentId: string,
    decision: 'approve' | 'revise',
    reason: string,
    expectedRevision: number,
): Ticket {
    if (ticket.revision !== expectedRevision) throw new Error('Data sudah berubah. Muat ulang sebelum melanjutkan.');
    const document = ticket.documents.find((item) => item.id === documentId);
    if (!document) throw new Error('Dokumen tidak ditemukan.');
    if (document.locked || ticket.closed) throw new Error('Versi dokumen sudah dikunci dan tidak dapat diubah.');
    if (decision === 'revise' && !reason.trim()) throw new Error('Alasan pengembalian wajib diisi.');
    if (decision === 'approve' && document.validation !== 'Lolos')
        throw new Error('Dokumen harus lolos validasi dasar terlebih dahulu.');
    if (decision === 'approve' && document.requiresQa && !document.qaApproved)
        throw new Error('Isi dokumen harus disetujui QA/QC terlebih dahulu.');
    return {
        ...ticket,
        revision: ticket.revision + 1,
        documents: ticket.documents.map((item) =>
            item.id === documentId
                ? {
                      ...item,
                      status: decision === 'approve' ? 'Disetujui' : 'Perlu revisi',
                      reason: decision === 'revise' ? reason.trim() : undefined,
                  }
                : item,
        ),
    };
}

export function activateContract(contract: Contract): Contract {
    if (contract.status !== 'Ditandatangani')
        throw new Error('Kontrak bertanda tangan harus diterima sebelum diaktifkan.');
    return { ...contract, status: 'Aktif' };
}

export function openPreRegistration(ticket: Ticket, contract: Contract): Ticket {
    if (contract.ticketId !== ticket.id) throw new Error('Kontrak tidak sesuai dengan tiket ini.');
    if (contract.status !== 'Aktif')
        throw new Error('Kontrak (MoU) bertanda tangan harus dikonfirmasi Marketing terlebih dahulu.');
    if (ticket.phase !== 'Awal') return { ...ticket, mouSigned: true };
    return {
        ...ticket,
        mouSigned: true,
        phase: 'Pra-registrasi',
        stage: 'Dokumen pra-registrasi',
        revision: ticket.revision + 1,
    };
}

export function verifyInvoice(invoice: Invoice, decision: 'approve' | 'reject', reason: string): Invoice {
    if (invoice.status === 'Lunas') return invoice;
    if (!invoice.proof) throw new Error('Bukti transfer belum diunggah.');
    if (decision === 'reject' && !reason.trim()) throw new Error('Alasan penolakan wajib diisi.');
    return {
        ...invoice,
        status: decision === 'approve' ? 'Lunas' : 'Terbit',
        proofStatus: decision === 'approve' ? 'approved' : 'rejected',
        reason: decision === 'reject' ? reason.trim() : undefined,
    };
}

export function openRegistration(ticket: Ticket): Ticket {
    if (!ticket.mouSigned) throw new Error('Kontrak (MoU) belum ditandatangani.');
    if (!ticket.preSubmitted || ticket.preResult !== 'approved')
        throw new Error('Hasil pra-registrasi harus tercatat dan disetujui sebelum registrasi dibuka.');
    if (
        ticket.documents.some(
            (doc) =>
                doc.required &&
                (doc.phase === 'Pra-registrasi' || doc.phase === 'Kedua fase') &&
                (doc.status !== 'Disetujui' || doc.validation !== 'Lolos' || (doc.requiresQa && !doc.qaApproved)),
        )
    )
        throw new Error('Dokumen wajib pra-registrasi belum seluruhnya disetujui.');
    return {
        ...ticket,
        phase: 'Registrasi',
        stage: 'Dokumen registrasi',
        submitted: false,
        revision: ticket.revision + 1,
        documents: ticket.documents.map((doc) => (doc.phase === 'Registrasi' ? { ...doc, locked: false } : doc)),
    };
}

export function closeTicketWithNie(ticket: Ticket, number: string, date: string): Ticket {
    if (!number.trim() || !date) throw new Error('Nomor dan tanggal NIE wajib diisi.');
    if (ticket.phase !== 'Registrasi' || !ticket.submitted)
        throw new Error('Pengajuan registrasi harus tercatat sebelum NIE diterbitkan.');
    return {
        ...ticket,
        closed: true,
        stage: 'Selesai',
        nie: number.trim(),
        nieDate: date,
        revision: ticket.revision + 1,
    };
}

export function validateEvidenceFile(file: Pick<File, 'name' | 'size' | 'type'> | null): string | undefined {
    if (file && !/\.(pdf|jpe?g|png)$/i.test(file.name)) return 'Format bukti harus PDF, JPG, atau PNG.';
    return validateUpload(file, { number: 'Bukti', issuedAt: '2026-10-21' }).file;
}

export function recordPreRegistrationResult(
    ticket: Ticket,
    outcome: 'approved' | 'rejected',
    affectedIds: string[],
): Ticket {
    if (outcome === 'approved') return openRegistration({ ...ticket, preResult: outcome });
    if (!affectedIds.length) throw new Error('Pilih dokumen yang perlu diperbaiki.');
    return {
        ...ticket,
        preResult: 'rejected',
        submitted: false,
        stage: 'Perbaikan dokumen',
        revision: ticket.revision + 1,
        documents: ticket.documents.map((doc) =>
            affectedIds.includes(doc.id) && (doc.phase === 'Pra-registrasi' || doc.phase === 'Kedua fase')
                ? {
                      ...doc,
                      locked: false,
                      status: 'Perlu revisi',
                      validation: 'Gagal',
                      reason: 'BPOM meminta perbaikan. Unggah versi baru sebelum pengajuan ulang.',
                  }
                : doc,
        ),
    };
}
