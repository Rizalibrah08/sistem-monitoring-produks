import { Link, usePage } from '@inertiajs/react';
import { Activity, CalendarDays, ClipboardList, Clock3, FileText, Leaf, MessageCircle, ShieldCheck, Sprout, Ticket, Users, Wallet } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { BackendSharedProps } from '../../backend-types';
import { EmptyState, PageHeader, Panel, StatCard } from '../../components/ui';
import { BackendLayout } from '../../layouts/backend-layout';
import { TaskRows, type TaskItem } from './Tasks';

type AccountSummary = { total: number; active: number; inactive: number; activities_today: number };
type Stat = { key: string; label: string; value: number | string | null; detail: string };
type Breakdown = { phase: string; stage: string; count: number };

const statIcons: Record<string, LucideIcon> = {
    active: Ticket, late: Clock3, waiting_bpom: FileText, review: ClipboardList,
    prospects: Sprout, messages: MessageCircle, contracts: FileText, waiting_client: Ticket,
    outstanding: Wallet, unpaid: FileText, paid_month: ShieldCheck, overdue: Clock3,
};
const statTones: Record<string, string> = { late: 'red', overdue: 'red', review: 'amber', waiting_client: 'amber', waiting_bpom: 'blue' };

export default function Dashboard({ accounts = null, today, stats = null, tasks = [], breakdown = null, urls }: {
    accounts?: AccountSummary | null;
    today?: string;
    stats?: Stat[] | null;
    tasks?: TaskItem[];
    breakdown?: Breakdown[] | null;
    urls?: { tasks: string };
} = {}) {
    const { auth, navigation, routes } = usePage<BackendSharedProps>().props;
    const admin = auth.user?.role === 'admin';
    const quickLinks = navigation.filter((item) => item.href !== routes.dashboard && item.available !== false).slice(0, 4);

    return (
        <BackendLayout title="Dasbor">
            <PageHeader title="Dasbor" eyebrow="RUANG KERJA ANDA" description={`Selamat datang, ${auth.user?.name ?? ''}. Semua pekerjaan ${auth.user?.role_label ?? ''} Anda dalam satu tempat.`}>
                {today && <span className="date-chip"><CalendarDays size={14} />{today}</span>}
                {admin && <Link href={routes.users} className="btn btn-primary"><Users size={16} />Kelola pengguna</Link>}
            </PageHeader>
            <div className="greeting-banner">
                <div><span className="greeting-icon"><Leaf size={20} strokeWidth={1.5} /></span><p><strong>Setiap proses, kini dalam satu ruang kerja.</strong><span>Angka di bawah dihitung langsung dari data tiket, dokumen, dan tagihan.</span></p></div>
                <span className="greeting-label"><span />Sesi akun aktif</span><Sprout className="greeting-decor" size={100} strokeWidth={0.8} />
            </div>
            <div className="stat-grid">
                {admin && accounts ? <>
                    <StatCard label="Total pengguna" value={accounts.total} icon={Users} detail="Akun staf yang tersimpan" />
                    <StatCard label="Pengguna aktif" value={accounts.active} icon={ShieldCheck} detail="Akun dapat mengakses aplikasi" />
                    <StatCard label="Pengguna nonaktif" value={accounts.inactive} icon={Users} detail="Akses akun dinonaktifkan" tone="amber" />
                    <StatCard label="Aktivitas hari ini" value={accounts.activities_today} icon={Activity} detail="Tercatat di log audit" tone="blue" />
                </> : (stats ?? []).map((stat) => <StatCard key={stat.key} label={stat.label} value={stat.value ?? '—'} icon={statIcons[stat.key] ?? Activity} detail={stat.detail} tone={statTones[stat.key]} />)}
            </div>
            <div className="two-column">
                <Panel title={admin ? 'Pengelolaan akun staf' : 'Pekerjaan hari ini'} description={admin ? 'Buat akun pengguna dan tetapkan role sesuai tanggung jawabnya.' : 'Tugas teratas sesuai role Anda.'}
                    action={!admin && tasks.length > 0 && urls ? <Link href={urls.tasks} className="text-link">Semua tugas</Link> : undefined}>
                    {admin ? <div className="panel-body space-y-4">
                        <p>Gunakan menu Pengguna dan role untuk menambah akun Marketing, R&D, QA/QC, Regulatory Affairs, Finance, Manajer, atau Admin.</p>
                        <p>Setiap pengguna masuk dengan akunnya sendiri. Role dan hak akses diperiksa oleh server, bukan dipilih dari halaman login.</p>
                        <Link href={routes.users} className="btn btn-secondary">Buka pengguna dan role</Link>
                    </div> : tasks.length ? <TaskRows tasks={tasks} /> : <EmptyState title="Tidak ada tugas yang menunggu" description="Tugas dokumen, verifikasi, dan tanggapan BPOM akan muncul di sini." icon={ClipboardList} />}
                </Panel>
                {breakdown ? <Panel title="Tiket aktif per fase dan tahap">
                    {breakdown.length ? <ul className="panel-body space-y-2 text-sm">{breakdown.map((row) => <li key={`${row.phase}-${row.stage}`} className="flex justify-between gap-3"><span><strong>{row.stage}</strong> <span className="text-gray-500">· {row.phase}</span></span><strong>{row.count}</strong></li>)}</ul>
                        : <EmptyState title="Belum ada tiket aktif" />}
                </Panel> : <Panel title="Akses cepat" description="Halaman yang tersedia untuk role akun Anda.">
                    <div className="panel-body flex flex-col gap-3">
                        {quickLinks.map((item) => <Link key={item.href} href={item.href} className="btn btn-secondary justify-start">{item.label}</Link>)}
                        {!quickLinks.length && <EmptyState title="Belum ada halaman tambahan" description="Halaman akan ditampilkan sesuai hak akses akun Anda." />}
                    </div>
                </Panel>}
            </div>
        </BackendLayout>
    );
}
