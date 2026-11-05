import type { ReactNode } from 'react';
import { StaffLayout } from './staff-layout';

export function BackendLayout({ title, children }: { title: string; children: ReactNode }) {
    return <StaffLayout title={title}>{children}</StaffLayout>;
}
