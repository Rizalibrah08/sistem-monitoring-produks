import { Link, router } from '../lib/preview-navigation';
import {
    Activity,
    ArrowUpRight,
    Bell,
    BookOpen,
    ChevronDown,
    ChevronRight,
    CircleHelp,
    ClipboardCheck,
    Clock3,
    FileCheck2,
    FileClock,
    FileText,
    FlaskConical,
    HandCoins,
    HeartPulse,
    Home,
    LayoutDashboard,
    LogOut,
    Menu,
    MessageCircle,
    Package,
    Plus,
    Search,
    Settings2,
    ShieldCheck,
    Sparkles,
    Sprout,
    Ticket,
    Users,
    Wallet,
    X,
    type LucideIcon,
} from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { usePreview } from '../mocks/preview-provider';
import { roleLabels, type Role } from '../types';
import { Avatar, Badge, Brand, Button, EmptyState, Modal, SearchInput } from '../components/ui';

type Navigation = { label: string; href: string; icon: LucideIcon; roles: Role[]; count?: string; group: string };
const everyone: Role[] = ['manager', 'marketing', 'rnd', 'qa', 'ra', 'finance', 'admin'];
const navigation: Navigation[] = [
    { label: 'Dasbor', href: '/', icon: LayoutDashboard, roles: everyone, group: 'Ruang kerja' },
    {
        label: 'Antrean prioritas',
        href: '/priorities',
        icon: Activity,
        roles: ['manager', 'ra', 'marketing', 'rnd', 'qa'],
        group: 'Ruang kerja',
    },
    {
        label: 'Daftar tiket',
        href: '/tickets',
        icon: Ticket,
        roles: ['manager', 'marketing', 'rnd', 'qa', 'ra'],
        group: 'Ruang kerja',
    },
    { label: 'Tugas dokumen', href: '/tasks', icon: ClipboardCheck, roles: ['rnd', 'qa', 'ra'], group: 'Ruang kerja' },
    { label: 'Prospek', href: '/prospects', icon: Sprout, roles: ['marketing'], count: '3', group: 'Operasional' },
    { label: 'Kontrak', href: '/contracts', icon: FileText, roles: ['marketing'], group: 'Operasional' },
    { label: 'Data klien', href: '/clients', icon: Users, roles: ['marketing'], group: 'Operasional' },
    { label: 'Verifikasi dokumen', href: '/verification', icon: FileCheck2, roles: ['ra'], group: 'Operasional' },
    { label: 'Verifikasi mutu', href: '/qa-review', icon: ShieldCheck, roles: ['qa'], group: 'Operasional' },
    { label: 'Dokumen bersama', href: '/shared-documents', icon: FileText, roles: ['qa', 'ra'], group: 'Operasional' },
    { label: 'Bahan baku tiket', href: '/ingredients', icon: FlaskConical, roles: ['rnd'], group: 'Operasional' },
    { label: 'Pengajuan BPOM', href: '/bpom', icon: Package, roles: ['ra'], group: 'Operasional' },
    { label: 'Tagihan', href: '/invoices', icon: FileClock, roles: ['finance'], group: 'Operasional' },
    { label: 'Verifikasi pembayaran', href: '/payments', icon: Wallet, roles: ['finance'], group: 'Operasional' },
    { label: 'Laporan piutang', href: '/receivables', icon: HandCoins, roles: ['finance'], group: 'Operasional' },
    {
        label: 'Kotak masuk',
        href: '/inbox',
        icon: MessageCircle,
        roles: ['marketing', 'manager', 'ra'],
        count: '3',
        group: 'Komunikasi',
    },
    { label: 'Kontak WhatsApp', href: '/contacts', icon: Users, roles: ['marketing'], group: 'Komunikasi' },
    { label: 'Laporan durasi', href: '/reports', icon: Clock3, roles: ['manager'], group: 'Pemantauan' },
    { label: 'Eskalasi', href: '/escalations', icon: Bell, roles: ['manager'], count: '3', group: 'Pemantauan' },
    { label: 'Pengguna dan role', href: '/users', icon: Users, roles: ['admin'], group: 'Administrasi' },
    { label: 'Master data', href: '/master', icon: Settings2, roles: ['admin'], group: 'Administrasi' },
    { label: 'Templat dokumen', href: '/templates', icon: FileText, roles: ['admin'], group: 'Administrasi' },
    { label: 'Log audit', href: '/audit', icon: ShieldCheck, roles: ['admin', 'manager'], group: 'Administrasi' },
];
const names: Record<Role, string> = {
    manager: 'Aditya Saputra',
    marketing: 'Rizal Pratama',
    rnd: 'Dina Maharani',
    qa: 'Farah Putri',
    ra: 'Aditya Saputra',
    finance: 'Sinta Dewi',
    admin: 'Admin Aisha',
};

export function PreviewStaffLayout({ children, screen }: { children: ReactNode; screen: string }) {
    const { data, role, setRole, update, toast, notify } = usePreview();
    const [mobileOpen, setMobileOpen] = useState(false);
    const [notifications, setNotifications] = useState(false);
    const [help, setHelp] = useState(false);
    const [search, setSearch] = useState('');
    const searchRef = useRef<HTMLInputElement>(null);
    const items = navigation.filter((item) => item.roles.includes(role));
    const groups = [...new Set(items.map((item) => item.group))];
    const matchingTickets = search.trim()
        ? data.tickets
              .filter((ticket) =>
                  `${ticket.id} ${ticket.product} ${data.clients.find((client) => client.id === ticket.clientId)?.name}`
                      .toLowerCase()
                      .includes(search.toLowerCase()),
              )
              .slice(0, 5)
        : [];
    const roleNotifications = data.notifications.filter((item) => item.role.includes(role));
    const unread = roleNotifications.filter((item) => !item.read).length;
    const currentLabel = screen.startsWith('ticket')
        ? 'Daftar tiket'
        : (navigation.find((item) => item.href.slice(1) === screen)?.label ??
          (screen === 'dashboard' ? 'Dasbor' : 'Ruang kerja'));
    useEffect(() => {
        function keydown(event: KeyboardEvent) {
            if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
                event.preventDefault();
                searchRef.current?.focus();
            }
            if (event.key === 'Escape') {
                setNotifications(false);
                setSearch('');
                setMobileOpen(false);
            }
        }
        document.addEventListener('keydown', keydown);
        return () => document.removeEventListener('keydown', keydown);
    }, []);
    function changeRole(next: Role) {
        setRole(next);
        setMobileOpen(false);
        router.visit('/', { preserveState: true });
    }
    return (
        <div className="app-shell">
            {mobileOpen && (
                <button className="sidebar-scrim" aria-label="Tutup navigasi" onClick={() => setMobileOpen(false)} />
            )}
            <aside className={`sidebar ${mobileOpen ? 'sidebar-open' : ''}`}>
                <Link href="/" className="brand-link" aria-label="Aisha Monitor beranda">
                    <Brand />
                </Link>
                <div className="workspace-selector">
                    <span className="workspace-icon">
                        <HeartPulse size={17} />
                    </span>
                    <div>
                        <strong>PT Aisha Zahra</strong>
                        <small>Herbalaboratories</small>
                    </div>
                    <ChevronDown size={14} />
                </div>
                <nav aria-label="Navigasi utama" className="sidebar-nav">
                    {groups.map((group) => (
                        <div className="nav-group" key={group}>
                            <div className="nav-group-label">{group}</div>
                            {items
                                .filter((item) => item.group === group)
                                .map(({ icon: Icon, ...item }) => {
                                    const active =
                                        item.href === '/'
                                            ? screen === 'dashboard'
                                            : screen.startsWith(item.href.slice(1)) ||
                                              (item.href === '/tickets' && screen.startsWith('ticket'));
                                    return (
                                        <Link
                                            key={item.href}
                                            href={item.href}
                                            preserveState
                                            onClick={() => setMobileOpen(false)}
                                            className={`nav-link ${active ? 'nav-active' : ''}`}
                                        >
                                            <Icon size={18} strokeWidth={1.7} />
                                            <span>{item.label}</span>
                                            {item.count && (
                                                <span
                                                    className={`nav-count ${item.label === 'Eskalasi' ? 'count-warning' : ''}`}
                                                >
                                                    {item.count}
                                                </span>
                                            )}
                                        </Link>
                                    );
                                })}
                        </div>
                    ))}
                </nav>
                <div className="sidebar-bottom">
                    <div className="help-card">
                        <span className="help-card-icon">
                            <Sprout size={22} />
                        </span>
                        <strong>Tumbuh lebih teratur.</strong>
                        <p>Setiap proses, kini dalam satu ruang kerja.</p>
                        <button onClick={() => setHelp(true)}>
                            Panduan penggunaan
                            <ArrowUpRight size={14} />
                        </button>
                        <LeafDecor />
                    </div>
                    <button className="sidebar-help" onClick={() => setHelp(true)}>
                        <CircleHelp size={17} />
                        Bantuan & panduan
                        <ArrowUpRight size={14} />
                    </button>
                    <div className="sidebar-user">
                        <Avatar
                            initials={names[role]
                                .split(' ')
                                .map((name) => name[0])
                                .join('')
                                .slice(0, 2)}
                        />
                        <div>
                            <strong>{names[role]}</strong>
                            <small>{roleLabels[role]}</small>
                        </div>
                        <Link href="/login" aria-label="Keluar dari demo" className="icon-button">
                            <LogOut size={17} />
                        </Link>
                    </div>
                </div>
            </aside>
            <div className="workspace-main">
                <header className="topbar">
                    <div className="topbar-left">
                        <button
                            className="icon-button mobile-menu"
                            aria-label="Buka navigasi"
                            onClick={() => setMobileOpen(true)}
                        >
                            <Menu size={21} />
                        </button>
                        <div className="breadcrumb">
                            <Home size={16} />
                            <ChevronRight size={13} />
                            <span>{currentLabel}</span>
                        </div>
                    </div>
                    <div className="global-search">
                        <Search size={17} />
                        <input
                            ref={searchRef}
                            aria-label="Pencarian global"
                            placeholder="Cari tiket, produk, atau klien..."
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                        <kbd>⌘ K</kbd>
                        {search && (
                            <div className="global-results">
                                {matchingTickets.length ? (
                                    matchingTickets.map((ticket) => (
                                        <Link
                                            preserveState
                                            href={`/tickets/${ticket.id}`}
                                            key={ticket.id}
                                            onClick={() => setSearch('')}
                                        >
                                            <Ticket size={17} />
                                            <span>
                                                <strong>{ticket.product}</strong>
                                                <small>{ticket.id}</small>
                                            </span>
                                            <ChevronRight size={14} />
                                        </Link>
                                    ))
                                ) : (
                                    <EmptyState
                                        title="Tidak ditemukan"
                                        description="Coba nama produk atau kode tiket lain."
                                    />
                                )}
                            </div>
                        )}
                    </div>
                    <div className="topbar-actions">
                        <span className="preview-pill">
                            <span />
                            Pratinjau
                        </span>
                        <div className="notification-anchor">
                            <button
                                className={`icon-button notification-trigger ${notifications ? 'selected' : ''}`}
                                aria-label="Buka notifikasi"
                                onClick={() => setNotifications(!notifications)}
                            >
                                <Bell size={19} />
                                {unread > 0 && <span className="notification-dot" />}
                            </button>
                            {notifications && (
                                <div className="notification-panel">
                                    <header>
                                        <h3>
                                            Notifikasi <span>{unread}</span>
                                        </h3>
                                        <button
                                            onClick={() =>
                                                update(
                                                    (state) => ({
                                                        ...state,
                                                        notifications: state.notifications.map((item) =>
                                                            item.role.includes(role) ? { ...item, read: true } : item,
                                                        ),
                                                    }),
                                                    'Notifikasi ditandai dibaca.',
                                                )
                                            }
                                        >
                                            Tandai dibaca
                                        </button>
                                    </header>
                                    <div>
                                        {roleNotifications.length ? (
                                            roleNotifications.map((item) => (
                                                <Link
                                                    href={item.href}
                                                    preserveState
                                                    key={item.id}
                                                    onClick={() => setNotifications(false)}
                                                >
                                                    <span
                                                        className={`notice-icon icon-${item.kind === 'warning' ? 'amber' : 'green'}`}
                                                    >
                                                        {item.kind === 'chat' ? (
                                                            <MessageCircle size={18} />
                                                        ) : item.kind === 'payment' ? (
                                                            <Wallet size={18} />
                                                        ) : (
                                                            <FileCheck2 size={18} />
                                                        )}
                                                    </span>
                                                    <span>
                                                        <strong>{item.title}</strong>
                                                        <p>{item.body}</p>
                                                        <small>{item.time}</small>
                                                    </span>
                                                    {!item.read && <i />}
                                                </Link>
                                            ))
                                        ) : (
                                            <EmptyState
                                                title="Semua sudah beres"
                                                description="Belum ada notifikasi untuk role ini."
                                            />
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>
                        <span className="topbar-divider" />
                        <div className="role-select">
                            <Avatar
                                initials={names[role]
                                    .split(' ')
                                    .map((name) => name[0])
                                    .join('')
                                    .slice(0, 2)}
                                small
                            />
                            <select
                                value={role}
                                onChange={(event) => changeRole(event.target.value as Role)}
                                aria-label="Pratinjau role"
                            >
                                {Object.entries(roleLabels).map(([key, label]) => (
                                    <option value={key} key={key}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                            <ChevronDown size={13} />
                        </div>
                    </div>
                </header>
                <main className={`main-content ${screen === 'inbox' ? 'content-inbox' : ''}`}>{children}</main>
                <footer className="workspace-footer">
                    <span>© 2026 PT Aisha Zahra Herbalaboratories</span>
                    <span>
                        <span className="live-dot" />
                        Data demo · perubahan tidak disimpan permanen
                    </span>
                </footer>
            </div>
            {toast && (
                <div role="status" className={`toast ${toast.error ? 'toast-error' : ''}`}>
                    <span>{toast.error ? <X size={18} /> : <CheckToast />}</span>
                    {toast.message}
                </div>
            )}
            {help && (
                <Modal
                    title="Selamat datang di Aisha Monitor"
                    description="Pratinjau interaktif sistem monitoring pendaftaran produk."
                    onClose={() => setHelp(false)}
                >
                    <div className="modal-body space-y-4">
                        <p>
                            Pilih role di kanan atas untuk menjelajahi ruang kerja setiap tim. Data, pembayaran,
                            dokumen, dan pesan di aplikasi ini adalah simulasi.
                        </p>
                        <div className="help-steps">
                            <span>1</span>
                            <div>
                                <strong>Mulai dari tiket</strong>
                                <p>Buat produk, catat sampel, dan siapkan kontraknya.</p>
                            </div>
                            <span>2</span>
                            <div>
                                <strong>Lengkapi dan verifikasi</strong>
                                <p>Unggah dokumen, tinjau versi, dan catat pengajuan BPOM.</p>
                            </div>
                            <span>3</span>
                            <div>
                                <strong>Pantau sampai selesai</strong>
                                <p>Ikuti prioritas, SLA, percakapan, dan pelunasan.</p>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Link href="/portal/demo-token" className="btn btn-secondary">
                                Lihat portal klien
                                <ArrowUpRight size={14} />
                            </Link>
                            <Link href="/chatbot" className="btn btn-secondary">
                                Simulasi chatbot
                                <MessageCircle size={14} />
                            </Link>
                        </div>
                    </div>
                </Modal>
            )}
        </div>
    );
}
function LeafDecor() {
    return <Sprout className="help-card-decor" size={100} strokeWidth={0.8} />;
}
function CheckToast() {
    return <ShieldCheck size={18} />;
}
