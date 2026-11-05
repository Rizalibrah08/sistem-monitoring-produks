import { Link, usePage } from '@inertiajs/react';
import { Activity, CalendarDays, ClipboardList, Clock3, FileText, Leaf, MessageCircle, ShieldCheck, Sprout, Ticket, Users, Wallet } from 'lucide-react';
import type { BackendSharedProps } from '../../backend-types';
import { EmptyState, PageHeader, Panel, StatCard } from '../../components/ui';
import { BackendLayout } from '../../layouts/backend-layout';

type AccountSummary = { total: number; active: number; inactive: number; activities_today: number };

export default function Dashboard({ accounts = null, today }: { accounts?: AccountSummary | null; today?: string } = {}) {
    const { auth, navigation, routes } = usePage<BackendSharedProps>().props;
    const admin = auth.user?.role === 'admin';
    const finance = auth.user?.role === 'finance';
    const marketing = auth.user?.role === 'marketing';
    const quickLinks = navigation.filter((item) => item.href !== routes.dashboard).slice(0, 4);
    const cards = finance
        ? [{ label: 'Total piutang', icon: Wallet }, { label: 'Menunggu verifikasi', icon: Clock3 }, { label: 'Pembayaran lunas', icon: ShieldCheck }, { label: 'Tagihan jatuh tempo', icon: FileText }]
        : marketing
            ? [{ label: 'Prospek baru', icon: Sprout }, { label: 'Pesan belum dibalas', icon: MessageCircle }, { label: 'Kontrak perlu konfirmasi', icon: FileText }, { label: 'Tiket aktif saya', icon: Ticket }]
            : [{ label: 'Tiket aktif', icon: Ticket }, { label: 'Terlambat SLA', icon: Clock3 }, { label: 'Menunggu BPOM', icon: FileText }, { label: 'Perlu ditinjau', icon: ClipboardList }];

    return (
        <BackendLayout title="Dasbor">
            <PageHeader title="Dasbor" eyebrow="RUANG KERJA ANDA" description={`Selamat datang, ${auth.user?.name ?? ''}. Semua pekerjaan ${auth.user?.role_label ?? ''} Anda dalam satu tempat.`}>
                {today && <span className="date-chip"><CalendarDays size={14} />{today}</span>}
                {admin && <Link href={routes.users} className="btn btn-primary"><Users size={16} />Kelola pengguna</Link>}
            </PageHeader>
            <div className="greeting-banner">
                <div><span className="greeting-icon"><Leaf size={20} strokeWidth={1.5} /></span><p><strong>Setiap proses, kini dalam satu ruang kerja.</strong><span>Akses halaman mengikuti role akun; data yang tersedia berasal dari backend.</span></p></div>
                <span className="greeting-label"><span />Sesi akun aktif</span><Sprout className="greeting-decor" size={100} strokeWidth={0.8} />
            </div>
            <div className="stat-grid">
                {admin && accounts ? <>
                    <StatCard label="Total pengguna" value={accounts.total} icon={Users} detail="Akun staf yang tersimpan" />
                    <StatCard label="Pengguna aktif" value={accounts.active} icon={ShieldCheck} detail="Akun dapat mengakses aplikasi" />
                    <StatCard label="Pengguna nonaktif" value={accounts.inactive} icon={Users} detail="Akses akun dinonaktifkan" tone="amber" />
                    <StatCard label="Aktivitas hari ini" value={accounts.activities_today} icon={Activity} detail="Tercatat di log audit" tone="blue" />
                </> : cards.map(({ label, icon }) => <StatCard key={label} label={label} value="—" icon={icon} detail="Modul operasional belum diaktifkan" />)}
            </div>
            <div className="two-column">
                <Panel title={admin ? 'Pengelolaan akun staf' : 'Pekerjaan hari ini'} description={admin ? 'Buat akun pengguna dan tetapkan role sesuai tanggung jawabnya.' : 'Tiket dan tugas akan tampil setelah modul operasional tersedia.'}>
                    {admin ? <div className="panel-body space-y-4">
                        <p>Gunakan menu Pengguna dan role untuk menambah akun Marketing, R&D, QA/QC, Regulatory Affairs, Finance, Manajer, atau Admin.</p>
                        <p>Setiap pengguna masuk dengan akunnya sendiri. Role dan hak akses diperiksa oleh server, bukan dipilih dari halaman login.</p>
                        <Link href={routes.users} className="btn btn-secondary">Buka pengguna dan role</Link>
                    </div> : <EmptyState title={finance ? 'Belum ada tagihan' : 'Belum ada tiket'} description="Modul tiket dan dokumen belum diaktifkan. Data operasional tidak digantikan dengan data demo." icon={ClipboardList} />}
                </Panel>
                <Panel title="Akses cepat" description="Halaman yang tersedia untuk role akun Anda.">
                    <div className="panel-body flex flex-col gap-3">
                        {quickLinks.map((item) => <Link key={item.href} href={item.href} className="btn btn-secondary justify-start">{item.label}</Link>)}
                        {!quickLinks.length && <EmptyState title="Belum ada halaman tambahan" description="Halaman akan ditampilkan sesuai hak akses akun Anda." />}
                    </div>
                </Panel>
            </div>
            {admin && <Panel title="Status modul operasional" className="mt-5">
                <div className="panel-body">
                    <p>Modul tiket, dokumen, kontrak, pembayaran, dan integrasi WhatsApp belum diaktifkan. Halamannya menampilkan kondisi kosong atau belum terhubung, bukan data simulasi.</p>
                </div>
            </Panel>}
        </BackendLayout>
    );
}
