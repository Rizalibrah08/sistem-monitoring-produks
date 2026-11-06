import { router, useForm } from '@inertiajs/react';
import { Download, FileText, Upload } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Badge, Button, EmptyState, Field, Modal, PageHeader, Panel, Select } from '../../components/ui';
import { BackendLayout } from '../../layouts/backend-layout';

type SharedVersion = { id: number; version: number; number: string; issued_at: string | null; expires_at: string | null; validation_passed: boolean; uploaded_by: string; uploaded_at: string | null; download_url: string };
type SharedDocument = { id: number; name: string; scope: string; owner: string; active_tickets: number; current: SharedVersion; expiring: boolean; versions: SharedVersion[] };
type CompanyType = { value: number; label: string; requires_issued_at: boolean; requires_expires_at: boolean };

export default function SharedDocuments({ documents, filters, companyTypes, urls }: { documents: SharedDocument[]; filters: { scope: string }; companyTypes: CompanyType[]; urls: { store: string } }) {
    const [uploading, setUploading] = useState(false);

    return (
        <BackendLayout title="Dokumen bersama">
            <PageHeader title="Dokumen bersama" eyebrow="OPERASIONAL" description="Dokumen milik klien, bahan baku, dan perusahaan yang dipakai ulang lintas tiket, beserta riwayat versinya.">
                {companyTypes.length > 0 && <Button icon={Upload} onClick={() => setUploading(true)}>Unggah dokumen perusahaan</Button>}
            </PageHeader>
            <div className="filter-bar"><Field label="Cakupan"><Select value={filters.scope} onChange={(event) => router.get(window.location.pathname, { scope: event.target.value || undefined }, { preserveScroll: true, replace: true })}>
                <option value="">Semua cakupan</option><option value="klien">Klien</option><option value="bahan_baku">Bahan baku</option><option value="perusahaan">Perusahaan</option>
            </Select></Field></div>
            <Panel>
                {documents.length ? (
                    <div className="overflow-x-auto">
                        <table className="w-full"><thead><tr><th>Dokumen</th><th>Pemilik</th><th>Versi terkini</th><th>Masa berlaku</th><th>Tiket aktif</th></tr></thead>
                            <tbody>{documents.map((document) => <tr key={document.id}>
                                <td><strong>{document.name}</strong><div><Badge dot={false}>{document.scope}</Badge></div></td>
                                <td>{document.owner}</td>
                                <td>
                                    <a className="text-link" href={document.current.download_url}>v{document.current.version} · No. {document.current.number}</a>
                                    {!document.current.validation_passed && <div><Badge tone="red">Gagal validasi</Badge></div>}
                                    {document.versions.length > 1 && <details className="mt-1 text-xs"><summary className="cursor-pointer text-gray-500">Riwayat {document.versions.length} versi</summary>
                                        <ul className="mt-1 space-y-1">{document.versions.map((version) => <li key={version.id}><a className="text-link" href={version.download_url}><Download size={12} className="inline" /> v{version.version}</a> · {version.uploaded_by}, {version.uploaded_at}</li>)}</ul>
                                    </details>}
                                </td>
                                <td className={document.expiring ? 'text-red-700' : ''}>{document.current.expires_at ?? '—'}{document.expiring && <div className="text-xs">Kedaluwarsa ≤ 30 hari</div>}</td>
                                <td>{document.active_tickets}</td>
                            </tr>)}</tbody>
                        </table>
                    </div>
                ) : <EmptyState title="Belum ada dokumen bersama" description="Dokumen bersama tersimpan saat diunggah dari tiket atau dari halaman ini." icon={FileText} />}
            </Panel>
            {uploading && <CompanyUploader types={companyTypes} url={urls.store} onClose={() => setUploading(false)} />}
        </BackendLayout>
    );
}

function CompanyUploader({ types, url, onClose }: { types: CompanyType[]; url: string; onClose: () => void }) {
    const form = useForm<{ document_type_id: string; file: File | null; number: string; issued_at: string; expires_at: string }>({ document_type_id: '', file: null, number: '', issued_at: '', expires_at: '' });
    const type = types.find((option) => String(option.value) === form.data.document_type_id);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(url, { preserveScroll: true, onSuccess: onClose });
    }

    return (
        <Modal title="Unggah dokumen bersama perusahaan" description="Versi baru divalidasi otomatis; tiket aktif yang memakainya ditandai perlu ditinjau." onClose={onClose}>
            <form onSubmit={submit} className="form-stack">
                <Field label="Jenis dokumen" error={form.errors.document_type_id}><Select required value={form.data.document_type_id} onChange={(event) => form.setData('document_type_id', event.target.value)}><option value="">Pilih jenis</option>{types.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</Select></Field>
                <Field label="Berkas dokumen" error={form.errors.file}><input type="file" accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx" onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)} /></Field>
                <Field label="Nomor dokumen" error={form.errors.number}><input required maxLength={100} value={form.data.number} onChange={(event) => form.setData('number', event.target.value)} /></Field>
                <Field label="Tanggal terbit" error={form.errors.issued_at} hint={type?.requires_issued_at ? 'Wajib: umur dokumen dibatasi.' : 'Opsional.'}><input type="date" required={type?.requires_issued_at} value={form.data.issued_at} onChange={(event) => form.setData('issued_at', event.target.value)} /></Field>
                {type?.requires_expires_at && <Field label="Tanggal kedaluwarsa" error={form.errors.expires_at}><input type="date" required value={form.data.expires_at} onChange={(event) => form.setData('expires_at', event.target.value)} /></Field>}
                <div className="form-footer"><Button variant="secondary" onClick={onClose} disabled={form.processing}>Batal</Button><Button type="submit" disabled={form.processing || !form.data.file}>Unggah versi baru</Button></div>
            </form>
        </Modal>
    );
}
