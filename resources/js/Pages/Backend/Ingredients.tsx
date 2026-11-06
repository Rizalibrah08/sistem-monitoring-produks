import { Link } from '@inertiajs/react';
import { FlaskConical } from 'lucide-react';
import { Badge, EmptyState, PageHeader, Panel } from '../../components/ui';
import { BackendLayout } from '../../layouts/backend-layout';

type IngredientRow = {
    key: string;
    ticket_code: string;
    product_name: string;
    ticket_url: string;
    material: string;
    supplier: string | null;
    documents: { name: string; status: string; status_label: string; expires_at: string | null }[];
};

const tones: Record<string, string> = { belum_ada: 'neutral', sedang_diverifikasi: 'amber', perlu_revisi: 'red', disetujui: 'green' };

export default function Ingredients({ rows }: { rows: IngredientRow[] }) {
    return (
        <BackendLayout title="Bahan baku tiket">
            <PageHeader title="Bahan baku tiket" eyebrow="OPERASIONAL" description="Bahan baku setiap tiket aktif dan status dokumen asal peredarannya. Pilih bahan dari detail tiket." />
            <Panel>
                {rows.length ? (
                    <div className="overflow-x-auto">
                        <table className="w-full"><thead><tr><th>Tiket</th><th>Bahan baku</th><th>Dokumen terkait</th></tr></thead>
                            <tbody>{rows.map((row) => <tr key={row.key}>
                                <td><Link href={row.ticket_url} className="font-semibold">{row.ticket_code}</Link><div className="text-xs text-gray-500">{row.product_name}</div></td>
                                <td><strong>{row.material}</strong>{row.supplier && <div className="text-xs text-gray-500">{row.supplier}</div>}</td>
                                <td>{row.documents.length ? <ul className="space-y-1">{row.documents.map((document) => <li key={document.name}>{document.name} <Badge tone={tones[document.status]}>{document.status_label}</Badge>{document.expires_at && <span className="text-xs text-gray-500"> · berlaku s.d. {document.expires_at}</span>}</li>)}</ul> : <span className="text-gray-500">Tidak ada dokumen bahan</span>}</td>
                            </tr>)}</tbody>
                        </table>
                    </div>
                ) : <EmptyState title="Belum ada bahan baku tiket" description="Bahan baku dipilih R&D dari detail tiket." icon={FlaskConical} />}
            </Panel>
        </BackendLayout>
    );
}
