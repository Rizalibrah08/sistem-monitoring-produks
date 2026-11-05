import { Link } from '../lib/preview-navigation';
import {
    Activity,
    ArrowRight,
    ArrowUpRight,
    Bell,
    CalendarDays,
    Check,
    CheckCheck,
    ChevronDown,
    Clock3,
    Download,
    FileCheck2,
    FileClock,
    FileText,
    FolderCheck,
    Leaf,
    MessageCircle,
    Package,
    Plus,
    ShieldCheck,
    Sparkles,
    Sprout,
    Ticket,
    TriangleAlert,
    Users,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';
import {
    Avatar,
    Badge,
    Button,
    InfoBox,
    LinkButton,
    PageHeader,
    Panel,
    Progress,
    StatCard,
    TextLink,
} from '../components/ui';
import { usePreview } from '../mocks/preview-provider';
import { downloadCsv, shortMoney } from '../lib/utils';
import { TicketTable } from '../features/tickets/ticket-table';
import { roleLabels } from '../types';

export function DashboardPage() {
    const { data, role } = usePreview();
    const [attentionOnly, setAttentionOnly] = useState(false);
    const active = data.tickets.filter((item) => !item.closed);
    const late = active.filter((item) => item.late);
    const waiting = active.filter((item) => item.stage.includes('BPOM'));
    const review = active.filter((item) => item.needsReview);
    const unpaid = data.invoices.filter((item) => item.status !== 'Lunas');
    const completed = data.tickets.filter((item) => item.closed);
    const marketing = role === 'marketing';
    const staff = role === 'rnd' || role === 'qa' || role === 'ra';
    const visible =
        role === 'marketing'
            ? data.tickets.filter(
                  (item) => data.clients.find((client) => client.id === item.clientId)?.assignedTo === 'marketing',
              )
            : data.tickets;
    const display = (
        attentionOnly ? visible.filter((item) => item.late || item.needsReview) : visible.filter((item) => !item.closed)
    ).slice(0, 5);
    const phaseCounts = [
        active.filter((item) => item.phase === 'Awal').length,
        active.filter((item) => item.phase === 'Pra-registrasi').length,
        active.filter((item) => item.phase === 'Registrasi').length,
    ];
    const stages = [
        'Pengembangan sampel',
        'Kontrak (MoU)',
        'Dokumen pra-registrasi',
        'Verifikasi pra-registrasi',
        'Dokumen registrasi',
        'Verifikasi registrasi',
        'Menunggu BPOM',
    ];
    const totalPiutang = unpaid.reduce((sum, item) => sum + item.amount, 0);
    return (
        <>
            <PageHeader
                title={staff ? 'Dasbor tugas' : 'Dasbor'}
                eyebrow="RUANG KERJA ANDA"
                description={
                    role === 'manager'
                        ? 'Selamat pagi, Aditya. Berikut perkembangan pendaftaran produk hari ini.'
                        : `Selamat datang kembali. Semua pekerjaan ${roleLabels[role]} Anda dalam satu tempat.`
                }
            >
                <span className="date-chip">
                    <CalendarDays size={14} />
                    Rabu, 21 Oktober 2026
                    <ChevronDown size={12} />
                </span>
                {marketing ? (
                    <LinkButton href="/tickets/new" icon={Plus}>
                        Buat tiket
                    </LinkButton>
                ) : (
                    <Button
                        variant="secondary"
                        icon={Download}
                        onClick={() =>
                            downloadCsv('ringkasan-tiket.csv', [
                                ['Kode', 'Produk', 'Tahap'],
                                ...active.map((item) => [item.id, item.product, item.stage]),
                            ])
                        }
                    >
                        Ekspor ringkasan
                    </Button>
                )}
            </PageHeader>
            <div className="greeting-banner">
                <div>
                    <span className="greeting-icon">
                        <Leaf size={20} strokeWidth={1.5} />
                    </span>
                    <p>
                        <strong>Setiap produk, selangkah lebih dekat.</strong>
                        <span>Pendaftaran yang teratur untuk produk kesehatan yang lebih baik.</span>
                    </p>
                </div>
                <span className="greeting-label">
                    <span />
                    Workspace demo aktif
                </span>
                <Sprout className="greeting-decor" size={100} strokeWidth={0.8} />
            </div>
            <div className="stat-grid">
                {role === 'finance' ? (
                    <>
                        <StatCard
                            label="Total piutang"
                            value={shortMoney(totalPiutang)}
                            icon={Wallet}
                            detail={`${unpaid.length} tagihan belum lunas`}
                        />
                        <StatCard
                            label="Menunggu verifikasi"
                            value={data.invoices.filter((item) => item.proofStatus === 'pending').length}
                            icon={FileClock}
                            tone="amber"
                            detail="Bukti transfer perlu ditinjau"
                        />
                        <StatCard
                            label="Lunas bulan ini"
                            value={data.invoices.filter((item) => item.status === 'Lunas').length}
                            icon={CheckCheck}
                            tone="blue"
                            detail="Pembayaran terverifikasi"
                        />
                        <StatCard
                            label="Lewat jatuh tempo"
                            value={unpaid.filter((item) => item.status === 'Lewat jatuh tempo').length}
                            icon={TriangleAlert}
                            tone="rose"
                            detail="Perlu pengingat pembayaran"
                        />
                    </>
                ) : marketing ? (
                    <>
                        <StatCard label="Prospek baru" value="3" icon={Sprout} detail="Menunggu tindak lanjut Anda" />
                        <StatCard
                            label="Pesan belum dibalas"
                            value={
                                data.conversations.filter(
                                    (item) => item.assigned === 'marketing' && item.state === 'Menunggu',
                                ).length
                            }
                            icon={MessageCircle}
                            tone="blue"
                            detail="Di nomor Marketing"
                        />
                        <StatCard
                            label="Kontrak perlu konfirmasi"
                            value={data.contracts.filter((item) => item.status === 'Ditandatangani').length}
                            icon={FileText}
                            tone="amber"
                            detail="Tanda tangan telah diterima"
                        />
                        <StatCard
                            label="Tiket aktif saya"
                            value={visible.filter((item) => !item.closed).length}
                            icon={Ticket}
                            detail="Seluruh produk klien Anda"
                        />
                    </>
                ) : (
                    <>
                        <StatCard
                            label="Tiket aktif"
                            value={active.length}
                            icon={Ticket}
                            detail="Produk dalam proses pendaftaran"
                        />
                        <StatCard
                            label="Terlambat SLA"
                            value={late.length}
                            icon={Clock3}
                            tone="rose"
                            detail="Membutuhkan tindak lanjut"
                        />
                        <StatCard
                            label="Menunggu BPOM"
                            value={waiting.length}
                            icon={FileClock}
                            tone="amber"
                            detail="SLA internal dihentikan"
                        />
                        <StatCard
                            label="NIE terbit bulan ini"
                            value={completed.length}
                            icon={ShieldCheck}
                            tone="blue"
                            detail="Produk berhasil terdaftar"
                        />
                    </>
                )}
            </div>
            {role === 'admin' && (
                <div className="quick-links">
                    <Link href="/users">
                        <Users size={18} />
                        Kelola pengguna
                        <ArrowRight size={15} />
                    </Link>
                    <Link href="/master">
                        <Package size={18} />
                        Atur master data
                        <ArrowRight size={15} />
                    </Link>
                    <Link href="/templates">
                        <FileText size={18} />
                        Templat dokumen
                        <ArrowRight size={15} />
                    </Link>
                </div>
            )}
            {staff && (
                <div className="task-banner">
                    <div>
                        <FolderCheck size={23} />
                        <span>
                            <strong>Daftar tugas khusus untuk Anda</strong>
                            <small>Lihat dokumen yang perlu diunggah, direvisi, atau diverifikasi.</small>
                        </span>
                    </div>
                    <LinkButton
                        href={role === 'ra' ? '/verification' : role === 'qa' ? '/qa-review' : '/tasks'}
                        secondary
                        icon={ArrowRight}
                    >
                        Buka tugas
                    </LinkButton>
                </div>
            )}
            <div className="dashboard-middle">
                <Panel
                    title="Progres pendaftaran"
                    description="Distribusi tiket aktif berdasarkan tahap"
                    action={
                        <Badge tone="neutral" dot={false}>
                            {active.length} tiket
                        </Badge>
                    }
                >
                    <div className="stage-chart">
                        {stages.map((stage, index) => {
                            const count = active.filter((item) => item.stage === stage).length;
                            return (
                                <Link
                                    href={`/tickets?stage=${encodeURIComponent(stage)}`}
                                    preserveState
                                    className="stage-row"
                                    key={stage}
                                >
                                    <span className={`stage-dot stage-color-${index % 4}`} />
                                    <span className="stage-name">{stage}</span>
                                    <div className="stage-bar-track">
                                        <span
                                            className={`stage-color-${index % 4}`}
                                            style={{ width: `${(count / Math.max(active.length, 1)) * 220}%` }}
                                        />
                                    </div>
                                    <strong>{count}</strong>
                                </Link>
                            );
                        })}
                    </div>
                    <div className="chart-footer">
                        <span>
                            <i />
                            Waktu menunggu BPOM tidak dihitung sebagai SLA internal.
                        </span>
                        <TextLink href="/tickets">Lihat seluruh tiket</TextLink>
                    </div>
                </Panel>
                <Panel title="Ringkasan fase" description="Dari pengajuan hingga izin edar">
                    <div className="phase-overview">
                        <div className="donut-chart">
                            <svg viewBox="0 0 150 150" aria-label="Diagram distribusi fase">
                                <circle cx="75" cy="75" r="58" fill="none" stroke="#f0f4eb" strokeWidth="17" />
                                {phaseCounts.map((count, index) => {
                                    const previous = phaseCounts.slice(0, index).reduce((sum, val) => sum + val, 0);
                                    return (
                                        <circle
                                            key={index}
                                            cx="75"
                                            cy="75"
                                            r="58"
                                            fill="none"
                                            stroke={['#d9e9bd', '#9bc697', '#3c9270'][index]}
                                            strokeWidth="17"
                                            strokeDasharray={`${(count / active.length) * 364 - 5} 364`}
                                            strokeDashoffset={(-previous / active.length) * 364}
                                            transform="rotate(-90 75 75)"
                                        />
                                    );
                                })}
                            </svg>
                            <div>
                                <strong>{active.length}</strong>
                                <span>Tiket aktif</span>
                            </div>
                        </div>
                        <div className="phase-legend">
                            {['Tahap awal', 'Pra-registrasi', 'Registrasi'].map((phase, index) => (
                                <div key={phase}>
                                    <i style={{ background: ['#d9e9bd', '#9bc697', '#3c9270'][index] }} />
                                    <span>{phase}</span>
                                    <strong>{phaseCounts[index]}</strong>
                                </div>
                            ))}
                        </div>
                    </div>
                </Panel>
            </div>
            <div className="dashboard-bottom">
                <Panel
                    title="Tiket terbaru"
                    description="Pantau perkembangan produk yang sedang berjalan"
                    action={<TextLink href="/tickets">Semua tiket</TextLink>}
                >
                    <div className="table-tabs">
                        <button className={!attentionOnly ? 'active' : ''} onClick={() => setAttentionOnly(false)}>
                            Semua tiket<span>{active.length}</span>
                        </button>
                        <button className={attentionOnly ? 'active' : ''} onClick={() => setAttentionOnly(true)}>
                            Perlu perhatian<span>{late.length + review.filter((item) => !item.late).length}</span>
                        </button>
                    </div>
                    <TicketTable tickets={display} compact />
                    <div className="table-footer">
                        <span>Menampilkan {display.length} tiket terbaru</span>
                        <Link href="/tickets" preserveState className="text-link">
                            Buka daftar tiket
                            <ArrowRight size={13} />
                        </Link>
                    </div>
                </Panel>
                <Panel
                    title="Perlu perhatian"
                    description="Hal penting yang tidak boleh terlewat"
                    action={<span className="attention-dot" />}
                >
                    <div className="attention-list">
                        <Link href="/escalations" preserveState>
                            <span className="attention-icon icon-rose">
                                <Clock3 size={18} />
                            </span>
                            <div>
                                <strong>{late.length} tiket melewati SLA</strong>
                                <p>Periksa hambatan pada tahap internal.</p>
                                <small>Perlu tindak lanjut</small>
                            </div>
                            <ArrowUpRight size={15} />
                        </Link>
                        <Link href="/tickets?review=1" preserveState>
                            <span className="attention-icon icon-amber">
                                <FileCheck2 size={18} />
                            </span>
                            <div>
                                <strong>Perlu ditinjau</strong>
                                <p>{review.length} tiket terdampak pembaruan dokumen.</p>
                                <small>Dokumen bersama</small>
                            </div>
                            <ArrowUpRight size={15} />
                        </Link>
                        <Link href="/inbox" preserveState>
                            <span className="attention-icon icon-blue">
                                <MessageCircle size={18} />
                            </span>
                            <div>
                                <strong>Percakapan belum dibalas</strong>
                                <p>
                                    {data.conversations.filter((item) => item.state === 'Menunggu').length} percakapan
                                    menunggu respons tim.
                                </p>
                                <small>Nomor Marketing</small>
                            </div>
                            <ArrowUpRight size={15} />
                        </Link>
                    </div>
                    <div className="attention-note">
                        <ShieldCheck size={16} />
                        <span>Keterlambatan klien dipantau terpisah dari kinerja internal.</span>
                    </div>
                </Panel>
            </div>
            <div className="dashboard-status">
                <span>
                    <span className="live-dot" />
                    Data ilustratif terakhir diperbarui 21 Oktober 2026, 10.30 WIB
                </span>
                <span>
                    <Leaf size={12} />
                    Dirancang untuk proses yang lebih sehat.
                </span>
            </div>
        </>
    );
}
