import { Head } from '@inertiajs/react';
import { LockKeyhole } from 'lucide-react';
import { EmptyState, PageHeader, Panel } from '../components/ui';
import { PreviewStaffLayout } from '../layouts/preview-staff-layout';
import { PreviewLayout } from '../layouts/preview-layout';
import { usePreview } from '../mocks/preview-provider';
import type { Role } from '../types';
import { DashboardPage } from './dashboards';
import { EscalationsPage, PrioritiesPage, ReportsPage } from './monitoring';
import { ClientsPage, CreateTicketPage, ProspectsPage, TicketDetailPage, TicketsPage } from './tickets';
import { ChecklistSyncPage, IngredientsPage, SharedDocumentsPage, TasksPage, VerificationPage } from './documents';
import { ContractsPage, CreateContractPage, InvoicesPage, ReceivablesPage, SamplesPage } from './payments';
import { BpomPage, NiePage } from './bpom';
import { ClientPortalPage, LoginPage } from './client';
import { ChatbotPage, ComposePage, ContactsPage, InboxPage } from './communications';
import { AuditPage, MasterPage, TemplatesPage, UsersPage } from './admin';

const access: Record<string, Role[]> = {
    'ticket-create': ['marketing'],
    clients: ['marketing'],
    prospects: ['marketing'],
    samples: ['marketing'],
    contracts: ['marketing'],
    'contract-create': ['marketing'],
    invoices: ['finance', 'manager'],
    payments: ['finance'],
    receivables: ['finance', 'manager'],
    verification: ['ra'],
    'qa-review': ['qa'],
    'shared-documents': ['qa', 'ra'],
    ingredients: ['rnd'],
    'sync-checklist': ['ra'],
    bpom: ['ra'],
    nie: ['ra'],
    inbox: ['marketing', 'ra', 'manager'],
    contacts: ['marketing'],
    compose: ['marketing'],
    tasks: ['rnd', 'qa', 'ra'],
    reports: ['manager'],
    escalations: ['manager'],
    users: ['admin'],
    templates: ['admin'],
    audit: ['admin', 'manager'],
    master: ['admin'],
};
const titles: Record<string, string> = {
    dashboard: 'Dasbor',
    tickets: 'Daftar tiket',
    'ticket-create': 'Buat tiket',
    'ticket-detail': 'Detail tiket',
    priorities: 'Antrean prioritas',
    clients: 'Data klien',
    prospects: 'Prospek',
    samples: 'Persetujuan sampel',
    contracts: 'Kontrak',
    'contract-create': 'Buat kontrak',
    invoices: 'Tagihan',
    payments: 'Verifikasi pembayaran',
    receivables: 'Laporan piutang',
    verification: 'Verifikasi dokumen',
    'qa-review': 'Verifikasi mutu',
    'shared-documents': 'Dokumen bersama',
    ingredients: 'Bahan baku',
    'sync-checklist': 'Sinkronisasi checklist',
    bpom: 'Pengajuan BPOM',
    nie: 'Input NIE',
    inbox: 'Kotak masuk WhatsApp',
    contacts: 'Kontak WhatsApp',
    compose: 'Chat baru',
    tasks: 'Tugas dokumen',
    reports: 'Laporan durasi',
    escalations: 'Eskalasi',
    users: 'Pengguna dan role',
    templates: 'Templat dokumen',
    audit: 'Log audit',
    master: 'Master data',
    portal: 'Portal klien',
    'portal-expired': 'Tautan tidak berlaku',
    chatbot: 'Simulasi chatbot',
    login: 'Masuk',
};

export default function Workspace({
    screen = 'dashboard',
    entityId,
}: {
    screen?: string;
    entityId?: string | null;
    initialRole?: Role;
}) {
    return <WorkspaceContent screen={screen} entityId={entityId} />;
}

function WorkspaceContent({ screen, entityId }: { screen: string; entityId?: string | null }) {
    const { role } = usePreview();
    const head = <Head title={titles[screen] ?? 'Workspace'} />;
    if (screen === 'login')
        return (
            <>
                {head}
                <LoginPage />
            </>
        );
    if (screen === 'portal' || screen === 'portal-expired')
        return (
            <>
                {head}
                <ClientPortalPage expired={screen === 'portal-expired'} />
            </>
        );
    if (screen === 'chatbot')
        return (
            <>
                {head}
                <ChatbotPage />
            </>
        );
    let content;
    if (access[screen] && !access[screen].includes(role))
        content = (
            <>
                <PageHeader
                    title="Ruang kerja role lain"
                    description="Pilih role yang sesuai melalui menu pratinjau di kanan atas."
                />
                <Panel>
                    <EmptyState
                        title="Aksi ini tidak tersedia untuk role Anda"
                        description="Pembatasan ini adalah demonstrasi UI, bukan otorisasi server."
                        icon={LockKeyhole}
                    />
                </Panel>
            </>
        );
    else
        switch (screen) {
            case 'dashboard':
                content = <DashboardPage />;
                break;
            case 'tickets':
                content = <TicketsPage />;
                break;
            case 'ticket-create':
                content = <CreateTicketPage />;
                break;
            case 'ticket-detail':
                content = <TicketDetailPage id={entityId} />;
                break;
            case 'priorities':
                content = <PrioritiesPage />;
                break;
            case 'clients':
                content = <ClientsPage />;
                break;
            case 'prospects':
                content = <ProspectsPage />;
                break;
            case 'samples':
                content = <SamplesPage />;
                break;
            case 'contracts':
                content = <ContractsPage />;
                break;
            case 'contract-create':
                content = <CreateContractPage />;
                break;
            case 'invoices':
                content = <InvoicesPage />;
                break;
            case 'payments':
                content = <InvoicesPage verification />;
                break;
            case 'receivables':
                content = <ReceivablesPage />;
                break;
            case 'verification':
                content = <VerificationPage />;
                break;
            case 'qa-review':
                content = <VerificationPage quality />;
                break;
            case 'shared-documents':
                content = <SharedDocumentsPage />;
                break;
            case 'ingredients':
                content = <IngredientsPage />;
                break;
            case 'sync-checklist':
                content = <ChecklistSyncPage />;
                break;
            case 'tasks':
                content = <TasksPage />;
                break;
            case 'bpom':
                content = <BpomPage />;
                break;
            case 'nie':
                content = <NiePage />;
                break;
            case 'inbox':
                content = <InboxPage />;
                break;
            case 'contacts':
                content = <ContactsPage />;
                break;
            case 'compose':
                content = <ComposePage />;
                break;
            case 'reports':
                content = <ReportsPage />;
                break;
            case 'escalations':
                content = <EscalationsPage />;
                break;
            case 'users':
                content = <UsersPage />;
                break;
            case 'templates':
                content = <TemplatesPage />;
                break;
            case 'audit':
                content = <AuditPage />;
                break;
            case 'master':
                content = <MasterPage initialTab={entityId} />;
                break;
            default:
                content = <EmptyState title="Halaman tidak ditemukan" />;
        }
    return (
        <>
            {head}
            <PreviewStaffLayout screen={screen}>
                <div key={`${screen}-${entityId ?? ''}`} className="page-enter">
                    {content}
                </div>
            </PreviewStaffLayout>
        </>
    );
}

Workspace.layout = PreviewLayout;
