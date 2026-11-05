import type { Role } from './types';

export type StaffIdentity = {
    id: number;
    name: string;
    email: string;
    role: Role;
    role_label: string;
    is_active: boolean;
    unavailable: boolean;
};

export interface BackendSharedProps {
    [key: string]: unknown;
    auth: { user: StaffIdentity | null; permissions: string[] };
    navigation: { id?: string; label: string; href: string; icon?: string; group?: string; available?: boolean }[];
    routes: Record<string, string>;
    flash: { status: string | null };
    workspace?: { search_available: boolean; whatsapp_connected: boolean; notifications_available: boolean };
}

export type StaffUser = Omit<StaffIdentity, 'role' | 'role_label'> & {
    role: Role | null;
    role_label: string | null;
    unavailable_from: string | null;
    unavailable_until: string | null;
    last_login_at: string | null;
    update_url: string;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type AuditEntry = {
    id: number;
    created_at: string;
    actor_name: string | null;
    actor_role: string | null;
    event: string | null;
    description: string;
    subject_type: string | null;
    subject_id: number | null;
    ticket_code: string | null;
    source: string;
    before: Record<string, unknown>;
    after: Record<string, unknown>;
    reason: string | null;
    ip_address: string | null;
    user_agent: string | null;
    prev_hash: string;
    hash: string;
};
