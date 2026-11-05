export type Role = 'manager' | 'marketing' | 'rnd' | 'qa' | 'ra' | 'finance' | 'admin';
export const roleLabels: Record<Role, string> = {
    manager: 'Manajer',
    marketing: 'Marketing',
    rnd: 'R&D',
    qa: 'QA/QC',
    ra: 'Regulatory Affairs',
    finance: 'Finance',
    admin: 'Admin',
};
export type DocumentStatus = 'Belum ada' | 'Menunggu verifikasi' | 'Perlu revisi' | 'Disetujui';
export type DocumentVersion = { version: number; date: string; author: string; note: string; fileName: string };
export type DocumentItem = {
    id: string;
    name: string;
    scope: 'Tiket' | 'Klien' | 'Perusahaan' | 'Bahan baku';
    pic: string;
    phase: 'Pra-registrasi' | 'Registrasi' | 'Kedua fase';
    status: DocumentStatus;
    validation: 'Lolos' | 'Gagal' | 'Belum diperiksa';
    required: boolean;
    conditional?: boolean;
    version: number;
    number?: string;
    issuedAt?: string;
    expiresAt?: string;
    reason?: string;
    requiresQa: boolean;
    qaApproved: boolean;
    locked: boolean;
    versions: DocumentVersion[];
};
export type Ticket = {
    id: string;
    product: string;
    clientId: string;
    form: string;
    category: string;
    phase: 'Awal' | 'Pra-registrasi' | 'Registrasi';
    stage: string;
    createdAt: string;
    dueDate: string;
    late: boolean;
    needsReview: boolean;
    closed: boolean;
    mouSigned: boolean;
    dpPaid: boolean;
    sampleApproved: boolean;
    submitted: boolean;
    preSubmitted: boolean;
    preResult: 'none' | 'pending' | 'approved' | 'rejected';
    revision: number;
    documents: DocumentItem[];
    ingredients: string[];
    score: number;
    cr: number;
    bpomDays: number | null;
    age: number;
    nie?: string;
    nieDate?: string;
    sampleHistory?: Array<{ note: string; result: string }>;
};
export type Client = {
    id: string;
    name: string;
    company: string;
    pic: string;
    phone: string;
    email: string;
    initials: string;
    color: string;
    assignedTo: 'marketing' | 'other';
};
export type Contract = {
    id: string;
    ticketId: string;
    clientId: string;
    amount: number;
    dpPercent: number;
    status: 'Draf' | 'Dikirim' | 'Ditandatangani' | 'Aktif';
    template: string;
    dueDays: number;
};
export type Invoice = {
    id: string;
    ticketId: string;
    clientId: string;
    term: string;
    amount: number;
    dueDate: string;
    status: 'Terbit' | 'Menunggu verifikasi' | 'Lunas' | 'Lewat jatuh tempo';
    proof: string | null;
    proofStatus: 'pending' | 'approved' | 'rejected' | null;
    reason?: string;
};
export type Message = { id: string; body: string; time: string; outgoing: boolean; file?: string; status?: string };
export type Conversation = {
    id: string;
    clientId: string | null;
    name: string;
    phone: string;
    initials: string;
    color: string;
    preview: string;
    time: string;
    unread: number;
    state: 'Menunggu' | 'Ditangani' | 'Selesai';
    assigned: Role;
    ticketId: string | null;
    messages: Message[];
};
export type AuditEntry = {
    id: string;
    time: string;
    actor: string;
    action: string;
    object: string;
    change: string;
    source: string;
};
export type Notification = {
    id: string;
    title: string;
    body: string;
    time: string;
    href: string;
    role: Role[];
    read: boolean;
    kind: 'document' | 'chat' | 'warning' | 'payment';
};
export type Prospect = {
    id: string;
    name: string;
    phone: string;
    product: string;
    time: string;
    status: 'Baru' | 'Dihubungi' | 'Dijadikan tiket' | 'Ditolak';
};
export type MasterItem = { id: string; name: string; detail: string; value?: string; kind?: string };
export type StaffUser = { id: string; name: string; email: string; role: Role; active: boolean; available: boolean };
export type DocumentTemplate = { id: string; name: string; kind: string; version: number; tokens: string[] };
export type DemoState = {
    tickets: Ticket[];
    clients: Client[];
    contracts: Contract[];
    invoices: Invoice[];
    conversations: Conversation[];
    audit: AuditEntry[];
    notifications: Notification[];
    prospects: Prospect[];
    masters: Record<string, MasterItem[]>;
    users: StaffUser[];
    templates: DocumentTemplate[];
    bpomNotes: Array<{ id: string; ticketId: string; text: string; date: string; due: string; done: boolean }>;
};
