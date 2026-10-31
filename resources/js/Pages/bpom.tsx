import { Link, router } from '@inertiajs/react';
import { ArrowRight, Check, FileCheck2, FilePlus2, LockKeyhole, Plus, ShieldCheck } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import {
    Badge,
    Button,
    CheckLine,
    EmptyState,
    Field,
    InfoBox,
    LinkButton,
    Modal,
    PageHeader,
    Panel,
    Select,
} from '../components/ui';
import { usePreview } from '../mocks/preview-provider';
import { DEMO_DATE, DEMO_TICKET } from '../mocks/fixtures';
import {
    canSubmitTicket,
    closeTicketWithNie,
    recordPreRegistrationResult,
    validateEvidenceFile,
} from '../features/workflow';

export function BpomPage() {
    const { data, update, updateTicket } = usePreview();
    const [id, setId] = useState(new URLSearchParams(window.location.search).get('ticket') ?? DEMO_TICKET);
    const ticket = data.tickets.find((item) => item.id === id) ?? data.tickets[0];
    const [date, setDate] = useState(DEMO_DATE);
    const [pnbp, setPnbp] = useState('');
    const [proof, setProof] = useState<File | null>(null);
    const [result, setResult] = useState(false);
    const [noteModal, setNoteModal] = useState(false);
    const [note, setNote] = useState('');
    const [deadline, setDeadline] = useState('2026-10-29');
    const notes = data.bpomNotes.filter((item) => item.ticketId === ticket.id);
    const [error, setError] = useState('');
    useEffect(() => {
        setDate(DEMO_DATE);
        setPnbp('');
        setProof(null);
        setError('');
    }, [ticket.id]);
    const eligibility = canSubmitTicket(ticket);
    function submit(event: FormEvent) {
        event.preventDefault();
        if (!eligibility.allowed) return setError('Lengkapi seluruh persyaratan pengajuan terlebih dahulu.');
        if (!date || pnbp === '' || Number(pnbp) < 0 || !proof)
            return setError('Tanggal, nilai PNBP, dan bukti pengajuan wajib diisi.');
        const fileError = validateEvidenceFile(proof);
        if (fileError) return setError(fileError);
        updateTicket(
            ticket.id,
            (current) => ({
                ...current,
                submitted: true,
                preSubmitted: current.phase === 'Pra-registrasi' ? true : current.preSubmitted,
                stage: 'Menunggu BPOM',
                revision: current.revision + 1,
                documents: current.documents.map((doc) =>
                    doc.phase === current.phase || doc.phase === 'Kedua fase' ? { ...doc, locked: true } : doc,
                ),
            }),
            'Pengajuan BPOM dicatat dan versi dokumen dikunci pada demo.',
        );
        setError('');
    }
    return (
        <>
            <PageHeader
                title="Pengajuan dan catatan BPOM"
                eyebrow="REGULATORY AFFAIRS"
                description="Catat proses eksternal BPOM tanpa kehilangan jejak dokumen dan tenggat."
            >
                <LinkButton href="/nie" secondary icon={ShieldCheck}>
                    Input NIE
                </LinkButton>
            </PageHeader>
            <div className="filter-bar">
                <Select value={ticket.id} onChange={(event) => setId(event.target.value)}>
                    {data.tickets
                        .filter((item) => !item.closed && item.dpPaid)
                        .map((item) => (
                            <option value={item.id} key={item.id}>
                                {item.product} · {item.id}
                            </option>
                        ))}
                </Select>
                <Badge tone="blue">{ticket.phase}</Badge>
            </div>
            <div className="bpom-grid">
                <Panel title="Catat pengajuan" description={ticket.product}>
                    <form key={ticket.id} className="panel-body form-stack" onSubmit={submit}>
                        <Field label="Fase">
                            <input value={ticket.phase} readOnly />
                        </Field>
                        <Field label="Tanggal pengajuan">
                            <input type="date" value={date} onChange={(event) => setDate(event.target.value)} />
                        </Field>
                        <Field label="Nilai PNBP (Rp)" hint="Biaya resmi BPOM, bukan pembayaran jasa klien.">
                            <input
                                type="number"
                                min="0"
                                placeholder="Sesuai tagihan BPOM"
                                value={pnbp}
                                onChange={(event) => setPnbp(event.target.value)}
                            />
                        </Field>
                        <Field label="Bukti pengajuan">
                            <input
                                className="file-input"
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png"
                                onChange={(event) => setProof(event.target.files?.[0] ?? null)}
                            />
                        </Field>
                        {error && (
                            <p className="form-error" role="alert">
                                {error}
                            </p>
                        )}
                        <Button type="submit" icon={LockKeyhole} disabled={!eligibility.allowed}>
                            Simpan dan kunci versi
                        </Button>
                        <InfoBox warning={!eligibility.allowed}>
                            {ticket.submitted
                                ? 'Pengajuan fase ini sudah dicatat. Versi dokumen yang diajukan tidak dapat diubah.'
                                : eligibility.allowed
                                  ? 'Semua persyaratan terpenuhi. Pengajuan dapat dicatat.'
                                  : eligibility.reasons.join(' ')}
                        </InfoBox>
                    </form>
                </Panel>
                <div className="space-y-5">
                    <Panel
                        title="Catatan & tambahan data"
                        description="Tenggat dan tanggapan BPOM untuk tiket yang dipilih."
                        action={
                            <Button variant="secondary" icon={Plus} onClick={() => setNoteModal(true)}>
                                Tambah catatan
                            </Button>
                        }
                    >
                        <div className="bpom-notes">
                            {notes.map((item) => (
                                <div key={item.id}>
                                    <span className={`bpom-note-icon ${item.done ? 'done' : ''}`}>
                                        <FileCheck2 size={17} />
                                    </span>
                                    <div>
                                        <strong>{item.text}</strong>
                                        <p>
                                            Dicatat {item.date} · tenggat {item.due}
                                        </p>
                                        <Badge>{item.done ? 'Selesai' : 'Menunggu tanggapan'}</Badge>
                                    </div>
                                    {!item.done && (
                                        <Button
                                            variant="secondary"
                                            onClick={() =>
                                                update(
                                                    (state) => ({
                                                        ...state,
                                                        bpomNotes: state.bpomNotes.map((row) =>
                                                            row.id === item.id ? { ...row, done: true } : row,
                                                        ),
                                                    }),
                                                    'Tanggapan BPOM dicatat pada demo.',
                                                    ticket.id,
                                                )
                                            }
                                        >
                                            Tandai ditanggapi
                                        </Button>
                                    )}
                                </div>
                            ))}
                        </div>
                    </Panel>
                    <Panel
                        title="Hasil pra-registrasi"
                        description="Dokumen hasil harus disetujui sebelum registrasi dibuka."
                    >
                        <div className="panel-body">
                            <div className="flex flex-wrap gap-3 items-center justify-between mb-5">
                                <Badge>
                                    {ticket.preResult === 'approved'
                                        ? 'Hasil disetujui'
                                        : ticket.preResult === 'rejected'
                                          ? 'Perlu perbaikan'
                                          : 'Belum dicatat'}
                                </Badge>
                                <Button
                                    variant="secondary"
                                    icon={FilePlus2}
                                    disabled={!ticket.preSubmitted || ticket.phase !== 'Pra-registrasi'}
                                    onClick={() => setResult(true)}
                                >
                                    Catat hasil pra-registrasi
                                </Button>
                            </div>
                            <InfoBox>
                                Hanya mengunggah dokumen hasil tidak cukup. Hasil pra-registrasi harus
                                diterima/disetujui untuk membuka fase registrasi.
                            </InfoBox>
                        </div>
                    </Panel>
                </div>
            </div>
            {result && <PreResultDialog ticketId={ticket.id} onClose={() => setResult(false)} />}
            {noteModal && (
                <Modal title="Catat tambahan data BPOM" description={ticket.id} onClose={() => setNoteModal(false)}>
                    <div className="form-stack">
                        <Field label="Catatan BPOM">
                            <textarea
                                value={note}
                                onChange={(event) => setNote(event.target.value)}
                                placeholder="Tuliskan permintaan tambahan data..."
                            />
                        </Field>
                        <Field label="Tenggat tanggapan">
                            <input type="date" value={deadline} onChange={(event) => setDeadline(event.target.value)} />
                        </Field>
                    </div>
                    <div className="form-footer">
                        <Button variant="secondary" onClick={() => setNoteModal(false)}>
                            Batal
                        </Button>
                        <Button
                            disabled={!note.trim() || !deadline}
                            onClick={() => {
                                update(
                                    (state) => ({
                                        ...state,
                                        bpomNotes: [
                                            {
                                                id: crypto.randomUUID(),
                                                ticketId: ticket.id,
                                                text: note,
                                                date: DEMO_DATE,
                                                due: deadline,
                                                done: false,
                                            },
                                            ...state.bpomNotes,
                                        ],
                                    }),
                                    'Catatan BPOM ditambahkan pada tiket demo.',
                                    ticket.id,
                                );
                                setNote('');
                                setNoteModal(false);
                            }}
                        >
                            Simpan catatan
                        </Button>
                    </div>
                </Modal>
            )}
        </>
    );
}

function PreResultDialog({ ticketId, onClose }: { ticketId: string; onClose: () => void }) {
    const { data, updateTicket } = usePreview();
    const ticket = data.tickets.find((item) => item.id === ticketId)!;
    const preDocs = ticket.documents.filter((doc) => doc.phase === 'Pra-registrasi' || doc.phase === 'Kedua fase');
    const [affectedIds, setAffectedIds] = useState(
        [preDocs.find((doc) => doc.phase === 'Pra-registrasi')?.id ?? preDocs[0]?.id].filter(Boolean) as string[],
    );
    const [outcome, setOutcome] = useState<'approved' | 'rejected'>('approved');
    const [file, setFile] = useState<File | null>(null);
    const [error, setError] = useState('');
    return (
        <Modal title="Hasil pra-registrasi" description={ticketId} onClose={onClose}>
            <div className="form-stack">
                <Field label="Keputusan BPOM">
                    <Select value={outcome} onChange={(event) => setOutcome(event.target.value as typeof outcome)}>
                        <option value="approved">Diterima / disetujui</option>
                        <option value="rejected">Dikembalikan / perlu perbaikan</option>
                    </Select>
                </Field>
                <Field label="Dokumen hasil pra-registrasi">
                    <input
                        className="file-input"
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png"
                        onChange={(event) => setFile(event.target.files?.[0] ?? null)}
                    />
                </Field>
                {outcome === 'rejected' && (
                    <div>
                        <div className="eyebrow">DOKUMEN YANG PERLU DIPERBAIKI</div>
                        {preDocs.map((doc) => (
                            <label className="manual-check" key={doc.id}>
                                <input
                                    type="checkbox"
                                    checked={affectedIds.includes(doc.id)}
                                    onChange={() =>
                                        setAffectedIds(
                                            affectedIds.includes(doc.id)
                                                ? affectedIds.filter((id) => id !== doc.id)
                                                : [...affectedIds, doc.id],
                                        )
                                    }
                                />
                                {doc.name}
                            </label>
                        ))}
                    </div>
                )}
                <InfoBox>
                    {outcome === 'approved'
                        ? 'Setelah hasil disimpan, fase registrasi dibuka pada simulasi ini.'
                        : 'Registrasi tetap terkunci sampai hasil pra-registrasi disetujui.'}
                </InfoBox>
                {error && (
                    <p className="form-error" role="alert">
                        {error}
                    </p>
                )}
            </div>
            <div className="form-footer">
                <Button variant="secondary" onClick={onClose}>
                    Batal
                </Button>
                <Button
                    disabled={!file}
                    onClick={() => {
                        const fileError = validateEvidenceFile(file);
                        if (fileError) return setError(fileError);
                        if (outcome === 'rejected' && !affectedIds.length)
                            return setError('Pilih dokumen yang perlu diperbaiki.');
                        if (
                            updateTicket(
                                ticketId,
                                (current) => recordPreRegistrationResult(current, outcome, affectedIds),
                                'Hasil pra-registrasi dicatat pada demo.',
                            )
                        )
                            onClose();
                    }}
                >
                    Simpan hasil
                </Button>
            </div>
        </Modal>
    );
}

export function NiePage() {
    const { data, update } = usePreview();
    const candidates = data.tickets.filter((item) => !item.closed && item.phase === 'Registrasi' && item.submitted);
    const [id, setId] = useState(candidates[0]?.id ?? '');
    const [number, setNumber] = useState('');
    const [date, setDate] = useState(DEMO_DATE);
    const [error, setError] = useState('');
    function submit(event: FormEvent) {
        event.preventDefault();
        const ticket = data.tickets.find((item) => item.id === id);
        if (!ticket) return setError('Pilih tiket registrasi yang telah diajukan.');
        try {
            const closed = closeTicketWithNie(ticket, number, date);
            const contract = data.contracts.find((item) => item.ticketId === id);
            if (!contract)
                return setError('Kontrak aktif tiket ini belum tersedia. Lengkapi kontraknya terlebih dahulu.');
            const invoiceId = `INV-${String(Math.max(...data.invoices.map((item) => Number(item.id.slice(4)))) + 1).padStart(4, '0')}`;
            if (
                update(
                    (state) => ({
                        ...state,
                        tickets: state.tickets.map((item) => (item.id === id ? closed : item)),
                        invoices: state.invoices.some(
                            (item) => item.ticketId === id && item.term.startsWith('Pelunasan'),
                        )
                            ? state.invoices
                            : [
                                  ...state.invoices,
                                  {
                                      id: invoiceId,
                                      ticketId: id,
                                      clientId: ticket.clientId,
                                      term: `Pelunasan ${100 - contract.dpPercent}%`,
                                      amount: Math.round((contract.amount * (100 - contract.dpPercent)) / 100),
                                      dueDate: '2026-10-28',
                                      status: 'Terbit',
                                      proof: null,
                                      proofStatus: null,
                                  },
                              ],
                    }),
                    'NIE dicatat, tiket ditutup, dan tagihan pelunasan dibuat pada demo.',
                )
            )
                router.visit(`/tickets/${id}`, { preserveState: true });
        } catch (caught) {
            setError((caught as Error).message);
        }
    }
    return (
        <>
            <PageHeader
                title="Input NIE"
                eyebrow="PENYELESAIAN PENDAFTARAN"
                description="Catat izin edar dan selesaikan perjalanan pendaftaran produk."
            />
            <div className="two-column">
                <Panel title="Data Nomor Izin Edar">
                    <form className="panel-body form-stack" onSubmit={submit}>
                        <Field label="Tiket">
                            <Select value={id} onChange={(event) => setId(event.target.value)}>
                                {candidates.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.product} · {item.id}
                                    </option>
                                ))}
                                {!candidates.length && <option>Tidak ada tiket yang memenuhi syarat</option>}
                            </Select>
                        </Field>
                        <Field label="Nomor NIE">
                            <input
                                value={number}
                                onChange={(event) => setNumber(event.target.value)}
                                placeholder="Contoh: TR262000123"
                            />
                        </Field>
                        <Field label="Tanggal terbit NIE">
                            <input type="date" value={date} onChange={(event) => setDate(event.target.value)} />
                        </Field>
                        <Field label="Sertifikat NIE (opsional untuk demo)">
                            <input className="file-input" type="file" accept=".pdf" />
                        </Field>
                        {error && (
                            <p className="form-error" role="alert">
                                {error}
                            </p>
                        )}
                        <Button type="submit" disabled={!candidates.length} icon={ShieldCheck}>
                            Simpan dan tutup tiket
                        </Button>
                    </form>
                </Panel>
                <Panel title="Setelah NIE dicatat">
                    <div className="panel-body">
                        <div className="completion-illustration">
                            <ShieldCheck size={45} strokeWidth={1.2} />
                        </div>
                        <CheckLine>Tiket pendaftaran ditutup.</CheckLine>
                        <CheckLine>Nomor NIE tampil pada detail produk.</CheckLine>
                        <CheckLine>Tagihan pelunasan diterbitkan.</CheckLine>
                        <CheckLine>Finance tetap memantau sampai lunas.</CheckLine>
                        <div className="mt-5">
                            <InfoBox>
                                Pengiriman NIE dan tagihan melalui WhatsApp belum aktif pada frontend demo.
                            </InfoBox>
                        </div>
                    </div>
                </Panel>
            </div>
        </>
    );
}
