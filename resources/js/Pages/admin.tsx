import {
    Check,
    CheckCircle2,
    ChevronRight,
    Download,
    FileClock,
    FileText,
    FlaskConical,
    History,
    LockKeyhole,
    Pencil,
    Plus,
    Printer,
    Search,
    Settings2,
    ShieldCheck,
    Trash2,
    Upload,
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
    FilePreview,
    InfoBox,
    Modal,
    PageHeader,
    Panel,
    SearchInput,
    Select,
} from '../components/ui';
import { usePreview } from '../mocks/preview-provider';
import { dateLabel, downloadCsv } from '../lib/utils';
import { roleLabels, type DocumentTemplate, type MasterItem, type Role, type StaffUser } from '../types';

export function UsersPage() {
    const { data, update } = usePreview();
    const [search, setSearch] = useState('');
    const [selected, setSelected] = useState<StaffUser | 'new' | null>(null);
    const users = data.users.filter((item) =>
        `${item.name} ${item.email} ${roleLabels[item.role]}`.toLowerCase().includes(search.toLowerCase()),
    );
    return (
        <>
            <PageHeader
                title="Pengguna dan role"
                eyebrow="ADMINISTRASI AKSES"
                description="Setiap anggota tim memiliki ruang kerja dan tanggung jawab yang jelas."
            >
                <Button icon={Plus} onClick={() => setSelected('new')}>
                    Tambah pengguna
                </Button>
            </PageHeader>
            <div className="filter-bar">
                <SearchInput
                    placeholder="Cari nama, email, atau role..."
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                />
                <Badge tone="neutral" dot={false}>
                    {data.users.filter((item) => item.active).length} pengguna aktif
                </Badge>
            </div>
            <Panel>
                <div className="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Pengguna</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Ketersediaan</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {users.map((item) => (
                                <tr key={item.id}>
                                    <td>
                                        <span className="client-cell">
                                            <Avatar
                                                initials={item.name
                                                    .split(' ')
                                                    .map((part) => part[0])
                                                    .join('')
                                                    .slice(0, 2)}
                                            />
                                            <span>
                                                <strong>{item.name}</strong>
                                                <small>{item.email}</small>
                                            </span>
                                        </span>
                                    </td>
                                    <td>
                                        <Badge tone="neutral" dot={false}>
                                            {roleLabels[item.role]}
                                        </Badge>
                                    </td>
                                    <td>
                                        <Badge>{item.active ? 'Aktif' : 'Nonaktif'}</Badge>
                                    </td>
                                    <td>
                                        {item.available ? (
                                            <span className="availability">
                                                <i />
                                                Tersedia
                                            </span>
                                        ) : (
                                            <span className="text-muted">Cuti / tidak tersedia</span>
                                        )}
                                    </td>
                                    <td>
                                        <div className="flex gap-2">
                                            <Button variant="secondary" onClick={() => setSelected(item)}>
                                                Ubah
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                onClick={() =>
                                                    update(
                                                        (state) => ({
                                                            ...state,
                                                            users: state.users.map((row) =>
                                                                row.id === item.id
                                                                    ? { ...row, active: !row.active }
                                                                    : row,
                                                            ),
                                                        }),
                                                        `Pengguna ${item.active ? 'dinonaktifkan' : 'diaktifkan'} pada demo.`,
                                                    )
                                                }
                                            >
                                                {item.active ? 'Nonaktifkan' : 'Aktifkan'}
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {!users.length && <EmptyState title="Pengguna tidak ditemukan" />}
                </div>
            </Panel>
            <div className="mt-5">
                <InfoBox>
                    Perubahan ini hanya memengaruhi data pratinjau. Autentikasi, kata sandi, dan kebijakan akses server
                    belum diimplementasikan.
                </InfoBox>
            </div>
            {selected && (
                <UserDialog user={selected === 'new' ? undefined : selected} onClose={() => setSelected(null)} />
            )}
        </>
    );
}
function UserDialog({ user, onClose }: { user?: StaffUser; onClose: () => void }) {
    const { data, update } = usePreview();
    const [form, setForm] = useState({
        name: user?.name ?? '',
        email: user?.email ?? '',
        role: user?.role ?? ('marketing' as Role),
        available: user?.available ?? true,
    });
    const [error, setError] = useState('');
    function submit(event: FormEvent) {
        event.preventDefault();
        if (!form.name.trim() || !form.email.trim()) return setError('Nama dan email wajib diisi.');
        if (
            data.users.some(
                (item) => item.id !== user?.id && item.email.toLowerCase() === form.email.trim().toLowerCase(),
            )
        )
            return setError('Email sudah digunakan pengguna lain.');
        const value = { ...form, id: user?.id ?? crypto.randomUUID(), active: user?.active ?? true };
        if (
            update(
                (state) => ({
                    ...state,
                    users: user
                        ? state.users.map((item) => (item.id === user.id ? value : item))
                        : [...state.users, value],
                }),
                'Data pengguna diperbarui pada demo.',
            )
        )
            onClose();
    }
    return (
        <Modal
            title={user ? 'Ubah pengguna' : 'Tambah pengguna'}
            description="Pengaturan identitas dan penugasan anggota tim."
            onClose={onClose}
        >
            <form onSubmit={submit} className="form-stack">
                <Field label="Nama lengkap">
                    <input value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} />
                </Field>
                <Field label="Email">
                    <input
                        type="email"
                        value={form.email}
                        onChange={(event) => setForm({ ...form, email: event.target.value })}
                    />
                </Field>
                <Field label="Role">
                    <Select
                        value={form.role}
                        onChange={(event) => setForm({ ...form, role: event.target.value as Role })}
                    >
                        {Object.entries(roleLabels).map(([key, label]) => (
                            <option key={key} value={key}>
                                {label}
                            </option>
                        ))}
                    </Select>
                </Field>
                <label className="manual-check">
                    <input
                        type="checkbox"
                        checked={!form.available}
                        onChange={(event) => setForm({ ...form, available: !event.target.checked })}
                    />
                    Sedang cuti / tidak tersedia
                </label>
                {error && (
                    <p className="form-error" role="alert">
                        {error}
                    </p>
                )}
                <div className="form-footer">
                    <Button variant="secondary" onClick={onClose}>
                        Batal
                    </Button>
                    <Button type="submit">Simpan pengguna</Button>
                </div>
            </form>
        </Modal>
    );
}

const allowedTokens = [
    'nama_klien',
    'nama_produk',
    'nilai_jasa',
    'nomor_kontrak',
    'dp_persen',
    'nomor_tagihan',
    'tanggal',
];
export function TemplatesPage() {
    const { data, update } = usePreview();
    const [selected, setSelected] = useState(data.templates[0].id);
    const [tokens, setTokens] = useState('nama_klien, nama_produk, nilai_jasa, nama_klein');
    const [file, setFile] = useState<File | null>(null);
    const [history, setHistory] = useState<DocumentTemplate | null>(null);
    const template = data.templates.find((item) => item.id === selected)!;
    const markers = tokens
        .split(',')
        .map((token) => token.trim())
        .filter(Boolean);
    const unknown = markers.filter((token) => !allowedTokens.includes(token));
    const fileValid = file && /\.docx$/i.test(file.name) && file.size > 0 && file.size <= 20 * 1024 * 1024;
    function save() {
        if (!fileValid || unknown.length) return;
        update(
            (state) => ({
                ...state,
                templates: state.templates.map((item) =>
                    item.id === selected ? { ...item, version: item.version + 1, tokens: markers } : item,
                ),
            }),
            'Versi templat ditambahkan pada data demo.',
        );
        setFile(null);
    }
    return (
        <>
            <PageHeader
                title="Templat dokumen"
                eyebrow="DOKUMEN YANG KONSISTEN"
                description="Kelola templat kontrak, surat, dan tagihan beserta riwayat versinya."
            />
            <div className="two-column admin-template-grid">
                <Panel title="Pustaka templat">
                    <div className="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Templat</th>
                                    <th>Jenis</th>
                                    <th>Versi</th>
                                    <th />
                                </tr>
                            </thead>
                            <tbody>
                                {data.templates.map((item) => (
                                    <tr
                                        key={item.id}
                                        className={selected === item.id ? 'selected-row' : ''}
                                        onClick={() => {
                                            setSelected(item.id);
                                            setTokens(item.tokens.join(', '));
                                            setFile(null);
                                        }}
                                    >
                                        <td>
                                            <span className="document-name">
                                                <span className="file-icon">
                                                    <FileText size={18} />
                                                </span>
                                                <strong>{item.name}</strong>
                                            </span>
                                        </td>
                                        <td>{item.kind}</td>
                                        <td>
                                            <Badge tone="green" dot={false}>
                                                v{item.version}
                                            </Badge>
                                        </td>
                                        <td>
                                            <Button
                                                variant="ghost"
                                                icon={History}
                                                onClick={(event) => {
                                                    event.stopPropagation();
                                                    setHistory(item);
                                                }}
                                            >
                                                Riwayat
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="panel-body">
                        <InfoBox>
                            Versi lama tidak dihapus. Setiap dokumen hasil akan mencatat versi templat yang digunakan.
                        </InfoBox>
                    </div>
                </Panel>
                <Panel title="Unggah versi baru" description={template.name}>
                    <div className="panel-body form-stack">
                        <Field label="Berkas templat Word (.docx)">
                            <input
                                className="file-input"
                                type="file"
                                accept=".docx"
                                onChange={(event) => setFile(event.target.files?.[0] ?? null)}
                            />
                        </Field>
                        <Field
                            label="Simulasi penanda isian"
                            hint="Masukkan penanda dipisahkan koma untuk mencoba kondisi valid/tidak dikenal."
                        >
                            <textarea value={tokens} onChange={(event) => setTokens(event.target.value)} />
                        </Field>
                        <div className="template-markers">
                            {markers.map((token, index) => (
                                <div key={`${token}-${index}`}>
                                    <code>{'${' + token + '}'}</code>
                                    <Badge tone={allowedTokens.includes(token) ? 'green' : 'rose'} dot={false}>
                                        {allowedTokens.includes(token) ? 'Dikenal' : 'Tidak dikenal'}
                                    </Badge>
                                </div>
                            ))}
                        </div>
                        {unknown.length > 0 && (
                            <p className="form-error">
                                Templat ditolak: penanda tidak dikenal. Perbaiki ejaan sebelum menyimpan.
                            </p>
                        )}
                        {file && !fileValid && (
                            <p className="form-error">Gunakan berkas DOCX tidak kosong, maksimal 20 MB.</p>
                        )}
                        <Button
                            icon={Upload}
                            disabled={!fileValid || unknown.length > 0 || markers.length === 0}
                            onClick={save}
                        >
                            Simpan sebagai v{template.version + 1}
                        </Button>
                        <p className="section-note">
                            Ekstraksi penanda dari DOCX asli dilakukan oleh backend PHPWord. Input di atas khusus
                            demonstrasi frontend.
                        </p>
                    </div>
                </Panel>
            </div>
            {history && (
                <Modal title="Riwayat versi templat" description={history.name} onClose={() => setHistory(null)}>
                    <div className="template-history">
                        {Array.from({ length: history.version }, (_, index) => history.version - index).map(
                            (version) => (
                                <div key={version}>
                                    <span className="file-icon">
                                        <FileClock size={18} />
                                    </span>
                                    <div>
                                        <strong>Versi {version}</strong>
                                        <p>
                                            {version === history.version
                                                ? 'Versi aktif saat ini'
                                                : 'Tersimpan · tidak dapat dihapus'}
                                        </p>
                                    </div>
                                    <Badge tone={version === history.version ? 'green' : 'neutral'} dot={false}>
                                        {version === history.version ? 'Aktif' : 'Arsip'}
                                    </Badge>
                                </div>
                            ),
                        )}
                    </div>
                </Modal>
            )}
        </>
    );
}

export function AuditPage() {
    const { data, notify } = usePreview();
    const [query, setQuery] = useState('');
    const [actor, setActor] = useState('');
    const [action, setAction] = useState('');
    const entries = data.audit.filter(
        (item) =>
            `${item.object} ${item.change}`.toLowerCase().includes(query.toLowerCase()) &&
            (!actor || item.actor === actor) &&
            (!action || item.action === action),
    );
    return (
        <>
            <PageHeader
                title="Log audit"
                eyebrow="JEJAK AKTIVITAS"
                description="Setiap keputusan memiliki konteks dan riwayat yang dapat ditelusuri."
            >
                <Button
                    variant="secondary"
                    icon={Download}
                    onClick={() =>
                        downloadCsv('log-audit-demo.csv', [
                            ['Waktu', 'Pelaku', 'Aksi', 'Objek', 'Perubahan', 'Sumber'],
                            ...entries.map((item) => [
                                item.time,
                                item.actor,
                                item.action,
                                item.object,
                                item.change,
                                item.source,
                            ]),
                        ])
                    }
                >
                    Ekspor CSV
                </Button>
                <Button variant="secondary" icon={Printer} onClick={() => window.print()}>
                    Cetak / PDF
                </Button>
            </PageHeader>
            <div className="filter-bar">
                <div className="filter-group">
                    <SearchInput
                        placeholder="Cari tiket atau objek..."
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                    />
                    <Select value={actor} onChange={(event) => setActor(event.target.value)}>
                        <option value="">Semua pelaku</option>
                        {[...new Set(data.audit.map((item) => item.actor))].map((item) => (
                            <option key={item}>{item}</option>
                        ))}
                    </Select>
                    <Select value={action} onChange={(event) => setAction(event.target.value)}>
                        <option value="">Semua aksi</option>
                        {[...new Set(data.audit.map((item) => item.action))].map((item) => (
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
                                <th>Waktu (WIB)</th>
                                <th>Pelaku</th>
                                <th>Aksi</th>
                                <th>Objek</th>
                                <th>Perubahan</th>
                                <th>Sumber</th>
                            </tr>
                        </thead>
                        <tbody>
                            {entries.map((item) => (
                                <tr key={item.id}>
                                    <td className="muted-cell">{item.time}</td>
                                    <td>
                                        <strong>{item.actor}</strong>
                                    </td>
                                    <td>{item.action}</td>
                                    <td>{item.object}</td>
                                    <td>{item.change}</td>
                                    <td>
                                        <Badge tone="neutral" dot={false}>
                                            {item.source}
                                        </Badge>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {!entries.length && <EmptyState title="Aktivitas tidak ditemukan" />}
                </div>
                <div className="table-footer">
                    <span>{entries.length} aktivitas demo · tanpa fungsi ubah/hapus</span>
                    <ShieldCheck size={14} />
                </div>
            </Panel>
            <div className="mt-5">
                <InfoBox>
                    Log di sini adalah demonstrasi riwayat UI. Penyimpanan append-only dan rantai hash belum terhubung,
                    sehingga integritas kriptografis tidak diklaim terverifikasi.
                </InfoBox>
            </div>
        </>
    );
}

const masterTabs = [
    ['forms', 'Sediaan & kategori'],
    ['ingredients', 'Bahan baku'],
    ['stages', 'Tahapan & SLA'],
    ['rules', 'Aturan dokumen'],
    ['weights', 'Kriteria AHP'],
    ['holidays', 'Hari libur'],
];
export function MasterPage({ initialTab }: { initialTab?: string | null }) {
    const { data, update } = usePreview();
    const [tab, setTab] = useState(masterTabs.some(([key]) => key === initialTab) ? initialTab! : 'forms');
    const [edit, setEdit] = useState<{ type: string; item?: MasterItem } | null>(null);
    const [rule, setRule] = useState(data.masters.rules[0].id);
    const selectedRule = data.masters.rules.find((item) => item.id === rule) ?? data.masters.rules[0];
    const rows = data.masters[tab] ?? [];
    function list(type: string, title: string) {
        return (
            <Panel
                title={title}
                action={
                    <Button variant="secondary" icon={Plus} onClick={() => setEdit({ type })}>
                        Tambah
                    </Button>
                }
            >
                <div className="master-simple-list">
                    {data.masters[type].map((item) => (
                        <div key={item.id}>
                            <span>{item.name}</span>
                            <Button variant="ghost" icon={Pencil} onClick={() => setEdit({ type, item })}>
                                Ubah
                            </Button>
                        </div>
                    ))}
                </div>
            </Panel>
        );
    }
    return (
        <>
            <PageHeader
                title="Master data"
                eyebrow="KONFIGURASI SISTEM"
                description="Aturan yang mudah dikelola, tanpa mengubah kode aplikasi."
            />
            <div className="tabs master-tabs">
                {masterTabs.map(([key, label]) => (
                    <button className={key === tab ? 'active' : ''} key={key} onClick={() => setTab(key)}>
                        {label}
                    </button>
                ))}
            </div>
            {tab === 'forms' ? (
                <div className="two-column">
                    {list('forms', 'Sediaan produk')}
                    {list('categories', 'Kategori produk')}
                </div>
            ) : tab === 'weights' ? (
                <WeightsPanel />
            ) : (
                <>
                    <div className="flex justify-end mb-4">
                        <Button variant="secondary" icon={Plus} onClick={() => setEdit({ type: tab })}>
                            Tambah{' '}
                            {tab === 'ingredients'
                                ? 'bahan baku'
                                : tab === 'holidays'
                                  ? 'hari libur'
                                  : tab === 'stages'
                                    ? 'tahap'
                                    : 'aturan'}
                        </Button>
                    </div>
                    <div className={tab === 'rules' ? 'two-column rules-grid' : ''}>
                        <Panel>
                            <div className="table-scroll">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>
                                                {tab === 'holidays'
                                                    ? 'Tanggal'
                                                    : tab === 'ingredients'
                                                      ? 'Bahan baku'
                                                      : tab === 'stages'
                                                        ? 'Tahap'
                                                        : 'Jenis dokumen'}
                                            </th>
                                            <th>
                                                {tab === 'holidays'
                                                    ? 'Keterangan'
                                                    : tab === 'ingredients'
                                                      ? 'Pemasok'
                                                      : tab === 'stages'
                                                        ? 'Fase'
                                                        : 'Fase & cakupan'}
                                            </th>
                                            {tab !== 'holidays' && (
                                                <th>
                                                    {tab === 'stages'
                                                        ? 'SLA (hari kerja)'
                                                        : tab === 'rules'
                                                          ? 'Syarat'
                                                          : 'Dokumen asal peredaran'}
                                                </th>
                                            )}
                                            <th />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rows.map((item) => (
                                            <tr
                                                className={tab === 'rules' && item.id === rule ? 'selected-row' : ''}
                                                key={item.id}
                                                onClick={() => {
                                                    if (tab === 'rules') setRule(item.id);
                                                }}
                                            >
                                                <td>
                                                    <strong>
                                                        {tab === 'holidays' ? dateLabel(item.detail) : item.name}
                                                    </strong>
                                                </td>
                                                <td>{tab === 'holidays' ? item.name : item.detail}</td>
                                                {tab !== 'holidays' && (
                                                    <td>
                                                        {tab === 'stages' ? (
                                                            item.value
                                                        ) : (
                                                            <Badge>{item.kind ?? 'Belum ada'}</Badge>
                                                        )}
                                                    </td>
                                                )}
                                                <td>
                                                    <div className="flex gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            icon={Pencil}
                                                            onClick={(event) => {
                                                                event.stopPropagation();
                                                                setEdit({ type: tab, item });
                                                            }}
                                                        >
                                                            Ubah
                                                        </Button>
                                                        {tab === 'holidays' && (
                                                            <Button
                                                                variant="ghost"
                                                                icon={Trash2}
                                                                onClick={() =>
                                                                    update(
                                                                        (state) => ({
                                                                            ...state,
                                                                            masters: {
                                                                                ...state.masters,
                                                                                holidays: state.masters.holidays.filter(
                                                                                    (entry) => entry.id !== item.id,
                                                                                ),
                                                                            },
                                                                        }),
                                                                        'Hari libur dihapus dari demo.',
                                                                    )
                                                                }
                                                            >
                                                                Hapus
                                                            </Button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </Panel>
                        {tab === 'rules' && <ValidationPanel key={selectedRule.id} item={selectedRule} />}
                    </div>
                </>
            )}
            {tab === 'stages' && (
                <div className="mt-5">
                    <InfoBox>
                        SLA dalam hari kerja, Senin–Jumat 08.00–16.00 WIB. Tahap menunggu BPOM tidak dihitung. Nilai
                        awal adalah contoh yang perlu dikalibrasi bersama perusahaan.
                    </InfoBox>
                </div>
            )}
            {tab === 'holidays' && (
                <div className="mt-5">
                    <InfoBox>
                        Hari libur dan cuti bersama dikecualikan dari perhitungan SLA serta batas respons. Perhitungan
                        kalender dilakukan oleh backend.
                    </InfoBox>
                </div>
            )}
            {edit && <MasterDialog type={edit.type} item={edit.item} onClose={() => setEdit(null)} />}
        </>
    );
}

function MasterDialog({ type, item, onClose }: { type: string; item?: MasterItem; onClose: () => void }) {
    const { update } = usePreview();
    const [form, setForm] = useState({
        name: item?.name ?? '',
        detail: item?.detail ?? '',
        value: item?.value ?? '',
        kind: item?.kind ?? (type === 'rules' ? 'Wajib' : ''),
    });
    function save(event: FormEvent) {
        event.preventDefault();
        if (!form.name.trim()) return;
        const value = { ...form, id: item?.id ?? crypto.randomUUID() };
        update(
            (state) => ({
                ...state,
                masters: {
                    ...state.masters,
                    [type]: item
                        ? state.masters[type].map((row) => (row.id === item.id ? value : row))
                        : [...state.masters[type], value],
                },
            }),
            'Master data diperbarui pada demo.',
        );
        onClose();
    }
    return (
        <Modal
            title={`${item ? 'Ubah' : 'Tambah'} ${type === 'forms' ? 'sediaan' : type === 'categories' ? 'kategori' : type === 'ingredients' ? 'bahan baku' : type === 'holidays' ? 'hari libur' : type === 'stages' ? 'tahap & SLA' : 'aturan dokumen'}`}
            onClose={onClose}
        >
            <form className="form-stack" onSubmit={save}>
                <Field label={type === 'holidays' ? 'Keterangan hari libur' : 'Nama'}>
                    <input
                        required
                        value={form.name}
                        onChange={(event) => setForm({ ...form, name: event.target.value })}
                    />
                </Field>
                {!['forms', 'categories'].includes(type) && (
                    <Field
                        label={
                            type === 'ingredients'
                                ? 'Pemasok'
                                : type === 'holidays'
                                  ? 'Tanggal'
                                  : type === 'stages'
                                    ? 'Fase'
                                    : 'Fase & cakupan'
                        }
                    >
                        {type === 'stages' ? (
                            <Select
                                value={form.detail}
                                onChange={(event) => setForm({ ...form, detail: event.target.value })}
                            >
                                <option value="">Pilih fase</option>
                                <option>Awal</option>
                                <option>Pra-registrasi</option>
                                <option>Registrasi</option>
                                <option>Kedua fase</option>
                            </Select>
                        ) : (
                            <input
                                required
                                type={type === 'holidays' ? 'date' : 'text'}
                                value={form.detail}
                                onChange={(event) => setForm({ ...form, detail: event.target.value })}
                            />
                        )}
                    </Field>
                )}
                {['stages', 'ingredients', 'rules'].includes(type) && (
                    <Field
                        label={
                            type === 'stages'
                                ? 'SLA hari kerja / Tidak dihitung'
                                : type === 'ingredients'
                                  ? 'Berlaku sampai'
                                  : 'Berlaku untuk'
                        }
                    >
                        <input
                            value={form.value}
                            onChange={(event) => setForm({ ...form, value: event.target.value })}
                        />
                    </Field>
                )}
                {type === 'rules' && (
                    <Field label="Sifat dokumen">
                        <Select value={form.kind} onChange={(event) => setForm({ ...form, kind: event.target.value })}>
                            <option>Wajib</option>
                            <option>Kondisional</option>
                            <option>Opsional</option>
                        </Select>
                    </Field>
                )}
                <div className="form-footer">
                    <Button variant="secondary" onClick={onClose}>
                        Batal
                    </Button>
                    <Button type="submit" disabled={!form.name.trim()}>
                        Simpan perubahan
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

function ValidationPanel({ item }: { item: MasterItem }) {
    const { data, update } = usePreview();
    const [expiry, setExpiry] = useState(item.name.includes('halal'));
    const [age, setAge] = useState('');
    const [product, setProduct] = useState(false);
    return (
        <Panel title={`Validasi · ${item.name}`} description="Konfigurasi contoh pemeriksaan per jenis dokumen.">
            <div className="panel-body form-stack">
                <Field label="Format diterima">
                    <Select>
                        <option>PDF</option>
                        <option>PDF, JPG, PNG, DOCX, XLSX</option>
                    </Select>
                </Field>
                <Field label="Ukuran maksimal">
                    <input value="20 MB" readOnly />
                </Field>
                <label className="manual-check">
                    <input type="checkbox" defaultChecked />
                    Nomor dokumen wajib
                </label>
                <label className="manual-check">
                    <input type="checkbox" defaultChecked />
                    Tanggal terbit wajib
                </label>
                <label className="manual-check">
                    <input type="checkbox" checked={expiry} onChange={(event) => setExpiry(event.target.checked)} />
                    Memiliki tanggal kedaluwarsa
                </label>
                <Field label="Umur maksimal (hari)">
                    <input
                        type="number"
                        min="1"
                        placeholder="Menunggu konfirmasi RA"
                        value={age}
                        onChange={(event) => setAge(event.target.value)}
                    />
                </Field>
                <label className="manual-check">
                    <input type="checkbox" checked={product} onChange={(event) => setProduct(event.target.checked)} />
                    Cocokkan nama produk pada PDF teks
                </label>
                <InfoBox>
                    PDF pindaian tidak dibaca dengan OCR. Nama produk tidak otomatis diwajibkan untuk dokumen bersama
                    atau CoA bahan baku.
                </InfoBox>
                <Button
                    onClick={() =>
                        update(
                            (state) => ({
                                ...state,
                                masters: {
                                    ...state.masters,
                                    validation: [
                                        ...(state.masters.validation ?? []).filter((row) => row.id !== item.id),
                                        {
                                            id: item.id,
                                            name: item.name,
                                            detail: JSON.stringify({ expiry, age, product }),
                                        },
                                    ],
                                },
                            }),
                            'Konfigurasi validasi disimpan pada demo; mesin validasi isi belum terhubung.',
                        )
                    }
                >
                    Simpan aturan demo
                </Button>
            </div>
        </Panel>
    );
}

function WeightsPanel() {
    const { data, update } = usePreview();
    const defaultWeights = ['0.455', '0.263', '0.141', '0.141'];
    const saved = data.masters.weights;
    const [edit, setEdit] = useState(false);
    const [weights, setWeights] = useState(saved?.map((item) => item.value ?? '0') ?? defaultWeights);
    const [cr, setCr] = useState('0.004');
    const sum = weights.reduce((total, val) => total + Number(val), 0);
    const valid =
        Math.abs(sum - 1) < 0.0001 &&
        weights.every((weight) => Number(weight) > 0 && Number(weight) <= 1) &&
        Number(cr) >= 0 &&
        Number(cr) <= 0.1;
    const names = ['Critical Ratio', 'Sisa hari tenggat BPOM', 'Kelengkapan dokumen wajib', 'Umur tiket'];
    const current = saved?.map((item) => item.value ?? '0') ?? defaultWeights;
    return (
        <div className="two-column weights-grid">
            <Panel title="Kriteria prioritas" description="Jenis kriteria berbeda sesuai antrean pekerjaan.">
                <div className="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Kriteria</th>
                                <th>Antrean RA</th>
                                <th>Pengunggah</th>
                                <th>Bobot demo</th>
                            </tr>
                        </thead>
                        <tbody>
                            {names.map((name, index) => (
                                <tr key={name}>
                                    <td>
                                        <Badge tone="neutral" dot={false}>
                                            C{index + 1}
                                        </Badge>
                                    </td>
                                    <td>
                                        <strong>{name}</strong>
                                    </td>
                                    <td>{index === 2 || index === 3 ? 'Benefit' : 'Cost'}</td>
                                    <td>{index === 3 ? 'Benefit' : 'Cost'}</td>
                                    <td>{Number(current[index]).toFixed(3).replace('.', ',')}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Panel>
            <Panel title="Bobot dari kuesioner AHP">
                <div className="panel-body">
                    <div className="consistency-icon">
                        <ShieldCheck size={29} />
                    </div>
                    <dl className="detail-list">
                        <div>
                            <dt>Responden</dt>
                            <dd>Manajer dan RA</dd>
                        </div>
                        <div>
                            <dt>CR AHP contoh</dt>
                            <dd>{cr.replace('.', ',')}</dd>
                        </div>
                    </dl>
                    <Button variant="secondary" className="w-full mt-5" onClick={() => setEdit(true)}>
                        Perbarui bobot hasil kuesioner
                    </Button>
                    <p className="section-note">
                        Bobot dan CR adalah ilustrasi/input, bukan hasil kalkulasi AHP di frontend. CR prioritas tiket
                        adalah konsep yang berbeda.
                    </p>
                </div>
            </Panel>
            {edit && (
                <Modal
                    title="Bobot hasil kuesioner AHP"
                    description="Masukkan bobot dan CR yang telah dihitung dan disahkan."
                    onClose={() => setEdit(false)}
                >
                    <div className="form-stack">
                        {names.map((name, index) => (
                            <Field label={`C${index + 1} · ${name}`} key={name}>
                                <input
                                    type="number"
                                    step="0.001"
                                    min="0"
                                    max="1"
                                    value={weights[index]}
                                    onChange={(event) =>
                                        setWeights(weights.map((val, i) => (i === index ? event.target.value : val)))
                                    }
                                />
                            </Field>
                        ))}
                        <Field label="Consistency Ratio AHP">
                            <input
                                type="number"
                                step="0.001"
                                min="0"
                                max="1"
                                value={cr}
                                onChange={(event) => setCr(event.target.value)}
                            />
                        </Field>
                        <InfoBox warning={!valid}>
                            Total bobot: {sum.toFixed(3)}. Jumlah harus 1 dan CR AHP maksimal 0,10.
                        </InfoBox>
                    </div>
                    <div className="form-footer">
                        <Button variant="secondary" onClick={() => setEdit(false)}>
                            Batal
                        </Button>
                        <Button
                            disabled={!valid}
                            onClick={() => {
                                update(
                                    (state) => ({
                                        ...state,
                                        masters: {
                                            ...state.masters,
                                            weights: names.map((name, i) => ({
                                                id: `C${i + 1}`,
                                                name,
                                                detail: 'Bobot kuesioner demo',
                                                value: weights[i],
                                            })),
                                        },
                                    }),
                                    'Bobot kuesioner disimpan sebagai data demo.',
                                );
                                setEdit(false);
                            }}
                        >
                            Simpan bobot
                        </Button>
                    </div>
                </Modal>
            )}
        </div>
    );
}
