import { Link } from '../lib/preview-navigation';
import {
    ArrowRight,
    Bell,
    CheckCheck,
    ChevronRight,
    Clock3,
    Download,
    FileText,
    ListOrdered,
    RefreshCw,
    Search,
    TriangleAlert,
} from 'lucide-react';
import { useState } from 'react';
import {
    Badge,
    Button,
    EmptyState,
    Field,
    InfoBox,
    Modal,
    PageHeader,
    Panel,
    Progress,
    SearchInput,
    Select,
    StatCard,
} from '../components/ui';
import { usePreview } from '../mocks/preview-provider';
import { downloadCsv, ticketProgress } from '../lib/utils';

export function PrioritiesPage() {
    const { data, role, notify } = usePreview();
    const [search, setSearch] = useState('');
    const [phase, setPhase] = useState('');
    const [selected, setSelected] = useState(data.tickets[0].id);
    const tickets = data.tickets
        .filter(
            (ticket) =>
                !ticket.closed &&
                !ticket.submitted &&
                (role !== 'ra' || ticket.dpPaid) &&
                (!phase || ticket.phase === phase) &&
                `${ticket.id} ${ticket.product}`.toLowerCase().includes(search.toLowerCase()),
        )
        .sort((a, b) => b.score - a.score);
    const ticket = tickets.find((item) => item.id === selected) ?? tickets[0];
    return (
        <>
            <PageHeader
                title="Antrean prioritas"
                description="Fokus pada pekerjaan yang paling membutuhkan perhatian Anda."
                eyebrow="PRIORITAS PEKERJAAN"
            >
                <Badge tone="green" dot={false}>
                    <ListOrdered size={12} />
                    AHP–TOPSIS · skor demo
                </Badge>
                <Button
                    variant="secondary"
                    icon={RefreshCw}
                    onClick={() =>
                        notify('Skor yang ditampilkan adalah data simulasi, bukan hasil perhitungan AHP–TOPSIS nyata.')
                    }
                >
                    Perbarui antrean
                </Button>
            </PageHeader>
            <div className="filter-bar">
                <div className="filter-group">
                    <SearchInput
                        placeholder="Cari kode atau nama produk..."
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                    <Select value={phase} onChange={(event) => setPhase(event.target.value)}>
                        <option value="">Semua fase</option>
                        <option>Pra-registrasi</option>
                        <option>Registrasi</option>
                    </Select>
                </div>
                <span className="text-xs text-muted">{tickets.length} tiket dalam antrean</span>
            </div>
            <Panel>
                <div className="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Peringkat</th>
                                <th>Produk & tiket</th>
                                <th>Skor prioritas</th>
                                <th>Alasan utama urutan</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {tickets.map((item, index) => (
                                <tr
                                    key={item.id}
                                    className={ticket?.id === item.id ? 'selected-row' : ''}
                                    onClick={() => setSelected(item.id)}
                                >
                                    <td>
                                        <span className={`rank-number ${index === 0 ? 'rank-first' : ''}`}>
                                            {index + 1}
                                        </span>
                                    </td>
                                    <td>
                                        <strong>{item.product}</strong>
                                        <small>{item.id}</small>
                                    </td>
                                    <td>
                                        <div className="score-cell">
                                            <strong>{item.score.toFixed(2).replace('.', ',')}</strong>
                                            <Progress value={Math.round(item.score * 100)} showLabel={false} />
                                        </div>
                                    </td>
                                    <td className="priority-reason">
                                        {item.bpomDays
                                            ? `Tenggat BPOM tinggal ${item.bpomDays} hari, dokumen perlu dituntaskan.`
                                            : item.late
                                              ? 'Sudah melewati jadwal internal. Perlu segera ditangani.'
                                              : `Tiket berjalan ${item.age} hari, ${ticketProgress(item)}% dokumen disetujui.`}
                                    </td>
                                    <td>
                                        <Link href={`/tickets/${item.id}`} preserveState className="btn btn-secondary">
                                            Buka
                                            <ArrowRight size={13} />
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {!tickets.length && (
                        <EmptyState
                            title="Antrean sudah bersih"
                            description="Tidak ada tiket yang sesuai filter ini."
                        />
                    )}
                </div>
            </Panel>
            {ticket && (
                <Panel
                    className="mt-5"
                    title={`Rincian prioritas · ${ticket.id}`}
                    description="Nilai kriteria ditampilkan agar urutan pekerjaan dapat dipahami."
                >
                    <div className="criteria-grid">
                        {[
                            [
                                'C1',
                                'Critical Ratio',
                                ticket.cr.toString().replace('.', ','),
                                'Sisa waktu / sisa pekerjaan',
                            ],
                            [
                                'C2',
                                'Tenggat BPOM',
                                ticket.bpomDays === null ? 'Tidak ada' : `${ticket.bpomDays} hari`,
                                'Tenggat tanggapan catatan BPOM',
                            ],
                            ['C3', 'Kelengkapan dokumen', `${ticketProgress(ticket)}%`, 'Dokumen wajib yang disetujui'],
                            ['C4', 'Umur tiket', `${ticket.age} hari`, 'Dihitung sejak tiket dibuat'],
                        ].map(([code, label, value, detail]) => (
                            <div key={code}>
                                <span>
                                    {code}
                                    <small>{label}</small>
                                </span>
                                <strong>{value}</strong>
                                <p>{detail}</p>
                            </div>
                        ))}
                    </div>
                </Panel>
            )}
            <div className="mt-5">
                <InfoBox>
                    Skor merupakan ilustrasi tampilan. Tiket yang menunggu BPOM tidak masuk antrean; khusus RA, DP harus
                    lunas. Perhitungan dan pembaruan otomatis diimplementasikan pada tahap backend.
                </InfoBox>
            </div>
        </>
    );
}

const durations = [
    ['Pengembangan sampel', 14, 9.2, 12, 5.1, 4.1],
    ['Dokumen pra-registrasi', 7, 6.8, 9, 4.2, 2.6],
    ['Verifikasi pra-registrasi', 3, 2.1, 3, 0, 2.1],
    ['Dokumen registrasi', 14, 12.5, 16, 7.9, 4.6],
    ['Verifikasi registrasi', 5, 3.4, 5, 0, 3.4],
    ['Menunggu BPOM', 0, 18, 24, 0, 0],
];
export function ReportsPage() {
    const [period, setPeriod] = useState('Juli–Oktober 2026');
    return (
        <>
            <PageHeader
                title="Laporan durasi"
                eyebrow="ANALISIS PROSES"
                description="Pahami waktu penyelesaian dan hambatan di setiap tahap."
            >
                <Button
                    variant="secondary"
                    icon={Download}
                    onClick={() =>
                        downloadCsv('laporan-durasi-demo.csv', [
                            ['Tahap', 'SLA', 'Rata-rata', 'P75', 'Menunggu klien', 'Internal'],
                            ...durations,
                        ])
                    }
                >
                    Ekspor CSV
                </Button>
            </PageHeader>
            <div className="stat-grid">
                <StatCard label="Rata-rata internal" value="4,6 hari" icon={Clock3} detail="Ilustrasi seluruh tahap" />
                <StatCard
                    label="Tahap paling lama"
                    value="Registrasi"
                    icon={FileText}
                    tone="amber"
                    detail="Pengumpulan dokumen"
                />
                <StatCard
                    label="Tiket selesai"
                    value="2"
                    icon={CheckCheck}
                    tone="blue"
                    detail="Pada data demonstrasi"
                />
                <StatCard
                    label="Sumber data"
                    value="Simulasi"
                    icon={ListOrdered}
                    detail="Bukan data historis perusahaan"
                />
            </div>
            <div className="filter-bar">
                <div className="filter-group">
                    <Select value={period} onChange={(event) => setPeriod(event.target.value)}>
                        <option>Juli–Oktober 2026</option>
                        <option>Oktober 2026</option>
                    </Select>
                    <Select aria-label="Sediaan">
                        <option>Semua sediaan</option>
                        <option>Kapsul</option>
                        <option>Serbuk</option>
                    </Select>
                </div>
                <Badge tone="neutral">Data ilustratif</Badge>
            </div>
            <Panel>
                <div className="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Tahap</th>
                                <th>SLA</th>
                                <th>Rata-rata</th>
                                <th>Persentil ke-75</th>
                                <th>Menunggu klien</th>
                                <th>Internal</th>
                                <th>Distribusi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {durations.map(([name, sla, average, p75, client, internal]) => (
                                <tr key={name}>
                                    <td>
                                        <strong>{name}</strong>
                                    </td>
                                    <td>{sla || '—'}</td>
                                    <td>{average} hari</td>
                                    <td>{p75} hari</td>
                                    <td>{client || '—'}</td>
                                    <td>{internal || '—'}</td>
                                    <td>
                                        {sla ? (
                                            <div className="duration-bars">
                                                <span style={{ width: `${Number(client) * 5}px` }} />
                                                <span style={{ width: `${Number(internal) * 5}px` }} />
                                            </div>
                                        ) : (
                                            <Badge tone="neutral" dot={false}>
                                                Di luar SLA
                                            </Badge>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Panel>
            <div className="mt-5">
                <InfoBox>
                    Durasi ditampilkan dalam hari kerja. Waktu menunggu klien dipisahkan dari waktu internal. Filter
                    periode merupakan pratinjau; agregasi historis tersedia setelah backend dihubungkan.
                </InfoBox>
            </div>
        </>
    );
}

export function EscalationsPage() {
    const { data, notify } = usePreview();
    const [selected, setSelected] = useState<string | null>(null);
    const [note, setNote] = useState('');
    const [resolved, setResolved] = useState<string[]>([]);
    const [filter, setFilter] = useState('Terbuka');
    const rows = data.tickets
        .filter((item) => item.late)
        .map((item) => ({
            id: item.id,
            type: 'SLA tahap',
            cause: `${item.stage} melewati batas waktu.`,
            pic: 'Regulatory Affairs',
        }));
    rows.push({
        id: 'Percakapan Alam Lestari',
        type: 'Respons chat',
        cause: 'Percakapan belum dibalas selama 4 jam kerja.',
        pic: 'Marketing',
    });
    const visible = rows.filter(
        (row) => filter === 'Semua' || (filter === 'Selesai' ? resolved.includes(row.id) : !resolved.includes(row.id)),
    );
    return (
        <>
            <PageHeader
                title="Daftar eskalasi"
                eyebrow="PEMANTAUAN SLA"
                description="Tindak lanjuti keterlambatan internal sebelum menjadi hambatan."
            />
            <div className="filter-bar">
                <div className="filter-group">
                    <Select value={filter} onChange={(event) => setFilter(event.target.value)}>
                        <option>Terbuka</option>
                        <option>Selesai</option>
                        <option>Semua</option>
                    </Select>
                </div>
                <Badge tone="rose">{rows.length - resolved.length} perlu perhatian</Badge>
            </div>
            <Panel>
                <div className="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Jenis</th>
                                <th>Tiket / percakapan</th>
                                <th>Penyebab</th>
                                <th>PIC</th>
                                <th>Status</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {visible.map((row) => (
                                <tr key={row.id}>
                                    <td className="muted-cell">21/10 · 08.00</td>
                                    <td>{row.type}</td>
                                    <td>
                                        <strong>{row.id}</strong>
                                    </td>
                                    <td>{row.cause}</td>
                                    <td>{row.pic}</td>
                                    <td>
                                        <Badge>
                                            {resolved.includes(row.id) ? 'Selesai' : 'Menunggu tindak lanjut'}
                                        </Badge>
                                    </td>
                                    <td>
                                        <Button
                                            variant="secondary"
                                            onClick={() => {
                                                setSelected(row.id);
                                                setNote('');
                                            }}
                                        >
                                            {resolved.includes(row.id) ? 'Lihat' : 'Tindak lanjut'}
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {!visible.length && (
                        <EmptyState
                            title="Tidak ada eskalasi"
                            description="Semua eskalasi pada filter ini sudah ditindaklanjuti."
                        />
                    )}
                </div>
            </Panel>
            <div className="mt-5">
                <InfoBox>
                    Keterlambatan dokumen milik klien tidak dieskalasikan ke Manajer. Klien menerima pengingat dan
                    Marketing menerima informasinya.
                </InfoBox>
            </div>
            {selected && (
                <Modal title="Tindak lanjut eskalasi" description={selected} onClose={() => setSelected(null)}>
                    <Field label="Catatan tindak lanjut">
                        <textarea
                            value={note}
                            onChange={(event) => setNote(event.target.value)}
                            placeholder="Catat keputusan atau koordinasi dengan PIC..."
                        />
                    </Field>
                    <div className="form-footer">
                        <Button variant="secondary" onClick={() => setSelected(null)}>
                            Batal
                        </Button>
                        <Button
                            disabled={!note.trim()}
                            onClick={() => {
                                setResolved([...resolved, selected]);
                                notify('Tindak lanjut eskalasi dicatat pada demo. Status SLA tiket tidak diubah.');
                                setSelected(null);
                            }}
                        >
                            Simpan tindak lanjut
                        </Button>
                    </div>
                </Modal>
            )}
        </>
    );
}
