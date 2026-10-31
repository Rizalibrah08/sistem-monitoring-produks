import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { PreviewProvider, usePreview } from '../../resources/js/mocks/preview-provider';
import { ReviewContent } from '../../resources/js/Pages/documents';

function ConcurrentReview() {
    const { data, updateTicket } = usePreview();
    const ticket = data.tickets[0];
    return (
        <>
            <button
                onClick={() =>
                    updateTicket(
                        ticket.id,
                        (current) => ({ ...current, revision: current.revision + 1, stage: 'Perubahan terbaru' }),
                        'Data lain berubah.',
                    )
                }
            >
                Simulasikan pembaruan
            </button>
            <output aria-label="Status dokumen terbaru">{ticket.documents[0].status}</output>
            <ReviewContent ticket={ticket} document={ticket.documents[0]} onDone={() => undefined} />
        </>
    );
}

describe('open document review', () => {
    it('rejects an obsolete form and keeps its rejection reason without overwriting newer state', async () => {
        const user = userEvent.setup();
        render(
            <PreviewProvider initialRole="ra">
                <ConcurrentReview />
            </PreviewProvider>,
        );
        await user.type(screen.getByLabelText('Catatan / alasan pengembalian'), 'Perbaiki nomor dokumen.');
        await user.click(screen.getByRole('button', { name: 'Simulasikan pembaruan' }));
        await user.click(screen.getByRole('button', { name: 'Kembalikan dokumen' }));
        expect(screen.getByRole('alert')).toHaveTextContent('Data sudah berubah');
        expect(screen.getByLabelText('Catatan / alasan pengembalian')).toHaveValue('Perbaiki nomor dokumen.');
        expect(screen.getByLabelText('Status dokumen terbaru')).toHaveTextContent('Menunggu verifikasi');
    });
});
