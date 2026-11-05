import type { ReactNode } from 'react';
import { PreviewProvider } from '../mocks/preview-provider';
import type { Role } from '../types';

export function PreviewLayout({ children, initialRole = 'manager' }: { children: ReactNode; initialRole?: Role }) {
    return <PreviewProvider initialRole={initialRole}>{children}</PreviewProvider>;
}
