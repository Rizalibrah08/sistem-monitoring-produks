import type { Ticket } from '../types';

export const currency = (value: number) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
export const shortMoney = (value: number) =>
    `Rp ${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(value / 1000000)} jt`;
export const dateLabel = (value: string) =>
    value
        ? new Intl.DateTimeFormat('id-ID', {
              day: '2-digit',
              month: 'short',
              year: 'numeric',
              timeZone: 'Asia/Jakarta',
          }).format(new Date(`${value}T08:00:00+07:00`))
        : '—';
export const ticketProgress = (ticket: Ticket) => {
    const docs = ticket.documents.filter(
        (doc) => doc.required && (doc.phase === ticket.phase || doc.phase === 'Kedua fase'),
    );
    return docs.length ? Math.round((docs.filter((doc) => doc.status === 'Disetujui').length / docs.length) * 100) : 0;
};
export const ticketStatus = (ticket: Ticket) =>
    ticket.closed
        ? 'Selesai'
        : ticket.late
          ? 'Terlambat'
          : !ticket.mouSigned
            ? 'Menunggu MoU'
            : ticket.stage.includes('BPOM')
              ? 'Menunggu BPOM'
              : 'Tepat waktu';

export function downloadCsv(name: string, rows: Array<Array<string | number>>) {
    const contents = rows
        .map((row) =>
            row
                .map((value) => {
                    let text = String(value);
                    if (/^[=+@-]/.test(text)) text = `'${text}`;
                    return `"${text.replaceAll('"', '""')}"`;
                })
                .join(','),
        )
        .join('\r\n');
    const url = URL.createObjectURL(new Blob(['\uFEFF', contents], { type: 'text/csv;charset=utf-8;' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = name;
    link.click();
    URL.revokeObjectURL(url);
}
