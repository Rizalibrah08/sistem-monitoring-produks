import { Head } from '@inertiajs/react';
import { LockKeyhole, MessageCircle, ShieldCheck } from 'lucide-react';
import { Badge, Brand } from '../../components/ui';
import { ClientFooter } from './UploadLink';

export default function UploadLinkExpired() {
    return (
        <div className="client-outer">
            <Head title="Tautan tidak berlaku"><meta name="robots" content="noindex, nofollow" /></Head>
            <div className="client-topbar">
                <Brand compact />
                <Badge tone="neutral" dot={false}>Portal klien</Badge>
            </div>
            <main className="expired-page">
                <span className="expired-icon"><LockKeyhole size={38} strokeWidth={1.4} /></span>
                <span className="eyebrow">KEAMANAN DOKUMEN ANDA</span>
                <h1>Tautan tidak berlaku</h1>
                <p>Tautan ini sudah kedaluwarsa, dicabut, atau tidak valid. Informasi pendaftaran Anda tetap terlindungi.</p>
                <p className="inline-flex items-center gap-2"><MessageCircle size={16} />Minta tautan baru kepada PIC Marketing Anda melalui WhatsApp.</p>
                <div className="client-secure-note"><ShieldCheck size={14} />Akses aman, hanya untuk pihak terkait</div>
            </main>
            <ClientFooter />
        </div>
    );
}
