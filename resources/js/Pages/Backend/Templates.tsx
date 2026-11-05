import { useForm } from '@inertiajs/react';
import { Download, FileText, Upload } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Badge, Button, EmptyState, Field, InfoBox, Modal, PageHeader, Panel, Select } from '../../components/ui';
import { BackendLayout } from '../../layouts/backend-layout';

type Option = { value: string; label: string };
type TemplateVariable = { name: string; description: string };
type TemplateVersion = {
    id: number;
    version: number;
    original_name: string;
    size: number;
    placeholders: string[];
    notes: string | null;
    uploaded_by: string;
    uploaded_at: string | null;
    download_url: string;
};
type DocumentTemplate = {
    id: number;
    name: string;
    kind: string;
    kind_label: string;
    is_active: boolean;
    current_version: number | null;
    upload_url: string;
    versions: TemplateVersion[];
};

export default function Templates({ templates, kinds, variables, urls }: { templates: DocumentTemplate[]; kinds: Option[]; variables: TemplateVariable[]; urls: { store: string } }) {
    const [uploader, setUploader] = useState<{ template: DocumentTemplate | null } | null>(null);

    return (
        <BackendLayout title="Templat dokumen">
            <PageHeader title="Templat dokumen" eyebrow="KONFIGURASI SISTEM" description="Templat Word berversi untuk kontrak, surat, dan tagihan. Penanda isian diperiksa setiap kali diunggah."><Button icon={Upload} onClick={() => setUploader({ template: null })}>Unggah templat</Button></PageHeader>
            <div className="two-column">
                <div className="space-y-5">
                    {templates.length ? templates.map((template) => <TemplateCard key={template.id} template={template} onUpload={() => setUploader({ template })} />)
                        : <Panel><EmptyState title="Belum ada templat dokumen" description="Unggah templat Word dengan penanda seperti ${nama_klien}." icon={FileText} /></Panel>}
                </div>
                <Panel title="Variabel yang diizinkan" description="Tulis penanda di Word sebagai ${nama_variabel}. Penanda lain akan ditolak.">
                    <div className="overflow-x-auto">
                        <table className="w-full"><thead><tr><th>Penanda</th><th>Isi</th></tr></thead>
                            <tbody>{variables.map((variable) => <tr key={variable.name}><td><code>{`\${${variable.name}}`}</code></td><td>{variable.description}</td></tr>)}</tbody>
                        </table>
                    </div>
                </Panel>
            </div>
            <div className="mt-5"><InfoBox>Versi lama tidak dapat dihapus. Dokumen yang dibuat dari templat mencatat versi yang dipakai.</InfoBox></div>
            {uploader && <TemplateUploader key={uploader.template?.id ?? 'new'} template={uploader.template} kinds={kinds} storeUrl={urls.store} onClose={() => setUploader(null)} />}
        </BackendLayout>
    );
}

function TemplateCard({ template, onUpload }: { template: DocumentTemplate; onUpload: () => void }) {
    const current = template.versions[0];

    return (
        <Panel title={template.name} description={template.kind_label} action={<Button variant="secondary" icon={Upload} aria-label={`Unggah versi baru ${template.name}`} onClick={onUpload}>Versi baru</Button>}>
            <div className="mb-3 flex flex-wrap items-center gap-2">
                <Badge tone="blue">{template.current_version ? `Versi ${template.current_version}` : 'Belum ada versi'}</Badge>
                {current?.placeholders.map((placeholder) => <Badge key={placeholder} dot={false}>{`\${${placeholder}}`}</Badge>)}
            </div>
            <div className="overflow-x-auto">
                <table className="w-full"><thead><tr><th>Versi</th><th>Berkas</th><th>Diunggah</th><th>Catatan</th><th>Aksi</th></tr></thead>
                    <tbody>{template.versions.map((version) => <tr key={version.id}>
                        <td><strong>v{version.version}</strong></td>
                        <td>{version.original_name}<div className="text-xs text-gray-500">{formatSize(version.size)} · {version.placeholders.length} penanda</div></td>
                        <td>{version.uploaded_by}<div className="text-xs text-gray-500">{version.uploaded_at ?? '—'}</div></td>
                        <td>{version.notes ?? '—'}</td>
                        <td><a className="btn btn-ghost" href={version.download_url} aria-label={`Unduh versi ${version.version}`}><Download size={16} strokeWidth={1.8} />Unduh</a></td>
                    </tr>)}</tbody>
                </table>
            </div>
        </Panel>
    );
}

function TemplateUploader({ template, kinds, storeUrl, onClose }: { template: DocumentTemplate | null; kinds: Option[]; storeUrl: string; onClose: () => void }) {
    const newTemplate = useForm<{ name: string; kind: string; file: File | null; notes: string }>({ name: '', kind: kinds[0]?.value ?? '', file: null, notes: '' });
    const newVersion = useForm<{ file: File | null; notes: string }>({ file: null, notes: '' });
    const errors = template ? newVersion.errors : newTemplate.errors;
    const processing = template ? newVersion.processing : newTemplate.processing;
    const notes = template ? newVersion.data.notes : newTemplate.data.notes;
    const file = template ? newVersion.data.file : newTemplate.data.file;

    function setFile(file: File | null) {
        if (template) newVersion.setData('file', file);
        else newTemplate.setData('file', file);
    }

    function setNotes(value: string) {
        if (template) newVersion.setData('notes', value);
        else newTemplate.setData('notes', value);
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (template) newVersion.post(template.upload_url, { preserveScroll: true, onSuccess: onClose });
        else newTemplate.post(storeUrl, { preserveScroll: true, onSuccess: onClose });
    }

    return (
        <Modal title={template ? `Versi baru ${template.name}` : 'Unggah templat'} description="Hanya berkas .docx, maksimal 20 MB. Penanda yang tidak dikenal akan ditolak beserta namanya." onClose={onClose}>
            <form onSubmit={submit} className="form-stack">
                {!template && <>
                    <Field label="Nama templat" error={newTemplate.errors.name}><input autoFocus required maxLength={150} value={newTemplate.data.name} onChange={(event) => newTemplate.setData('name', event.target.value)} /></Field>
                    <Field label="Jenis templat" error={newTemplate.errors.kind}><Select value={newTemplate.data.kind} onChange={(event) => newTemplate.setData('kind', event.target.value)}>{kinds.map((kind) => <option key={kind.value} value={kind.value}>{kind.label}</option>)}</Select></Field>
                </>}
                <Field label="Berkas Word (.docx)" error={errors.file}><input type="file" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" onChange={(event) => setFile(event.target.files?.[0] ?? null)} /></Field>
                <Field label="Catatan perubahan" error={errors.notes} hint="Opsional."><input maxLength={255} value={notes} onChange={(event) => setNotes(event.target.value)} /></Field>
                <div className="form-footer"><Button variant="secondary" onClick={onClose} disabled={processing}>Batal</Button><Button type="submit" disabled={processing || file === null}>{processing ? 'Mengunggah...' : 'Unggah'}</Button></div>
            </form>
        </Modal>
    );
}

function formatSize(bytes: number) {
    return bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toLocaleString('id-ID', { maximumFractionDigits: 1 })} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}
