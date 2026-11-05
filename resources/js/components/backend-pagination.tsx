import { Link } from '@inertiajs/react';
import type { Paginated } from '../backend-types';

export function BackendPagination({ page }: { page: Omit<Paginated<unknown>, 'data'> }) {
    return (
        <div className="table-footer">
            <span>{page.total} data · Halaman {page.current_page} dari {page.last_page}</span>
            <nav aria-label="Paginasi" className="flex gap-2">
                {page.prev_page_url && <Link href={page.prev_page_url} preserveScroll className="btn btn-ghost">Sebelumnya</Link>}
                {page.next_page_url && <Link href={page.next_page_url} preserveScroll className="btn btn-ghost">Berikutnya</Link>}
            </nav>
        </div>
    );
}
