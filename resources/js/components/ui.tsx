import { Link } from '@inertiajs/react';
import { previewHref } from '../lib/preview-navigation';
import {
    ArrowDown,
    ArrowRight,
    Check,
    ChevronDown,
    FileText,
    FolderOpen,
    Info,
    Leaf,
    Search,
    X,
    type LucideIcon,
} from 'lucide-react';
import {
    cloneElement,
    isValidElement,
    useEffect,
    useId,
    useRef,
    type ButtonHTMLAttributes,
    type InputHTMLAttributes,
    type ReactElement,
    type ReactNode,
    type SelectHTMLAttributes,
} from 'react';

export function Brand({ compact = false }: { compact?: boolean }) {
    return (
        <div className="brand">
            <span className="brand-mark">
                <Leaf size={24} strokeWidth={1.8} />
            </span>
            <span>
                <strong>
                    aisha<span className="brand-dot">.</span>
                </strong>
                {!compact && <small>MONITORING WORKSPACE</small>}
            </span>
        </div>
    );
}
export function Button({
    variant = 'primary',
    icon: Icon,
    className = '',
    children,
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & {
    variant?: 'primary' | 'secondary' | 'ghost' | 'danger';
    icon?: LucideIcon;
}) {
    return (
        <button type="button" {...props} className={`btn btn-${variant} ${className}`}>
            {Icon && <Icon size={16} strokeWidth={1.8} />}
            {children}
        </button>
    );
}
export function LinkButton({
    href,
    children,
    icon: Icon,
    secondary = false,
}: {
    href: string;
    children: ReactNode;
    icon?: LucideIcon;
    secondary?: boolean;
}) {
    return (
        <Link href={uiHref(href)} preserveState className={`btn btn-${secondary ? 'secondary' : 'primary'}`}>
            {Icon && <Icon size={16} />}
            {children}
        </Link>
    );
}
export function Badge({ children, tone, dot = true }: { children: ReactNode; tone?: string; dot?: boolean }) {
    const value = typeof children === 'string' ? children.toLowerCase() : '';
    const color =
        (tone === 'red' ? 'rose' : tone) ??
        (/terlambat|revisi|gagal|ditolak|lewat/.test(value)
            ? 'rose'
            : /menunggu|ditinjau|dikirim|ditandatangani|terbit|kondisional/.test(value)
              ? 'amber'
              : /disetujui|lunas|aktif|selesai|lolos|tersedia|tertaut/.test(value)
                ? 'green'
                : /registrasi|ditangani/.test(value)
                  ? 'blue'
                  : 'neutral');
    return (
        <span className={`badge badge-${color}`}>
            {dot && <span className="badge-dot" />}
            {children}
        </span>
    );
}
export function Avatar({
    initials,
    color = 'mint',
    small = false,
}: {
    initials: string;
    color?: string;
    small?: boolean;
}) {
    return <span className={`avatar avatar-${color} ${small ? 'avatar-small' : ''}`}>{initials}</span>;
}
export function PageHeader({
    title,
    description,
    eyebrow,
    children,
}: {
    title: string;
    description?: string;
    eyebrow?: string;
    children?: ReactNode;
}) {
    return (
        <div className="page-header">
            <div>
                {eyebrow && <div className="eyebrow">{eyebrow}</div>}
                <h1>{title}</h1>
                {description && <p>{description}</p>}
            </div>
            {children && <div className="page-actions">{children}</div>}
        </div>
    );
}
export function Panel({
    title,
    description,
    action,
    children,
    className = '',
}: {
    title?: string;
    description?: string;
    action?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section className={`panel ${className}`}>
            {title && (
                <div className="panel-header">
                    <div>
                        <h2>{title}</h2>
                        {description && <p>{description}</p>}
                    </div>
                    {action}
                </div>
            )}
            {children}
        </section>
    );
}
export function StatCard({
    label,
    value,
    icon: Icon,
    detail,
    tone = 'green',
    trend,
}: {
    label: string;
    value: ReactNode;
    icon: LucideIcon;
    detail?: string;
    tone?: string;
    trend?: string;
}) {
    return (
        <div className="stat-card">
            <div className="stat-top">
                <span>{label}</span>
                <span className={`stat-icon icon-${tone}`}>
                    <Icon size={19} strokeWidth={1.7} />
                </span>
            </div>
            <div className="stat-value">{value}</div>
            <div className="stat-bottom">
                {trend && (
                    <span className="trend">
                        <ArrowDown size={12} className="rotate-180" />
                        {trend}
                    </span>
                )}
                <span>{detail}</span>
            </div>
        </div>
    );
}
export function Progress({ value, showLabel = true }: { value: number; showLabel?: boolean }) {
    return (
        <div className="progress-line">
            <div className="progress-track">
                <span style={{ width: `${Math.max(0, Math.min(value, 100))}%` }} />
            </div>
            {showLabel && <span>{value}%</span>}
        </div>
    );
}
export function SearchInput({ className = '', ...props }: InputHTMLAttributes<HTMLInputElement>) {
    return (
        <div className={`search-input ${className}`}>
            <Search size={17} />
            <input type="search" {...props} />
        </div>
    );
}
export function Field({
    label,
    error,
    hint,
    children,
}: {
    label: string;
    error?: string;
    hint?: string;
    children: ReactNode;
}) {
    const id = useId();
    const control = isValidElement(children)
        ? cloneElement(
              children as ReactElement<{ id?: string; 'aria-labelledby'?: string; 'aria-describedby'?: string }>,
              { id, 'aria-labelledby': `${id}-label`, 'aria-describedby': error || hint ? `${id}-help` : undefined },
          )
        : children;
    return (
        <label htmlFor={id} className={`field ${error ? 'field-invalid' : ''}`}>
            <span id={`${id}-label`}>{label}</span>
            {control}
            {error && (
                <small id={`${id}-help`} role="alert">
                    {error}
                </small>
            )}
            {hint && !error && (
                <small id={`${id}-help`} className="field-hint">
                    {hint}
                </small>
            )}
        </label>
    );
}
export function Select({ children, ...props }: SelectHTMLAttributes<HTMLSelectElement>) {
    return (
        <div className="select-wrap">
            <select {...props}>{children}</select>
            <ChevronDown size={14} />
        </div>
    );
}
export function EmptyState({
    title = 'Belum ada data',
    description = 'Data yang sesuai akan ditampilkan di sini.',
    icon: Icon = FolderOpen,
    children,
}: {
    title?: string;
    description?: string;
    icon?: LucideIcon;
    children?: ReactNode;
}) {
    return (
        <div className="empty-state">
            <span>
                <Icon size={27} strokeWidth={1.4} />
            </span>
            <h3>{title}</h3>
            <p>{description}</p>
            {children}
        </div>
    );
}
export function InfoBox({ children, warning = false }: { children: ReactNode; warning?: boolean }) {
    return (
        <div className={`info-box ${warning ? 'info-warning' : ''}`}>
            <Info size={17} />
            <div>{children}</div>
        </div>
    );
}
export function Modal({
    title,
    description,
    children,
    onClose,
    wide = false,
}: {
    title: string;
    description?: string;
    children: ReactNode;
    onClose: () => void;
    wide?: boolean;
}) {
    const ref = useRef<HTMLDialogElement>(null);
    const id = useId();
    useEffect(() => {
        const dialog = ref.current;
        dialog?.showModal();
        return () => {
            if (dialog?.open) dialog.close();
        };
    }, []);
    return (
        <dialog
            ref={ref}
            className={`modal ${wide ? 'modal-wide' : ''}`}
            aria-labelledby={id}
            onCancel={onClose}
            onClick={(event) => {
                if (event.target === event.currentTarget) onClose();
            }}
        >
            <div className="modal-inner">
                <header className="modal-header">
                    <div>
                        <h2 id={id}>{title}</h2>
                        {description && <p>{description}</p>}
                    </div>
                    <button className="icon-button" aria-label="Tutup dialog" onClick={onClose}>
                        <X size={20} />
                    </button>
                </header>
                {children}
            </div>
        </dialog>
    );
}
export function FilePreview({ title, subtitle }: { title: string; subtitle?: string }) {
    return (
        <div className="file-preview">
            <div className="file-preview-head">
                <span>
                    <FileText size={16} />
                    {title}
                </span>
                <Badge tone="neutral" dot={false}>
                    Pratinjau demo
                </Badge>
            </div>
            <div className="document-paper">
                <div className="paper-brand">
                    <Leaf size={24} />
                    <span>
                        AISHA ZAHRA<small>HERBALABORATORIES</small>
                    </span>
                </div>
                <div className="paper-divider" />
                <h3>{title}</h3>
                {subtitle && <p>{subtitle}</p>}
                <div className="paper-lines">
                    {[100, 85, 95, 100, 60, 0, 100, 90, 80, 100, 65].map((width, i) => (
                        <span key={i} style={{ width: `${width}%`, marginTop: width === 0 ? 22 : undefined }} />
                    ))}
                </div>
                <div className="paper-signature">
                    <span>
                        Disiapkan oleh,
                        <br />
                        <strong>PT Aisha Zahra Herbalaboratories</strong>
                    </span>
                    <span>
                        Disetujui oleh,
                        <br />
                        <strong>Pihak terkait</strong>
                    </span>
                </div>
            </div>
        </div>
    );
}
export function CheckLine({ children, checked = true }: { children: ReactNode; checked?: boolean }) {
    return (
        <div className={`check-line ${checked ? 'is-checked' : ''}`}>
            <span>{checked && <Check size={12} />}</span>
            {children}
        </div>
    );
}
export function TextLink({ href, children }: { href: string; children: ReactNode }) {
    return (
        <Link href={uiHref(href)} preserveState className="text-link">
            {children}
            <ArrowRight size={14} />
        </Link>
    );
}

function uiHref(href: string): string {
    return typeof window !== 'undefined' && /^\/preview(?:\/|$)/.test(window.location.pathname) ? previewHref(href) : href;
}
