import { Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Check, Copy, Download, FileText, FlaskConical, Link2, Plus, Upload } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import type { BackendSharedProps } from '../../backend-types';
import { Badge, Button, EmptyState, Field, InfoBox, Modal, Panel, Progress, Select } from '../../components/ui';
import { BackendLayout } from '../../layouts/backend-layout';
import { sampleTones } from './Samples';
import { contractTones } from './Contracts';
import { InvoiceStatus, type InvoiceRow } from './Invoices';
import { ContractForm, rupiah, type ContractDefaults, type ContractOption } from '../../components/contract-form';

type TicketDetail = {
    id: number;
    code: string;
    product_name: string;
    client: string;
    client_pic: string;
    marketing_user: string;
    dosage_form: string;
    category: string;
    phase: string;
    phase_label: string;
    status: string;
    status_label: string;
    current_stage: string | null;
    created_by: string;
    created_at: string | null;
};
type DocumentVersion = { version: number; number: string; original_name: string; issued_at: string | null; expires_at: string | null; uploaded_at: string | null; download_url: string };
type ChecklistDocument = {
    id: number;
    name: string;
    material: string | null;
    scope: string;
    requirement: string;
    pic: string | null;
    both_phases: boolean;
    status: string;
    status_label: string;
    revision_reason: string | null;
    content_verified: boolean | null;
    requires_issued_at: boolean;
    requires_expires_at: boolean;
    can_upload: boolean;
    upload_url: string;
    current_version: DocumentVersion | null;
};
type ChecklistPhase = { phase: string; label: string; progress: number; documents: ChecklistDocument[] };
type StageEntry = { name: string; started_at: string; due_at: string | null; finished_at: string | null };
type HistoryEntry = { id: number; event: string; label: string; actor: string; created_at: string | null };
type TicketMaterial = { id: number; name: string; supplier: string | null };
type Option = { value: number; label: string };
type MaterialAccess = { manage_materials: boolean; create_material: boolean; manage_upload_links: boolean; record_sample: boolean; resolve_reviews: boolean; manage_contract: boolean; create_contract: boolean };
type MaterialUrls = { materials: string; create_material: string; upload_links: string; samples: string; resolve_reviews: string; contract_store: string };
type ContractDetail = {
    id: number; number: string; status: string; status_label: string; service_value: number; dp_percent: number; payment_due_days: number;
    document_template_id: number; template: string; dp_amount: number; settlement_amount: number; is_generated: boolean; generation_error: string | null;
    signed_at: string | null; confirmed_at: string | null; files: Partial<Record<'word' | 'pdf' | 'signed', string>>;
    urls: { update: string; send: string; signed: string; confirm: string };
};
type ContractOptions = { templates: ContractOption[]; defaults: ContractDefaults };
type ReviewEntry = { id: number; trigger_label: string; message: string; flagged_at: string | null };
type SampleEntry = { id: number; revision: number; decision: string; decision_label: string; note: string | null; recorded_by: string; recorded_at: string | null };
type UploadLink = { id: number; phase_label: string; expires_at: string; created_by: string; last_used_at: string | null; revoke_url: string };
type UploadLinks = { default_days: number; max_days: number; active: UploadLink[] };

const statusTones: Record<string, string> = { belum_ada: 'neutral', diunggah: 'blue', sedang_diverifikasi: 'amber', perlu_revisi: 'red', disetujui: 'green' };

export default function TicketShow({ ticket, checklist, stages, history, materials, materialOptions, can, urls, uploadLinks, samples, reviews, contract, contractOptions, invoices }: {
    ticket: TicketDetail;
    checklist: ChecklistPhase[];
    stages: StageEntry[];
    history: HistoryEntry[];
    materials: TicketMaterial[];
    materialOptions: Option[] | null;
    can: MaterialAccess;
    urls: MaterialUrls;
    uploadLinks: UploadLinks | null;
    samples: SampleEntry[];
    reviews: ReviewEntry[];
    contract: ContractDetail | null;
    contractOptions: ContractOptions | null;
    invoices: InvoiceRow[];
}) {
    const { routes } = usePage<BackendSharedProps>().props;
    const [uploading, setUploading] = useState<ChecklistDocument | null>(null);
    const [choosingMaterials, setChoosingMaterials] = useState(false);

    return (
        <BackendLayout title={`${ticket.code} · ${ticket.product_name}`}>
            <Link href={routes.tickets} className="mb-3 inline-flex items-center gap-1 text-sm text-gray-500"><ArrowLeft size={14} />Daftar tiket</Link>
            <div className="ticket-heading mb-5">
                <p className="text-sm text-gray-500">{ticket.code}</p>
                <h1 className="text-2xl font-semibold">{ticket.product_name}</h1>
                <div className="mt-2 flex flex-wrap gap-2"><Badge tone="blue">{ticket.phase_label}</Badge><Badge>{ticket.current_stage ?? 'Belum ada tahap'}</Badge><Badge tone={ticket.status === 'aktif' ? 'blue' : ticket.status === 'selesai' ? 'green' : 'red'}>{ticket.status_label}</Badge></div>
            </div>
            {reviews.length > 0 && <div className="mb-5"><InfoBox warning>
                <strong>Perlu ditinjau</strong>
                <ul className="mt-1 space-y-1 text-sm">{reviews.map((review) => <li key={review.id}>{review.message} <span className="text-gray-500">· {review.flagged_at}</span></li>)}</ul>
                {can.resolve_reviews && <Button className="mt-2" variant="secondary" icon={Check} onClick={() => router.post(urls.resolve_reviews, {}, { preserveScroll: true })}>Tandai sudah ditinjau</Button>}
            </InfoBox></div>}
            <div className="two-column">
                <Panel title="Informasi produk">
                    <dl className="panel-body grid grid-cols-2 gap-3 text-sm">
                        <dt className="text-gray-500">Klien</dt><dd>{ticket.client}</dd>
                        <dt className="text-gray-500">PIC klien</dt><dd>{ticket.client_pic}</dd>
                        <dt className="text-gray-500">PIC Marketing</dt><dd>{ticket.marketing_user}</dd>
                        <dt className="text-gray-500">Sediaan</dt><dd>{ticket.dosage_form}</dd>
                        <dt className="text-gray-500">Kategori</dt><dd>{ticket.category}</dd>
                        <dt className="text-gray-500">Dibuat</dt><dd>{ticket.created_at} oleh {ticket.created_by}</dd>
                    </dl>
                </Panel>
                <Panel title="Tahapan">
                    <ol className="panel-body space-y-3">
                        {stages.map((stage, index) => <li key={`${stage.name}-${index}`}>
                            <strong>{stage.name}</strong>
                            <div className="text-xs text-gray-500">Mulai {stage.started_at}{stage.due_at ? ` · tenggat ${stage.due_at}` : ''}{stage.finished_at ? ` · selesai ${stage.finished_at}` : ' · berjalan'}</div>
                        </li>)}
                    </ol>
                </Panel>
            </div>
            {(contract || can.create_contract) && <div className="mt-5"><ContractPanel ticketId={ticket.id} contract={contract} options={contractOptions} can={can} storeUrl={urls.contract_store} /></div>}
            {invoices.length > 0 && <div className="mt-5"><Panel title="Tagihan" description="Dibayar klien melalui Midtrans; status lunas tercatat otomatis. Tagihan DP tidak menahan proses.">
                <ul className="panel-body space-y-2 text-sm">{invoices.map((invoice) => <li key={invoice.id} className="flex flex-wrap items-center justify-between gap-2">
                    <span><strong>{invoice.number}</strong> · {invoice.term_label} · {rupiah(invoice.amount)} <span className="text-gray-500">· jatuh tempo {invoice.due_date}{invoice.paid_at ? ` · lunas ${invoice.paid_at}` : ''}</span></span>
                    <InvoiceStatus invoice={invoice} />
                </li>)}</ul>
            </Panel></div>}
            <div className="mt-5"><SamplePanel samples={samples} canRecord={can.record_sample} storeUrl={urls.samples} /></div>
            {uploadLinks && <div className="mt-5"><UploadLinkPanel links={uploadLinks} storeUrl={urls.upload_links} /></div>}
            <div className="mt-5">
                <Panel title="Bahan baku" description="Dokumen asal peredaran bahan yang masih berlaku langsung tertaut; bahan tanpa dokumen muncul sebagai butir checklist." action={can.manage_materials && materialOptions ? <Button variant="secondary" icon={FlaskConical} onClick={() => setChoosingMaterials(true)}>Atur bahan baku</Button> : undefined}>
                    {materials.length ? (
                        <div className="panel-body flex flex-wrap gap-2">{materials.map((material) => <Badge key={material.id} dot={false}>{material.supplier ? `${material.name} · ${material.supplier}` : material.name}</Badge>)}</div>
                    ) : <EmptyState title="Belum ada bahan baku." description="R&D memilih bahan baku produk dari master." icon={FlaskConical} />}
                </Panel>
            </div>
            <div className="mt-5 two-column">
                {checklist.map((phase) => (
                    <Panel key={phase.phase} title={`${phase.label} · ${phase.progress}%`}>
                        <div className="panel-body"><Progress value={phase.progress} showLabel={false} /></div>
                        {phase.documents.length ? (
                            <div className="overflow-x-auto">
                                <table className="w-full"><thead><tr><th>Dokumen</th><th>PIC</th><th>Status</th><th>Aksi</th></tr></thead>
                                    <tbody>{phase.documents.map((document) => <tr key={document.id}>
                                        <td>
                                            <strong>{document.name}</strong>{document.material && <span className="text-gray-500"> · {document.material}</span>}
                                            <div className="mt-1 flex flex-wrap gap-1"><Badge dot={false}>{document.requirement}</Badge><Badge dot={false}>{document.scope}</Badge>{document.both_phases && <Badge dot={false} tone="blue">Kedua fase</Badge>}{document.content_verified && <Badge dot={false} tone="green">Isi terverifikasi QA</Badge>}</div>
                                            {document.current_version && <div className="mt-1 text-xs text-gray-500">v{document.current_version.version} · No. {document.current_version.number}{document.current_version.issued_at ? ` · terbit ${document.current_version.issued_at}` : ''}{document.current_version.expires_at ? ` · berlaku s.d. ${document.current_version.expires_at}` : ''}</div>}
                                            {document.revision_reason && <p className="mt-1 text-xs text-red-700">{document.revision_reason}</p>}
                                        </td>
                                        <td>{document.pic ?? '—'}</td>
                                        <td><Badge tone={statusTones[document.status]}>{document.status_label}</Badge></td>
                                        <td><div className="flex gap-1">
                                            {document.current_version && <a className="btn btn-ghost" href={document.current_version.download_url} aria-label={`Unduh v${document.current_version.version} ${document.name}`}><Download size={16} strokeWidth={1.8} />Unduh</a>}
                                            {document.can_upload && <Button variant="ghost" icon={Upload} aria-label={`Unggah ${document.name}`} onClick={() => setUploading(document)}>Unggah</Button>}
                                        </div></td>
                                    </tr>)}</tbody>
                                </table>
                            </div>
                        ) : <EmptyState title="Belum ada dokumen di fase ini." description="Checklist mengikuti jenis dokumen wajib dan aturan dokumen saat tiket dibuat." icon={FileText} />}
                    </Panel>
                ))}
            </div>
            <div className="mt-5"><InfoBox>Setiap unggahan menjadi versi baru dan langsung divalidasi otomatis. Dokumen yang gagal validasi dikembalikan dengan alasannya. Dokumen yang berlaku untuk kedua fase cukup disiapkan sekali.</InfoBox></div>
            <div className="mt-5">
                <Panel title="Riwayat">
                    <ul className="panel-body space-y-2 text-sm">
                        {history.map((entry) => <li key={entry.id}><strong>{entry.label}</strong> <span className="text-gray-500">· {entry.actor} · {entry.created_at}</span></li>)}
                    </ul>
                </Panel>
            </div>
            {uploading && <DocumentUploader key={uploading.id} document={uploading} onClose={() => setUploading(null)} />}
            {choosingMaterials && materialOptions && <MaterialPicker selected={materials.map((material) => material.id)} options={materialOptions} canCreate={can.create_material} urls={urls} onClose={() => setChoosingMaterials(false)} />}
        </BackendLayout>
    );
}

function DocumentUploader({ document, onClose }: { document: ChecklistDocument; onClose: () => void }) {
    const form = useForm<{ file: File | null; number: string; issued_at: string; expires_at: string }>({ file: null, number: '', issued_at: '', expires_at: '' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(document.upload_url, { preserveScroll: true, onSuccess: onClose });
    }

    return (
        <Modal title={`Unggah ${document.name}`} description="PDF, JPG, PNG, DOCX, atau XLSX, maksimal 20 MB. Versi sebelumnya tetap tersimpan." onClose={onClose}>
            <form onSubmit={submit} className="form-stack">
                <Field label="Berkas dokumen" error={form.errors.file}><input type="file" accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx" onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)} /></Field>
                <Field label="Nomor dokumen" error={form.errors.number}><input required maxLength={100} value={form.data.number} onChange={(event) => form.setData('number', event.target.value)} /></Field>
                <Field label="Tanggal terbit" error={form.errors.issued_at} hint={document.requires_issued_at ? 'Wajib: umur dokumen dibatasi.' : 'Opsional.'}><input type="date" required={document.requires_issued_at} value={form.data.issued_at} onChange={(event) => form.setData('issued_at', event.target.value)} /></Field>
                {document.requires_expires_at && <Field label="Tanggal kedaluwarsa" error={form.errors.expires_at}><input type="date" required value={form.data.expires_at} onChange={(event) => form.setData('expires_at', event.target.value)} /></Field>}
                <div className="form-footer"><Button variant="secondary" onClick={onClose} disabled={form.processing}>Batal</Button><Button type="submit" disabled={form.processing || form.data.file === null}>{form.processing ? 'Mengunggah...' : 'Unggah versi baru'}</Button></div>
            </form>
        </Modal>
    );
}

function MaterialPicker({ selected, options, canCreate, urls, onClose }: { selected: number[]; options: Option[]; canCreate: boolean; urls: MaterialUrls; onClose: () => void }) {
    const form = useForm<{ material_ids: number[] }>({ material_ids: selected });
    const newMaterial = useForm({ name: '', supplier: '' });
    const [search, setSearch] = useState('');
    const visible = options.filter((option) => option.label.toLowerCase().includes(search.trim().toLowerCase()));
    const selectionError = Object.entries(form.errors as Record<string, string>).find(([key]) => key === 'material_ids' || key.startsWith('material_ids.'))?.[1];

    function toggle(id: number, checked: boolean) {
        form.setData('material_ids', checked ? [...form.data.material_ids, id] : form.data.material_ids.filter((value) => value !== id));
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.put(urls.materials, { preserveScroll: true, onSuccess: onClose });
    }

    function addMaterial(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        newMaterial.post(urls.create_material, { preserveScroll: true, preserveState: true, onSuccess: () => {
            setSearch(newMaterial.data.name);
            newMaterial.reset();
        } });
    }

    return (
        <Modal title="Atur bahan baku" description="Bahan yang dilepas menghapus butir checklist bahannya dari tiket ini; dokumen bahannya tetap tersimpan." onClose={onClose}>
            <form onSubmit={submit} className="form-stack">
                <Field label="Cari bahan"><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Nama bahan atau pemasok" /></Field>
                <fieldset className="max-h-72 space-y-1 overflow-y-auto" aria-label="Daftar bahan baku">
                    {visible.map((option) => <label key={option.value} className="flex items-center gap-2 text-sm">
                        <input type="checkbox" checked={form.data.material_ids.includes(option.value)} onChange={(event) => toggle(option.value, event.target.checked)} />
                        {option.label}
                    </label>)}
                    {!visible.length && <p className="text-sm text-gray-500">Bahan tidak ditemukan.</p>}
                </fieldset>
                {selectionError && <p className="form-error" role="alert">{selectionError}</p>}
                <div className="form-footer"><Button variant="secondary" onClick={onClose} disabled={form.processing}>Batal</Button><Button type="submit" disabled={form.processing}>{form.processing ? 'Menyimpan...' : `Simpan ${form.data.material_ids.length} bahan`}</Button></div>
            </form>
            {canCreate && <form onSubmit={addMaterial} className="form-stack mt-5 border-t pt-4">
                <p className="text-sm font-semibold">Bahan belum ada di master?</p>
                <Field label="Nama bahan baru" error={newMaterial.errors.name}><input required maxLength={150} value={newMaterial.data.name} onChange={(event) => newMaterial.setData('name', event.target.value)} /></Field>
                <Field label="Pemasok" error={newMaterial.errors.supplier} hint="Opsional."><input maxLength={150} value={newMaterial.data.supplier} onChange={(event) => newMaterial.setData('supplier', event.target.value)} /></Field>
                <div className="form-footer"><Button type="submit" variant="secondary" icon={Plus} disabled={newMaterial.processing}>{newMaterial.processing ? 'Menambahkan...' : 'Tambah ke master'}</Button></div>
            </form>}
        </Modal>
    );
}

function UploadLinkPanel({ links, storeUrl }: { links: UploadLinks; storeUrl: string }) {
    const { flash } = usePage<BackendSharedProps>().props;
    const form = useForm({ phase: 'pra_registrasi', days: String(links.default_days) });
    const [copied, setCopied] = useState(false);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setCopied(false);
        form.post(storeUrl, { preserveScroll: true });
    }

    async function copy(url: string) {
        await navigator.clipboard?.writeText(url);
        setCopied(true);
    }

    return (
        <Panel title="Tautan unggah klien" description="Klien membuka tautan tanpa login dan hanya melihat dokumen bagiannya pada fase yang dipilih. Membuat tautan baru mencabut tautan lama di fase yang sama.">
            <div className="panel-body space-y-4">
                {flash.upload_link && <InfoBox>
                    <p className="mb-2">Salin tautan ini sekarang dan kirim ke klien. Tautan tidak dapat ditampilkan lagi.</p>
                    <div className="flex flex-wrap items-center gap-2">
                        <code className="break-all text-xs" aria-label="Tautan unggah baru">{flash.upload_link}</code>
                        <Button variant="secondary" icon={Copy} onClick={() => copy(flash.upload_link!)}>{copied ? 'Tersalin' : 'Salin'}</Button>
                    </div>
                </InfoBox>}
                <form onSubmit={submit} className="flex flex-wrap items-end gap-3">
                    <Field label="Fase" error={form.errors.phase}>
                        <Select value={form.data.phase} onChange={(event) => form.setData('phase', event.target.value)}>
                            <option value="pra_registrasi">Pra-registrasi</option>
                            <option value="registrasi">Registrasi</option>
                        </Select>
                    </Field>
                    <Field label="Berlaku (hari)" error={form.errors.days}><input type="number" min={1} max={links.max_days} required value={form.data.days} onChange={(event) => form.setData('days', event.target.value)} /></Field>
                    <Button type="submit" icon={Link2} disabled={form.processing}>{form.processing ? 'Membuat...' : 'Buat tautan'}</Button>
                </form>
                {links.active.length ? <ul className="space-y-2 text-sm">
                    {links.active.map((link) => <li key={link.id} className="flex flex-wrap items-center justify-between gap-2">
                        <span><strong>{link.phase_label}</strong> <span className="text-gray-500">· berlaku s.d. {link.expires_at} · dibuat {link.created_by}{link.last_used_at ? ` · terakhir dipakai ${link.last_used_at}` : ' · belum dipakai'}</span></span>
                        <Button variant="ghost" aria-label={`Cabut tautan ${link.phase_label}`} onClick={() => router.delete(link.revoke_url, { preserveScroll: true })}>Cabut</Button>
                    </li>)}
                </ul> : <p className="text-sm text-gray-500">Belum ada tautan aktif.</p>}
            </div>
        </Panel>
    );
}

function SamplePanel({ samples, canRecord, storeUrl }: { samples: SampleEntry[]; canRecord: boolean; storeUrl: string }) {
    const form = useForm({ decision: '', note: '' });

    function record(decision: string) {
        form.transform((data) => ({ ...data, decision }));
        form.post(storeUrl, { preserveScroll: true, onSuccess: () => form.reset() });
    }

    return (
        <Panel title="Persetujuan sampel" description="Keputusan klien atas sampel; nomor revisi bertambah setiap kali dicatat.">
            <div className="panel-body space-y-4">
                {samples.length ? <ol className="space-y-2 text-sm">
                    {samples.map((sample) => <li key={sample.id}>
                        <strong>Revisi {sample.revision}</strong> <Badge tone={sampleTones[sample.decision]}>{sample.decision_label}</Badge>
                        <span className="text-gray-500"> · {sample.recorded_by} · {sample.recorded_at}</span>
                        {sample.note && <p className="mt-1">{sample.note}</p>}
                    </li>)}
                </ol> : <p className="text-sm text-gray-500">Belum ada keputusan sampel.</p>}
                {canRecord && <div className="form-stack">
                    <Field label="Catatan klien" error={form.errors.note ?? form.errors.decision} hint="Wajib diisi bila klien meminta revisi."><textarea maxLength={2000} value={form.data.note} onChange={(event) => form.setData('note', event.target.value)} placeholder="Tuliskan persetujuan atau masukan dari klien..." /></Field>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="secondary" disabled={form.processing} onClick={() => record('perlu_revisi')}>Perlu revisi</Button>
                        <Button icon={Check} disabled={form.processing} onClick={() => record('disetujui')}>Setujui sampel</Button>
                    </div>
                </div>}
            </div>
        </Panel>
    );
}

function ContractPanel({ ticketId, contract, options, can, storeUrl }: { ticketId: number; contract: ContractDetail | null; options: ContractOptions | null; can: MaterialAccess; storeUrl: string }) {
    const [editing, setEditing] = useState(false);
    const signed = useForm<{ file: File | null }>({ file: null });
    const action = (url: string) => router.post(url, {}, { preserveScroll: true });

    if (!contract) {
        return options && <Panel title="Kontrak (MoU)" description="Sampel sudah disetujui; buat kontrak dari templat.">
            <div className="panel-body"><ContractForm url={storeUrl} templates={options.templates} submitLabel="Buat kontrak" initial={{ ticket_id: ticketId, document_template_id: '', service_value: '', ...options.defaults }} /></div>
        </Panel>;
    }

    const manage = can.manage_contract;

    return (
        <Panel title={`Kontrak ${contract.number}`} description={`Templat ${contract.template}`} action={<Badge tone={contractTones[contract.status]}>{contract.status_label}</Badge>}>
            <div className="panel-body space-y-3 text-sm">
                <p>Nilai jasa <strong>{rupiah(contract.service_value)}</strong> · DP {contract.dp_percent}% ({rupiah(contract.dp_amount)}) · pelunasan {100 - contract.dp_percent}% ({rupiah(contract.settlement_amount)}) · jatuh tempo {contract.payment_due_days} hari</p>
                {!contract.is_generated && <p className="text-gray-500">Berkas kontrak sedang disiapkan. Muat ulang halaman sebentar lagi.</p>}
                {contract.generation_error && <p className="text-red-700">{contract.generation_error}</p>}
                <div className="flex flex-wrap gap-2">
                    {contract.files.pdf && <a className="btn btn-ghost" href={contract.files.pdf}><Download size={16} strokeWidth={1.8} />PDF</a>}
                    {contract.files.word && <a className="btn btn-ghost" href={contract.files.word}><Download size={16} strokeWidth={1.8} />Word</a>}
                    {contract.files.signed && <a className="btn btn-ghost" href={contract.files.signed}><Download size={16} strokeWidth={1.8} />Bertanda tangan</a>}
                </div>
                {contract.signed_at && <p className="text-gray-500">Berkas bertanda tangan diunggah {contract.signed_at}{contract.confirmed_at ? ` · dikonfirmasi ${contract.confirmed_at}` : ''}</p>}
                {manage && contract.status === 'draf' && (editing && options
                    ? <ContractForm url={contract.urls.update} method="put" templates={options.templates} submitLabel="Simpan perubahan" onCancel={() => setEditing(false)} onSuccess={() => setEditing(false)}
                        initial={{ document_template_id: contract.document_template_id, service_value: contract.service_value, dp_percent: contract.dp_percent, payment_due_days: contract.payment_due_days }} />
                    : <div className="flex flex-wrap gap-2">
                        <Button variant="secondary" onClick={() => setEditing(true)}>Ubah kontrak</Button>
                        <Button disabled={!contract.is_generated} onClick={() => action(contract.urls.send)}>Tandai dikirim</Button>
                    </div>)}
                {manage && (contract.status === 'dikirim' || contract.status === 'ditandatangani') && <form className="flex flex-wrap items-end gap-2" onSubmit={(event) => { event.preventDefault(); signed.post(contract.urls.signed, { preserveScroll: true, onSuccess: () => signed.reset() }); }}>
                    <Field label="Kontrak bertanda tangan (atas nama klien)" error={signed.errors.file}><input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(event) => signed.setData('file', event.target.files?.[0] ?? null)} /></Field>
                    <Button type="submit" variant="secondary" icon={Upload} disabled={signed.processing || !signed.data.file}>Unggah</Button>
                </form>}
                {manage && contract.status === 'ditandatangani' && <Button icon={Check} onClick={() => action(contract.urls.confirm)}>Konfirmasi kontrak aktif</Button>}
            </div>
        </Panel>
    );
}
