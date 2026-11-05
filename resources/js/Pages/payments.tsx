import { Link, router } from '../lib/preview-navigation';
import {
    ArrowLeft,
    ArrowRight,
    BadgeCheck,
    Banknote,
    CalendarDays,
    Check,
    CheckCheck,
    ClipboardCheck,
    CreditCard,
    Download,
    Eye,
    FileCheck2,
    FileSignature,
    FileText,
    Plus,
    Receipt,
    ShieldCheck,
    Wallet,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import {
    Avatar,
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
    SearchInput,
    Select,
    StatCard,
} from '../components/ui';
import { usePreview } from '../mocks/preview-provider';
import { activateContract, openPreRegistration, verifyInvoice } from '../features/workflow';
import { currency, dateLabel, downloadCsv, shortMoney } from '../lib/utils';
import { DEMO_TICKET } from '../mocks/fixtures';
import type { Contract, Invoice, Ticket } from '../types';

export function TicketPayments({ ticket }: { ticket: Ticket }) {
    const { data, role } = usePreview();
    const [preview, setPreview] = useState(false);
    const contract = data.contracts.find((item) => item.ticketId === ticket.id);
    const invoices = data.invoices.filter((item) => item.ticketId === ticket.id);
    const financialAccess = ['marketing', 'finance', 'manager'].includes(role);
    if (!financialAccess)
        return (
            <Panel title="Status kontrak & pembayaran">
                <div className="panel-body space-y-4">
                    <div className="flex justify-between">
                        <span>Kontrak</span>
                        <Badge>{contract?.status ?? 'Belum dibuat'}</Badge>
                    </div>
                    <div className="flex justify-between">
                        <span>Pembayaran DP</span>
                        <Badge>{ticket.dpPaid ? 'Lunas' : 'Menunggu pembayaran'}</Badge>
                    </div>
                    <InfoBox>
                        Nominal kontrak dan bukti transfer hanya ditampilkan untuk Marketing, Finance, dan Manajer pada
                        pratinjau ini. Hak akses final mengikuti matriks perusahaan.
                    </InfoBox>
                </div>
            </Panel>
        );
    return (
        <div className="space-y-5">
            <Panel title="Kontrak pendaftaran" action={contract && <Badge>{contract.status}</Badge>}>
                <div className="panel-body">
                    {contract ? (
                        <>
                            <dl className="detail-list">
                                <div>
                                    <dt>Nomor kontrak</dt>
                                    <dd>{contract.id}</dd>
                                </div>
                                <div>
                                    <dt>Nilai jasa</dt>
                                    <dd>{currency(contract.amount)}</dd>
                                </div>
                                <div>
                                    <dt>Templat</dt>
                                    <dd>{contract.template}</dd>
                                </div>
                                <div>
                                    <dt>Termin</dt>
                                    <dd>
                                        DP {contract.dpPercent}% · pelunasan {100 - contract.dpPercent}%
                                    </dd>
                                </div>
                            </dl>
                            <div className="flex justify-end mt-5">
                                <Button variant="secondary" icon={Eye} onClick={() => setPreview(true)}>
                                    Lihat kontrak
                                </Button>
                            </div>
                        </>
                    ) : (
                        <EmptyState
                            title="Kontrak belum dibuat"
                            description="Marketing dapat membuat kontrak setelah sampel disetujui."
                        >
                            {role === 'marketing' && (
                                <LinkButton href={`/contracts/new?ticket=${ticket.id}`}>Buat kontrak</LinkButton>
                            )}
                        </EmptyState>
                    )}
                </div>
            </Panel>
            <Panel title="Termin pembayaran" description="Tagihan tetap dipantau meskipun tiket pendaftaran selesai.">
                <InvoiceTable invoices={invoices} />
            </Panel>
            <InfoBox>
                Kontrak (MoU) yang ditandatangani dan dikonfirmasi Marketing membuka fase pra-registrasi. Tagihan DP tetap diingatkan, tetapi tidak menahan proses. Tagihan pelunasan diterbitkan ketika NIE dicatat.
            </InfoBox>
            {preview && contract && (
                <Modal
                    title="Pratinjau kontrak"
                    description={`${contract.id} · ${ticket.product}`}
                    wide
                    onClose={() => setPreview(false)}
                >
                    <ContractPreview contract={contract} />
                    <div className="form-footer">
                        <Button variant="secondary" onClick={() => setPreview(false)}>
                            Tutup pratinjau
                        </Button>
                    </div>
                </Modal>
            )}
        </div>
    );
}

export function SamplesPage() {
    const { data, updateTicket } = usePreview();
    const initial = new URLSearchParams(window.location.search).get('ticket');
    const [id, setId] = useState(initial ?? data.tickets.find((item) => !item.sampleApproved)?.id ?? DEMO_TICKET);
    const [note, setNote] = useState('');
    const [error, setError] = useState('');
    const ticket = data.tickets.find((item) => item.id === id)!;
    if (!ticket) return <EmptyState title="Tiket tidak ditemukan" />;
    const revisions = ticket.sampleHistory ?? [];
    return (
        <>
            <PageHeader
                title="Persetujuan sampel"
                eyebrow="PENGEMBANGAN PRODUK"
                description="Catat keputusan klien agar setiap revisi dapat ditelusuri."
            />
            <div className="filter-bar">
                <Select
                    value={id}
                    onChange={(event) => {
                        setId(event.target.value);
                        setNote('');
                        setError('');
                    }}
                >
                    {data.tickets
                        .filter((item) => !item.closed)
                        .map((item) => (
                            <option value={item.id} key={item.id}>
                                {item.product} · {item.id}
                            </option>
                        ))}
                </Select>
                <Badge>{ticket.sampleApproved ? 'Disetujui' : 'Menunggu keputusan'}</Badge>
            </div>
            <div className="two-column">
                <Panel title="Riwayat sampel" description={ticket.product}>
                    <div className="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Versi</th>
                                    <th>Tanggal</th>
                                    <th>Hasil</th>
                                    <th>Catatan klien</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>v1</td>
                                    <td>10/10/2026</td>
                                    <td>
                                        <Badge>Perlu revisi</Badge>
                                    </td>
                                    <td>Rasa perlu disesuaikan.</td>
                                </tr>
                                {revisions.map((item, index) => (
                                    <tr key={index}>
                                        <td>v{index + 2}</td>
                                        <td>21/10/2026</td>
                                        <td>
                                            <Badge>{item.result}</Badge>
                                        </td>
                                        <td>{item.note}</td>
                                    </tr>
                                ))}
                                {ticket.sampleApproved && !revisions.length && (
                                    <tr>
                                        <td>v2</td>
                                        <td>18/10/2026</td>
                                        <td>
                                            <Badge>Disetujui</Badge>
                                        </td>
                                        <td>Sampel telah sesuai.</td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </Panel>
                <Panel title="Catat keputusan klien">
                    <div className="panel-body form-stack">
                        <Field label="Catatan klien" error={error}>
                            <textarea
                                placeholder="Tuliskan persetujuan atau masukan dari klien..."
                                value={note}
                                onChange={(event) => setNote(event.target.value)}
                            />
                        </Field>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant="secondary"
                                disabled={ticket.sampleApproved}
                                onClick={() => {
                                    if (!note.trim()) return setError('Catatan revisi wajib diisi.');
                                    updateTicket(
                                        id,
                                        (item) => ({
                                            ...item,
                                            sampleHistory: [...revisions, { note, result: 'Perlu revisi' }],
                                        }),
                                        'Revisi sampel dicatat pada tiket demo.',
                                    );
                                    setNote('');
                                    setError('');
                                }}
                            >
                                Perlu revisi
                            </Button>
                            <Button
                                disabled={ticket.sampleApproved}
                                icon={Check}
                                onClick={() => {
                                    updateTicket(
                                        id,
                                        (item) => ({
                                            ...item,
                                            sampleApproved: true,
                                            stage: 'Kontrak (MoU)',
                                            sampleHistory: [
                                                ...revisions,
                                                { note: note || 'Sampel disetujui klien.', result: 'Disetujui' },
                                            ],
                                        }),
                                        'Persetujuan sampel dicatat pada demo.',
                                    );
                                    setNote('');
                                }}
                            >
                                Setujui sampel
                            </Button>
                        </div>
                        {ticket.sampleApproved && (
                            <LinkButton href={`/contracts/new?ticket=${ticket.id}`} secondary icon={ArrowRight}>
                                Lanjut ke kontrak
                            </LinkButton>
                        )}
                        <InfoBox>
                            Persetujuan dicatat oleh Marketing berdasarkan keputusan klien. Penandatanganan kontrak
                            dilakukan pada langkah berikutnya.
                        </InfoBox>
                    </div>
                </Panel>
            </div>
        </>
    );
}

export function ContractsPage() {
    const { data, update } = usePreview();
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('');
    const [selected, setSelected] = useState<Contract | null>(null);
    const contracts = data.contracts.filter(
        (item) =>
            (!status || item.status === status) &&
            `${item.id} ${data.clients.find((client) => client.id === item.clientId)?.name}`
                .toLowerCase()
                .includes(search.toLowerCase()),
    );
    function activate() {
        if (!selected) return;
        const next = activateContract(selected);
        const invoiceId = `INV-${String(Math.max(...data.invoices.map((item) => Number(item.id.slice(4)))) + 1).padStart(4, '0')}`;
        if (
            update(
                (state) => ({
                    ...state,
                    contracts: state.contracts.map((item) => (item.id === next.id ? next : item)),
                    tickets: state.tickets.map((item) =>
                        item.id === next.ticketId ? openPreRegistration(item, next) : item,
                    ),
                    invoices: state.invoices.some(
                        (item) => item.ticketId === next.ticketId && item.term.startsWith('DP'),
                    )
                        ? state.invoices
                        : [
                              ...state.invoices,
                              {
                                  id: invoiceId,
                                  ticketId: next.ticketId,
                                  clientId: next.clientId,
                                  term: `DP ${next.dpPercent}%`,
                                  amount: Math.round((next.amount * next.dpPercent) / 100),
                                  dueDate: '2026-10-28',
                                  status: 'Terbit',
                                  proof: null,
                                  proofStatus: null,
                              },
                          ],
                }),
                'Kontrak aktif, fase pra-registrasi dibuka, dan tagihan DP dibuat pada demo.',
            )
        )
            setSelected(null);
    }
    return (
        <>
            <PageHeader
                title="Daftar kontrak"
                eyebrow="KERJA SAMA KLIEN"
                description="Kelola kontrak setiap pendaftaran dari draf hingga aktif."
            >
                <LinkButton href="/contracts/new" icon={Plus}>
                    Buat kontrak
                </LinkButton>
            </PageHeader>
            <div className="filter-bar">
                <div className="filter-group">
                    <SearchInput
                        placeholder="Cari nomor kontrak atau klien..."
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                    <Select value={status} onChange={(event) => setStatus(event.target.value)}>
                        <option value="">Semua status</option>
                        {['Draf', 'Dikirim', 'Ditandatangani', 'Aktif'].map((item) => (
                            <option key={item}>{item}</option>
                        ))}
                    </Select>
                </div>
            </div>
            <Panel>
                <div className="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Nomor kontrak</th>
                                <th>Produk / tiket</th>
                                <th>Klien</th>
                                <th>Nilai jasa</th>
                                <th>Status</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {contracts.map((item) => (
                                <tr key={item.id}>
                                    <td>
                                        <span className="document-name">
                                            <span className="file-icon">
                                                <FileSignature size={17} />
                                            </span>
                                            <strong>{item.id}</strong>
                                        </span>
                                    </td>
                                    <td>
                                        {data.tickets.find((ticket) => ticket.id === item.ticketId)?.product}
                                        <small>{item.ticketId}</small>
                                    </td>
                                    <td>{data.clients.find((client) => client.id === item.clientId)?.name}</td>
                                    <td>{currency(item.amount)}</td>
                                    <td>
                                        <Badge>{item.status}</Badge>
                                    </td>
                                    <td>
                                        {item.status === 'Draf' ? (
                                            <Link
                                                href={`/contracts/new?draft=${encodeURIComponent(item.id)}`}
                                                preserveState
                                                className="btn btn-secondary"
                                                aria-label={`Lanjutkan draf ${item.id}`}
                                            >
                                                Lanjutkan draf
                                            </Link>
                                        ) : (
                                            <Button
                                                variant={item.status === 'Ditandatangani' ? 'primary' : 'secondary'}
                                                onClick={() => setSelected(item)}
                                                aria-label={`${item.status === 'Ditandatangani' ? 'Konfirmasi kontrak' : 'Lihat kontrak'} ${item.id}`}
                                            >
                                                {item.status === 'Ditandatangani' ? 'Konfirmasi' : 'Lihat'}
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {!contracts.length && <EmptyState title="Kontrak tidak ditemukan" />}
                </div>
            </Panel>
            {selected && (
                <Modal
                    title={
                        selected.status === 'Ditandatangani'
                            ? 'Konfirmasi kontrak bertanda tangan'
                            : 'Pratinjau kontrak'
                    }
                    description={selected.id}
                    wide
                    onClose={() => setSelected(null)}
                >
                    <ContractPreview contract={selected} />
                    <div className="form-footer">
                        <Button variant="secondary" onClick={() => setSelected(null)}>
                            Tutup
                        </Button>
                        {selected.status === 'Dikirim' && (
                            <Button
                                onClick={() => {
                                    update(
                                        (state) => ({
                                            ...state,
                                            contracts: state.contracts.map((item) =>
                                                item.id === selected.id ? { ...item, status: 'Ditandatangani' } : item,
                                            ),
                                        }),
                                        'Unggahan kontrak bertanda tangan disimulasikan.',
                                    );
                                    setSelected(null);
                                }}
                            >
                                Simulasikan tanda tangan klien
                            </Button>
                        )}
                        {selected.status === 'Ditandatangani' && (
                            <Button icon={Check} onClick={activate}>
                                Aktifkan kontrak
                            </Button>
                        )}
                    </div>
                </Modal>
            )}
        </>
    );
}

export function ContractPreview({ contract }: { contract: Contract }) {
    const { data } = usePreview();
    const ticket = data.tickets.find((item) => item.id === contract.ticketId);
    const client = data.clients.find((item) => item.id === contract.clientId);
    return (
        <div className="contract-preview-grid">
            <FilePreview title="Kontrak Maklon dan Pendaftaran" subtitle={contract.id} />
            <div className="contract-summary">
                <Badge>{contract.status}</Badge>
                <h3>Ringkasan kontrak</h3>
                <dl className="detail-list">
                    <div>
                        <dt>Klien</dt>
                        <dd>{client?.company}</dd>
                    </div>
                    <div>
                        <dt>Produk</dt>
                        <dd>{ticket?.product}</dd>
                    </div>
                    <div>
                        <dt>Nilai jasa</dt>
                        <dd>{currency(contract.amount)}</dd>
                    </div>
                    <div>
                        <dt>DP {contract.dpPercent}%</dt>
                        <dd>{currency((contract.amount * contract.dpPercent) / 100)}</dd>
                    </div>
                    <div>
                        <dt>Pelunasan {100 - contract.dpPercent}%</dt>
                        <dd>{currency((contract.amount * (100 - contract.dpPercent)) / 100)}</dd>
                    </div>
                    <div>
                        <dt>Templat</dt>
                        <dd>{contract.template}</dd>
                    </div>
                </dl>
                <InfoBox>
                    Pratinjau tampilan, bukan dokumen hukum atau PDF hasil PHPWord. Konversi dan pengiriman berkas
                    dihubungkan pada tahap backend.
                </InfoBox>
            </div>
        </div>
    );
}

export function CreateContractPage() {
    const { data, update } = usePreview();
    const query = new URLSearchParams(window.location.search);
    const draft = data.contracts.find((item) => item.id === query.get('draft') && item.status === 'Draf');
    const candidates = data.tickets.filter(
        (item) =>
            item.sampleApproved &&
            !item.closed &&
            (!data.contracts.some((contract) => contract.ticketId === item.id) || item.id === draft?.ticketId),
    );
    const queryId = query.get('ticket');
    const [form, setForm] = useState({
        ticketId:
            draft?.ticketId ??
            (queryId ? (candidates.find((item) => item.id === queryId)?.id ?? '') : (candidates[0]?.id ?? '')),
        amount: String(draft?.amount ?? 30000000),
        dp: String(draft?.dpPercent ?? 50),
        template: draft?.template.replace(/ v\d+$/, '') ?? data.templates[0].name,
    });
    const [preview, setPreview] = useState(false);
    const [error, setError] = useState('');
    const selected = data.tickets.find((item) => item.id === form.ticketId);
    const value: Contract = {
        id: draft?.id ?? `KTR/2026/${form.ticketId.slice(-4)}`,
        ticketId: form.ticketId,
        clientId: selected?.clientId ?? '',
        amount: Number(form.amount),
        dpPercent: Number(form.dp),
        status: 'Draf',
        template: `${form.template} v${data.templates.find((item) => item.name === form.template)?.version ?? 1}`,
        dueDays: 7,
    };
    function validate() {
        if (!selected || !selected.sampleApproved) {
            setError('Pilih tiket dengan sampel yang sudah disetujui.');
            return false;
        }
        if (value.amount <= 0 || !Number.isFinite(value.amount) || value.dpPercent <= 0 || value.dpPercent >= 100) {
            setError('Nilai jasa harus positif dan persentase DP antara 1–99%.');
            return false;
        }
        setError('');
        return true;
    }
    function save(status: Contract['status']) {
        if (!validate()) return;
        if (
            update(
                (state) => ({
                    ...state,
                    contracts: draft
                        ? state.contracts.map((item) => (item.id === draft.id ? { ...value, status } : item))
                        : [...state.contracts, { ...value, status }],
                }),
                status === 'Draf'
                    ? 'Draf kontrak disimpan pada demo.'
                    : 'Pengiriman kontrak disimulasikan; tidak ada pesan WhatsApp nyata.',
                value.id,
            )
        )
            router.visit('/contracts', { preserveState: true });
    }
    return (
        <>
            <Link href="/contracts" className="back-link">
                <ArrowLeft size={14} />
                Daftar kontrak
            </Link>
            <PageHeader
                title="Buat kontrak"
                eyebrow="KONTRAK PENDAFTARAN"
                description="Siapkan kerja sama yang jelas untuk setiap pendaftaran produk."
            />
            <div className="contract-stepper">
                {['Draf', 'Dikirim', 'Ditandatangani', 'Aktif'].map((item, index) => (
                    <span key={item} className={index === 0 ? 'current' : ''}>
                        <i>{index + 1}</i>
                        {item}
                        {index < 3 && <ArrowRight size={13} />}
                    </span>
                ))}
            </div>
            <div className="two-column">
                <Panel title="Data kontrak">
                    <div className="panel-body form-stack">
                        <Field label="Tiket">
                            <Select
                                value={form.ticketId}
                                disabled={Boolean(draft)}
                                onChange={(event) => setForm({ ...form, ticketId: event.target.value })}
                            >
                                <option value="">Pilih tiket</option>
                                {candidates.map((item) => (
                                    <option value={item.id} key={item.id}>
                                        {item.product} · {item.id}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label="Templat kontrak">
                            <Select
                                value={form.template}
                                onChange={(event) => setForm({ ...form, template: event.target.value })}
                            >
                                {data.templates
                                    .filter((item) => item.kind === 'Kontrak')
                                    .map((item) => (
                                        <option key={item.id}>{item.name}</option>
                                    ))}
                            </Select>
                        </Field>
                        <Field label="Nilai jasa (Rp)">
                            <input
                                type="number"
                                min="1"
                                value={form.amount}
                                onChange={(event) => setForm({ ...form, amount: event.target.value })}
                            />
                        </Field>
                        <div className="form-grid">
                            <Field label="DP (%)">
                                <input
                                    type="number"
                                    min="1"
                                    max="99"
                                    value={form.dp}
                                    onChange={(event) => setForm({ ...form, dp: event.target.value })}
                                />
                            </Field>
                            <Field label="Pelunasan (%)">
                                <input value={100 - Number(form.dp)} readOnly />
                            </Field>
                        </div>
                        <Field label="Jatuh tempo DP">
                            <input value="7 hari setelah kontrak aktif · usulan demo" readOnly />
                        </Field>
                        {error && (
                            <p className="form-error" role="alert">
                                {error}
                            </p>
                        )}
                        <div className="form-footer">
                            <Button variant="secondary" onClick={() => save('Draf')}>
                                Simpan draf
                            </Button>
                            <Button
                                icon={Eye}
                                onClick={() => {
                                    if (validate()) setPreview(true);
                                }}
                            >
                                Pratinjau sebelum kirim
                            </Button>
                        </div>
                    </div>
                </Panel>
                <Panel title="Ringkasan termin">
                    <div className="panel-body">
                        <div className="contract-total">
                            <small>Nilai kerja sama</small>
                            <strong>{currency(value.amount || 0)}</strong>
                        </div>
                        <div className="payment-term">
                            <span className="stat-icon icon-green">
                                <Wallet size={18} />
                            </span>
                            <div>
                                <strong>DP {value.dpPercent || 0}%</strong>
                                <small>Terbit setelah kontrak aktif</small>
                            </div>
                            <strong>{currency((value.amount * value.dpPercent) / 100 || 0)}</strong>
                        </div>
                        <div className="payment-term">
                            <span className="stat-icon icon-blue">
                                <Receipt size={18} />
                            </span>
                            <div>
                                <strong>Pelunasan {100 - (value.dpPercent || 0)}%</strong>
                                <small>Terbit saat NIE dicatat</small>
                            </div>
                            <strong>{currency((value.amount * (100 - value.dpPercent)) / 100 || 0)}</strong>
                        </div>
                        <div className="mt-5">
                            <InfoBox>
                                Setiap tiket hanya memiliki satu kontrak. Persentase termin dan jatuh tempo final perlu
                                disahkan Finance.
                            </InfoBox>
                        </div>
                    </div>
                </Panel>
            </div>
            {preview && (
                <Modal
                    title="Tinjau sebelum dikirim"
                    description="Pastikan data dan termin sudah sesuai."
                    wide
                    onClose={() => setPreview(false)}
                >
                    <ContractPreview contract={value} />
                    <div className="form-footer">
                        <Button variant="secondary" onClick={() => setPreview(false)}>
                            Kembali mengubah
                        </Button>
                        <Button onClick={() => save('Dikirim')}>Simulasikan pengiriman kontrak</Button>
                    </div>
                </Modal>
            )}
        </>
    );
}

export function InvoiceTable({ invoices, verification = false }: { invoices: Invoice[]; verification?: boolean }) {
    const { data, role } = usePreview();
    const [selected, setSelected] = useState<Invoice | null>(null);
    if (!invoices.length)
        return (
            <EmptyState title="Belum ada tagihan" description="Tagihan diterbitkan sesuai status kontrak dan NIE." />
        );
    return (
        <>
            <div className="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Tagihan</th>
                            <th>Klien / tiket</th>
                            <th>Termin</th>
                            <th>Nominal</th>
                            <th>Jatuh tempo</th>
                            <th>Status</th>
                            <th />
                        </tr>
                    </thead>
                    <tbody>
                        {invoices.map((invoice) => (
                            <tr key={invoice.id}>
                                <td>
                                    <span className="document-name">
                                        <span className="file-icon">
                                            <Receipt size={17} />
                                        </span>
                                        <strong>{invoice.id}</strong>
                                    </span>
                                </td>
                                <td>
                                    {data.clients.find((item) => item.id === invoice.clientId)?.name}
                                    <small>
                                        <Link
                                            href={`/tickets/${invoice.ticketId}`}
                                            preserveState
                                            className="invoice-ticket-link"
                                        >
                                            {invoice.ticketId}
                                        </Link>
                                    </small>
                                </td>
                                <td>{invoice.term}</td>
                                <td>
                                    <strong>{currency(invoice.amount)}</strong>
                                </td>
                                <td>{dateLabel(invoice.dueDate)}</td>
                                <td>
                                    <Badge>{invoice.status}</Badge>
                                    {invoice.proofStatus === 'rejected' && (
                                        <small className="text-rose-500">Bukti ditolak</small>
                                    )}
                                </td>
                                <td>
                                    {invoice.proof && (
                                        <Button
                                            variant={verification ? 'primary' : 'secondary'}
                                            aria-label={`Periksa pembayaran ${invoice.id}`}
                                            onClick={() => setSelected(invoice)}
                                        >
                                            {verification ? 'Periksa bukti' : 'Lihat bukti'}
                                        </Button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {selected && (
                <PaymentReview
                    invoice={selected}
                    readOnly={role !== 'finance' || selected.status === 'Lunas'}
                    onClose={() => setSelected(null)}
                />
            )}
        </>
    );
}

export function PaymentReview({
    invoice,
    onClose,
    readOnly = false,
}: {
    invoice: Invoice;
    onClose: () => void;
    readOnly?: boolean;
}) {
    const { data, update } = usePreview();
    const [reason, setReason] = useState('');
    const [error, setError] = useState('');
    function decide(decision: 'approve' | 'reject') {
        if (readOnly) return;
        try {
            const next = verifyInvoice(invoice, decision, reason);
            if (
                update(
                    (state) => ({
                        ...state,
                        invoices: state.invoices.map((item) => (item.id === invoice.id ? next : item)),
                        tickets: state.tickets.map((item) =>
                            item.id === invoice.ticketId && invoice.term.startsWith('DP') && decision === 'approve'
                                ? { ...item, dpPaid: true }
                                : item,
                        ),
                    }),
                    decision === 'approve'
                        ? 'Pembayaran diverifikasi lunas pada demo.'
                        : 'Bukti transfer ditolak dengan alasan.',
                )
            )
                onClose();
        } catch (caught) {
            setError((caught as Error).message);
        }
    }
    return (
        <Modal
            title={readOnly ? 'Bukti pembayaran' : 'Verifikasi bukti pembayaran'}
            description={`${invoice.id} · ${data.clients.find((item) => item.id === invoice.clientId)?.name}`}
            onClose={onClose}
        >
            <div className="transfer-proof">
                <span className="transfer-logo">
                    <Banknote size={28} />
                </span>
                <span className="eyebrow">ILUSTRASI BUKTI TRANSFER</span>
                <h3>Transfer berhasil</h3>
                <strong>{currency(invoice.amount)}</strong>
                <p>{invoice.proof}</p>
                <div>
                    <span>Tujuan pembayaran</span>
                    <b>PT Aisha Zahra Herbalaboratories</b>
                </div>
                <div>
                    <span>Tagihan terkait</span>
                    <b>
                        {invoice.id} · {invoice.term}
                    </b>
                </div>
            </div>
            {!readOnly && (
                <div className="mt-5">
                    <Field label="Alasan penolakan" error={error} hint="Wajib diisi hanya jika bukti ditolak.">
                        <textarea
                            value={reason}
                            onChange={(event) => setReason(event.target.value)}
                            placeholder="Contoh: nominal transfer belum sesuai tagihan..."
                        />
                    </Field>
                </div>
            )}
            <div className="form-footer">
                <Button variant="secondary" onClick={onClose}>
                    Tutup
                </Button>
                {!readOnly && (
                    <>
                        <Button variant="danger" onClick={() => decide('reject')}>
                            Tolak bukti
                        </Button>
                        <Button icon={Check} onClick={() => decide('approve')}>
                            Konfirmasi lunas
                        </Button>
                    </>
                )}
            </div>
        </Modal>
    );
}

export function InvoicesPage({ verification = false }: { verification?: boolean }) {
    const { data } = usePreview();
    const [status, setStatus] = useState('');
    const [client, setClient] = useState('');
    const base = verification ? data.invoices.filter((item) => item.proofStatus === 'pending') : data.invoices;
    const invoices = base.filter(
        (item) => (!status || item.status === status) && (!client || item.clientId === client),
    );
    return (
        <>
            <PageHeader
                title={verification ? 'Verifikasi pembayaran' : 'Daftar tagihan'}
                eyebrow="KEUANGAN KLIEN"
                description={
                    verification
                        ? 'Pastikan bukti transfer sesuai dengan tagihan sebelum mengonfirmasi.'
                        : 'Pantau termin, jatuh tempo, dan pembayaran seluruh pendaftaran.'
                }
            />
            <div className="stat-grid">
                <StatCard
                    label="Menunggu verifikasi"
                    value={data.invoices.filter((item) => item.proofStatus === 'pending').length}
                    icon={ClipboardCheck}
                    tone="amber"
                    detail="Bukti transfer diterima"
                />
                <StatCard
                    label="Lunas bulan ini"
                    value={data.invoices.filter((item) => item.status === 'Lunas').length}
                    icon={CheckCheck}
                    detail="Pembayaran terkonfirmasi"
                />
                <StatCard
                    label="Total piutang"
                    value={shortMoney(
                        data.invoices
                            .filter((item) => item.status !== 'Lunas')
                            .reduce((sum, item) => sum + item.amount, 0),
                    )}
                    icon={Wallet}
                    tone="blue"
                    detail="Termasuk tiket yang selesai"
                />
                <StatCard
                    label="Lewat jatuh tempo"
                    value={data.invoices.filter((item) => item.status === 'Lewat jatuh tempo').length}
                    icon={CalendarDays}
                    tone="rose"
                    detail="Perlu pengingat ke klien"
                />
            </div>
            <div className="filter-bar">
                <div className="filter-group">
                    <Select value={status} onChange={(event) => setStatus(event.target.value)}>
                        <option value="">Semua status</option>
                        {['Terbit', 'Menunggu verifikasi', 'Lunas', 'Lewat jatuh tempo'].map((item) => (
                            <option key={item}>{item}</option>
                        ))}
                    </Select>
                    <Select value={client} onChange={(event) => setClient(event.target.value)}>
                        <option value="">Semua klien</option>
                        {data.clients.map((item) => (
                            <option value={item.id} key={item.id}>
                                {item.name}
                            </option>
                        ))}
                    </Select>
                </div>
                <span className="text-xs text-muted">{invoices.length} tagihan</span>
            </div>
            <Panel>
                <InvoiceTable invoices={invoices} verification={verification} />
            </Panel>
        </>
    );
}

export function ReceivablesPage() {
    const { data } = usePreview();
    const [selected, setSelected] = useState<string | null>(null);
    const unpaid = data.invoices.filter((item) => item.status !== 'Lunas');
    const clients = data.clients
        .map((client) => ({ ...client, invoices: unpaid.filter((item) => item.clientId === client.id) }))
        .filter((item) => item.invoices.length);
    const rows = clients.map((client) => [
        client.name,
        client.invoices.length,
        client.invoices.reduce((sum, item) => sum + item.amount, 0),
        client.invoices
            .filter((item) => item.status === 'Lewat jatuh tempo')
            .reduce((sum, item) => sum + item.amount, 0),
    ]);
    return (
        <>
            <PageHeader
                title="Laporan piutang"
                eyebrow="RINGKASAN KEUANGAN"
                description="Pelunasan tetap terlihat, bahkan setelah tiket pendaftaran ditutup."
            >
                <Button
                    variant="secondary"
                    icon={Download}
                    onClick={() =>
                        downloadCsv('laporan-piutang.csv', [
                            ['Klien', 'Tagihan belum lunas', 'Total piutang', 'Lewat jatuh tempo'],
                            ...rows,
                        ])
                    }
                >
                    Ekspor CSV
                </Button>
            </PageHeader>
            <div className="stat-grid">
                <StatCard
                    label="Total piutang"
                    value={shortMoney(unpaid.reduce((sum, item) => sum + item.amount, 0))}
                    icon={Wallet}
                    detail={`${unpaid.length} tagihan belum lunas`}
                />
                <StatCard
                    label="Lewat jatuh tempo"
                    value={shortMoney(
                        unpaid
                            .filter((item) => item.status === 'Lewat jatuh tempo')
                            .reduce((sum, item) => sum + item.amount, 0),
                    )}
                    icon={CalendarDays}
                    tone="rose"
                    detail="Perlu pengingat pembayaran"
                />
                <StatCard
                    label="Klien berpiutang"
                    value={clients.length}
                    icon={Receipt}
                    tone="blue"
                    detail="Dengan tagihan aktif"
                />
            </div>
            <Panel>
                <div className="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Klien</th>
                                <th>Tagihan belum lunas</th>
                                <th>Total piutang</th>
                                <th>Lewat jatuh tempo</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {clients.map((client) => (
                                <tr key={client.id}>
                                    <td>
                                        <span className="client-cell">
                                            <Avatar initials={client.initials} color={client.color} />
                                            <strong>{client.name}</strong>
                                        </span>
                                    </td>
                                    <td>{client.invoices.length}</td>
                                    <td>
                                        <strong>
                                            {currency(client.invoices.reduce((sum, item) => sum + item.amount, 0))}
                                        </strong>
                                    </td>
                                    <td>
                                        {currency(
                                            client.invoices
                                                .filter((item) => item.status === 'Lewat jatuh tempo')
                                                .reduce((sum, item) => sum + item.amount, 0),
                                        )}
                                    </td>
                                    <td>
                                        <Button variant="secondary" onClick={() => setSelected(client.id)}>
                                            Rincian
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Panel>
            {selected && (
                <Modal
                    title="Rincian piutang klien"
                    description={data.clients.find((item) => item.id === selected)?.company}
                    wide
                    onClose={() => setSelected(null)}
                >
                    <InvoiceTable invoices={unpaid.filter((item) => item.clientId === selected)} />
                </Modal>
            )}
        </>
    );
}
