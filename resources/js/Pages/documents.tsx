import { Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    CloudUpload,
    FileCheck2,
    FileText,
    FlaskConical,
    History,
    Link2,
    LockKeyhole,
    Plus,
    RefreshCw,
    ShieldCheck,
    Upload,
    X,
} from 'lucide-react';
import { useRef, useState, type FormEvent } from 'react';
import {
    Badge,
    Button,
    CheckLine,
    EmptyState,
    Field,
    FilePreview,
    InfoBox,
    LinkButton,
    Modal,
    PageHeader,
    Panel,
    Progress,
    Select,
} from '../components/ui';
import { usePreview } from '../mocks/preview-provider';
import { DEMO_DATE, DEMO_TICKET } from '../mocks/fixtures';
import { dateLabel, ticketProgress } from '../lib/utils';
import { canSubmitTicket, reviewDocument, validateUpload } from '../features/workflow';
import { roleLabels, type DocumentItem, type Ticket } from '../types';

export function DocumentChecklist({ ticket }: { ticket: Ticket }) {
    const { role } = usePreview();
    const [phase, setPhase] = useState('');
    const [upload, setUpload] = useState<DocumentItem | null>(null);
    const [review, setReview] = useState<DocumentItem | null>(null);
    const docs = ticket.documents.filter((doc) => !phase || doc.phase === phase || doc.phase === 'Kedua fase');
    const eligibility = canSubmitTicket(ticket);
    return (
        <>
            <Panel
                title="Checklist dokumen"
                description="Periksa kelengkapan, validasi dasar, dan versi setiap dokumen."
                action={
                    <Select aria-label="Fase dokumen" value={phase} onChange={(event) => setPhase(event.target.value)}>
                        <option value="">Semua fase</option>
                        <option>Pra-registrasi</option>
                        <option>Registrasi</option>
                    </Select>
                }
            >
                <div className="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Dokumen</th>
                                <th>Cakupan / PIC</th>
                                <th>Versi</th>
                                <th>Validasi dasar</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {docs.map((doc) => (
                                <tr key={doc.id}>
                                    <td>
                                        <div className="document-name">
                                            <span className="file-icon">
                                                <FileText size={18} />
                                            </span>
                                            <span>
                                                <strong>{doc.name}</strong>
                                                <small>
                                                    {doc.phase}
                                                    {doc.conditional
                                                        ? ' · Kondisional wajib'
                                                        : doc.required
                                                          ? ' · Wajib'
                                                          : ' · Opsional'}
                                                </small>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        {doc.scope}
                                        <small>{doc.pic}</small>
                                    </td>
                                    <td>
                                        {doc.version ? (
                                            <button className="version-link" onClick={() => setReview(doc)}>
                                                v{doc.version}
                                                <History size={11} />
                                            </button>
                                        ) : (
                                            <span className="text-muted">—</span>
                                        )}
                                        {doc.locked && <LockKeyhole size={12} className="mt-1 text-muted" />}
                                    </td>
                                    <td>
                                        <span
                                            className={`validation-label ${doc.validation === 'Lolos' ? 'passed' : doc.validation === 'Gagal' ? 'failed' : ''}`}
                                        >
                                            {doc.validation === 'Lolos' ? (
                                                <CheckCircle2 size={12} />
                                            ) : doc.validation === 'Gagal' ? (
                                                <X size={12} />
                                            ) : null}
                                            {doc.validation}
                                        </span>
                                        {doc.reason && (
                                            <small className="max-w-48 whitespace-normal">{doc.reason}</small>
                                        )}
                                    </td>
                                    <td>
                                        <Badge>{doc.status}</Badge>
                                    </td>
                                    <td>
                                        <div className="flex gap-1">
                                            {!doc.locked &&
                                                !ticket.closed &&
                                                ((role === 'rnd' && doc.pic === 'R&D') ||
                                                    (role === 'qa' && doc.pic === 'QA/QC') ||
                                                    (role === 'marketing' && doc.pic === 'Klien')) && (
                                                    <Button
                                                        variant="secondary"
                                                        icon={Upload}
                                                        onClick={() => setUpload(doc)}
                                                    >
                                                        Unggah
                                                    </Button>
                                                )}
                                            <Button
                                                variant={
                                                    role === 'ra' && doc.status === 'Menunggu verifikasi'
                                                        ? 'primary'
                                                        : 'ghost'
                                                }
                                                aria-label={`Periksa dokumen ${doc.name}`}
                                                onClick={() => setReview(doc)}
                                            >
                                                {role === 'ra' && doc.status === 'Menunggu verifikasi'
                                                    ? 'Periksa'
                                                    : 'Lihat'}
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div className="document-progress-footer">
                    <div>
                        <span>Progres dokumen wajib</span>
                        <strong>{ticketProgress(ticket)}% disetujui</strong>
                        <Progress value={ticketProgress(ticket)} showLabel={false} />
                    </div>
                    {role === 'ra' && (
                        <Button
                            icon={ArrowRight}
                            disabled={!eligibility.allowed}
                            onClick={() => router.visit(`/bpom?ticket=${ticket.id}`, { preserveState: true })}
                        >
                            Ajukan ke BPOM
                        </Button>
                    )}
                </div>
            </Panel>
            {role === 'ra' && !eligibility.allowed && (
                <div className="mt-4">
                    <InfoBox warning>
                        <strong>Pengajuan belum dapat dilakukan</strong>
                        <ul className="gate-reasons">
                            {eligibility.reasons.map((reason) => (
                                <li key={reason}>{reason}</li>
                            ))}
                        </ul>
                    </InfoBox>
                </div>
            )}
            {upload && <UploadDialog ticket={ticket} document={upload} onClose={() => setUpload(null)} />}
            {review && (
                <Modal
                    title={review.name}
                    description={`${ticket.id} · ${ticket.product}`}
                    wide
                    onClose={() => setReview(null)}
                >
                    <ReviewContent ticket={ticket} document={review} onDone={() => setReview(null)} />
                </Modal>
            )}
        </>
    );
}

export function ReviewContent({
    ticket,
    document: selectedDocument,
    onDone,
    quality = false,
}: {
    ticket: Ticket;
    document: DocumentItem;
    onDone: () => void;
    quality?: boolean;
}) {
    const { role, updateTicket } = usePreview();
    const [doc] = useState(selectedDocument);
    const [expectedRevision] = useState(ticket.revision);
    const [reason, setReason] = useState('');
    const [error, setError] = useState('');
    const [checks, setChecks] = useState([false, false, false]);
    const canReview = (quality ? role === 'qa' : role === 'ra') && !doc.locked && !ticket.closed && doc.version > 0;
    function decide(decision: 'approve' | 'revise') {
        setError('');
        if (decision === 'revise' && !reason.trim()) return setError('Alasan pengembalian wajib diisi.');
        if (!canReview) return;
        try {
            if (
                updateTicket(
                    ticket.id,
                    (current) => {
                        if (current.revision !== expectedRevision) {
                            const message =
                                'Data sudah berubah. Tutup dan buka kembali pemeriksaan sebelum melanjutkan.';
                            setError(message);
                            throw new Error(message);
                        }
                        return quality && decision === 'approve'
                            ? {
                                  ...current,
                                  revision: current.revision + 1,
                                  documents: current.documents.map((item) =>
                                      item.id === doc.id ? { ...item, qaApproved: true } : item,
                                  ),
                              }
                            : reviewDocument(current, doc.id, decision, reason, expectedRevision);
                    },
                    decision === 'approve'
                        ? quality
                            ? 'Isi dokumen disetujui QA pada demo.'
                            : 'Pemakaian dokumen disetujui RA pada demo.'
                        : 'Dokumen dikembalikan untuk revisi.',
                )
            )
                onDone();
        } catch (caught) {
            setError((caught as Error).message);
        }
    }
    return (
        <div className="review-grid">
            <FilePreview title={doc.name} subtitle={`${ticket.product} · versi ${doc.version || '—'}`} />
            <div className="review-details">
                <div className="review-doc-meta">
                    <Badge tone="neutral" dot={false}>
                        v{doc.version}
                    </Badge>
                    <Badge>{doc.status}</Badge>
                    {doc.locked && (
                        <Badge tone="neutral">
                            <LockKeyhole size={11} />
                            Versi terkunci
                        </Badge>
                    )}
                </div>
                <h3>Pemeriksaan dokumen</h3>
                <div className="review-checks">
                    <div className="eyebrow">VALIDASI DASAR · DEMO</div>
                    <CheckLine checked={doc.validation === 'Lolos'}>Format dan ukuran berkas</CheckLine>
                    <CheckLine checked={Boolean(doc.issuedAt)}>
                        Tanggal terbit {doc.issuedAt ? dateLabel(doc.issuedAt) : 'belum diisi'}
                    </CheckLine>
                    <CheckLine checked={Boolean(doc.number)}>Nomor dokumen terisi</CheckLine>
                </div>
                {doc.requiresQa && (
                    <div className="qa-approval">
                        <ShieldCheck size={17} />
                        <span>
                            <strong>{doc.qaApproved ? 'Isi disetujui QA/QC' : 'Menunggu persetujuan QA/QC'}</strong>
                            <small>Persetujuan berlaku untuk versi dokumen ini.</small>
                        </span>
                    </div>
                )}
                {canReview && (
                    <>
                        <div className="eyebrow mt-5">PEMERIKSAAN {quality ? 'QA/QC' : 'RA'}</div>
                        {[
                            'Identitas dan nomor batch sesuai',
                            'Parameter dokumen lengkap',
                            'Pengesahan penanggung jawab tersedia',
                        ].map((label, index) => (
                            <label className="manual-check" key={label}>
                                <input
                                    type="checkbox"
                                    checked={checks[index]}
                                    onChange={() => setChecks(checks.map((val, i) => (i === index ? !val : val)))}
                                />
                                {label}
                            </label>
                        ))}
                        <Field label="Catatan / alasan pengembalian" error={error}>
                            <textarea
                                placeholder="Tulis alasan jika dokumen perlu direvisi..."
                                value={reason}
                                onChange={(event) => setReason(event.target.value)}
                            />
                        </Field>
                        <div className="review-actions">
                            <Button
                                variant="secondary"
                                aria-label="Kembalikan dokumen"
                                onClick={() => decide('revise')}
                            >
                                Kembalikan
                            </Button>
                            <Button
                                disabled={
                                    !checks.every(Boolean) ||
                                    doc.validation !== 'Lolos' ||
                                    (!quality && doc.requiresQa && !doc.qaApproved)
                                }
                                onClick={() => decide('approve')}
                            >
                                {quality ? 'Setujui isi' : 'Setujui pemakaian'}
                            </Button>
                        </div>
                        <p className="section-note">
                            Centang seluruh pemeriksaan sebelum menyetujui. Alasan wajib diisi saat mengembalikan.
                        </p>
                    </>
                )}
                {!canReview && (
                    <InfoBox>
                        {doc.locked
                            ? 'Versi ini sudah diajukan dan dikunci.'
                            : 'Anda dapat melihat dokumen. Persetujuan dilakukan oleh role verifikator.'}
                    </InfoBox>
                )}
                <div className="version-history">
                    <h4>
                        <History size={14} />
                        Riwayat versi
                    </h4>
                    {doc.versions.map((version) => (
                        <div key={version.version}>
                            <strong>
                                v{version.version}
                                <span>
                                    {dateLabel(version.date)} · {version.author}
                                </span>
                            </strong>
                            <p>{version.note}</p>
                        </div>
                    ))}
                    {!doc.versions.length && <p>Dokumen belum diunggah.</p>}
                </div>
            </div>
        </div>
    );
}

export function UploadDialog({
    ticket,
    document: doc,
    onClose,
    source = 'internal',
    attachment,
}: {
    ticket: Ticket;
    document: DocumentItem;
    onClose: () => void;
    source?: 'internal' | 'client' | 'chat';
    attachment?: string;
}) {
    const { role, updateTicket, update } = usePreview();
    const ref = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(
        attachment ? new File(['Demonstration attachment only'], attachment, { type: 'application/pdf' }) : null,
    );
    const [metadata, setMetadata] = useState({ number: '', issuedAt: '', expiresAt: '' });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [dragging, setDragging] = useState(false);
    function submit(event: FormEvent) {
        event.preventDefault();
        const next = validateUpload(file, metadata, Boolean(doc.expiresAt));
        if (ticket.closed || doc.locked) next.file = 'Dokumen sudah dikunci dan tidak dapat diubah.';
        setErrors(next);
        if (Object.keys(next).length || !file) return;
        const version = doc.version + 1;
        const value: DocumentItem = {
            ...doc,
            ...metadata,
            version,
            status: 'Menunggu verifikasi',
            validation: 'Lolos',
            reason: undefined,
            qaApproved: !doc.requiresQa,
            versions: [
                {
                    version,
                    fileName: file.name,
                    date: DEMO_DATE,
                    author: source === 'client' ? 'Klien' : roleLabels[role],
                    note: `Diunggah dari ${source === 'chat' ? 'lampiran chat' : source === 'client' ? 'portal klien' : 'web internal'} · validasi dasar demo`,
                },
                ...doc.versions,
            ],
        };
        const shared = doc.scope === 'Perusahaan' || doc.scope === 'Bahan baku' || doc.scope === 'Klien';
        const success = shared
            ? update(
                  (state) => ({
                      ...state,
                      tickets: state.tickets.map((item) =>
                          item.closed || item.submitted || (doc.scope === 'Klien' && item.clientId !== ticket.clientId)
                              ? item
                              : {
                                    ...item,
                                    needsReview: true,
                                    revision: item.revision + 1,
                                    documents: item.documents.map((existing) =>
                                        existing.id === doc.id ? value : existing,
                                    ),
                                },
                      ),
                  }),
                  `Dokumen bersama v${version} disimpan pada demo; tiket terkait perlu ditinjau.`,
              )
            : updateTicket(
                  ticket.id,
                  (current) => ({
                      ...current,
                      revision: current.revision + 1,
                      documents: current.documents.map((item) => (item.id === doc.id ? value : item)),
                  }),
                  `Dokumen v${version} berhasil diunggah pada demo.`,
              );
        if (success) onClose();
    }
    return (
        <Modal
            title={source === 'chat' ? 'Jadikan lampiran sebagai dokumen' : 'Unggah dokumen'}
            description={`${doc.name} · ${ticket.id}`}
            onClose={onClose}
        >
            <form onSubmit={submit}>
                <div
                    className={`upload-dropzone ${dragging ? 'is-dragging' : ''}`}
                    onDragOver={(event) => {
                        event.preventDefault();
                        setDragging(true);
                    }}
                    onDragLeave={() => setDragging(false)}
                    onDrop={(event) => {
                        event.preventDefault();
                        setDragging(false);
                        setFile(event.dataTransfer.files[0] ?? null);
                    }}
                >
                    <span className="upload-cloud">
                        <CloudUpload size={27} strokeWidth={1.5} />
                    </span>
                    <strong>{file ? file.name : 'Seret berkas ke sini'}</strong>
                    <span>
                        {file
                            ? `${(file.size / 1024).toFixed(1)} KB · siap diunggah`
                            : 'atau pilih dari perangkat Anda'}
                    </span>
                    <input
                        ref={ref}
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx"
                        className="sr-only"
                        aria-label="Pilih berkas dokumen"
                        onChange={(event) => setFile(event.target.files?.[0] ?? null)}
                    />
                    <Button variant="secondary" onClick={() => ref.current?.click()}>
                        {file ? 'Ganti berkas' : 'Pilih berkas'}
                    </Button>
                    <small>PDF, JPG, PNG, DOCX, XLSX · maksimal 20 MB</small>
                </div>
                {errors.file && (
                    <p className="form-error mt-2" role="alert">
                        {errors.file}
                    </p>
                )}
                <div className="form-grid mt-5">
                    <Field label="Nomor dokumen" error={errors.number}>
                        <input
                            value={metadata.number}
                            onChange={(event) => setMetadata({ ...metadata, number: event.target.value })}
                            placeholder="Contoh: COA/2026/012"
                        />
                    </Field>
                    <Field label="Tanggal terbit" error={errors.issuedAt}>
                        <input
                            type="date"
                            value={metadata.issuedAt}
                            onChange={(event) => setMetadata({ ...metadata, issuedAt: event.target.value })}
                        />
                    </Field>
                    {doc.expiresAt && (
                        <Field label="Tanggal kedaluwarsa" error={errors.expiresAt}>
                            <input
                                type="date"
                                value={metadata.expiresAt}
                                onChange={(event) => setMetadata({ ...metadata, expiresAt: event.target.value })}
                            />
                        </Field>
                    )}
                </div>
                <div className="mt-5">
                    <InfoBox>
                        Pratinjau hanya menyimpan nama dan metadata berkas di memori browser. Isi berkas tidak diunggah
                        ke server; pemeriksaan isi tetap dilakukan pada tahap backend.
                    </InfoBox>
                </div>
                {doc.versions.length > 0 && (
                    <div className="version-history mt-5">
                        <h4>
                            <History size={14} />
                            Versi sebelumnya tetap tersimpan
                        </h4>
                        {doc.versions.slice(0, 2).map((version) => (
                            <div key={version.version}>
                                <strong>
                                    v{version.version}
                                    <span>
                                        {dateLabel(version.date)} · {version.author}
                                    </span>
                                </strong>
                                <p>{version.note}</p>
                            </div>
                        ))}
                    </div>
                )}
                <div className="form-footer">
                    <Button variant="secondary" onClick={onClose}>
                        Batal
                    </Button>
                    <Button type="submit" icon={Upload}>
                        Unggah sebagai v{doc.version + 1}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

export function VerificationPage({ quality = false }: { quality?: boolean }) {
    const { data } = usePreview();
    const [index, setIndex] = useState(0);
    const rows = data.tickets
        .filter((item) => !item.closed && !item.submitted && item.dpPaid)
        .flatMap((ticket) =>
            ticket.documents
                .filter(
                    (doc) => doc.status === 'Menunggu verifikasi' && (!quality || (doc.requiresQa && !doc.qaApproved)),
                )
                .map((doc) => ({ ticket, doc })),
        );
    const current = rows[Math.min(index, Math.max(rows.length - 1, 0))];
    return (
        <>
            <PageHeader
                title={quality ? 'Verifikasi dokumen mutu' : 'Verifikasi dokumen'}
                eyebrow={quality ? 'PEMERIKSAAN QA/QC' : 'REGULATORY AFFAIRS'}
                description={
                    quality
                        ? 'Pastikan isi dokumen mutu sesuai sebelum digunakan RA.'
                        : 'Periksa dokumen satu per satu, tanpa kehilangan konteks tiket.'
                }
            >
                {current && (
                    <div className="review-pagination">
                        <Button
                            variant="secondary"
                            icon={ChevronLeft}
                            disabled={index === 0}
                            onClick={() => setIndex(index - 1)}
                        >
                            Sebelumnya
                        </Button>
                        <span>
                            {Math.min(index + 1, rows.length)} / {rows.length}
                        </span>
                        <Button
                            variant="secondary"
                            disabled={index >= rows.length - 1}
                            onClick={() => setIndex(index + 1)}
                        >
                            Berikutnya
                            <ChevronRight size={14} />
                        </Button>
                    </div>
                )}
            </PageHeader>
            {current ? (
                <>
                    <div className="review-ticket-context">
                        <Badge tone="blue" dot={false}>
                            {current.ticket.phase}
                        </Badge>
                        <strong>{current.ticket.product}</strong>
                        <Link href={`/tickets/${current.ticket.id}?tab=documents`} preserveState>
                            {current.ticket.id}
                            <ArrowRight size={12} />
                        </Link>
                    </div>
                    <Panel className="panel-body">
                        <ReviewContent
                            key={`${current.ticket.id}-${current.doc.id}-${current.doc.version}`}
                            ticket={current.ticket}
                            document={current.doc}
                            quality={quality}
                            onDone={() => setIndex(0)}
                        />
                    </Panel>
                </>
            ) : (
                <Panel>
                    <EmptyState
                        title="Semua dokumen sudah diperiksa"
                        description="Dokumen yang lolos validasi dasar akan muncul di sini."
                        icon={ShieldCheck}
                    />
                </Panel>
            )}
        </>
    );
}

export function TasksPage() {
    const { data, role } = usePreview();
    const [upload, setUpload] = useState<{ ticket: Ticket; doc: DocumentItem } | null>(null);
    const tickets = data.tickets
        .filter((item) => !item.closed && !item.submitted)
        .slice(0, 5)
        .sort((a, b) => b.score - a.score);
    const rows = tickets.flatMap((ticket, index) =>
        ticket.documents
            .filter(
                (doc) =>
                    doc.pic === (role === 'qa' ? 'QA/QC' : 'R&D') &&
                    (doc.status === 'Belum ada' || doc.status === 'Perlu revisi'),
            )
            .map((doc) => ({ ticket, doc, rank: index + 1 })),
    );
    return (
        <>
            <PageHeader
                title="Tugas dokumen"
                eyebrow="PEKERJAAN SAYA"
                description="Dokumen yang perlu Anda lengkapi, diurutkan berdasarkan prioritas tiket."
            />
            <Panel title="Daftar tugas" description={`${rows.length} tugas pada tiket yang ditugaskan kepada Anda`}>
                <div className="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Prioritas tiket</th>
                                <th>Produk</th>
                                <th>Dokumen</th>
                                <th>Tenggat</th>
                                <th>Status</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map(({ ticket, doc, rank }) => (
                                <tr key={`${ticket.id}-${doc.id}`}>
                                    <td>
                                        <span className="rank-number">{rank}</span>
                                    </td>
                                    <td>
                                        <strong>{ticket.product}</strong>
                                        <small>{ticket.id}</small>
                                    </td>
                                    <td>{doc.name}</td>
                                    <td>{dateLabel(ticket.dueDate)}</td>
                                    <td>
                                        <Badge>{doc.status}</Badge>
                                    </td>
                                    <td>
                                        <Button
                                            variant="secondary"
                                            icon={Upload}
                                            onClick={() => setUpload({ ticket, doc })}
                                        >
                                            Unggah
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {!rows.length && <EmptyState title="Tidak ada tugas unggah" />}
                </div>
            </Panel>
            {upload && <UploadDialog ticket={upload.ticket} document={upload.doc} onClose={() => setUpload(null)} />}
        </>
    );
}

export function SharedDocumentsPage() {
    const { data } = usePreview();
    const [selected, setSelected] = useState<DocumentItem | null>(null);
    const ticket = data.tickets[0];
    const docs = ticket.documents.filter((doc) => doc.scope === 'Perusahaan' || doc.scope === 'Bahan baku');
    return (
        <>
            <PageHeader
                title="Dokumen bersama"
                eyebrow="PUSTAKA PERUSAHAAN"
                description="Satu dokumen yang terkelola, untuk berbagai pendaftaran produk."
            />
            <Panel>
                <div className="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Dokumen</th>
                                <th>Cakupan</th>
                                <th>Versi</th>
                                <th>Berlaku sampai</th>
                                <th>Tiket terdampak</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {docs.map((doc) => (
                                <tr key={doc.id}>
                                    <td>
                                        <div className="document-name">
                                            <span className="file-icon">
                                                <FileText size={18} />
                                            </span>
                                            <strong>{doc.name}</strong>
                                        </div>
                                    </td>
                                    <td>{doc.scope}</td>
                                    <td>v{doc.version}</td>
                                    <td>{doc.expiresAt ? dateLabel(doc.expiresAt) : 'Tidak ditentukan'}</td>
                                    <td>
                                        <Badge tone="neutral" dot={false}>
                                            {data.tickets.filter((item) => !item.closed && !item.submitted).length}{' '}
                                            tiket aktif
                                        </Badge>
                                    </td>
                                    <td>
                                        <Button variant="secondary" icon={Upload} onClick={() => setSelected(doc)}>
                                            Unggah versi baru
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Panel>
            <div className="mt-5">
                <InfoBox>
                    Versi baru menandai tiket aktif yang belum diajukan sebagai perlu ditinjau. Versi yang telah
                    diajukan ke BPOM tetap terkunci dan tidak diganti.
                </InfoBox>
            </div>
            {selected && <UploadDialog ticket={ticket} document={selected} onClose={() => setSelected(null)} />}
        </>
    );
}

export function IngredientsPage() {
    const { data, updateTicket } = usePreview();
    const [ticketId, setTicketId] = useState(DEMO_TICKET);
    const [ingredient, setIngredient] = useState('');
    const ticket = data.tickets.find((item) => item.id === ticketId)!;
    return (
        <>
            <PageHeader
                title="Bahan baku tiket"
                eyebrow="FORMULASI PRODUK"
                description="Tautkan bahan dan dokumen asal peredarannya pada produk."
            />
            <div className="filter-bar">
                <Select value={ticketId} onChange={(event) => setTicketId(event.target.value)}>
                    {data.tickets
                        .filter((item) => !item.closed)
                        .map((item) => (
                            <option value={item.id} key={item.id}>
                                {item.product} · {item.id}
                            </option>
                        ))}
                </Select>
            </div>
            <div className="two-column">
                <Panel title={ticket.product}>
                    <div className="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Bahan baku</th>
                                    <th>Pemasok</th>
                                    <th>Asal peredaran</th>
                                    <th />
                                </tr>
                            </thead>
                            <tbody>
                                {ticket.ingredients.map((name) => {
                                    const master = data.masters.ingredients.find((item) => item.name === name);
                                    return (
                                        <tr key={name}>
                                            <td>
                                                <strong>{name}</strong>
                                            </td>
                                            <td>{master?.detail ?? '—'}</td>
                                            <td>
                                                <Badge>{master?.kind ?? 'Belum ada'}</Badge>
                                            </td>
                                            <td>
                                                <Button
                                                    variant="ghost"
                                                    disabled={ticket.submitted}
                                                    onClick={() =>
                                                        updateTicket(
                                                            ticket.id,
                                                            (item) => ({
                                                                ...item,
                                                                ingredients: item.ingredients.filter(
                                                                    (val) => val !== name,
                                                                ),
                                                            }),
                                                            'Bahan dihapus dari tiket demo.',
                                                        )
                                                    }
                                                >
                                                    Hapus
                                                </Button>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </Panel>
                <Panel title="Tambah bahan dari master">
                    <div className="panel-body form-stack">
                        <Field label="Bahan baku">
                            <Select value={ingredient} onChange={(event) => setIngredient(event.target.value)}>
                                <option value="">Pilih bahan baku</option>
                                {data.masters.ingredients
                                    .filter((item) => !ticket.ingredients.includes(item.name))
                                    .map((item) => (
                                        <option key={item.id}>{item.name}</option>
                                    ))}
                            </Select>
                        </Field>
                        <Button
                            disabled={!ingredient || ticket.submitted}
                            icon={Plus}
                            onClick={() => {
                                updateTicket(
                                    ticket.id,
                                    (item) => ({ ...item, ingredients: [...item.ingredients, ingredient] }),
                                    'Bahan baku ditambahkan ke tiket demo.',
                                );
                                setIngredient('');
                            }}
                        >
                            Tambah ke tiket
                        </Button>
                        <InfoBox>
                            Dokumen asal peredaran bahan yang masih berlaku ditautkan melalui master. Bahan yang belum
                            ada dapat ditambahkan pada master bahan baku.
                        </InfoBox>
                    </div>
                </Panel>
            </div>
        </>
    );
}

export function ChecklistSyncPage() {
    const { data, update } = usePreview();
    const [selected, setSelected] = useState<string[]>([]);
    const tickets = data.tickets.filter((item) => !item.closed && item.phase === 'Registrasi');
    return (
        <>
            <PageHeader
                title="Sinkronisasi checklist"
                eyebrow="PEMBARUAN ATURAN"
                description="Terapkan aturan baru secara sadar pada tiket yang masih berjalan."
            />
            <Panel title="Aturan dokumen baru">
                <div className="panel-body">
                    <div className="flex flex-wrap items-center gap-3">
                        <span className="stat-icon icon-green">
                            <FileCheck2 size={20} />
                        </span>
                        <div>
                            <h3 className="font-semibold">Uji cemaran logam berat</h3>
                            <p className="section-note">Berlaku untuk kapsul dan serbuk · mulai 15 Oktober 2026</p>
                        </div>
                    </div>
                </div>
            </Panel>
            <Panel className="mt-5">
                <div className="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Pilih</th>
                                <th>Tiket / produk</th>
                                <th>Fase</th>
                                <th>Sudah diajukan?</th>
                                <th>Dampak</th>
                            </tr>
                        </thead>
                        <tbody>
                            {tickets.map((ticket) => (
                                <tr key={ticket.id}>
                                    <td>
                                        <input
                                            type="checkbox"
                                            aria-label={`Sinkronkan ${ticket.id}`}
                                            disabled={
                                                ticket.submitted ||
                                                ticket.documents.some((doc) => doc.id === 'new-rule')
                                            }
                                            checked={selected.includes(ticket.id)}
                                            onChange={() =>
                                                setSelected(
                                                    selected.includes(ticket.id)
                                                        ? selected.filter((id) => id !== ticket.id)
                                                        : [...selected, ticket.id],
                                                )
                                            }
                                        />
                                    </td>
                                    <td>
                                        <strong>{ticket.product}</strong>
                                        <small>{ticket.id}</small>
                                    </td>
                                    <td>{ticket.phase}</td>
                                    <td>{ticket.submitted ? 'Sudah' : 'Belum'}</td>
                                    <td>
                                        {ticket.submitted
                                            ? 'Tidak diubah · versi terkunci'
                                            : ticket.documents.some((doc) => doc.id === 'new-rule')
                                              ? 'Sudah disinkronkan'
                                              : 'Item checklist ditambahkan'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Panel>
            <div className="flex justify-end mt-5">
                <Button
                    icon={RefreshCw}
                    disabled={!selected.length}
                    onClick={() => {
                        update(
                            (state) => ({
                                ...state,
                                tickets: state.tickets.map((ticket) =>
                                    !selected.includes(ticket.id) || ticket.submitted
                                        ? ticket
                                        : {
                                              ...ticket,
                                              needsReview: false,
                                              documents: [
                                                  ...ticket.documents,
                                                  {
                                                      ...ticket.documents[0],
                                                      id: 'new-rule',
                                                      name: 'Uji cemaran logam berat',
                                                      status: 'Belum ada',
                                                      validation: 'Belum diperiksa',
                                                      version: 0,
                                                      versions: [],
                                                      locked: false,
                                                      qaApproved: false,
                                                  },
                                              ],
                                          },
                                ),
                            }),
                            `${selected.length} checklist tiket disinkronkan pada demo.`,
                        );
                        setSelected([]);
                    }}
                >
                    Sinkronkan {selected.length} tiket
                </Button>
            </div>
        </>
    );
}
