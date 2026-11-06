import { router, useForm, usePage } from '@inertiajs/react';
import { CalendarDays, FileText, FlaskConical, ListOrdered, Pencil, Plus, Tags, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import type { BackendSharedProps } from '../../backend-types';
import { Badge, Button, EmptyState, Field, InfoBox, Modal, PageHeader, Panel, Select } from '../../components/ui';
import { BackendLayout } from '../../layouts/backend-layout';

type MasterTab = 'forms' | 'ingredients' | 'stages' | 'documents' | 'weights' | 'holidays';
type NamedItem = { id: number; name: string; is_active: boolean; update_url: string };
type MaterialItem = NamedItem & { supplier: string | null };
type StageItem = NamedItem & { code: string; phase: string; phase_label: string; sort_order: number; sla_days: number | null; waits_for_external: boolean };
type PhaseOption = { value: string; label: string };
type DocumentTypeItem = NamedItem & {
    phase: string; phase_label: string; scope: string; scope_label: string; requirement: string; requirement_label: string;
    pic_role: string; pic_label: string; verifier_role: string | null; verifier_label: string | null; requires_expiry_date: boolean; checks_product_name?: boolean; max_age_months: number | null;
};
type DocumentOptions = { phases: PhaseOption[]; scopes: PhaseOption[]; requirements: PhaseOption[]; pics: PhaseOption[]; verifiers: PhaseOption[] };
type CriterionItem = { code: string; name: string; weight: number; type: string };
type CriteriaQueue = { queue: string; label: string; update_url: string; criteria: CriterionItem[] };
type HolidayItem = { id: number; date: string; name: string; update_url: string; delete_url: string };
type MasterUrls = { dosageForms: string; categories: string; materials: string; stages: string; documentTypes: string; holidays: string };
type NamedKind = { title: string; noun: string; storeUrl: string };
type Editor =
    | { kind: 'named'; list: NamedKind; item: NamedItem | null }
    | { kind: 'material'; item: MaterialItem | null }
    | { kind: 'stage'; item: StageItem | null }
    | { kind: 'document'; item: DocumentTypeItem | null }
    | { kind: 'weights'; queue: CriteriaQueue }
    | { kind: 'holiday'; item: HolidayItem | null };

const tabs: Array<[MasterTab, string]> = [
    ['forms', 'Sediaan & kategori'],
    ['ingredients', 'Bahan baku'],
    ['stages', 'Tahapan & SLA'],
    ['documents', 'Jenis dokumen'],
    ['weights', 'Kriteria AHP'],
    ['holidays', 'Hari libur'],
];

export default function Master({ tab, dosageForms, categories, materials, stages, phases, documentTypes, documentOptions, criteriaQueues, criterionTypes, holidays, urls }: { tab: MasterTab; dosageForms: NamedItem[]; categories: NamedItem[]; materials: MaterialItem[]; stages: StageItem[]; phases: PhaseOption[]; documentTypes: DocumentTypeItem[]; documentOptions: DocumentOptions; criteriaQueues: CriteriaQueue[]; criterionTypes: PhaseOption[]; holidays: HolidayItem[]; urls: MasterUrls }) {
    const { routes } = usePage<BackendSharedProps>().props;
    const [activeTab, setActiveTab] = useState<MasterTab>(tab);
    const [editor, setEditor] = useState<Editor | null>(null);
    const forms: NamedKind = { title: 'Sediaan produk', noun: 'sediaan', storeUrl: urls.dosageForms };
    const kinds: NamedKind = { title: 'Kategori produk', noun: 'kategori', storeUrl: urls.categories };

    function openTab(next: MasterTab) {
        setActiveTab(next);
        router.get(routes.master, { tab: next }, { preserveState: true, preserveScroll: true, replace: true });
    }

    function removeHoliday(holiday: HolidayItem) {
        if (window.confirm(`Hapus hari libur ${holiday.name} (${formatDate(holiday.date)})?`)) router.delete(holiday.delete_url, { preserveScroll: true });
    }

    return (
        <BackendLayout title="Master data">
            <PageHeader title="Master data" eyebrow="KONFIGURASI SISTEM" description="Aturan yang mudah dikelola tanpa mengubah kode aplikasi. Setiap perubahan dicatat dalam audit." />
            <div className="tabs master-tabs" role="tablist">
                {tabs.map(([key, label]) => <button type="button" role="tab" aria-selected={key === activeTab} className={key === activeTab ? 'active' : ''} key={key} onClick={() => openTab(key)}>{label}</button>)}
            </div>
            {activeTab === 'forms' && (
                <div className="two-column">
                    <NamedList list={forms} items={dosageForms} onEdit={(item) => setEditor({ kind: 'named', list: forms, item })} />
                    <NamedList list={kinds} items={categories} onEdit={(item) => setEditor({ kind: 'named', list: kinds, item })} />
                </div>
            )}
            {activeTab === 'ingredients' && (
                <Panel title="Bahan baku" description="R&D juga dapat menambah bahan baku baru saat menyusun daftar bahan tiket." action={<Button variant="secondary" icon={Plus} onClick={() => setEditor({ kind: 'material', item: null })}>Tambah bahan baku</Button>}>
                    {materials.length ? (
                        <div className="overflow-x-auto">
                            <table className="w-full"><thead><tr><th>Bahan baku</th><th>Pemasok</th><th>Status</th><th>Aksi</th></tr></thead>
                                <tbody>{materials.map((material) => <tr key={material.id}>
                                    <td><strong>{material.name}</strong></td>
                                    <td>{material.supplier ?? '—'}</td>
                                    <td><StatusBadge active={material.is_active} /></td>
                                    <td><Button variant="ghost" icon={Pencil} aria-label={`Ubah ${material.name}`} onClick={() => setEditor({ kind: 'material', item: material })}>Ubah</Button></td>
                                </tr>)}</tbody>
                            </table>
                        </div>
                    ) : <EmptyState title="Belum ada bahan baku" description="Tambahkan bahan baku yang dipakai dalam produk klien." icon={FlaskConical} />}
                </Panel>
            )}
            {activeTab === 'stages' && (
                <>
                    <Panel title="Tahapan & SLA" description="Urutan proses pendaftaran dan batas waktu tiap tahap dalam hari kerja." action={<Button variant="secondary" icon={Plus} onClick={() => setEditor({ kind: 'stage', item: null })}>Tambah tahap</Button>}>
                        {stages.length ? (
                            <div className="overflow-x-auto">
                                <table className="w-full"><thead><tr><th>Urutan</th><th>Tahap</th><th>Fase</th><th>SLA</th><th>Jenis</th><th>Status</th><th>Aksi</th></tr></thead>
                                    <tbody>{stages.map((stage) => <tr key={stage.id}>
                                        <td>{stage.sort_order}</td>
                                        <td><strong>{stage.name}</strong><div className="text-xs text-gray-500">{stage.code}</div></td>
                                        <td>{stage.phase_label}</td>
                                        <td>{stage.sla_days === null ? '—' : `${stage.sla_days} hari kerja`}</td>
                                        <td>{stage.waits_for_external ? <Badge tone="amber">Menunggu pihak luar</Badge> : 'Internal'}</td>
                                        <td><StatusBadge active={stage.is_active} /></td>
                                        <td><Button variant="ghost" icon={Pencil} aria-label={`Ubah ${stage.name}`} onClick={() => setEditor({ kind: 'stage', item: stage })}>Ubah</Button></td>
                                    </tr>)}</tbody>
                                </table>
                            </div>
                        ) : <EmptyState title="Belum ada tahapan" description="Tahapan bawaan dibuat saat aplikasi dipasang." icon={ListOrdered} />}
                    </Panel>
                    <div className="mt-5"><InfoBox>SLA dihitung dalam hari kerja dan berhenti selama tahap menunggu BPOM atau pihak luar. Kode tahap tetap walaupun nama diubah, karena dipakai alur tiket.</InfoBox></div>
                </>
            )}
            {activeTab === 'documents' && (
                <>
                    <Panel title="Jenis dokumen" description="Dokumen pada checklist pra-registrasi dan registrasi beserta PIC dan aturan masa berlakunya." action={<Button variant="secondary" icon={Plus} onClick={() => setEditor({ kind: 'document', item: null })}>Tambah jenis dokumen</Button>}>
                        {documentTypes.length ? (
                            <div className="overflow-x-auto">
                                <table className="w-full"><thead><tr><th>Jenis dokumen</th><th>Fase</th><th>Cakupan</th><th>Sifat</th><th>PIC</th><th>Verifikator isi</th><th>Masa berlaku</th><th>Status</th><th>Aksi</th></tr></thead>
                                    <tbody>{documentTypes.map((type) => <tr key={type.id}>
                                        <td><strong>{type.name}</strong></td>
                                        <td>{type.phase_label}</td>
                                        <td>{type.scope_label}</td>
                                        <td>{type.requirement_label}</td>
                                        <td>{type.pic_label}</td>
                                        <td>{type.verifier_label ?? 'Hanya RA'}</td>
                                        <td>{validityLabel(type)}</td>
                                        <td><StatusBadge active={type.is_active} /></td>
                                        <td><Button variant="ghost" icon={Pencil} aria-label={`Ubah ${type.name}`} onClick={() => setEditor({ kind: 'document', item: type })}>Ubah</Button></td>
                                    </tr>)}</tbody>
                                </table>
                            </div>
                        ) : <EmptyState title="Belum ada jenis dokumen" description="Daftar dokumen per fase menunggu konfirmasi Regulatory Affairs." icon={FileText} />}
                    </Panel>
                    <div className="mt-5"><InfoBox>Dokumen ber-PIC klien diunggah melalui tautan bertoken. RA selalu menyetujui pemakaian dokumen; verifikator isi menambah pemeriksaan sebelum itu.</InfoBox></div>
                </>
            )}
            {activeTab === 'weights' && (
                <>
                    <div className="two-column">
                        {criteriaQueues.map((queue) => (
                            <Panel key={queue.queue} title={`Antrean ${queue.label}`} action={<Button variant="secondary" icon={Pencil} aria-label={`Ubah bobot ${queue.label}`} onClick={() => setEditor({ kind: 'weights', queue })}>Ubah</Button>}>
                                <div className="overflow-x-auto">
                                    <table className="w-full"><thead><tr><th>Kode</th><th>Kriteria</th><th>Bobot</th><th>Jenis</th></tr></thead>
                                        <tbody>{queue.criteria.map((criterion) => <tr key={criterion.code}>
                                            <td><strong>{criterion.code}</strong></td>
                                            <td>{criterion.name}</td>
                                            <td>{formatWeight(criterion.weight)}</td>
                                            <td><Badge tone={criterion.type === 'benefit' ? 'green' : 'neutral'}>{criterionTypes.find((type) => type.value === criterion.type)?.label ?? criterion.type}</Badge></td>
                                        </tr>)}</tbody>
                                    </table>
                                </div>
                                <p className="mt-3 text-sm text-gray-500">Total {formatWeight(queue.criteria.reduce((sum, criterion) => sum + criterion.weight, 0))}</p>
                            </Panel>
                        ))}
                    </div>
                    <div className="mt-5"><InfoBox>Bobot awal adalah hasil AHP konsensus Manajer dan RA dengan rasio konsistensi 0,057. Benefit: makin besar makin diprioritaskan; cost: makin kecil makin diprioritaskan.</InfoBox></div>
                </>
            )}
            {activeTab === 'holidays' && (
                <>
                    <Panel title="Hari libur" description="Libur nasional dan cuti bersama yang tidak dihitung sebagai hari kerja." action={<Button variant="secondary" icon={Plus} onClick={() => setEditor({ kind: 'holiday', item: null })}>Tambah hari libur</Button>}>
                        {holidays.length ? (
                            <div className="overflow-x-auto">
                                <table className="w-full"><thead><tr><th>Tanggal</th><th>Keterangan</th><th>Aksi</th></tr></thead>
                                    <tbody>{holidays.map((holiday) => <tr key={holiday.id}>
                                        <td><strong>{formatDate(holiday.date)}</strong></td>
                                        <td>{holiday.name}</td>
                                        <td><div className="flex gap-1">
                                            <Button variant="ghost" icon={Pencil} aria-label={`Ubah ${holiday.name}`} onClick={() => setEditor({ kind: 'holiday', item: holiday })}>Ubah</Button>
                                            <Button variant="ghost" icon={Trash2} aria-label={`Hapus ${holiday.name}`} onClick={() => removeHoliday(holiday)}>Hapus</Button>
                                        </div></td>
                                    </tr>)}</tbody>
                                </table>
                            </div>
                        ) : <EmptyState title="Belum ada hari libur" description="Tambahkan libur nasional dan cuti bersama tahun berjalan." icon={CalendarDays} />}
                    </Panel>
                    <div className="mt-5"><InfoBox>SLA dihitung dalam hari kerja Senin–Jumat, 08.00–16.00 WIB. Tanggal di daftar ini dikecualikan dari perhitungan.</InfoBox></div>
                </>
            )}
            {editor?.kind === 'named' && <NamedEditor key={editor.item?.id ?? 'new'} list={editor.list} item={editor.item} onClose={() => setEditor(null)} />}
            {editor?.kind === 'material' && <MaterialEditor key={editor.item?.id ?? 'new'} item={editor.item} storeUrl={urls.materials} onClose={() => setEditor(null)} />}
            {editor?.kind === 'stage' && <StageEditor key={editor.item?.id ?? 'new'} item={editor.item} phases={phases} nextOrder={Math.max(0, ...stages.map((stage) => stage.sort_order)) + 1} storeUrl={urls.stages} onClose={() => setEditor(null)} />}
            {editor?.kind === 'document' && <DocumentTypeEditor key={editor.item?.id ?? 'new'} item={editor.item} options={documentOptions} storeUrl={urls.documentTypes} onClose={() => setEditor(null)} />}
            {editor?.kind === 'weights' && <WeightsEditor key={editor.queue.queue} queue={editor.queue} types={criterionTypes} onClose={() => setEditor(null)} />}
            {editor?.kind === 'holiday' && <HolidayEditor key={editor.item?.id ?? 'new'} item={editor.item} storeUrl={urls.holidays} onClose={() => setEditor(null)} />}
        </BackendLayout>
    );
}

function NamedList({ list, items, onEdit }: { list: NamedKind; items: NamedItem[]; onEdit: (item: NamedItem | null) => void }) {
    return (
        <Panel title={list.title} action={<Button variant="secondary" icon={Plus} onClick={() => onEdit(null)}>Tambah {list.noun}</Button>}>
            {items.length ? (
                <div className="overflow-x-auto">
                    <table className="w-full"><thead><tr><th>Nama</th><th>Status</th><th>Aksi</th></tr></thead>
                        <tbody>{items.map((item) => <tr key={item.id}>
                            <td><strong>{item.name}</strong></td>
                            <td><StatusBadge active={item.is_active} /></td>
                            <td><Button variant="ghost" icon={Pencil} aria-label={`Ubah ${item.name}`} onClick={() => onEdit(item)}>Ubah</Button></td>
                        </tr>)}</tbody>
                    </table>
                </div>
            ) : <EmptyState title={`Belum ada ${list.noun}`} description="Daftar ini menunggu konfirmasi Regulatory Affairs." icon={Tags} />}
        </Panel>
    );
}

function NamedEditor({ list, item, onClose }: { list: NamedKind; item: NamedItem | null; onClose: () => void }) {
    const form = useForm({ name: item?.name ?? '', is_active: item?.is_active ?? true });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (item) form.put(item.update_url, { preserveScroll: true, onSuccess: onClose });
        else form.post(list.storeUrl, { preserveScroll: true, onSuccess: onClose });
    }

    return (
        <Modal title={item ? `Ubah ${list.noun}` : `Tambah ${list.noun}`} description="Data yang dinonaktifkan tidak dapat dipilih untuk tiket baru, tetapi tetap tersimpan pada tiket lama." onClose={onClose}>
            <form onSubmit={submit} className="form-stack">
                <Field label={`Nama ${list.noun}`} error={form.errors.name}><input autoFocus required maxLength={100} value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></Field>
                {item && <ActiveToggle checked={form.data.is_active} error={form.errors.is_active} onChange={(checked) => form.setData('is_active', checked)} />}
                <EditorFooter processing={form.processing} onClose={onClose} />
            </form>
        </Modal>
    );
}

function MaterialEditor({ item, storeUrl, onClose }: { item: MaterialItem | null; storeUrl: string; onClose: () => void }) {
    const form = useForm({ name: item?.name ?? '', supplier: item?.supplier ?? '', is_active: item?.is_active ?? true });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (item) form.put(item.update_url, { preserveScroll: true, onSuccess: onClose });
        else form.post(storeUrl, { preserveScroll: true, onSuccess: onClose });
    }

    return (
        <Modal title={item ? 'Ubah bahan baku' : 'Tambah bahan baku'} description="Dokumen asal peredaran bahan dikelola pada tahap pendaftaran tiket." onClose={onClose}>
            <form onSubmit={submit} className="form-stack">
                <Field label="Nama bahan baku" error={form.errors.name}><input autoFocus required maxLength={150} value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></Field>
                <Field label="Pemasok" error={form.errors.supplier} hint="Opsional."><input maxLength={150} value={form.data.supplier} onChange={(event) => form.setData('supplier', event.target.value)} /></Field>
                {item && <ActiveToggle checked={form.data.is_active} error={form.errors.is_active} onChange={(checked) => form.setData('is_active', checked)} />}
                <EditorFooter processing={form.processing} onClose={onClose} />
            </form>
        </Modal>
    );
}

function StageEditor({ item, phases, nextOrder, storeUrl, onClose }: { item: StageItem | null; phases: PhaseOption[]; nextOrder: number; storeUrl: string; onClose: () => void }) {
    const form = useForm({
        name: item?.name ?? '',
        phase: item?.phase ?? phases[0]?.value ?? '',
        sort_order: String(item?.sort_order ?? nextOrder),
        sla_days: item?.sla_days === null || item?.sla_days === undefined ? '' : String(item.sla_days),
        waits_for_external: item?.waits_for_external ?? false,
        is_active: item?.is_active ?? true,
    });

    function setWaiting(waiting: boolean) {
        form.setData((data) => ({ ...data, waits_for_external: waiting, sla_days: waiting ? '' : data.sla_days }));
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (item) form.put(item.update_url, { preserveScroll: true, onSuccess: onClose });
        else form.post(storeUrl, { preserveScroll: true, onSuccess: onClose });
    }

    return (
        <Modal title={item ? 'Ubah tahap' : 'Tambah tahap'} description={item ? `Kode tahap: ${item.code}` : 'Kode tahap dibuat dari nama dan tidak berubah setelah disimpan.'} onClose={onClose}>
            <form onSubmit={submit} className="form-stack">
                <Field label="Nama tahap" error={form.errors.name}><input autoFocus required maxLength={100} value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></Field>
                <Field label="Fase" error={form.errors.phase}><Select value={form.data.phase} onChange={(event) => form.setData('phase', event.target.value)}>{phases.map((phase) => <option key={phase.value} value={phase.value}>{phase.label}</option>)}</Select></Field>
                <Field label="Urutan" error={form.errors.sort_order}><input type="number" required min={1} max={999} value={form.data.sort_order} onChange={(event) => form.setData('sort_order', event.target.value)} /></Field>
                <label className="flex items-center gap-2"><input type="checkbox" checked={form.data.waits_for_external} onChange={(event) => setWaiting(event.target.checked)} />Menunggu BPOM atau pihak luar</label>
                <Field label="SLA (hari kerja)" error={form.errors.sla_days} hint={form.data.waits_for_external ? 'Tidak dipakai: perhitungan SLA berhenti selama menunggu pihak luar.' : undefined}><input type="number" min={1} max={365} required={!form.data.waits_for_external} disabled={form.data.waits_for_external} value={form.data.sla_days} onChange={(event) => form.setData('sla_days', event.target.value)} /></Field>
                {item && <ActiveToggle checked={form.data.is_active} error={form.errors.is_active} onChange={(checked) => form.setData('is_active', checked)} />}
                <EditorFooter processing={form.processing} onClose={onClose} />
            </form>
        </Modal>
    );
}

function DocumentTypeEditor({ item, options, storeUrl, onClose }: { item: DocumentTypeItem | null; options: DocumentOptions; storeUrl: string; onClose: () => void }) {
    const form = useForm({
        name: item?.name ?? '',
        phase: item?.phase ?? options.phases[0]?.value ?? '',
        scope: item?.scope ?? options.scopes[0]?.value ?? '',
        requirement: item?.requirement ?? options.requirements[0]?.value ?? '',
        pic_role: item?.pic_role ?? options.pics[0]?.value ?? '',
        verifier_role: item?.verifier_role ?? '',
        requires_expiry_date: item?.requires_expiry_date ?? false,
        checks_product_name: item?.checks_product_name ?? false,
        max_age_months: item?.max_age_months === null || item?.max_age_months === undefined ? '' : String(item.max_age_months),
        is_active: item?.is_active ?? true,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (item) form.put(item.update_url, { preserveScroll: true, onSuccess: onClose });
        else form.post(storeUrl, { preserveScroll: true, onSuccess: onClose });
    }

    return (
        <Modal title={item ? 'Ubah jenis dokumen' : 'Tambah jenis dokumen'} description="Perubahan berlaku untuk checklist tiket baru; tiket aktif disinkronkan oleh RA." onClose={onClose}>
            <form onSubmit={submit} className="form-stack">
                <Field label="Nama jenis dokumen" error={form.errors.name}><input autoFocus required maxLength={150} value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></Field>
                <Field label="Fase" error={form.errors.phase}><Select value={form.data.phase} onChange={(event) => form.setData('phase', event.target.value)}>{options.phases.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</Select></Field>
                <Field label="Cakupan" error={form.errors.scope} hint="Dokumen klien, bahan baku, dan perusahaan dipakai ulang lintas tiket."><Select value={form.data.scope} onChange={(event) => form.setData('scope', event.target.value)}>{options.scopes.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</Select></Field>
                <Field label="Sifat" error={form.errors.requirement}><Select value={form.data.requirement} onChange={(event) => form.setData('requirement', event.target.value)}>{options.requirements.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</Select></Field>
                <Field label="PIC" error={form.errors.pic_role}><Select value={form.data.pic_role} onChange={(event) => form.setData('pic_role', event.target.value)}>{options.pics.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</Select></Field>
                <Field label="Verifikator isi" error={form.errors.verifier_role}><Select value={form.data.verifier_role} onChange={(event) => form.setData('verifier_role', event.target.value)}><option value="">Tidak ada (hanya persetujuan RA)</option>{options.verifiers.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</Select></Field>
                <label className="flex items-center gap-2"><input type="checkbox" checked={form.data.requires_expiry_date} onChange={(event) => form.setData('requires_expiry_date', event.target.checked)} />Memiliki masa berlaku (wajib isi tanggal kedaluwarsa)</label>
                {form.errors.requires_expiry_date && <p role="alert" className="text-red-700">{form.errors.requires_expiry_date}</p>}
                <label className="flex items-center gap-2"><input type="checkbox" checked={form.data.checks_product_name} onChange={(event) => form.setData('checks_product_name', event.target.checked)} />Cocokkan nama produk pada PDF teks</label>
                <p className="text-xs text-gray-500">PDF hasil pindaian tidak dibaca dengan OCR. Pencocokan hanya berlaku untuk dokumen bercakupan tiket.</p>
                <Field label="Umur maksimal (bulan)" error={form.errors.max_age_months} hint="Opsional. Contoh: CoA paling lama 6 bulan sejak terbit."><input type="number" min={1} max={120} value={form.data.max_age_months} onChange={(event) => form.setData('max_age_months', event.target.value)} /></Field>
                {item && <ActiveToggle checked={form.data.is_active} error={form.errors.is_active} onChange={(checked) => form.setData('is_active', checked)} />}
                <EditorFooter processing={form.processing} onClose={onClose} />
            </form>
        </Modal>
    );
}

function WeightsEditor({ queue, types, onClose }: { queue: CriteriaQueue; types: PhaseOption[]; onClose: () => void }) {
    const form = useForm({
        criteria: Object.fromEntries(queue.criteria.map((criterion) => [criterion.code, { weight: String(criterion.weight), type: criterion.type }])) as Record<string, { weight: string; type: string }>,
    });
    const total = Object.values(form.data.criteria).reduce((sum, criterion) => sum + (Number(criterion.weight) || 0), 0);
    const balanced = Math.abs(total - 1) <= 0.0005;
    const errors = form.errors as Record<string, string | undefined>;

    function change(code: string, field: 'weight' | 'type', value: string) {
        form.setData('criteria', { ...form.data.criteria, [code]: { ...form.data.criteria[code], [field]: value } });
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.put(queue.update_url, { preserveScroll: true, onSuccess: onClose });
    }

    return (
        <Modal title={`Bobot antrean ${queue.label}`} description="Bobot keempat kriteria harus berjumlah 1,000. Perubahan berlaku pada perhitungan antrean berikutnya." onClose={onClose}>
            <form onSubmit={submit} className="form-stack">
                {queue.criteria.map((criterion) => (
                    <div key={criterion.code} className="two-column">
                        <Field label={`Bobot ${criterion.code}`} error={errors[`criteria.${criterion.code}.weight`]} hint={criterion.name}><input type="number" required min={0.001} max={0.999} step={0.001} value={form.data.criteria[criterion.code].weight} onChange={(event) => change(criterion.code, 'weight', event.target.value)} /></Field>
                        <Field label={`Jenis ${criterion.code}`} error={errors[`criteria.${criterion.code}.type`]}><Select value={form.data.criteria[criterion.code].type} onChange={(event) => change(criterion.code, 'type', event.target.value)}>{types.map((type) => <option key={type.value} value={type.value}>{type.label}</option>)}</Select></Field>
                    </div>
                ))}
                <p role="status" className={balanced ? 'text-sm text-gray-500' : 'text-sm text-red-700'}>Total {formatWeight(total)}{balanced ? '' : ' — harus 1,000'}</p>
                {errors.criteria && <p role="alert" className="text-red-700">{errors.criteria}</p>}
                <div className="form-footer"><Button variant="secondary" onClick={onClose} disabled={form.processing}>Batal</Button><Button type="submit" disabled={form.processing || !balanced}>{form.processing ? 'Menyimpan...' : 'Simpan'}</Button></div>
            </form>
        </Modal>
    );
}

function formatWeight(value: number) {
    return value.toLocaleString('id-ID', { minimumFractionDigits: 3, maximumFractionDigits: 3 });
}

function validityLabel(type: DocumentTypeItem) {
    const parts = [type.requires_expiry_date ? 'Ada masa berlaku' : null, type.max_age_months ? `Maks. ${type.max_age_months} bulan` : null, type.checks_product_name ? 'Cek nama produk' : null].filter(Boolean);
    return parts.length ? parts.join(' · ') : '—';
}

function HolidayEditor({ item, storeUrl, onClose }: { item: HolidayItem | null; storeUrl: string; onClose: () => void }) {
    const form = useForm({ date: item?.date ?? '', name: item?.name ?? '' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (item) form.put(item.update_url, { preserveScroll: true, onSuccess: onClose });
        else form.post(storeUrl, { preserveScroll: true, onSuccess: onClose });
    }

    return (
        <Modal title={item ? 'Ubah hari libur' : 'Tambah hari libur'} onClose={onClose}>
            <form onSubmit={submit} className="form-stack">
                <Field label="Tanggal" error={form.errors.date}><input type="date" autoFocus required value={form.data.date} onChange={(event) => form.setData('date', event.target.value)} /></Field>
                <Field label="Keterangan" error={form.errors.name}><input required maxLength={150} placeholder="Contoh: Hari Raya Natal" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></Field>
                <EditorFooter processing={form.processing} onClose={onClose} />
            </form>
        </Modal>
    );
}

function ActiveToggle({ checked, error, onChange }: { checked: boolean; error?: string; onChange: (checked: boolean) => void }) {
    return (
        <>
            <label className="flex items-center gap-2"><input type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} />Aktif</label>
            {error && <p role="alert" className="text-red-700">{error}</p>}
        </>
    );
}

function EditorFooter({ processing, onClose }: { processing: boolean; onClose: () => void }) {
    return <div className="form-footer"><Button variant="secondary" onClick={onClose} disabled={processing}>Batal</Button><Button type="submit" disabled={processing}>{processing ? 'Menyimpan...' : 'Simpan'}</Button></div>;
}

function StatusBadge({ active }: { active: boolean }) {
    return <Badge tone={active ? 'green' : 'neutral'}>{active ? 'Aktif' : 'Nonaktif'}</Badge>;
}

function formatDate(value: string) {
    return value.split('-').reverse().join('/');
}
