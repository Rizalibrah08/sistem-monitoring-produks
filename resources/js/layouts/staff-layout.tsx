import { Head, Link, usePage } from '@inertiajs/react';
import {
    Activity, ArrowUpRight, Bell, ChevronRight, CircleHelp, ClipboardCheck, Clock3,
    FileCheck2, FileClock, FileText, FlaskConical, HandCoins, HeartPulse, Home,
    LayoutDashboard, LogOut, Menu, MessageCircle, Package, Search, Settings2,
    ShieldCheck, Sprout, Ticket, Users, Wallet, type LucideIcon,
} from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import type { BackendSharedProps } from '../backend-types';
import { Avatar, Badge, Brand } from '../components/ui';

const icons: Record<string, LucideIcon> = {
    dashboard: LayoutDashboard, activity: Activity, ticket: Ticket, clipboard: ClipboardCheck,
    users: Users, sprout: Sprout, file: FileText, 'file-check': FileCheck2, shield: ShieldCheck,
    flask: FlaskConical, package: Package, 'file-clock': FileClock, wallet: Wallet, coins: HandCoins,
    message: MessageCircle, clock: Clock3, bell: Bell, settings: Settings2,
};

export function StaffLayout({ title, children }: { title: string; children: ReactNode }) {
    const page = usePage<BackendSharedProps>();
    const { auth, navigation, routes, flash, workspace } = page.props;
    const unread = workspace?.notifications_unread ?? 0;
    const currentPath = page.url.split('?')[0];
    const [mobileOpen, setMobileOpen] = useState(false);
    const groups = [...new Set(navigation.map((item) => item.group ?? 'Ruang kerja'))];

    useEffect(() => {
        const closeOnEscape = (event: KeyboardEvent) => {
            if (event.key === 'Escape') setMobileOpen(false);
        };
        document.addEventListener('keydown', closeOnEscape);
        return () => document.removeEventListener('keydown', closeOnEscape);
    }, []);

    if (!auth.user) return null;
    const user = auth.user;
    const initials = user.name.trim().split(/\s+/).map((part) => part[0]).join('').slice(0, 2);

    return (
        <div className="app-shell">
            <Head title={title} />
            {mobileOpen && <button className="sidebar-scrim" aria-label="Tutup navigasi" onClick={() => setMobileOpen(false)} />}
            <aside className={`sidebar ${mobileOpen ? 'sidebar-open' : ''}`}>
                <Link href={routes.dashboard} className="brand-link" aria-label="Aisha Monitor beranda"><Brand /></Link>
                <div className="workspace-selector">
                    <span className="workspace-icon"><HeartPulse size={17} /></span>
                    <div><strong>PT Aisha Zahra</strong><small>Herbalaboratories</small></div>
                </div>
                <nav aria-label="Navigasi utama" className="sidebar-nav">
                    {groups.map((group) => <div className="nav-group" key={group}>
                        <div className="nav-group-label">{group}</div>
                        {navigation.filter((item) => (item.group ?? 'Ruang kerja') === group).map((item) => {
                            const Icon = icons[item.icon ?? ''] ?? FileText;
                            const active = currentPath === item.href || currentPath.startsWith(`${item.href}/`);
                            return <Link key={item.href} href={item.href} onClick={() => setMobileOpen(false)} className={`nav-link ${active ? 'nav-active' : ''}`} aria-current={active ? 'page' : undefined}>
                                <Icon size={18} strokeWidth={1.7} /><span>{item.label}</span>
                            </Link>;
                        })}
                    </div>)}
                </nav>
                <div className="sidebar-bottom">
                    <div className="help-card">
                        <span className="help-card-icon"><Sprout size={22} /></span>
                        <strong>Tumbuh lebih teratur.</strong>
                        <p>Setiap proses, kini dalam satu ruang kerja.</p>
                        <button disabled title="Panduan penggunaan belum tersedia">Panduan penggunaan<ArrowUpRight size={14} /></button>
                        <Sprout className="help-card-decor" size={100} strokeWidth={0.8} />
                    </div>
                    <button className="sidebar-help" disabled title="Pusat bantuan belum tersedia"><CircleHelp size={17} />Bantuan & panduan<ArrowUpRight size={14} /></button>
                    <div className="sidebar-user">
                        <Avatar initials={initials} />
                        <div className="min-w-0"><strong className="break-words">{user.name}</strong><small>{user.role_label}</small></div>
                        <Link href={routes.logout} method="post" as="button" aria-label="Keluar" className="icon-button"><LogOut size={17} /></Link>
                    </div>
                </div>
            </aside>
            <div className="workspace-main">
                <header className="topbar">
                    <div className="topbar-left">
                        <button className="icon-button mobile-menu" aria-label="Buka navigasi" onClick={() => setMobileOpen(true)}><Menu size={21} /></button>
                        <div className="breadcrumb"><Home size={16} /><ChevronRight size={13} /><span>{title}</span></div>
                    </div>
                    <div className="global-search" title="Pencarian tiket belum tersedia">
                        <Search size={17} /><input aria-label="Pencarian global" placeholder="Pencarian tiket belum tersedia" disabled />
                    </div>
                    <div className="topbar-actions">
                        <Link href={routes.notifications} className="icon-button relative" aria-label={unread ? `Buka notifikasi, ${unread} belum dibaca` : 'Buka notifikasi'}><Bell size={19} />{unread > 0 && <span className="notification-dot" />}</Link>
                        <span className="topbar-divider" />
                        <Avatar initials={initials} small /><span className="hidden md:inline-flex"><Badge tone="neutral" dot={false}>{user.role_label}</Badge></span>
                    </div>
                </header>
                <main className="main-content">
                    {flash.status && <div role="status" className="info-box mb-5">{flash.status}</div>}
                    {children}
                </main>
                <footer className="workspace-footer"><span>© 2026 PT Aisha Zahra Herbalaboratories</span><span><span className="live-dot" />Sesi akun aktif · Data sistem · WIB</span></footer>
            </div>
        </div>
    );
}
