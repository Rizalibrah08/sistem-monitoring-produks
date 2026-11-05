import { Link, router } from '../lib/preview-navigation';
import {
    ArrowLeft,
    ArrowRight,
    BadgeCheck,
    Check,
    ChevronRight,
    FileSignature,
    FileText,
    HeartPulse,
    Leaf,
    Link2,
    LockKeyhole,
    MessageCircle,
    ShieldCheck,
    Upload,
    Wallet,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Badge, Brand, Button, Field, InfoBox, Modal, Progress, Select } from '../components/ui';
import { usePreview } from '../mocks/preview-provider';
import { DEMO_TICKET } from '../mocks/fixtures';
import { currency, dateLabel, ticketProgress } from '../lib/utils';
import { UploadDialog } from './documents';
import { ContractPreview } from './payments';
import type { DocumentItem, Invoice } from '../types';

export function ClientPortalPage({ expired = false }: { expired?: boolean }) {
    const { data, update, toast } = usePreview();
    const [upload, setUpload] = useState<DocumentItem | null>(null);
    const [proof, setProof] = useState<Invoice | null>(null);
    const [contractOpen, setContractOpen] = useState(false);
    if (expired)
        return (
            <div className="client-outer">
                <div className="client-topbar">
                    <Brand compact />
                    <Badge tone="neutral" dot={false}>
                        Portal klien
                    </Badge>
                </div>
                <main className="expired-page">
                    <span className="expired-icon">
                        <LockKeyhole size={38} strokeWidth={1.4} />
                    </span>
                    <span className="eyebrow">KEAMANAN DOKUMEN ANDA</span>
                    <h1>Tautan tidak berlaku</h1>
                    <p>Tautan ini sudah kedaluwarsa atau tidak valid. Informasi pendaftaran Anda tetap terlindungi.</p>
                    <Link href="/chatbot?request=link" className="btn btn-primary">
                        <MessageCircle size={16} />
                        Minta tautan baru lewat WhatsApp
                    </Link>
                    <small>Tim akan memverifikasi nomor dan kepemilikan tiket sebelum mengirim tautan baru.</small>
                    <div className="client-secure-note">
                        <ShieldCheck size={14} />
                        Akses aman, hanya untuk pihak terkait
                    </div>
                </main>
                <ClientFooter />
            </div>
        );
    const ticket = data.tickets.find((item) => item.id === DEMO_TICKET)!;
    const client = data.clients.find((item) => item.id === ticket.clientId)!;
    const contract = data.contracts.find((item) => item.ticketId === ticket.id);
    const documents = ticket.documents.filter((item) => item.pic === 'Klien');
    const invoices = data.invoices.filter((item) => item.ticketId === ticket.id);
    return (
        <div className="client-outer">
            <div className="client-topbar">
                <Brand compact />
                <Badge tone="green">
                    <LockKeyhole size={10} />
                    Portal klien
                </Badge>
            </div>
            <main className="client-content">
                <div className="client-welcome">
                    <span className="eyebrow">PENDAFTARAN PRODUK ANDA</span>
                    <h1>
                        Halo, Kak Nadia<span>.</span>
                    </h1>
                    <p>Setiap langkahnya kini lebih jelas. Lengkapi kebutuhan produk Anda di sini.</p>
                </div>
                <section className="client-product-card">
                    <span className="client-product-icon">
                        <Leaf size={25} strokeWidth={1.4} />
                    </span>
                    <div>
                        <small>{ticket.id}</small>
                        <h2>{ticket.product}</h2>
                        <span>{client.company}</span>
                    </div>
                    <Badge tone="blue">{ticket.phase}</Badge>
                </section>
                <section className="client-progress-card">
                    <div>
                        <strong>Perjalanan pendaftaran</strong>
                        <span>Langkah {ticket.closed ? '5' : '4'} dari 5</span>
                    </div>
                    <div className="client-steps">
                        {['Sampel', 'Kontrak', 'DP', 'Dokumen', 'NIE'].map((step, index) => (
                            <div
                                key={step}
                                className={index < 3 || ticket.closed ? 'done' : index === 3 ? 'active' : ''}
                            >
                                <span>{index < 3 || ticket.closed ? <Check size={13} /> : index + 1}</span>
                                <small>{step}</small>
                            </div>
                        ))}
                    </div>
                </section>
                <div className="client-section-heading">
                    <span className="client-section-number">01</span>
                    <div>
                        <h2>Dokumen yang dibutuhkan</h2>
                        <p>Hanya dokumen yang menjadi bagian Anda.</p>
                    </div>
                </div>
                {documents.map((doc) => (
                    <section className="client-doc-card" key={doc.id}>
                        <div className="client-doc-title">
                            <span className="file-icon">
                                <FileText size={19} />
                            </span>
                            <div>
                                <h3>{doc.name}</h3>
                                <small>
                                    {doc.version ? `Versi ${doc.version} · ${doc.status}` : 'Belum ada berkas'}
                                </small>
                            </div>
                            <Badge>{doc.status}</Badge>
                        </div>
                        {doc.reason && <p className="client-revision-note">{doc.reason}</p>}
                        {!doc.locked && !ticket.closed && (
                            <Button variant="secondary" icon={Upload} onClick={() => setUpload(doc)}>
                                {doc.version ? 'Unggah versi baru' : 'Unggah dokumen'}
                            </Button>
                        )}
                    </section>
                ))}
                <div className="client-section-heading">
                    <span className="client-section-number">02</span>
                    <div>
                        <h2>Kontrak & pembayaran</h2>
                        <p>Semua kebutuhan kerja sama di satu tempat.</p>
                    </div>
                </div>
                {contract && (
                    <section className="client-doc-card">
                        <div className="client-doc-title">
                            <span className="file-icon">
                                <FileSignature size={19} />
                            </span>
                            <div>
                                <h3>Kontrak pendaftaran</h3>
                                <small>{contract.id}</small>
                            </div>
                            <Badge>{contract.status}</Badge>
                        </div>
                        <Button variant="secondary" onClick={() => setContractOpen(true)}>
                            Lihat kontrak
                            <ChevronRight size={14} />
                        </Button>
                        {contract.status === 'Dikirim' && (
                            <Button
                                onClick={() =>
                                    update(
                                        (state) => ({
                                            ...state,
                                            contracts: state.contracts.map((item) =>
                                                item.id === contract.id ? { ...item, status: 'Ditandatangani' } : item,
                                            ),
                                        }),
                                        'Unggahan kontrak bertanda tangan disimulasikan.',
                                    )
                                }
                            >
                                Simulasikan unggahan kontrak bertanda tangan
                            </Button>
                        )}
                    </section>
                )}
                {invoices.map((invoice) => (
                    <section
                        className={`client-invoice-card ${invoice.status === 'Lunas' ? 'paid' : ''}`}
                        key={invoice.id}
                    >
                        <div className="client-doc-title">
                            <span className="file-icon">
                                <Wallet size={19} />
                            </span>
                            <div>
                                <h3>{invoice.term}</h3>
                                <small>
                                    {invoice.id} · jatuh tempo {dateLabel(invoice.dueDate)}
                                </small>
                            </div>
                            <Badge>{invoice.status}</Badge>
                        </div>
                        <strong className="client-amount">{currency(invoice.amount)}</strong>
                        {invoice.status !== 'Lunas' && (
                            <Button icon={Upload} onClick={() => setProof(invoice)}>
                                Unggah bukti transfer
                            </Button>
                        )}
                        {invoice.proofStatus === 'rejected' && (
                            <p className="client-revision-note">Bukti sebelumnya ditolak: {invoice.reason}</p>
                        )}
                    </section>
                ))}
                <div className="client-help">
                    <span>
                        <MessageCircle size={22} />
                    </span>
                    <div>
                        <h3>Butuh bantuan?</h3>
                        <p>Tim Marketing siap membantu pertanyaan Anda.</p>
                        <Link href="/chatbot" className="text-link">
                            Hubungi melalui WhatsApp
                            <ArrowRight size={13} />
                        </Link>
                    </div>
                </div>
                <div className="client-secure-note">
                    <ShieldCheck size={14} />
                    Pratinjau frontend · tidak ada berkas dikirim ke server
                </div>
            </main>
            <ClientFooter />
            {upload && (
                <UploadDialog ticket={ticket} document={upload} source="client" onClose={() => setUpload(null)} />
            )}
            {contractOpen && contract && (
                <Modal
                    title="Kontrak pendaftaran"
                    description={contract.id}
                    wide
                    onClose={() => setContractOpen(false)}
                >
                    <ContractPreview contract={contract} />
                </Modal>
            )}
            {proof && <ProofUploadDialog invoice={proof} onClose={() => setProof(null)} />}
            {toast && (
                <div role="status" className={`toast ${toast.error ? 'toast-error' : ''}`}>
                    {toast.message}
                </div>
            )}
        </div>
    );
}
function ClientFooter() {
    return (
        <footer className="client-footer">
            <HeartPulse size={15} />
            <span>
                PT Aisha Zahra Herbalaboratories<small>Mendampingi setiap langkah produk kesehatan Anda.</small>
            </span>
            <Link href="/">
                Kembali ke demo
                <ArrowRight size={11} />
            </Link>
        </footer>
    );
}

function ProofUploadDialog({ invoice, onClose }: { invoice: Invoice; onClose: () => void }) {
    const { update } = usePreview();
    const [file, setFile] = useState<File | null>(null);
    const [error, setError] = useState('');
    function submit(event: FormEvent) {
        event.preventDefault();
        if (!file || !/\.(pdf|jpe?g|png)$/i.test(file.name) || file.size > 20 * 1024 * 1024 || file.size === 0)
            return setError('Pilih bukti PDF, JPG, atau PNG yang tidak kosong, maksimal 20 MB.');
        if (
            update(
                (state) => ({
                    ...state,
                    invoices: state.invoices.map((item) =>
                        item.id === invoice.id
                            ? { ...item, proof: file.name, proofStatus: 'pending', status: 'Menunggu verifikasi' }
                            : item,
                    ),
                }),
                'Bukti transfer demo diterima dan menunggu verifikasi Finance.',
            )
        )
            onClose();
    }
    return (
        <Modal
            title="Unggah bukti transfer"
            description={`${invoice.id} · ${invoice.term} · ${currency(invoice.amount)}`}
            onClose={onClose}
        >
            <form onSubmit={submit}>
                <Field label="Berkas bukti transfer" error={error}>
                    <input
                        className="file-input"
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png"
                        onChange={(event) => setFile(event.target.files?.[0] ?? null)}
                    />
                </Field>
                <div className="mt-5">
                    <InfoBox>
                        Bukti ini hanya terkait tagihan {invoice.id}. Status lunas ditetapkan setelah verifikasi
                        Finance, bukan setelah unggahan.
                    </InfoBox>
                </div>
                <div className="form-footer">
                    <Button variant="secondary" onClick={onClose}>
                        Batal
                    </Button>
                    <Button type="submit" icon={Upload}>
                        Kirim bukti transfer
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

export function LoginPage() {
    const { role, setRole } = usePreview();
    return (
        <div className="login-page">
            <aside className="login-brand-panel">
                <Brand />
                <div className="login-story">
                    <span className="login-story-tag">
                        <Leaf size={13} />
                        DARI ALAM, UNTUK KESEHATAN
                    </span>
                    <h1>
                        Proses lebih terarah.
                        <br />
                        Produk lebih dipercaya.
                    </h1>
                    <p>Satu ruang kerja untuk mendampingi produk kesehatan Anda, dari pengajuan hingga izin edar.</p>
                    <div className="login-plant">
                        <SproutDrawing />
                    </div>
                    <div className="login-assurance">
                        <ShieldCheck size={21} />
                        <span>
                            Dokumen terkelola. Tim terhubung.
                            <br />
                            Setiap langkah dapat ditelusuri.
                        </span>
                    </div>
                </div>
                <small>© 2026 PT Aisha Zahra Herbalaboratories</small>
            </aside>
            <main className="login-main">
                <span className="login-mobile-brand">
                    <Brand />
                </span>
                <div className="login-card">
                    <span className="eyebrow">SELAMAT DATANG KEMBALI</span>
                    <h2>Masuk ke ruang kerja Anda</h2>
                    <p>Kelola pendaftaran produk dengan lebih tenang.</p>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.visit('/', { preserveState: true });
                        }}
                        className="form-stack"
                    >
                        <Field label="Email">
                            <input type="email" placeholder="nama@perusahaan.com" defaultValue="demo@aisha.example" />
                        </Field>
                        <Field label="Kata sandi">
                            <input type="password" placeholder="Tidak diperlukan untuk pratinjau" autoComplete="off" />
                        </Field>
                        <Field label="Jelajahi sebagai">
                            <Select value={role} onChange={(event) => setRole(event.target.value as typeof role)}>
                                <option value="manager">Manajer</option>
                                <option value="marketing">Marketing</option>
                                <option value="rnd">R&D</option>
                                <option value="qa">QA/QC</option>
                                <option value="ra">Regulatory Affairs</option>
                                <option value="finance">Finance</option>
                                <option value="admin">Admin</option>
                            </Select>
                        </Field>
                        <Button type="submit" icon={ArrowRight}>
                            Masuk ke demo
                        </Button>
                    </form>
                    <div className="login-demo-note">
                        <LockKeyhole size={15} />
                        <p>
                            Ini adalah pratinjau frontend, bukan autentikasi nyata. Jangan gunakan kata sandi atau data
                            perusahaan asli.
                        </p>
                    </div>
                </div>
                <span className="login-bottom">
                    <HeartPulse size={14} />
                    Bekerja lebih teratur, tumbuh lebih sehat.
                </span>
            </main>
        </div>
    );
}
function SproutDrawing() {
    return (
        <svg viewBox="0 0 320 220" fill="none" aria-hidden="true">
            <ellipse cx="158" cy="205" rx="105" ry="8" fill="#bdd2ae" opacity=".35" />
            <path d="M162 205C163 137 185 86 200 37M161 204C160 141 124 109 96 70" stroke="#71956b" strokeWidth="2" />
            <path d="M170 151C194 113 232 120 253 96C247 146 218 170 170 151Z" fill="#99b68b" />
            <path d="M161 173C145 132 106 149 81 123C85 164 114 190 161 173Z" fill="#bccca3" />
            <path d="M184 107C187 65 219 66 235 38C237 77 221 109 184 107Z" fill="#759e73" />
            <path d="M144 136C143 95 104 104 81 77C86 116 109 143 144 136Z" fill="#92af7e" />
            <path d="M198 47C197 21 216 11 224 0C232 33 216 47 198 47Z" fill="#b7c99f" />
            <path d="M110 92C87 70 90 45 65 28C65 69 76 95 110 92Z" fill="#b4c99d" />
            <path
                d="M164 192L105 156M175 147L227 120M187 98L219 61M136 127L100 100"
                stroke="#709365"
                strokeWidth="1"
                opacity=".5"
            />
        </svg>
    );
}
