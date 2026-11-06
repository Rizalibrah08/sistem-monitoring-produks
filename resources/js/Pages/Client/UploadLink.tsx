import { Head, useForm, usePage } from '@inertiajs/react';
import { Download, FileSignature, FileText, HeartPulse, Leaf, LockKeyhole, MessageCircle, ShieldCheck, Upload } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import type { BackendSharedProps } from '../../backend-types';
import { Badge, Brand, Button, Field, Modal, Progress } from '../../components/ui';

type ClientTicket = { code: string; product_name: string; client: string; client_pic: string; marketing_user: string; phase_label: string; is_active: boolean };
type ClientDocument = {
    id: number;
    name: string;
    status: string;
    status_label: string;
    revision_reason: string | null;
    requires_issued_at: boolean;
    requires_expires_at: boolean;
    can_upload: boolean;
    upload_url: string;
    current_version: { version: number; original_name: string; uploaded_at: string | null } | null;
};

type ClientContract = { number: string; status: string; status_label: string; signed_at: string | null; can_sign: boolean; download_url: string; upload_url: string };

const statusTones: Record<string, string> = { belum_ada: 'neutral', diunggah: 'blue', sedang_diverifikasi: 'amber', perlu_revisi: 'red', disetujui: 'green' };

export default function UploadLink({ ticket, expires_at, progress, documents, contract }: { ticket: ClientTicket; expires_at: string; progress: number; documents: ClientDocument[]; contract: ClientContract | null }) {
    const { flash } = usePage<BackendSharedProps>().props;
    const [uploading, setUploading] = useState<ClientDocument | null>(null);

    return (
        <div className="client-outer">
            <Head title={`Unggah dokumen ${ticket.code}`}><meta name="robots" content="noindex, nofollow" /></Head>
            <div className="client-topbar">
                <Brand compact />
                <Badge tone="green"><LockKeyhole size={10} />Portal klien</Badge>
            </div>
            <main className="client-content">
                <div className="client-welcome">
                    <span className="eyebrow">PENDAFTARAN PRODUK ANDA</span>
                    <h1>Halo, {ticket.client_pic}<span>.</span></h1>
                    <p>Unggah dokumen yang menjadi bagian Anda untuk fase {ticket.phase_label}. Tautan ini berlaku sampai {expires_at}.</p>
                </div>
                {flash.status && <p role="status" className="info-box mb-5">{flash.status}</p>}
                <section className="client-product-card">
                    <span className="client-product-icon"><Leaf size={25} strokeWidth={1.4} /></span>
                    <div>
                        <small>{ticket.code}</small>
                        <h2>{ticket.product_name}</h2>
                        <span>{ticket.client}</span>
                    </div>
                    <Badge tone="blue">{ticket.phase_label}</Badge>
                </section>
                <section className="client-progress-card">
                    <div><strong>Dokumen disetujui</strong><span>{progress}%</span></div>
                    <Progress value={progress} showLabel={false} />
                </section>
                {!ticket.is_active && <p className="info-box info-warning mb-5">Pendaftaran ini sudah tidak aktif, sehingga dokumen tidak dapat diunggah lagi.</p>}
                {contract && <ContractSection contract={contract} />}
                <div className="client-section-heading">
                    <span className="client-section-number">01</span>
                    <div>
                        <h2>Dokumen yang dibutuhkan</h2>
                        <p>PDF, JPG, PNG, DOCX, atau XLSX, maksimal 20 MB per berkas.</p>
                    </div>
                </div>
                {documents.length ? documents.map((document) => (
                    <section className="client-doc-card" key={document.id}>
                        <div className="client-doc-title">
                            <span className="file-icon"><FileText size={19} /></span>
                            <div>
                                <h3>{document.name}</h3>
                                <small>{document.current_version ? `Versi ${document.current_version.version} · ${document.current_version.original_name}` : 'Belum ada berkas'}</small>
                            </div>
                            <Badge tone={statusTones[document.status]}>{document.status_label}</Badge>
                        </div>
                        {document.revision_reason && <p className="client-revision-note">{document.revision_reason}</p>}
                        {document.can_upload && <Button variant="secondary" icon={Upload} aria-label={`Unggah ${document.name}`} onClick={() => setUploading(document)}>
                            {document.current_version ? 'Unggah versi baru' : 'Unggah dokumen'}
                        </Button>}
                    </section>
                )) : <p className="info-box">Tidak ada dokumen yang perlu Anda unggah pada fase ini.</p>}
                <div className="client-help">
                    <span><MessageCircle size={22} /></span>
                    <div>
                        <h3>Butuh bantuan?</h3>
                        <p>Hubungi PIC Marketing Anda, {ticket.marketing_user}, melalui WhatsApp.</p>
                    </div>
                </div>
                <div className="client-secure-note"><ShieldCheck size={14} />Akses aman, hanya untuk pihak terkait</div>
            </main>
            <ClientFooter />
            {uploading && <ClientUploader key={uploading.id} document={uploading} onClose={() => setUploading(null)} />}
        </div>
    );
}

export function ClientFooter() {
    return (
        <footer className="client-footer">
            <HeartPulse size={15} />
            <span>PT Aisha Zahra Herbalaboratories<small>Mendampingi setiap langkah produk kesehatan Anda.</small></span>
        </footer>
    );
}

function ClientUploader({ document, onClose }: { document: ClientDocument; onClose: () => void }) {
    const form = useForm<{ file: File | null; number: string; issued_at: string; expires_at: string }>({ file: null, number: '', issued_at: '', expires_at: '' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(document.upload_url, { preserveScroll: true, onSuccess: onClose });
    }

    return (
        <Modal title={`Unggah ${document.name}`} description="Berkas langsung diperiksa otomatis. Versi sebelumnya tetap tersimpan." onClose={onClose}>
            <form onSubmit={submit} className="form-stack">
                <Field label="Berkas dokumen" error={form.errors.file}><input type="file" accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx" onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)} /></Field>
                <Field label="Nomor dokumen" error={form.errors.number} hint="Tulis nomor yang tertera pada dokumen."><input required maxLength={100} value={form.data.number} onChange={(event) => form.setData('number', event.target.value)} /></Field>
                <Field label="Tanggal terbit" error={form.errors.issued_at} hint={document.requires_issued_at ? 'Wajib diisi.' : 'Opsional.'}><input type="date" required={document.requires_issued_at} value={form.data.issued_at} onChange={(event) => form.setData('issued_at', event.target.value)} /></Field>
                {document.requires_expires_at && <Field label="Tanggal kedaluwarsa" error={form.errors.expires_at}><input type="date" required value={form.data.expires_at} onChange={(event) => form.setData('expires_at', event.target.value)} /></Field>}
                <div className="form-footer"><Button variant="secondary" onClick={onClose} disabled={form.processing}>Batal</Button><Button type="submit" disabled={form.processing || form.data.file === null}>{form.processing ? 'Mengunggah...' : 'Kirim dokumen'}</Button></div>
            </form>
        </Modal>
    );
}

function ContractSection({ contract }: { contract: ClientContract }) {
    const form = useForm<{ file: File | null }>({ file: null });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(contract.upload_url, { preserveScroll: true, onSuccess: () => form.reset() });
    }

    return (
        <section className="client-doc-card">
            <div className="client-doc-title">
                <span className="file-icon"><FileSignature size={19} /></span>
                <div>
                    <h3>Kontrak pendaftaran</h3>
                    <small>{contract.number}{contract.signed_at ? ` · bertanda tangan diunggah ${contract.signed_at}` : ''}</small>
                </div>
                <Badge>{contract.status_label}</Badge>
            </div>
            {contract.status !== 'aktif' && <a className="btn btn-secondary" href={contract.download_url}><Download size={16} />Unduh kontrak</a>}
            {contract.can_sign && <form onSubmit={submit} className="form-stack mt-3">
                <Field label="Kontrak bertanda tangan" error={form.errors.file} hint="PDF, JPG, atau PNG, maksimal 20 MB."><input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)} /></Field>
                <Button type="submit" icon={Upload} disabled={form.processing || !form.data.file}>{form.processing ? 'Mengunggah...' : 'Kirim kontrak bertanda tangan'}</Button>
            </form>}
        </section>
    );
}
