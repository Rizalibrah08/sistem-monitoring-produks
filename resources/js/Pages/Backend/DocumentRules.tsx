import { useForm } from '@inertiajs/react';
import { FileCheck2, Pencil, Plus } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Badge, Button, EmptyState, Field, InfoBox, Modal, PageHeader, Panel, Select } from '../../components/ui';
import { BackendLayout } from '../../layouts/backend-layout';

type Option = { value: number; label: string };
type RuleOptions = { documentTypes: Option[]; dosageForms: Option[]; categories: Option[] };
type DocumentRule = {
    id: number;
    document_type_id: number;
    document_type: string;
    dosage_form_id: number | null;
    dosage_form: string | null;
    category_id: number | null;
    category: string | null;
    effective_from: string;
    notes: string | null;
    is_active: boolean;
    update_url: string;
};

export default function DocumentRules({ rules, options, urls }: { rules: DocumentRule[]; options: RuleOptions; urls: { store: string } }) {
    const [editor, setEditor] = useState<{ rule: DocumentRule | null } | null>(null);
    const canAdd = options.documentTypes.length > 0;

    return (
        <BackendLayout title="Aturan dokumen">
            <PageHeader title="Aturan dokumen" eyebrow="CHECKLIST KONDISIONAL" description="Dokumen kondisional yang wajib untuk sediaan atau kategori tertentu, mulai tanggal berlaku."><Button icon={Plus} disabled={!canAdd} onClick={() => setEditor({ rule: null })}>Tambah aturan</Button></PageHeader>
            {!canAdd && <div className="mb-5"><InfoBox warning>Belum ada jenis dokumen kondisional yang aktif. Admin perlu menambahkannya di Master data → Jenis dokumen.</InfoBox></div>}
            <Panel>
                {rules.length ? (
                    <div className="overflow-x-auto">
                        <table className="w-full"><thead><tr><th>Jenis dokumen</th><th>Sediaan</th><th>Kategori</th><th>Berlaku mulai</th><th>Catatan</th><th>Status</th><th>Aksi</th></tr></thead>
                            <tbody>{rules.map((rule) => <tr key={rule.id}>
                                <td><strong>{rule.document_type}</strong></td>
                                <td>{rule.dosage_form ?? 'Semua sediaan'}</td>
                                <td>{rule.category ?? 'Semua kategori'}</td>
                                <td>{formatDate(rule.effective_from)}</td>
                                <td>{rule.notes ?? '—'}</td>
                                <td><Badge tone={rule.is_active ? 'green' : 'neutral'}>{rule.is_active ? 'Aktif' : 'Nonaktif'}</Badge></td>
                                <td><Button variant="ghost" icon={Pencil} aria-label={`Ubah aturan ${rule.document_type}`} onClick={() => setEditor({ rule })}>Ubah</Button></td>
                            </tr>)}</tbody>
                        </table>
                    </div>
                ) : <EmptyState title="Belum ada aturan dokumen" description="Aturan menentukan dokumen kondisional yang masuk ke checklist tiket baru." icon={FileCheck2} />}
            </Panel>
            <div className="mt-5"><InfoBox>Aturan hanya berlaku untuk tiket yang dibuat sejak tanggal mulai berlaku. Tiket aktif mengikuti aturan baru setelah RA menyinkronkan checklist.</InfoBox></div>
            {editor && <RuleEditor key={editor.rule?.id ?? 'new'} rule={editor.rule} options={options} storeUrl={urls.store} onClose={() => setEditor(null)} />}
        </BackendLayout>
    );
}

function RuleEditor({ rule, options, storeUrl, onClose }: { rule: DocumentRule | null; options: RuleOptions; storeUrl: string; onClose: () => void }) {
    const form = useForm({
        document_type_id: String(rule?.document_type_id ?? options.documentTypes[0]?.value ?? ''),
        dosage_form_id: rule?.dosage_form_id ? String(rule.dosage_form_id) : '',
        category_id: rule?.category_id ? String(rule.category_id) : '',
        effective_from: rule?.effective_from ?? '',
        notes: rule?.notes ?? '',
        is_active: rule?.is_active ?? true,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (rule) form.put(rule.update_url, { preserveScroll: true, onSuccess: onClose });
        else form.post(storeUrl, { preserveScroll: true, onSuccess: onClose });
    }

    return (
        <Modal title={rule ? 'Ubah aturan dokumen' : 'Tambah aturan dokumen'} description="Kosongkan sediaan atau kategori agar aturan berlaku untuk semuanya. Setiap perubahan dicatat dalam audit." onClose={onClose}>
            <form onSubmit={submit} className="form-stack">
                <Field label="Jenis dokumen" error={form.errors.document_type_id}><Select value={form.data.document_type_id} onChange={(event) => form.setData('document_type_id', event.target.value)}>{options.documentTypes.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</Select></Field>
                <Field label="Sediaan" error={form.errors.dosage_form_id}><Select value={form.data.dosage_form_id} onChange={(event) => form.setData('dosage_form_id', event.target.value)}><option value="">Semua sediaan</option>{options.dosageForms.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</Select></Field>
                <Field label="Kategori" error={form.errors.category_id}><Select value={form.data.category_id} onChange={(event) => form.setData('category_id', event.target.value)}><option value="">Semua kategori</option>{options.categories.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</Select></Field>
                <Field label="Berlaku mulai" error={form.errors.effective_from}><input type="date" required value={form.data.effective_from} onChange={(event) => form.setData('effective_from', event.target.value)} /></Field>
                <Field label="Catatan" error={form.errors.notes} hint="Opsional, misalnya dasar aturan dari BPOM."><input maxLength={255} value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} /></Field>
                {rule && <label className="flex items-center gap-2"><input type="checkbox" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} />Aktif</label>}
                {form.errors.is_active && <p role="alert" className="text-red-700">{form.errors.is_active}</p>}
                <div className="form-footer"><Button variant="secondary" onClick={onClose} disabled={form.processing}>Batal</Button><Button type="submit" disabled={form.processing}>{form.processing ? 'Menyimpan...' : 'Simpan'}</Button></div>
            </form>
        </Modal>
    );
}

function formatDate(value: string) {
    return value.split('-').reverse().join('/');
}
