import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button, Field, Select } from './ui';

export type ContractOption = { value: number; label: string };
export type ContractDefaults = { dp_percent: number; payment_due_days: number };
export type ContractTerms = { document_template_id: number | ''; service_value: number | ''; dp_percent: number; payment_due_days: number };

export const rupiah = (value: number) => `Rp${value.toLocaleString('id-ID')}`;

/** Form kontrak bersama untuk halaman Kontrak dan detail tiket (FR-73). */
export function ContractForm({ url, method = 'post', tickets, templates, initial, submitLabel, onCancel, onSuccess }: {
    url: string;
    method?: 'post' | 'put';
    tickets?: ContractOption[];
    templates: ContractOption[];
    initial: ContractTerms & { ticket_id?: number | '' };
    submitLabel: string;
    onCancel?: () => void;
    onSuccess?: () => void;
}) {
    const form = useForm({ ticket_id: initial.ticket_id ?? '', ...initial } as ContractTerms & { ticket_id: number | '' });
    const value = Number(form.data.service_value) || 0;
    const dp = Math.floor((value * Number(form.data.dp_percent)) / 100);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.transform((data) => (data.ticket_id === '' ? { ...data, ticket_id: undefined } : data));
        form[method](url, { preserveScroll: true, onSuccess });
    }

    return (
        <form onSubmit={submit} className="form-stack">
            {tickets && <Field label="Tiket" error={form.errors.ticket_id} hint="Hanya tiket aktif yang sampelnya sudah disetujui klien.">
                <Select required value={String(form.data.ticket_id)} onChange={(event) => form.setData('ticket_id', event.target.value ? Number(event.target.value) : '')}>
                    <option value="">Pilih tiket</option>
                    {tickets.map((ticket) => <option key={ticket.value} value={ticket.value}>{ticket.label}</option>)}
                </Select>
            </Field>}
            <Field label="Templat kontrak" error={form.errors.document_template_id} hint={templates.length ? undefined : 'Belum ada templat kontrak aktif; minta Admin mengunggahnya.'}>
                <Select required value={String(form.data.document_template_id)} onChange={(event) => form.setData('document_template_id', event.target.value ? Number(event.target.value) : '')}>
                    <option value="">Pilih templat</option>
                    {templates.map((template) => <option key={template.value} value={template.value}>{template.label}</option>)}
                </Select>
            </Field>
            <Field label="Nilai jasa (Rp)" error={form.errors.service_value}><input type="number" min={1} required value={form.data.service_value} onChange={(event) => form.setData('service_value', event.target.value ? Number(event.target.value) : '')} /></Field>
            <Field label="DP (%)" error={form.errors.dp_percent} hint={value ? `DP ${rupiah(dp)} · pelunasan ${rupiah(value - dp)}` : 'Bawaan 50%; sisanya menjadi pelunasan.'}><input type="number" min={1} max={99} required value={form.data.dp_percent} onChange={(event) => form.setData('dp_percent', Number(event.target.value))} /></Field>
            <Field label="Jatuh tempo tagihan (hari)" error={form.errors.payment_due_days}><input type="number" min={1} max={90} required value={form.data.payment_due_days} onChange={(event) => form.setData('payment_due_days', Number(event.target.value))} /></Field>
            <div className="form-footer">
                {onCancel && <Button variant="secondary" onClick={onCancel} disabled={form.processing}>Batal</Button>}
                <Button type="submit" disabled={form.processing}>{form.processing ? 'Menyimpan...' : submitLabel}</Button>
            </div>
        </form>
    );
}
