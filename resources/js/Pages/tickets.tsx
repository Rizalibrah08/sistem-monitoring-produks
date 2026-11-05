import { Link, router } from '../lib/preview-navigation';
import {
    ArrowLeft,
    ArrowRight,
    ArrowUpRight,
    Check,
    ChevronLeft,
    ChevronRight,
    Download,
    FileCheck2,
    FileText,
    Filter,
    FlaskConical,
    Info,
    Mail,
    MessageCircle,
    MoreHorizontal,
    Phone,
    Plus,
    Search,
    Ticket as TicketIcon,
    Users,
    X,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import {
    Avatar,
    Badge,
    Button,
    EmptyState,
    Field,
    InfoBox,
    LinkButton,
    Modal,
    PageHeader,
    Panel,
    Progress,
    SearchInput,
    Select,
    TextLink,
} from '../components/ui';
import { usePreview } from '../mocks/preview-provider';
import { createFixtures, DEMO_TICKET } from '../mocks/fixtures';
import { dateLabel, downloadCsv, ticketProgress, ticketStatus } from '../lib/utils';
import { TicketTable } from '../features/tickets/ticket-table';
import type { Client, Ticket } from '../types';
import { DocumentChecklist } from './documents';
import { TicketPayments } from './payments';

export function TicketsPage() {
    const { data, role } = usePreview();
    const query = new URLSearchParams(window.location.search);
    const [search, setSearch] = useState('');
    const [phase, setPhase] = useState('');
    const [status, setStatus] = useState(query.has('review') ? 'Perlu ditinjau' : '');
    const [page, setPage] = useState(1);
    const stage = query.get('stage');
    const filtered = data.tickets.filter(
        (ticket) =>
            (role !== 'marketing' ||
                data.clients.find((client) => client.id === ticket.clientId)?.assignedTo === 'marketing') &&
            (!phase || ticket.phase === phase) &&
            (!stage || ticket.stage === stage) &&
            (!status || (status === 'Perlu ditinjau' ? ticket.needsReview : ticketStatus(ticket) === status)) &&
            `${ticket.id} ${ticket.product} ${data.clients.find((client) => client.id === ticket.clientId)?.name}`
                .toLowerCase()
                .includes(search.toLowerCase()),
    );
    const pages = Math.ceil(filtered.length / 8);
    const currentPage = Math.min(page, Math.max(pages, 1));
    const rows = filtered.slice((currentPage - 1) * 8, currentPage * 8);
    return (
        <>
            <PageHeader
                title="Daftar tiket"
                eyebrow="PENDAFTARAN PRODUK"
                description="Satu produk, satu tiket. Pantau seluruh proses pendaftaran di sini."
            >
                <Button
                    variant="secondary"
                    icon={Download}
                    onClick={() =>
                        downloadCsv('daftar-tiket.csv', [
                            ['Kode', 'Produk', 'Fase', 'Tahap', 'Status'],
                            ...filtered.map((item) => [
                                item.id,
                                item.product,
                                item.phase,
                                item.stage,
                                ticketStatus(item),
                            ]),
                        ])
                    }
                >
                    Ekspor
                </Button>
                {role === 'marketing' && (
                    <LinkButton href="/tickets/new" icon={Plus}>
                        Buat tiket
                    </LinkButton>
                )}
            </PageHeader>
            <div className="filter-bar">
                <div className="filter-group">
                    <SearchInput
                        placeholder="Cari kode, produk, atau klien..."
                        value={search}
                        onChange={(event) => {
                            setSearch(event.target.value);
                            setPage(1);
                        }}
                    />
                    <Select
                        value={phase}
                        onChange={(event) => {
                            setPhase(event.target.value);
                            setPage(1);
                        }}
                        aria-label="Filter fase"
                    >
                        <option value="">Semua fase</option>
                        <option>Awal</option>
                        <option>Pra-registrasi</option>
                        <option>Registrasi</option>
                    </Select>
                    <Select
                        value={status}
                        onChange={(event) => {
                            setStatus(event.target.value);
                            setPage(1);
                        }}
                        aria-label="Filter status"
                    >
                        <option value="">Semua status</option>
                        {['Tepat waktu', 'Terlambat', 'Menunggu MoU', 'Menunggu BPOM', 'Perlu ditinjau', 'Selesai'].map(
                            (item) => (
                                <option key={item}>{item}</option>
                            ),
                        )}
                    </Select>
                </div>
                <span className="text-xs text-muted">{filtered.length} tiket ditemukan</span>
            </div>
            {stage && (
                <div className="mb-4">
                    <InfoBox>
                        Filter tahap: <strong>{stage}</strong> ·{' '}
                        <Link href="/tickets" className="underline">
                            Hapus filter
                        </Link>
                    </InfoBox>
                </div>
            )}
            <Panel>
                <TicketTable tickets={rows} />
                <div className="table-footer">
                    <span>
                        Menampilkan {filtered.length ? (currentPage - 1) * 8 + 1 : 0}–
                        {Math.min(currentPage * 8, filtered.length)} dari {filtered.length} tiket
                    </span>
                    <div className="pagination">
                        <button
                            aria-label="Halaman sebelumnya"
                            disabled={currentPage === 1}
                            onClick={() => setPage(currentPage - 1)}
                        >
                            <ChevronLeft size={13} />
                        </button>
                        {Array.from({ length: pages }, (_, i) => (
                            <button
                                key={i}
                                className={currentPage === i + 1 ? 'active' : ''}
                                onClick={() => setPage(i + 1)}
                            >
                                {i + 1}
                            </button>
                        ))}
                        <button
                            aria-label="Halaman berikutnya"
                            disabled={currentPage >= pages}
                            onClick={() => setPage(currentPage + 1)}
                        >
                            <ChevronRight size={13} />
                        </button>
                    </div>
                </div>
            </Panel>
        </>
    );
}

export function CreateTicketPage() {
    const { data, update } = usePreview();
    const prospectId = new URLSearchParams(window.location.search).get('prospect');
    const prospect = data.prospects.find((item) => item.id === prospectId);
    const [form, setForm] = useState({ clientId: '', product: prospect?.product ?? '', form: '', category: '' });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [clientModal, setClientModal] = useState(false);
    function submit(event: FormEvent) {
        event.preventDefault();
        const next: Record<string, string> = {};
        if (!form.clientId) next.clientId = 'Pilih klien terlebih dahulu.';
        if (!form.product.trim()) next.product = 'Nama produk wajib diisi.';
        if (!form.form) next.form = 'Pilih sediaan produk.';
        if (!form.category) next.category = 'Pilih kategori produk.';
        setErrors(next);
        if (Object.keys(next).length) return;
        const number = Math.max(...data.tickets.map((item) => Number(item.id.slice(-4)))) + 1;
        const id = `PRD-2026-${String(number).padStart(4, '0')}`;
        const base = createFixtures().tickets[0];
        const ticket: Ticket = {
            ...base,
            ...form,
            product: form.product.trim(),
            id,
            phase: 'Awal',
            stage: 'Pengembangan sampel',
            closed: false,
            late: false,
            needsReview: false,
            mouSigned: false,
            dpPaid: false,
            sampleApproved: false,
            submitted: false,
            preSubmitted: false,
            preResult: 'none',
            revision: 1,
            createdAt: '2026-10-21',
            dueDate: '2026-11-10',
            age: 0,
            score: 0,
            bpomDays: null,
            documents: base.documents.map((doc) => ({
                ...doc,
                locked: false,
                status: doc.scope === 'Klien' || doc.scope === 'Perusahaan' ? doc.status : 'Belum ada',
                version: doc.scope === 'Tiket' ? 0 : doc.version,
                versions: doc.scope === 'Tiket' ? [] : doc.versions,
                validation: doc.scope === 'Tiket' ? 'Belum diperiksa' : doc.validation,
            })),
        };
        if (
            update(
                (state) => ({
                    ...state,
                    tickets: [ticket, ...state.tickets],
                    prospects: state.prospects.map((item) =>
                        item.id === prospectId ? { ...item, status: 'Dijadikan tiket' } : item,
                    ),
                }),
                `Tiket ${id} berhasil dibuat pada demo.`,
            )
        )
            router.visit(`/tickets/${id}`, { preserveState: true });
    }
    return (
        <>
            <Link href="/tickets" preserveState className="back-link">
                <ArrowLeft size={14} />
                Kembali ke daftar tiket
            </Link>
            <PageHeader
                title="Buat tiket pendaftaran"
                eyebrow="TIKET BARU"
                description="Mulai perjalanan pendaftaran produk dengan data yang lengkap."
            />
            <form onSubmit={submit}>
                <div className="two-column">
                    <Panel title="Data pengajuan" description="Lengkapi informasi produk dan pemilik merek.">
                        <div className="panel-body form-stack">
                            <Field label="Klien" error={errors.clientId}>
                                <Select
                                    value={form.clientId}
                                    onChange={(event) => setForm({ ...form, clientId: event.target.value })}
                                >
                                    <option value="">Pilih klien</option>
                                    {data.clients.map((client) => (
                                        <option key={client.id} value={client.id}>
                                            {client.company}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <button type="button" className="text-link self-start" onClick={() => setClientModal(true)}>
                                <Plus size={13} />
                                Tambah klien baru
                            </button>
                            <Field label="Nama produk" error={errors.product}>
                                <input
                                    value={form.product}
                                    onChange={(event) => setForm({ ...form, product: event.target.value })}
                                    placeholder="Contoh: Kapsul jahe merah"
                                />
                            </Field>
                            <div className="form-grid">
                                <Field label="Sediaan" error={errors.form}>
                                    <Select
                                        value={form.form}
                                        onChange={(event) => setForm({ ...form, form: event.target.value })}
                                    >
                                        <option value="">Pilih sediaan</option>
                                        {data.masters.forms.map((item) => (
                                            <option key={item.id}>{item.name}</option>
                                        ))}
                                    </Select>
                                </Field>
                                <Field label="Kategori" error={errors.category}>
                                    <Select
                                        value={form.category}
                                        onChange={(event) => setForm({ ...form, category: event.target.value })}
                                    >
                                        <option value="">Pilih kategori</option>
                                        {data.masters.categories.map((item) => (
                                            <option key={item.id}>{item.name}</option>
                                        ))}
                                    </Select>
                                </Field>
                            </div>
                            <InfoBox>
                                Daftar bahan baku dilengkapi oleh R&D setelah tiket dibuat. Kode tiket dibuat otomatis.
                            </InfoBox>
                            <div className="form-footer">
                                <LinkButton href="/tickets" secondary>
                                    Batal
                                </LinkButton>
                                <Button type="submit" icon={Plus}>
                                    Simpan tiket
                                </Button>
                            </div>
                        </div>
                    </Panel>
                    <Panel title="Pratinjau checklist" description="Disusun otomatis berdasarkan sediaan dan kategori.">
                        <div className="panel-body">
                            <div className="checklist-phase">
                                <span>01</span>
                                <h3>Pra-registrasi</h3>
                            </div>
                            {[
                                ['Master formula', 'Perlu unggah'],
                                ['Kontrak pendaftaran', 'Dari templat'],
                                ['Surat kuasa', 'Tertaut otomatis'],
                                ['Pernyataan keabsahan', 'Tertaut otomatis'],
                            ].map(([name, status]) => (
                                <div className="checklist-preview-row" key={name}>
                                    <FileText size={15} />
                                    <span>{name}</span>
                                    <Badge tone={status.includes('Tertaut') ? 'green' : 'neutral'} dot={false}>
                                        {status}
                                    </Badge>
                                </div>
                            ))}
                            <div className="checklist-phase mt-7">
                                <span>02</span>
                                <h3>Registrasi</h3>
                            </div>
                            {[
                                ['CoA bahan baku', 'Perlu unggah'],
                                ['Spesifikasi kemasan', 'Perlu unggah'],
                                ['Sertifikat halal', 'Tertaut otomatis'],
                                ['Uji stabilitas', 'Perlu unggah'],
                            ].map(([name, status]) => (
                                <div className="checklist-preview-row" key={name}>
                                    <FileText size={15} />
                                    <span>{name}</span>
                                    <Badge tone={status.includes('Tertaut') ? 'green' : 'neutral'} dot={false}>
                                        {status}
                                    </Badge>
                                </div>
                            ))}
                            <p className="section-note">
                                Contoh checklist. Daftar final mengikuti aturan RA yang disahkan pada backend.
                            </p>
                        </div>
                    </Panel>
                </div>
            </form>
            {clientModal && (
                <ClientDialog
                    onClose={() => setClientModal(false)}
                    onSaved={(id) => {
                        setForm({ ...form, clientId: id });
                        setClientModal(false);
                    }}
                />
            )}
        </>
    );
}

export function TicketDetailPage({ id }: { id?: string | null }) {
    const { data, role } = usePreview();
    const ticket = data.tickets.find((item) => item.id === (id ?? DEMO_TICKET));
    const [tab, setTab] = useState(new URLSearchParams(window.location.search).get('tab') ?? 'info');
    if (!ticket)
        return (
            <EmptyState title="Tiket tidak ditemukan" description="Tiket ini tidak tersedia dalam data demonstrasi." />
        );
    const client = data.clients.find((item) => item.id === ticket.clientId)!;
    const tabs = [
        ['info', 'Info'],
        ['payments', 'Kontrak dan pembayaran'],
        ['stages', 'Tahapan'],
        ['documents', 'Dokumen'],
        ['history', 'Riwayat'],
    ];
    return (
        <>
            <Link href="/tickets" preserveState className="back-link">
                <ArrowLeft size={14} />
                Daftar tiket
            </Link>
            <div className="ticket-heading">
                <div className="ticket-heading-main">
                    <span className="ticket-heading-icon">
                        <FlaskConical size={24} strokeWidth={1.5} />
                    </span>
                    <div>
                        <div className="ticket-id">
                            {ticket.id}
                            <span>·</span>
                            {client.name}
                        </div>
                        <h1>{ticket.product}</h1>
                        <p>
                            {ticket.form}
                            <span>·</span>
                            {ticket.category}
                            <span>·</span>PIC Marketing: Rizal
                        </p>
                    </div>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge tone="blue">{ticket.phase}</Badge>
                    <Badge>{ticketStatus(ticket)}</Badge>
                </div>
            </div>
            <div className="tabs">
                {tabs.map(([key, label]) => (
                    <button key={key} className={tab === key ? 'active' : ''} onClick={() => setTab(key)}>
                        {label}
                        {key === 'documents' && <span className="tab-count">{ticket.documents.length}</span>}
                    </button>
                ))}
            </div>
            {tab === 'info' && (
                <div className="two-column">
                    <Panel
                        title="Informasi pendaftaran"
                        action={
                            <Badge tone="neutral" dot={false}>
                                Tiket produk
                            </Badge>
                        }
                    >
                        <div className="panel-body">
                            <dl className="detail-list">
                                {[
                                    ['Klien', client.company],
                                    ['Produk', ticket.product],
                                    ['Sediaan & kategori', `${ticket.form} · ${ticket.category}`],
                                    ['Dibuat pada', dateLabel(ticket.createdAt)],
                                    ['Tahap berjalan', ticket.stage],
                                    ['Tenggat tahap', dateLabel(ticket.dueDate)],
                                    ...(ticket.nie
                                        ? [
                                              ['Nomor Izin Edar', ticket.nie],
                                              [
                                                  'Tanggal terbit NIE',
                                                  ticket.nieDate ? dateLabel(ticket.nieDate) : 'Belum dicatat',
                                              ],
                                          ]
                                        : []),
                                ].map(([label, value]) => (
                                    <div key={label}>
                                        <dt>{label}</dt>
                                        <dd>{value}</dd>
                                    </div>
                                ))}
                            </dl>
                            <div className="ticket-progress-box">
                                <div>
                                    <span>Dokumen wajib disetujui</span>
                                    <strong>{ticketProgress(ticket)}%</strong>
                                </div>
                                <Progress value={ticketProgress(ticket)} showLabel={false} />
                            </div>
                        </div>
                    </Panel>
                    <div className="space-y-5">
                        <Panel title="Bahan baku & sampel">
                            <div className="panel-body">
                                <div className="ingredient-tags">
                                    {ticket.ingredients.map((item) => (
                                        <span key={item}>
                                            <FlaskConical size={13} />
                                            {item}
                                        </span>
                                    ))}
                                </div>
                                <div className="sample-state">
                                    <span
                                        className={`stat-icon ${ticket.sampleApproved ? 'icon-green' : 'icon-amber'}`}
                                    >
                                        <FileCheck2 size={18} />
                                    </span>
                                    <div>
                                        <strong>
                                            {ticket.sampleApproved
                                                ? 'Sampel telah disetujui'
                                                : 'Sampel menunggu persetujuan'}
                                        </strong>
                                        <p>
                                            {ticket.sampleApproved
                                                ? 'Versi 2 · persetujuan dicatat Marketing'
                                                : 'Pembuatan kontrak menunggu keputusan klien.'}
                                        </p>
                                    </div>
                                </div>
                                {role === 'marketing' && (
                                    <TextLink href={`/samples?ticket=${ticket.id}`}>Lihat riwayat sampel</TextLink>
                                )}
                            </div>
                        </Panel>
                        <Panel title="Kontak klien">
                            <div className="panel-body">
                                <div className="client-contact-head">
                                    <Avatar initials={client.initials} color={client.color} />
                                    <div>
                                        <strong>{client.pic}</strong>
                                        <small>{client.company}</small>
                                    </div>
                                </div>
                                <p className="contact-line">
                                    <Phone size={14} />
                                    {client.phone}
                                </p>
                                <p className="contact-line">
                                    <Mail size={14} />
                                    {client.email}
                                </p>
                            </div>
                        </Panel>
                    </div>
                </div>
            )}
            {tab === 'payments' && <TicketPayments ticket={ticket} />}
            {tab === 'documents' && <DocumentChecklist ticket={ticket} />}
            {tab === 'stages' && (
                <Panel
                    title="Perjalanan pendaftaran"
                    description="Waktu kerja internal dan waktu tunggu dicatat terpisah."
                >
                    <div className="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Tahap</th>
                                    <th>SLA</th>
                                    <th>Mulai</th>
                                    <th>Selesai</th>
                                    <th>Durasi kerja</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {[
                                    'Pengembangan sampel',
                                    'Kontrak (MoU)',
                                    'Dokumen pra-registrasi',
                                    'Verifikasi pra-registrasi',
                                    'Menunggu hasil BPOM',
                                    'Dokumen registrasi',
                                    'Verifikasi registrasi',
                                    'Menunggu evaluasi BPOM',
                                    'Input NIE',
                                ].map((name, index) => (
                                    <tr key={name}>
                                        <td>
                                            <span className="timeline-stage">
                                                <i className={index < 6 ? 'done' : index === 6 ? 'current' : ''}>
                                                    {index < 6 ? <Check size={10} /> : index + 1}
                                                </i>
                                                {name}
                                            </span>
                                        </td>
                                        <td>
                                            {[14, '—', 7, 3, '—', 14, 5, '—', 1][index]}
                                            {index !== 1 && index !== 4 && index !== 7 ? ' hari' : ''}
                                        </td>
                                        <td>
                                            {
                                                [
                                                    '12/09',
                                                    '18/09',
                                                    '05/10',
                                                    '09/10',
                                                    '12/10',
                                                    '15/10',
                                                    '20/10',
                                                    '—',
                                                    '—',
                                                ][index]
                                            }
                                        </td>
                                        <td>
                                            {index < 6
                                                ? ['18/09', '05/10', '09/10', '12/10', '15/10', '20/10'][index]
                                                : '—'}
                                        </td>
                                        <td>
                                            {index === 4 || index === 7
                                                ? 'Di luar SLA'
                                                : index < 7
                                                  ? `${[5, 12, 4, 1, 0, 3, 1][index]} hari`
                                                  : '—'}
                                        </td>
                                        <td>
                                            <Badge>
                                                {ticket.closed || index < 6
                                                    ? 'Selesai'
                                                    : index === 6
                                                      ? 'Berjalan'
                                                      : 'Belum dimulai'}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="panel-body">
                        <InfoBox>
                            Ilustrasi timeline untuk tiket contoh. SLA final dan kalender hari kerja dihitung oleh
                            backend.
                        </InfoBox>
                    </div>
                </Panel>
            )}
            {tab === 'history' && (
                <Panel
                    title="Riwayat aktivitas"
                    description="Keputusan, dokumen, dan komunikasi dalam satu jejak kronologis."
                >
                    <div className="activity-timeline">
                        {data.audit
                            .filter(
                                (item) =>
                                    item.object === ticket.id ||
                                    data.invoices.some(
                                        (invoice) => invoice.ticketId === ticket.id && invoice.id === item.object,
                                    ) ||
                                    data.contracts.some(
                                        (contract) => contract.ticketId === ticket.id && contract.id === item.object,
                                    ),
                            )
                            .map((entry) => (
                                <div key={entry.id}>
                                    <span className="activity-timeline-icon">
                                        <FileText size={15} />
                                    </span>
                                    <div>
                                        <strong>{entry.action}</strong>
                                        <p>{entry.change}</p>
                                        <small>
                                            {entry.actor}
                                            <span>·</span>
                                            {entry.time}
                                            <span>·</span>
                                            {entry.source}
                                        </small>
                                    </div>
                                </div>
                            ))}
                    </div>
                </Panel>
            )}
        </>
    );
}

export function ClientsPage() {
    const { data, role } = usePreview();
    const [search, setSearch] = useState('');
    const [selected, setSelected] = useState<Client | 'new' | null>(null);
    const clients = data.clients.filter(
        (item) =>
            (role !== 'marketing' || item.assignedTo === 'marketing') &&
            `${item.name} ${item.phone}`.toLowerCase().includes(search.toLowerCase()),
    );
    return (
        <>
            <PageHeader
                title="Data klien"
                eyebrow="RELASI & KONTAK"
                description="Kelola informasi pemilik merek dan kontak penanggung jawab."
            >
                <Button icon={Plus} onClick={() => setSelected('new')}>
                    Tambah klien
                </Button>
            </PageHeader>
            <div className="filter-bar">
                <SearchInput
                    placeholder="Cari nama atau nomor WhatsApp..."
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                />
                <Badge tone="neutral" dot={false}>
                    {clients.length} klien
                </Badge>
            </div>
            <Panel>
                <div className="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Klien</th>
                                <th>PIC</th>
                                <th>Nomor WhatsApp</th>
                                <th>Email</th>
                                <th>Tiket aktif</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {clients.map((client) => (
                                <tr key={client.id}>
                                    <td>
                                        <span className="client-cell">
                                            <Avatar initials={client.initials} color={client.color} />
                                            <span>
                                                <strong>{client.name}</strong>
                                                <small>{client.company}</small>
                                            </span>
                                        </span>
                                    </td>
                                    <td>{client.pic}</td>
                                    <td>{client.phone}</td>
                                    <td className="muted-cell">{client.email}</td>
                                    <td>
                                        <Badge tone="neutral" dot={false}>
                                            {
                                                data.tickets.filter(
                                                    (item) => item.clientId === client.id && !item.closed,
                                                ).length
                                            }{' '}
                                            tiket
                                        </Badge>
                                    </td>
                                    <td>
                                        <Button variant="secondary" onClick={() => setSelected(client)}>
                                            Ubah
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {!clients.length && <EmptyState title="Klien tidak ditemukan" />}
                </div>
            </Panel>
            {selected && (
                <ClientDialog
                    client={selected === 'new' ? undefined : selected}
                    onClose={() => setSelected(null)}
                    onSaved={() => setSelected(null)}
                />
            )}
        </>
    );
}

export function ClientDialog({
    client,
    onClose,
    onSaved,
}: {
    client?: Client;
    onClose: () => void;
    onSaved: (id: string) => void;
}) {
    const { data, update } = usePreview();
    const [form, setForm] = useState({
        name: client?.name ?? '',
        company: client?.company ?? '',
        pic: client?.pic ?? '',
        phone: client?.phone ?? '',
        email: client?.email ?? '',
    });
    const [error, setError] = useState('');
    function save(event: FormEvent) {
        event.preventDefault();
        const normalize = (phone: string) => phone.replace(/\D/g, '').replace(/^0/, '62');
        if (!form.name.trim() || !form.pic.trim() || !/^\+?[\d\s-]{9,18}$/.test(form.phone))
            return setError('Nama, PIC, dan nomor WhatsApp yang valid wajib diisi.');
        if (data.clients.some((item) => item.id !== client?.id && normalize(item.phone) === normalize(form.phone)))
            return setError('Nomor WhatsApp sudah digunakan klien lain.');
        const id = client?.id ?? crypto.randomUUID();
        const value: Client = {
            ...form,
            company: form.company || form.name,
            id,
            initials: form.name
                .split(' ')
                .map((word) => word[0])
                .join('')
                .slice(0, 2)
                .toUpperCase(),
            color: client?.color ?? 'mint',
            assignedTo: 'marketing',
        };
        if (
            update(
                (state) => ({
                    ...state,
                    clients: client
                        ? state.clients.map((item) => (item.id === id ? value : item))
                        : [...state.clients, value],
                }),
                'Data klien diperbarui pada demo.',
            )
        )
            onSaved(id);
    }
    return (
        <Modal
            title={client ? 'Ubah data klien' : 'Tambah klien baru'}
            description="Nomor WhatsApp digunakan untuk mengenali pemilik tiket."
            onClose={onClose}
        >
            <form onSubmit={save} className="form-stack">
                {Object.entries({
                    name: 'Nama klien',
                    company: 'Nama perusahaan',
                    pic: 'Nama PIC',
                    phone: 'Nomor WhatsApp',
                    email: 'Email',
                }).map(([key, label]) => (
                    <Field key={key} label={label}>
                        <input
                            value={form[key as keyof typeof form]}
                            type={key === 'email' ? 'email' : 'text'}
                            onChange={(event) => setForm({ ...form, [key]: event.target.value })}
                        />
                    </Field>
                ))}
                {error && (
                    <p className="form-error" role="alert">
                        {error}
                    </p>
                )}
                <div className="form-footer">
                    <Button variant="secondary" onClick={onClose}>
                        Batal
                    </Button>
                    <Button type="submit">Simpan klien</Button>
                </div>
            </form>
        </Modal>
    );
}

export function ProspectsPage() {
    const { data, update } = usePreview();
    const [filter, setFilter] = useState('');
    const rows = data.prospects.filter((item) => !filter || item.status === filter);
    return (
        <>
            <PageHeader
                title="Prospek dari chatbot"
                eyebrow="PELUANG BARU"
                description="Tindak lanjuti pengajuan produk dari nomor layanan perusahaan."
            />
            <div className="filter-bar">
                <Select value={filter} onChange={(event) => setFilter(event.target.value)}>
                    <option value="">Semua status</option>
                    <option>Baru</option>
                    <option>Dihubungi</option>
                    <option>Dijadikan tiket</option>
                    <option>Ditolak</option>
                </Select>
                <Badge tone="green">
                    {data.prospects.filter((item) => item.status === 'Baru').length} prospek baru
                </Badge>
            </div>
            <Panel>
                <div className="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Prospek</th>
                                <th>Kontak</th>
                                <th>Gambaran produk</th>
                                <th>Waktu</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((item) => (
                                <tr key={item.id}>
                                    <td>
                                        <strong>{item.name}</strong>
                                        <small>{item.id}</small>
                                    </td>
                                    <td>{item.phone}</td>
                                    <td>{item.product}</td>
                                    <td className="muted-cell">{item.time}</td>
                                    <td>
                                        <Badge>{item.status}</Badge>
                                    </td>
                                    <td>
                                        <div className="flex gap-2">
                                            {!['Dijadikan tiket', 'Ditolak'].includes(item.status) && (
                                                <>
                                                    <LinkButton href={`/tickets/new?prospect=${item.id}`} secondary>
                                                        Jadikan tiket
                                                    </LinkButton>
                                                    <Button
                                                        variant="ghost"
                                                        onClick={() =>
                                                            update(
                                                                (state) => ({
                                                                    ...state,
                                                                    prospects: state.prospects.map((row) =>
                                                                        row.id === item.id
                                                                            ? { ...row, status: 'Ditolak' }
                                                                            : row,
                                                                    ),
                                                                }),
                                                                'Prospek ditandai tidak dilanjutkan.',
                                                            )
                                                        }
                                                    >
                                                        Tolak
                                                    </Button>
                                                </>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {!rows.length && <EmptyState title="Belum ada prospek" />}
                </div>
            </Panel>
            <div className="mt-5">
                <InfoBox>
                    Prospek berasal dari menu “Ajukan produk baru” pada chatbot nomor layanan. Konversi membuka form
                    tiket untuk dilengkapi Marketing.
                </InfoBox>
            </div>
        </>
    );
}
