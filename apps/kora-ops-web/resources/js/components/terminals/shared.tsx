import { cn } from '@/lib/utils';
import * as terminalsRoutes from '@/routes/terminals';

export type TerminalStatus = 'active' | 'inactive' | 'maintenance' | 'lost';
export type ConnectivityStatus = 'online' | 'monitor' | 'offline';

export type PersonSummary = { uuid: string; name: string };
export type AgencySummary = { uuid: string; code?: string; name: string };

export type TerminalSummary = {
    uuid: string;
    serial_number: string;
    type: 'p501';
    hardware_model: string | null;
    os_version: string | null;
    app_version: string | null;
    status: TerminalStatus;
    connectivity_status: ConnectivityStatus;
    assigned_user: PersonSummary | null;
    agency: AgencySummary | null;
    last_seen_at: string | null;
    last_activity_at: string | null;
    last_sync_at: string | null;
    last_handshake_at: string | null;
    active_sessions_count: number;
    created_at: string | null;
};

export type Pagination<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

export type AssignmentRecord = {
    uuid: string;
    assigned_user: PersonSummary | null;
    agency: AgencySummary | null;
    assigned_by: PersonSummary | null;
    assigned_at: string | null;
    unassigned_at: string | null;
    reason: string | null;
};

export type TerminalSession = {
    uuid: string;
    user: PersonSummary | null;
    started_at: string | null;
    last_seen_at: string | null;
    expires_at: string | null;
    absolute_expires_at: string | null;
    renewed_at: string | null;
    renewal_count: number;
};

export type HistoryEvent = {
    type: string;
    occurred_at: string;
    actor: PersonSummary | null;
    summary: string;
    metadata: Record<string, string | number | boolean | null>;
};

export type HistoryTypeOption = { value: string; label: string };

export type HistoryFilters = { event_type: string; from: string; to: string };

export type UserCandidateRole = {
    code: string;
    label: string;
    category: 'central' | 'agency' | 'terrain' | null;
};

export type UserCandidate = PersonSummary & {
    agency: { uuid: string; name: string } | null;
    role: UserCandidateRole | null;
};

export const statusLabels: Record<TerminalStatus, string> = {
    active: 'Autorisé',
    inactive: 'Désactivé',
    maintenance: 'Maintenance',
    lost: 'Bloqué / perdu',
};

export const statusTones: Record<
    TerminalStatus,
    'navy' | 'gold' | 'blue' | 'muted' | 'destructive'
> = {
    active: 'navy',
    inactive: 'muted',
    maintenance: 'gold',
    lost: 'destructive',
};

export const connectivityLabels: Record<ConnectivityStatus, string> = {
    online: 'En ligne',
    monitor: 'À surveiller',
    offline: 'Hors ligne',
};

export const connectivityTones: Record<
    ConnectivityStatus,
    'blue' | 'gold' | 'muted'
> = {
    online: 'blue',
    monitor: 'gold',
    offline: 'muted',
};

export const destructiveActions = ['lost', 'deactivate', 'stock', 'revoke'];

export function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('fr-FR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

export function formatRelative(value: string | null): string {
    if (!value) {
        return 'Jamais connecté';
    }

    const seconds = Math.round((new Date(value).getTime() - Date.now()) / 1000);
    const formatter = new Intl.RelativeTimeFormat('fr-FR', { numeric: 'auto' });

    if (Math.abs(seconds) < 60) {
        return formatter.format(seconds, 'second');
    }

    const minutes = Math.round(seconds / 60);

    if (Math.abs(minutes) < 60) {
        return formatter.format(minutes, 'minute');
    }

    return formatter.format(Math.round(minutes / 60), 'hour');
}

export function errorMessage(error: unknown): string {
    if (error instanceof Error && error.message) {
        return error.message;
    }

    return 'La demande n’a pas pu être exécutée.';
}

export function terminalActionRoute(action: string, terminalUuid: string) {
    const args = { terminal: terminalUuid };

    switch (action) {
        case 'activate':
            return terminalsRoutes.activate(args);
        case 'deactivate':
            return terminalsRoutes.deactivate(args);
        case 'maintenance':
            return terminalsRoutes.maintenance(args);
        case 'lost':
            return terminalsRoutes.lost(args);
        case 'recover':
            return terminalsRoutes.recover(args);
        case 'unassign':
            return terminalsRoutes.unassign(args);
        case 'stock':
            return terminalsRoutes.stock(args);
        case 'revoke':
            return terminalsRoutes.revokeSessions(args);
        default:
            return terminalsRoutes.assign(args);
    }
}

export function InfoBentoCard({
    icon: Icon,
    label,
    value,
    hint,
}: {
    icon: React.ComponentType<{ className?: string }>;
    label: string;
    value: string;
    hint: string;
}) {
    return (
        <div className="rounded-xl border border-border bg-muted/20 p-3.5">
            <div className="flex items-center gap-2 text-muted-foreground">
                <Icon className="size-3.5 text-gold" />
                <span className="text-[0.6875rem] font-bold tracking-wider uppercase">
                    {label}
                </span>
            </div>
            <p className="mt-2 truncate text-sm font-extrabold text-foreground">
                {value}
            </p>
            <span className="mt-0.5 inline-block text-[0.65rem] font-semibold text-muted-foreground">
                {hint}
            </span>
        </div>
    );
}

export function ActionRow({
    icon: Icon,
    label,
    hint,
    cta,
    tone = 'neutral',
    onClick,
}: {
    icon: React.ComponentType<{ className?: string }>;
    label: string;
    hint: string;
    cta: string;
    tone?: 'neutral' | 'gold' | 'blue' | 'destructive' | 'emerald';
    onClick: () => void;
}) {
    const toneStyles = {
        neutral: {
            row: 'border-border bg-card hover:bg-muted/40',
            icon: 'bg-muted text-foreground',
            cta: 'text-gold',
        },
        gold: {
            row: 'border-border bg-card hover:bg-muted/40',
            icon: 'bg-gold/15 text-gold',
            cta: 'text-gold',
        },
        blue: {
            row: 'border-border bg-card hover:bg-muted/40',
            icon: 'bg-blue-500/15 text-blue-500',
            cta: 'text-blue-500',
        },
        destructive: {
            row: 'border-destructive/20 bg-destructive/5 hover:bg-destructive/10',
            icon: 'bg-destructive/15 text-destructive',
            cta: 'text-destructive',
        },
        emerald: {
            row: 'border-emerald-500/25 bg-emerald-500/5 hover:bg-emerald-500/10',
            icon: 'bg-emerald-500/15 text-emerald-600',
            cta: 'text-emerald-600',
        },
    };

    const styles = toneStyles[tone];

    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'flex w-full items-center justify-between rounded-xl border p-3 text-left transition',
                styles.row,
            )}
        >
            <div className="flex items-center gap-3">
                <span
                    className={cn(
                        'grid size-8 place-items-center rounded-lg',
                        styles.icon,
                    )}
                >
                    <Icon className="size-4" />
                </span>
                <div>
                    <span
                        className={cn(
                            'block text-xs font-bold',
                            tone === 'destructive'
                                ? 'text-destructive'
                                : tone === 'emerald'
                                  ? 'text-emerald-600'
                                  : 'text-foreground',
                        )}
                    >
                        {label}
                    </span>
                    <small className="text-[0.6875rem] text-muted-foreground">
                        {hint}
                    </small>
                </div>
            </div>
            <span className={cn('text-xs font-bold', styles.cta)}>{cta} →</span>
        </button>
    );
}

export function EmptyLine({ text }: { text: string }) {
    return (
        <p className="rounded-xl border border-dashed border-border/70 px-4 py-5 text-center text-xs text-muted-foreground">
            {text}
        </p>
    );
}
