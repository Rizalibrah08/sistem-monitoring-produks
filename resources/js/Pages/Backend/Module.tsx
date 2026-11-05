import { FileText, MessageCircle } from 'lucide-react';
import { Badge, Button, EmptyState, InfoBox, PageHeader, Panel } from '../../components/ui';
import { BackendLayout } from '../../layouts/backend-layout';

type WorkspaceModule = {
    id: string;
    title: string;
    description: string;
    empty_title: string;
    columns: string[];
    actions: string[];
    state: 'not_connected' | 'not_implemented';
    message: string;
};

export default function Module({ module }: { module: WorkspaceModule }) {
    const disconnected = module.state === 'not_connected';
    const empty = <EmptyState title={module.empty_title} description={module.message} icon={disconnected ? MessageCircle : FileText} />;

    return (
        <BackendLayout title={module.title}>
            <PageHeader title={module.title} description={module.description} eyebrow="RUANG KERJA ANDA">
                <Badge tone={disconnected ? 'amber' : 'neutral'}>{disconnected ? 'Belum terhubung' : 'Belum tersedia'}</Badge>
                {module.actions.map((action) => <Button key={action} disabled title={module.message}>{action}</Button>)}
            </PageHeader>
            <div className="mb-5"><InfoBox warning={disconnected}>{module.message}</InfoBox></div>
            <Panel>
                {module.columns.length ? <div className="overflow-x-auto">
                    <table className="w-full">
                        <thead><tr>{module.columns.map((column) => <th key={column}>{column}</th>)}</tr></thead>
                        <tbody><tr><td colSpan={module.columns.length}>{empty}</td></tr></tbody>
                    </table>
                </div> : empty}
            </Panel>
        </BackendLayout>
    );
}
