import { Link, router } from '../lib/preview-navigation';
import {
    ArrowLeft,
    ArrowRight,
    ArrowUpRight,
    Check,
    CheckCheck,
    ChevronDown,
    ChevronLeft,
    Circle,
    FileText,
    Link2,
    MessageCircle,
    MoreHorizontal,
    Paperclip,
    Phone,
    Plus,
    Search,
    Send,
    ShieldCheck,
    Smile,
    Users,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState, type FormEvent } from 'react';
import {
    Avatar,
    Badge,
    Button,
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
} from '../components/ui';
import { usePreview } from '../mocks/preview-provider';
import { DEMO_DATE, DEMO_TICKET } from '../mocks/fixtures';
import { currency, ticketProgress } from '../lib/utils';
import { UploadDialog } from './documents';
import { ClientDialog } from './tickets';
import type { Conversation, DocumentItem, MasterItem, Message } from '../types';

export function InboxPage() {
    const { data, role, update, notify } = usePreview();
    const [search, setSearch] = useState('');
    const [filter, setFilter] = useState('Semua');
    const visible = data.conversations.filter(
        (item) =>
            (role === 'manager' || item.assigned === role) &&
            (filter === 'Semua' || (filter === 'Belum dibaca' ? item.unread > 0 : item.state === filter)) &&
            `${item.name} ${item.phone}`.toLowerCase().includes(search.toLowerCase()),
    );
    const requestedChat = new URLSearchParams(window.location.search).get('chat');
    const [selected, setSelected] = useState(
        requestedChat ? (visible.find((item) => item.id === requestedChat)?.id ?? '') : (visible[0]?.id ?? ''),
    );
    const [mobileChat, setMobileChat] = useState(Boolean(requestedChat));
    const [message, setMessage] = useState('');
    const [infoOpen, setInfoOpen] = useState(false);
    const [filePreview, setFilePreview] = useState<string | null>(null);
    const [convert, setConvert] = useState<string | null>(null);
    const [convertDoc, setConvertDoc] = useState<DocumentItem | null>(null);
    const [targetDocument, setTargetDocument] = useState('d7');
    const [linkModal, setLinkModal] = useState(false);
    const [linkTicket, setLinkTicket] = useState(DEMO_TICKET);
    const [incoming, setIncoming] = useState(false);
    const fileRef = useRef<HTMLInputElement>(null);
    const scrollRef = useRef<HTMLDivElement>(null);
    const conversation = data.conversations.find(
        (item) => item.id === selected && (role === 'manager' || item.assigned === role),
    );
    const client = data.clients.find((item) => item.id === conversation?.clientId);
    const ticket = data.tickets.find((item) => item.id === conversation?.ticketId);
    useEffect(() => {
        scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight, behavior: 'smooth' });
    }, [conversation?.messages.length, selected]);
    function editConversation(recipe: (item: Conversation) => Conversation, text: string) {
        if (conversation)
            update(
                (state) => ({
                    ...state,
                    conversations: state.conversations.map((item) =>
                        item.id === conversation.id ? recipe(item) : item,
                    ),
                }),
                text,
            );
    }
    function send(event: FormEvent) {
        event.preventDefault();
        if (!message.trim() || !conversation) return;
        const value: Message = {
            id: crypto.randomUUID(),
            body: message.trim(),
            time: '10.30',
            outgoing: true,
            status: 'Terkirim · demo',
        };
        editConversation(
            (item) => ({
                ...item,
                messages: [...item.messages, value],
                preview: value.body,
                state: 'Ditangani',
                unread: 0,
            }),
            'Pesan ditambahkan ke percakapan demo, tidak dikirim ke WhatsApp.',
        );
        setMessage('');
    }
    const info = conversation && (
        <>
            <div className="contact-info-header">
                <Avatar initials={conversation.initials} color={conversation.color} />
                <h3>{client?.name ?? conversation.phone}</h3>
                <p>{client?.pic ?? 'Kontak belum terdaftar'}</p>
                <Badge>{client ? 'Klien terdaftar' : 'Belum dikenal'}</Badge>
            </div>
            <div className="contact-info-section">
                <span className="eyebrow">INFORMASI KONTAK</span>
                <p>
                    <Phone size={13} />
                    {conversation.phone}
                </p>
                <p>
                    <Users size={13} />
                    PIC:{' '}
                    {conversation.assigned === 'manager'
                        ? 'Manajer'
                        : conversation.assigned === 'ra'
                          ? 'Regulatory Affairs'
                          : 'Rizal · Marketing'}
                </p>
            </div>
            <div className="contact-info-section">
                <span className="eyebrow">TIKET TERKAIT</span>
                {ticket ? (
                    <Link href={`/tickets/${ticket.id}`} preserveState className="linked-ticket">
                        <FileText size={16} />
                        <span>
                            <strong>{ticket.product}</strong>
                            <small>{ticket.id}</small>
                        </span>
                        <ArrowUpRight size={13} />
                    </Link>
                ) : (
                    <p>Belum ditautkan ke tiket.</p>
                )}
                <Button variant="secondary" icon={Link2} onClick={() => setLinkModal(true)}>
                    Tautkan ke tiket
                </Button>
            </div>
            <div className="contact-info-section">
                <Field label="Alihkan percakapan">
                    <Select
                        value={conversation.assigned}
                        onChange={(event) => {
                            const assigned = event.target.value as Conversation['assigned'];
                            editConversation((item) => ({ ...item, assigned }), 'Percakapan dialihkan pada demo.');
                            setInfoOpen(false);
                        }}
                    >
                        <option value="marketing">Marketing</option>
                        <option value="ra">Regulatory Affairs</option>
                        <option value="manager">Manajer</option>
                    </Select>
                </Field>
                <Button
                    variant={conversation.state === 'Selesai' ? 'secondary' : 'primary'}
                    icon={CheckCheck}
                    onClick={() =>
                        editConversation(
                            (item) => ({
                                ...item,
                                state: item.state === 'Selesai' ? 'Ditangani' : 'Selesai',
                                unread: 0,
                            }),
                            'Status percakapan diperbarui pada demo.',
                        )
                    }
                >
                    {conversation.state === 'Selesai' ? 'Buka kembali' : 'Tandai selesai'}
                </Button>
            </div>
            <div className="inbox-security-note">
                <ShieldCheck size={16} />
                <p>Riwayat percakapan ditautkan ke tiket. Batas respons awal: 2 jam kerja.</p>
            </div>
        </>
    );
    return (
        <>
            <PageHeader
                title="Kotak masuk WhatsApp"
                eyebrow="KOMUNIKASI TERPUSAT"
                description="Percakapan klien yang lebih teratur, tanpa kehilangan konteks."
            >
                <Badge tone="green">
                    <span className="live-dot" />
                    Nomor Marketing · simulasi
                </Badge>
                {role === 'marketing' && (
                    <LinkButton href="/compose" icon={Plus}>
                        Chat baru
                    </LinkButton>
                )}
            </PageHeader>
            <div className={`inbox-layout ${mobileChat ? 'show-mobile-chat' : ''}`}>
                <aside className="conversation-list">
                    <div className="conversation-list-head">
                        <div>
                            <h2>Pesan masuk</h2>
                            <Badge tone="neutral" dot={false}>
                                {visible.length}
                            </Badge>
                        </div>
                        <SearchInput
                            placeholder="Cari nama atau nomor..."
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                        <div className="conversation-filters">
                            {['Semua', 'Belum dibaca', 'Menunggu', 'Selesai'].map((item) => (
                                <button
                                    key={item}
                                    className={filter === item ? 'active' : ''}
                                    onClick={() => setFilter(item)}
                                >
                                    {item}
                                </button>
                            ))}
                        </div>
                    </div>
                    <div className="conversation-list-items">
                        {visible.map((item) => (
                            <button
                                key={item.id}
                                className={`conversation-item ${selected === item.id ? 'active' : ''}`}
                                onClick={() => {
                                    setSelected(item.id);
                                    setMobileChat(true);
                                }}
                            >
                                <Avatar initials={item.initials} color={item.color} />
                                <span>
                                    <span className="conversation-name">
                                        <strong>{item.name}</strong>
                                        <small>{item.time}</small>
                                    </span>
                                    <span className="conversation-preview">{item.preview}</span>
                                    <span className="conversation-state">
                                        <Badge dot={false}>{item.state}</Badge>
                                        {item.unread > 0 && <b>{item.unread}</b>}
                                    </span>
                                </span>
                            </button>
                        ))}
                        {!visible.length && (
                            <EmptyState
                                title="Tidak ada percakapan"
                                description="Percakapan yang ditugaskan ke role ini akan muncul di sini."
                                icon={MessageCircle}
                            />
                        )}
                    </div>
                    <div className="inbox-list-footer">
                        <ShieldCheck size={12} />
                        Workspace komunikasi perusahaan
                    </div>
                </aside>
                <section className="chat-area">
                    {conversation ? (
                        <>
                            <header className="chat-header">
                                <button
                                    className="icon-button mobile-chat-back"
                                    aria-label="Kembali ke percakapan"
                                    onClick={() => setMobileChat(false)}
                                >
                                    <ChevronLeft size={21} />
                                </button>
                                <Avatar initials={conversation.initials} color={conversation.color} />
                                <div>
                                    <strong>{conversation.name}</strong>
                                    <small>
                                        <span className="live-dot" />
                                        {conversation.phone}
                                    </small>
                                </div>
                                <button
                                    className="icon-button"
                                    aria-label="Info kontak percakapan"
                                    onClick={() => setInfoOpen(true)}
                                >
                                    <Users size={17} />
                                </button>
                                <button
                                    className="icon-button"
                                    aria-label="Opsi simulasi pesan"
                                    onClick={() => setIncoming(!incoming)}
                                >
                                    <MoreHorizontal size={20} />
                                </button>
                            </header>
                            {incoming && (
                                <div className="simulation-message">
                                    <span>Uji pesan masuk membuka kembali percakapan selesai.</span>
                                    <Button
                                        variant="secondary"
                                        onClick={() => {
                                            editConversation(
                                                (item) => ({
                                                    ...item,
                                                    messages: [
                                                        ...item.messages,
                                                        {
                                                            id: crypto.randomUUID(),
                                                            body: 'Halo Kak, apakah sudah ada pembaruan untuk produk saya?',
                                                            time: '10.31',
                                                            outgoing: false,
                                                        },
                                                    ],
                                                    state: 'Menunggu',
                                                    unread: item.unread + 1,
                                                }),
                                                'Pesan masuk disimulasikan.',
                                            );
                                            setIncoming(false);
                                        }}
                                    >
                                        Simulasikan pesan klien
                                    </Button>
                                </div>
                            )}
                            <div className="chat-messages" ref={scrollRef}>
                                <div className="chat-date">
                                    <span>Hari ini, 21 Oktober 2026</span>
                                </div>
                                {conversation.id === 'chat1' && (
                                    <div className="chat-context">
                                        <MessageCircle size={14} />
                                        <span>
                                            Diteruskan dari chatbot nomor layanan
                                            <small>Klien memilih Hubungi Marketing · revisi desain label</small>
                                        </span>
                                    </div>
                                )}
                                {conversation.messages.map((item) => (
                                    <div key={item.id} className={`message-row ${item.outgoing ? 'outgoing' : ''}`}>
                                        {!item.outgoing && (
                                            <Avatar initials={conversation.initials} color={conversation.color} small />
                                        )}
                                        <div className="message-bubble">
                                            {item.file ? (
                                                <>
                                                    <div className="message-file">
                                                        <span>
                                                            <FileText size={23} />
                                                        </span>
                                                        <div>
                                                            <strong>{item.file}</strong>
                                                            <small>Dokumen PDF · lampiran demo</small>
                                                        </div>
                                                    </div>
                                                    <div className="message-file-actions">
                                                        <Button
                                                            variant="secondary"
                                                            onClick={() => setFilePreview(item.file!)}
                                                        >
                                                            Pratinjau
                                                        </Button>
                                                        {ticket && (
                                                            <Button
                                                                variant="secondary"
                                                                onClick={() => {
                                                                    setConvert(item.file!);
                                                                    setTargetDocument('d7');
                                                                }}
                                                            >
                                                                Jadikan dokumen
                                                            </Button>
                                                        )}
                                                    </div>
                                                </>
                                            ) : (
                                                <p>{item.body}</p>
                                            )}
                                            <div className="message-meta">
                                                <span>{item.time}</span>
                                                {item.outgoing && <CheckCheck size={12} />}
                                                <span>{item.status}</span>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                            <div className="chat-composer">
                                <div className="quick-replies">
                                    {['Dokumen kami terima', 'Mohon unggah lewat tautan', 'Terima kasih, Kak'].map(
                                        (text) => (
                                            <button key={text} onClick={() => setMessage(text)}>
                                                {text}
                                            </button>
                                        ),
                                    )}
                                </div>
                                <form onSubmit={send}>
                                    <input
                                        ref={fileRef}
                                        type="file"
                                        className="sr-only"
                                        aria-label="Lampiran percakapan"
                                        onChange={(event) => {
                                            const file = event.target.files?.[0];
                                            if (file)
                                                editConversation(
                                                    (item) => ({
                                                        ...item,
                                                        messages: [
                                                            ...item.messages,
                                                            {
                                                                id: crypto.randomUUID(),
                                                                body: 'Lampiran',
                                                                file: file.name,
                                                                time: '10.30',
                                                                outgoing: true,
                                                                status: 'Simulasi',
                                                            },
                                                        ],
                                                    }),
                                                    'Lampiran ditambahkan ke demo; tidak diunggah ke server.',
                                                );
                                        }}
                                    />
                                    <button
                                        type="button"
                                        className="icon-button"
                                        aria-label="Tambah lampiran"
                                        onClick={() => fileRef.current?.click()}
                                    >
                                        <Paperclip size={19} />
                                    </button>
                                    <input
                                        aria-label="Balasan pesan"
                                        placeholder="Tulis pesan Anda..."
                                        value={message}
                                        onChange={(event) => setMessage(event.target.value)}
                                    />
                                    <Button
                                        type="submit"
                                        icon={Send}
                                        aria-label="Kirim pesan"
                                        disabled={!message.trim()}
                                    >
                                        Kirim
                                    </Button>
                                </form>
                                <p>
                                    <LockKeyholeSmall />
                                    Mode demo · pesan tidak dikirim ke gateway WhatsApp.
                                </p>
                            </div>
                        </>
                    ) : (
                        <EmptyState
                            title="Pilih percakapan"
                            description="Pesan dan konteks klien akan ditampilkan di sini."
                            icon={MessageCircle}
                        />
                    )}
                </section>
                <aside className="contact-info">{info}</aside>
            </div>
            {infoOpen && conversation && (
                <Modal title="Informasi percakapan" onClose={() => setInfoOpen(false)}>
                    {info}
                </Modal>
            )}
            {filePreview && (
                <Modal title="Pratinjau lampiran" wide onClose={() => setFilePreview(null)}>
                    <FilePreview title={filePreview} />
                </Modal>
            )}
            {convert && !convertDoc && ticket && (
                <Modal
                    title="Jadikan lampiran sebagai dokumen"
                    description={`${convert} · ${ticket.id}`}
                    onClose={() => setConvert(null)}
                >
                    <p className="section-note mb-4">
                        Pilih item checklist yang sesuai. Metadata diminta pada langkah berikutnya.
                    </p>
                    <div className="document-options">
                        {ticket.documents
                            .filter((doc) => !doc.locked)
                            .map((doc) => (
                                <label key={doc.id} className={targetDocument === doc.id ? 'selected' : ''}>
                                    <input
                                        type="radio"
                                        name="target-document"
                                        checked={targetDocument === doc.id}
                                        onChange={() => setTargetDocument(doc.id)}
                                    />
                                    <span>
                                        <strong>{doc.name}</strong>
                                        <small>{doc.status}</small>
                                    </span>
                                </label>
                            ))}
                    </div>
                    <div className="form-footer">
                        <Button variant="secondary" onClick={() => setConvert(null)}>
                            Batal
                        </Button>
                        <Button
                            onClick={() =>
                                setConvertDoc(ticket.documents.find((doc) => doc.id === targetDocument) ?? null)
                            }
                        >
                            Lengkapi metadata
                            <ArrowRight size={14} />
                        </Button>
                    </div>
                </Modal>
            )}
            {convertDoc && ticket && (
                <UploadDialog
                    ticket={ticket}
                    document={convertDoc}
                    attachment={convert ?? undefined}
                    source="chat"
                    onClose={() => {
                        setConvertDoc(null);
                        setConvert(null);
                    }}
                />
            )}
            {linkModal && conversation && (
                <Modal title="Tautkan percakapan ke tiket" onClose={() => setLinkModal(false)}>
                    <Field label="Tiket klien">
                        <Select value={linkTicket} onChange={(event) => setLinkTicket(event.target.value)}>
                            {data.tickets
                                .filter((item) => item.clientId === conversation.clientId)
                                .map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.product} · {item.id}
                                    </option>
                                ))}
                        </Select>
                    </Field>
                    <div className="form-footer">
                        <Button variant="secondary" onClick={() => setLinkModal(false)}>
                            Batal
                        </Button>
                        <Button
                            disabled={
                                !data.tickets.some(
                                    (item) => item.id === linkTicket && item.clientId === conversation.clientId,
                                )
                            }
                            onClick={() => {
                                editConversation(
                                    (item) => ({ ...item, ticketId: linkTicket }),
                                    'Percakapan ditautkan ke tiket demo.',
                                );
                                setLinkModal(false);
                            }}
                        >
                            Tautkan
                        </Button>
                    </div>
                </Modal>
            )}
        </>
    );
}
function LockKeyholeSmall() {
    return <ShieldCheck size={11} />;
}

export function ContactsPage() {
    const { data } = usePreview();
    const [search, setSearch] = useState('');
    const [filter, setFilter] = useState('');
    const [selected, setSelected] = useState(data.clients[0].id);
    const [newClient, setNewClient] = useState(false);
    const contacts = [
        ...data.clients.map((item) => ({
            id: item.id,
            name: item.name,
            phone: item.phone,
            label: 'Klien',
            initials: item.initials,
            color: item.color,
        })),
        ...data.prospects
            .filter((item) => item.status !== 'Dijadikan tiket')
            .map((item) => ({
                id: item.id,
                name: item.name,
                phone: item.phone,
                label: 'Prospek',
                initials: item.name.slice(0, 2).toUpperCase(),
                color: 'sand',
            })),
    ].filter(
        (item) =>
            (!filter || item.label === filter) &&
            `${item.name} ${item.phone}`.toLowerCase().includes(search.toLowerCase()),
    );
    const contact = contacts.find((item) => item.id === selected) ?? contacts[0];
    return (
        <>
            <PageHeader
                title="Kontak WhatsApp"
                eyebrow="KOMUNIKASI KLIEN"
                description="Kontak berasal dari data klien, prospek, dan percakapan perusahaan."
            >
                <LinkButton href="/compose" icon={Plus}>
                    Chat baru
                </LinkButton>
            </PageHeader>
            <div className="filter-bar">
                <div className="filter-group">
                    <SearchInput
                        placeholder="Cari nama atau nomor..."
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                    <Select value={filter} onChange={(event) => setFilter(event.target.value)}>
                        <option value="">Semua label</option>
                        <option>Klien</option>
                        <option>Prospek</option>
                    </Select>
                </div>
            </div>
            <div className="two-column contacts-grid">
                <Panel>
                    <div className="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Kontak</th>
                                    <th>Nomor WhatsApp</th>
                                    <th>Label</th>
                                    <th />
                                </tr>
                            </thead>
                            <tbody>
                                {contacts.map((item) => (
                                    <tr
                                        className={selected === item.id ? 'selected-row' : ''}
                                        key={item.id}
                                        onClick={() => setSelected(item.id)}
                                    >
                                        <td>
                                            <span className="client-cell">
                                                <Avatar initials={item.initials} color={item.color} />
                                                <strong>{item.name}</strong>
                                            </span>
                                        </td>
                                        <td>{item.phone}</td>
                                        <td>
                                            <Badge>{item.label}</Badge>
                                        </td>
                                        <td>
                                            <Link
                                                href={
                                                    data.conversations.find(
                                                        (chat) =>
                                                            chat.clientId === item.id && chat.assigned === 'marketing',
                                                    )
                                                        ? `/inbox?chat=${data.conversations.find((chat) => chat.clientId === item.id && chat.assigned === 'marketing')!.id}`
                                                        : `/compose?client=${item.id}`
                                                }
                                                preserveState
                                                className="icon-button"
                                                aria-label={`Chat ${item.name}`}
                                            >
                                                <MessageCircle size={17} />
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Panel>
                {contact && (
                    <Panel title="Detail kontak">
                        <div className="panel-body">
                            <div className="contact-info-header">
                                <Avatar initials={contact.initials} color={contact.color} />
                                <h3>{contact.name}</h3>
                                <p>{contact.phone}</p>
                                <Badge>{contact.label}</Badge>
                            </div>
                            <div className="space-y-3 mt-5">
                                <LinkButton href={`/compose?client=${contact.id}`} secondary icon={MessageCircle}>
                                    Mulai percakapan
                                </LinkButton>
                                {contact.label === 'Prospek' && (
                                    <Button variant="secondary" icon={Users} onClick={() => setNewClient(true)}>
                                        Jadikan klien
                                    </Button>
                                )}
                            </div>
                            <p className="section-note mt-5">
                                Tidak ada sinkronisasi buku kontak ponsel pada prototipe ini.
                            </p>
                        </div>
                    </Panel>
                )}
            </div>
            {newClient && (
                <ClientDialog
                    onClose={() => setNewClient(false)}
                    onSaved={(id) => {
                        setSelected(id);
                        setNewClient(false);
                    }}
                />
            )}
        </>
    );
}

const defaultPresets: MasterItem[] = [
    {
        id: 'msg1',
        name: 'Pengingat dokumen kurang',
        detail: 'Halo Kak {nama_klien}, dokumen {nama_dokumen} untuk {kode_tiket} masih perlu dilengkapi. Terima kasih.',
        kind: 'Preset internal',
    },
    {
        id: 'msg2',
        name: 'Konfirmasi sampel',
        detail: 'Halo Kak {nama_klien}, sampel produk {nama_produk} sudah siap diperiksa. Mohon kabari tim kami setelah dicoba.',
        kind: 'Preset internal',
    },
    {
        id: 'msg3',
        name: 'Tindak lanjut prospek',
        detail: 'Terima kasih atas minatnya, Kak {nama_klien}. Tim Marketing siap membantu rencana pendaftaran produk Anda.',
        kind: 'Preset internal',
    },
];
export function ComposePage() {
    const { data, update } = usePreview();
    const presets = data.masters.messageTemplates ?? defaultPresets;
    const requestedClient = new URLSearchParams(window.location.search).get('client');
    const initialClientId = requestedClient ?? data.clients[0]?.id ?? '';
    const [clientId, setClientId] = useState(initialClientId);
    const [presetId, setPresetId] = useState(presets[0].id);
    const [ticketId, setTicketId] = useState(data.tickets.find((item) => item.clientId === initialClientId)?.id ?? '');
    const [edit, setEdit] = useState<MasterItem | null>(null);
    const [name, setName] = useState('');
    const [text, setText] = useState('');
    const client = data.clients.find((item) => item.id === clientId);
    const preset = presets.find((item) => item.id === presetId) ?? presets[0];
    const ticket = data.tickets.find((item) => item.id === ticketId && item.clientId === clientId);
    const body = preset.detail
        .replaceAll('{nama_klien}', client?.pic ?? '')
        .replaceAll('{nama_produk}', ticket?.product ?? 'Anda')
        .replaceAll('{kode_tiket}', ticket?.id ?? 'pendaftaran Anda')
        .replaceAll('{nama_dokumen}', 'spesifikasi kemasan');
    function send() {
        if (!client) return;
        const existing = data.conversations.find((item) => item.clientId === clientId && item.assigned === 'marketing');
        const message: Message = {
            id: crypto.randomUUID(),
            body,
            time: '10.30',
            outgoing: true,
            status: 'Terkirim · demo',
        };
        const conversation: Conversation = existing
            ? { ...existing, messages: [...existing.messages, message], preview: body, state: 'Ditangani' }
            : {
                  id: crypto.randomUUID(),
                  clientId,
                  name: `${client.pic} · ${client.name}`,
                  phone: client.phone,
                  initials: client.initials,
                  color: client.color,
                  preview: body,
                  time: '10.30',
                  unread: 0,
                  state: 'Ditangani',
                  assigned: 'marketing',
                  ticketId: ticket?.id ?? null,
                  messages: [message],
              };
        if (
            update(
                (state) => ({
                    ...state,
                    conversations: existing
                        ? state.conversations.map((item) => (item.id === conversation.id ? conversation : item))
                        : [conversation, ...state.conversations],
                }),
                'Pesan ditambahkan ke inbox demo, bukan dikirim ke WhatsApp.',
                ticket?.id ?? conversation.id,
            )
        )
            router.visit(`/inbox?chat=${conversation.id}`, { preserveState: true });
    }
    if (!client)
        return (
            <>
                <PageHeader
                    title="Penerima belum tersedia"
                    description="Kontak ini belum menjadi klien atau tidak ditugaskan kepada Anda."
                />
                <Panel>
                    <EmptyState
                        title="Pesan tidak dikirim"
                        description="Daftarkan prospek sebagai klien atau pilih kontak yang sesuai. Tidak ada penerima pengganti otomatis."
                        icon={Users}
                    >
                        <LinkButton href="/contacts" secondary>
                            Kembali ke kontak
                        </LinkButton>
                    </EmptyState>
                </Panel>
            </>
        );
    return (
        <>
            <PageHeader
                title="Chat baru"
                eyebrow="PESAN MARKETING"
                description="Gunakan preset untuk menulis pesan yang jelas dan konsisten."
            />
            <div className="two-column">
                <Panel title="Mulai percakapan">
                    <div className="panel-body form-stack">
                        <Field label="Kontak klien">
                            <Select
                                value={clientId}
                                onChange={(event) => {
                                    setClientId(event.target.value);
                                    setTicketId(
                                        data.tickets.find((item) => item.clientId === event.target.value)?.id ?? '',
                                    );
                                }}
                            >
                                {data.clients.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.name} · {item.phone}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label="Preset pesan">
                            <Select value={presetId} onChange={(event) => setPresetId(event.target.value)}>
                                {presets.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.name}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label="Tiket terkait">
                            <Select value={ticketId} onChange={(event) => setTicketId(event.target.value)}>
                                {data.tickets
                                    .filter((item) => item.clientId === clientId)
                                    .map((item) => (
                                        <option key={item.id} value={item.id}>
                                            {item.product} · {item.id}
                                        </option>
                                    ))}
                            </Select>
                        </Field>
                        <Field label="Pratinjau pesan">
                            <div className="compose-preview">{body}</div>
                        </Field>
                        <InfoBox>
                            Preset ini adalah teks internal Marketing, bukan template Cloud API yang telah disetujui
                            Meta. Aturan jendela 24 jam diterapkan saat integrasi resmi diaktifkan.
                        </InfoBox>
                        <div className="form-footer">
                            <LinkButton href="/inbox" secondary>
                                Batal
                            </LinkButton>
                            <Button icon={Send} onClick={send}>
                                Simulasikan kirim pesan
                            </Button>
                        </div>
                    </div>
                </Panel>
                <Panel title="Preset pesan internal" description="Dapat digunakan ulang untuk komunikasi petugas.">
                    <div className="preset-list">
                        {presets.map((item) => (
                            <div key={item.id}>
                                <span className="file-icon">
                                    <MessageCircle size={17} />
                                </span>
                                <div>
                                    <strong>{item.name}</strong>
                                    <p>{item.detail}</p>
                                    <Badge tone="neutral" dot={false}>
                                        Preset internal
                                    </Badge>
                                </div>
                                <Button
                                    variant="ghost"
                                    onClick={() => {
                                        setEdit(item);
                                        setName(item.name);
                                        setText(item.detail);
                                    }}
                                >
                                    Ubah
                                </Button>
                            </div>
                        ))}
                    </div>
                    <div className="panel-body">
                        <Button
                            variant="secondary"
                            icon={Plus}
                            onClick={() => {
                                setEdit({ id: '', name: '', detail: '' });
                                setName('');
                                setText('');
                            }}
                        >
                            Tambah preset
                        </Button>
                    </div>
                </Panel>
            </div>
            {edit && (
                <Modal title={edit.id ? 'Ubah preset pesan' : 'Tambah preset pesan'} onClose={() => setEdit(null)}>
                    <div className="form-stack">
                        <Field label="Nama preset">
                            <input value={name} onChange={(event) => setName(event.target.value)} />
                        </Field>
                        <Field label="Teks pesan">
                            <textarea value={text} onChange={(event) => setText(event.target.value)} />
                        </Field>
                        <InfoBox>
                            Variabel: {'{nama_klien}'}, {'{nama_produk}'}, {'{kode_tiket}'}, {'{nama_dokumen}'}.
                        </InfoBox>
                    </div>
                    <div className="form-footer">
                        <Button variant="secondary" onClick={() => setEdit(null)}>
                            Batal
                        </Button>
                        <Button
                            disabled={!name.trim() || !text.trim()}
                            onClick={() => {
                                const value = {
                                    id: edit.id || crypto.randomUUID(),
                                    name,
                                    detail: text,
                                    kind: 'Preset internal',
                                };
                                update(
                                    (state) => ({
                                        ...state,
                                        masters: {
                                            ...state.masters,
                                            messageTemplates: edit.id
                                                ? presets.map((item) => (item.id === edit.id ? value : item))
                                                : [...presets, value],
                                        },
                                    }),
                                    'Preset pesan disimpan pada demo.',
                                );
                                setEdit(null);
                            }}
                        >
                            Simpan preset
                        </Button>
                    </div>
                </Modal>
            )}
        </>
    );
}

export function ChatbotPage() {
    const { data, update } = usePreview();
    const [clientId, setClientId] = useState('c1');
    const [input, setInput] = useState('');
    const [collecting, setCollecting] = useState(false);
    const [messages, setMessages] = useState<Array<{ body: string; out: boolean }>>([
        {
            body: 'Selamat datang di layanan PT Aisha Zahra Herbalaboratories.\n\n1. Ajukan produk baru\n2. Cek status\n3. Cek tagihan\n4. Hubungi Marketing',
            out: false,
        },
    ]);
    function submit(value: string) {
        const text = value.trim();
        if (!text) return;
        const upper = text.toUpperCase();
        let reply = '';
        if (upper.startsWith('STATUS') || upper === '2') {
            const id = text.split(' ')[1];
            const tickets = data.tickets.filter(
                (item) => item.clientId === clientId && !item.closed && (!id || item.id === id),
            );
            reply = tickets.length
                ? tickets
                      .map(
                          (ticket) =>
                              `${ticket.id} · ${ticket.product}\nTahap: ${ticket.stage}\nProgres dokumen: ${ticketProgress(ticket)}%\nKurang: ${
                                  ticket.documents
                                      .filter((doc) => doc.status !== 'Disetujui')
                                      .map((doc) => doc.name)
                                      .join(', ') || 'tidak ada'
                              }`,
                      )
                      .join('\n\n')
                : 'Data tiket tidak dapat ditampilkan untuk nomor ini. Hubungi Marketing untuk verifikasi.';
        } else if (upper.startsWith('TAUTAN'))
            reply =
                clientId === 'unknown'
                    ? 'Kami perlu memverifikasi nomor dan kepemilikan tiket Anda melalui Marketing.'
                    : 'Permintaan tautan baru diteruskan ke Marketing. Tautan hanya dikirim setelah kepemilikan tiket diverifikasi.';
        else if (upper === '1') {
            reply =
                'Mohon kirim: nama; nomor kontak; gambaran produk.\nContoh: Nadia; 081288000001; minuman herbal jahe.';
            setCollecting(true);
        } else if (collecting) {
            const parts = text.split(';').map((part) => part.trim());
            if (parts.length < 3 || !parts.every(Boolean))
                reply = 'Mohon lengkapi tiga bagian: nama; nomor kontak; gambaran produk.';
            else {
                const id = `PROS-${String(43 + data.prospects.length).padStart(4, '0')}`;
                update(
                    (state) => ({
                        ...state,
                        prospects: [
                            {
                                id,
                                name: parts[0],
                                phone: parts[1],
                                product: parts[2],
                                time: '21/10 · 10.30',
                                status: 'Baru',
                            },
                            ...state.prospects,
                        ],
                    }),
                    'Prospek ditambahkan ke data demo.',
                );
                reply = `Terima kasih. Pengajuan tercatat dengan kode ${id} dan diteruskan ke Marketing.`;
                setCollecting(false);
            }
        } else if (upper === '3' || upper.includes('TAGIHAN')) {
            const invoices = data.invoices.filter((item) => item.clientId === clientId && item.status !== 'Lunas');
            reply = invoices.length
                ? invoices
                      .map(
                          (invoice) =>
                              `${invoice.id} · ${invoice.term}\n${currency(invoice.amount)} · jatuh tempo ${invoice.dueDate}`,
                      )
                      .join('\n\n') +
                  '\n\nBukti pembayaran harus ditautkan ke tagihan yang dipilih melalui portal klien.'
                : clientId === 'unknown'
                  ? 'Informasi tagihan tidak tersedia untuk nomor yang belum diverifikasi.'
                  : 'Tidak ada tagihan belum lunas untuk nomor Anda pada data demo.';
        } else if (upper === '4')
            reply =
                'Silakan lanjutkan dengan tim Marketing. Ringkasan kebutuhan Anda diteruskan ke inbox Marketing pada sistem nyata. Simulasi ini tidak mengirim pesan WhatsApp.';
        else
            reply =
                'Maaf, saya belum memahami pesan ini. Balas 1–4, ketik STATUS, atau pilih Hubungi Marketing untuk bantuan petugas.';
        setMessages([...messages, { body: text, out: true }, { body: reply, out: false }]);
        setInput('');
    }
    return (
        <div className="chatbot-demo">
            <div className="chatbot-explanation">
                <Link href="/" className="back-link">
                    <ArrowLeft size={14} />
                    Kembali ke workspace
                </Link>
                <span className="eyebrow">SIMULASI NOMOR LAYANAN</span>
                <h1>
                    Percakapan yang membantu,
                    <br />
                    bukan menambah antrean.
                </h1>
                <p>
                    Jelajahi alur chatbot dari wireframe 37 dan 46. Ini demonstrasi perilaku, bukan koneksi WhatsApp
                    nyata.
                </p>
                <Field label="Simulasikan nomor milik">
                    <Select value={clientId} onChange={(event) => setClientId(event.target.value)}>
                        {data.clients.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.pic} · {item.name}
                            </option>
                        ))}
                        <option value="unknown">Nomor belum terdaftar</option>
                    </Select>
                </Field>
                <div className="chatbot-scenarios">
                    {['STATUS', 'STATUS PRD-2026-0012', '1', '3', '4', 'TAUTAN PRD-2026-0012'].map((item) => (
                        <button key={item} onClick={() => submit(item)}>
                            {item}
                            <ArrowRight size={13} />
                        </button>
                    ))}
                </div>
                <InfoBox>
                    Nomor yang tidak berhak tidak menerima informasi tiket atau tagihan. Token asli, webhook, dan
                    pengiriman berkas membutuhkan backend.
                </InfoBox>
            </div>
            <section className="chatbot-phone">
                <header>
                    <Avatar initials="AZ" color="mint" />
                    <span>
                        <strong>Layanan PT Aisha Zahra</strong>
                        <small>Nomor layanan · chatbot demo</small>
                    </span>
                    <ShieldCheck size={19} />
                </header>
                <div className="chatbot-messages">
                    {messages.map((message, index) => (
                        <div key={index} className={`bot-message ${message.out ? 'outgoing' : ''}`}>
                            <p>{message.body}</p>
                            <small>10.30{message.out && <CheckCheck size={11} />}</small>
                        </div>
                    ))}
                </div>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit(input);
                    }}
                >
                    <input
                        value={input}
                        onChange={(event) => setInput(event.target.value)}
                        placeholder="Ketik pesan..."
                        aria-label="Pesan chatbot"
                    />
                    <Button type="submit" aria-label="Kirim ke chatbot" disabled={!input.trim()} icon={Send} />
                </form>
            </section>
        </div>
    );
}
