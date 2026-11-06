import { Link, router } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';
import type { Paginated } from '../../backend-types';
import { BackendPagination } from '../../components/backend-pagination';
import { Badge, Button, EmptyState, PageHeader, Panel } from '../../components/ui';
import { BackendLayout } from '../../layouts/backend-layout';

type NotificationRow = { id: string; kind: string | null; title: string; body: string; ticket_code: string | null; read: boolean; created_at: string | null; open_url: string };

const urgentKinds = ['eskalasi_sla', 'tenggat_bpom', 'dokumen_dikembalikan'];

export default function Notifications({ notifications, unread, urls }: { notifications: Paginated<NotificationRow>; unread: number; urls: { read_all: string } }) {
    return (
        <BackendLayout title="Pusat notifikasi">
            <PageHeader title="Pusat notifikasi" eyebrow="RUANG KERJA" description="Tenggat, keterlambatan, dokumen, kontrak, dan pembayaran yang memerlukan perhatian Anda.">
                {unread > 0 && <Button variant="secondary" icon={CheckCheck} onClick={() => router.post(urls.read_all, {}, { preserveScroll: true })}>Tandai semua dibaca ({unread})</Button>}
            </PageHeader>
            <Panel>
                {notifications.data.length ? (
                    <ul className="divide-y">
                        {notifications.data.map((notification) => <li key={notification.id} className={`panel-body ${notification.read ? 'text-gray-500' : ''}`}>
                            <Link href={notification.open_url} className="block">
                                <div className="flex flex-wrap items-center gap-2">
                                    {!notification.read && <Badge tone={urgentKinds.includes(notification.kind ?? '') ? 'red' : 'blue'}>Baru</Badge>}
                                    <strong>{notification.title}</strong>
                                </div>
                                <p className="text-sm">{notification.body}</p>
                                <small className="text-gray-500">{notification.created_at}</small>
                            </Link>
                        </li>)}
                    </ul>
                ) : <EmptyState title="Belum ada notifikasi" description="Pengingat tenggat dan perubahan status akan muncul di sini." icon={Bell} />}
                <BackendPagination page={notifications} />
            </Panel>
        </BackendLayout>
    );
}
