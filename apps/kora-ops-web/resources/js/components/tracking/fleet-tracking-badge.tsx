import {
    AlertOctagon,
    CarFront,
    CheckCircle2,
    Clock,
    Flag,
    Radio,
    ShieldAlert,
    WifiOff,
    Wrench,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import type {
    TrackingIncidentStatus,
    TrackingIncidentType,
    TrackingLiveState,
} from '@/types/tracking';

type Props = {
    state: TrackingLiveState;
    ageSeconds?: number | null;
    showIcon?: boolean;
    showPulse?: boolean;
    className?: string;
    size?: 'sm' | 'md' | 'lg';
};

export const TRACKING_STATE_META: Record<
    TrackingLiveState,
    {
        label: string;
        shortLabel: string;
        description: string;
        tone: 'live' | 'stale' | 'gps' | 'offline' | 'closed';
        colorHex: string;
        badgeClass: string;
        dotClass: string;
        pulseClass: string;
        icon: typeof Radio;
    }
> = {
    live: {
        label: 'En direct',
        shortLabel: 'Live',
        description: 'Position GPS récente (< 90s)',
        tone: 'live',
        colorHex: '#10b981',
        badgeClass:
            'bg-emerald-500/10 text-emerald-700 border-emerald-500/30 dark:bg-emerald-500/15 dark:text-emerald-400 dark:border-emerald-500/30',
        dotClass: 'bg-emerald-500',
        pulseClass: 'bg-emerald-400/40 animate-ping',
        icon: Radio,
    },
    stale: {
        label: 'Position ancienne',
        shortLabel: 'Ancien',
        description: 'Signal retardé (> 90s) ou dégradé',
        tone: 'stale',
        colorHex: '#f59e0b',
        badgeClass:
            'bg-amber-500/10 text-amber-700 border-amber-500/30 dark:bg-amber-500/15 dark:text-amber-400 dark:border-amber-500/30',
        dotClass: 'bg-amber-500',
        pulseClass: 'bg-amber-400/30 animate-pulse',
        icon: Clock,
    },
    gps_unavailable: {
        label: 'GPS indisponible',
        shortLabel: 'Sans GPS',
        description: 'Terminal actif sans signal satellite',
        tone: 'gps',
        colorHex: '#ef4444',
        badgeClass:
            'bg-rose-500/10 text-rose-700 border-rose-500/30 dark:bg-rose-500/15 dark:text-rose-400 dark:border-rose-500/30',
        dotClass: 'bg-rose-500',
        pulseClass: 'bg-rose-400/30',
        icon: AlertOctagon,
    },
    terminal_offline: {
        label: 'Terminal hors ligne',
        shortLabel: 'Hors ligne',
        description: 'Aucune communication (> 5 min)',
        tone: 'offline',
        colorHex: '#64748b',
        badgeClass:
            'bg-slate-500/10 text-slate-700 border-slate-500/30 dark:bg-slate-500/15 dark:text-slate-400 dark:border-slate-500/30',
        dotClass: 'bg-slate-400',
        pulseClass: '',
        icon: WifiOff,
    },
    session_closed: {
        label: 'Session clôturée',
        shortLabel: 'Clôturé',
        description: 'Trajet terminé ou départ annulé',
        tone: 'closed',
        colorHex: '#94a3b8',
        badgeClass:
            'bg-muted text-muted-foreground border-border/40 dark:bg-muted/40 dark:text-muted-foreground',
        dotClass: 'bg-muted-foreground',
        pulseClass: '',
        icon: CheckCircle2,
    },
};

export const INCIDENT_TYPE_META: Record<
    TrackingIncidentType,
    { label: string; icon: typeof Wrench }
> = {
    breakdown: { label: 'Panne', icon: Wrench },
    delay: { label: 'Retard', icon: Clock },
    accident: { label: 'Accident', icon: CarFront },
    security: { label: 'Sécurité', icon: ShieldAlert },
    other: { label: 'Autre', icon: Flag },
};

export const INCIDENT_STATUS_META: Record<
    TrackingIncidentStatus,
    { label: string; markerColor: string; badgeClass: string }
> = {
    open: {
        label: 'Ouvert',
        markerColor: '#ef4444',
        badgeClass:
            'bg-rose-500/10 text-rose-700 border-rose-500/30 dark:bg-rose-500/15 dark:text-rose-400 dark:border-rose-500/30',
    },
    in_progress: {
        label: 'En cours',
        markerColor: '#f59e0b',
        badgeClass:
            'bg-amber-500/10 text-amber-700 border-amber-500/30 dark:bg-amber-500/15 dark:text-amber-400 dark:border-amber-500/30',
    },
    resolved: {
        label: 'Résolu',
        markerColor: '#10b981',
        badgeClass:
            'bg-emerald-500/10 text-emerald-700 border-emerald-500/30 dark:bg-emerald-500/15 dark:text-emerald-400 dark:border-emerald-500/30',
    },
    cancelled: {
        label: 'Annulé',
        markerColor: '#94a3b8',
        badgeClass:
            'bg-muted text-muted-foreground border-border/40 dark:bg-muted/40 dark:text-muted-foreground',
    },
};

export function countActiveIncidents(session: {
    incidents?: { status: TrackingIncidentStatus }[];
}): number {
    return (session.incidents ?? []).filter(
        (i) => i.status === 'open' || i.status === 'in_progress',
    ).length;
}

export function formatAge(seconds: number | null | undefined): string {
    if (seconds === null || seconds === undefined || isNaN(seconds)) {
        return 'Indisponible';
    }

    if (seconds < 60) {
        return `il y a ${seconds}s`;
    }

    const minutes = Math.floor(seconds / 60);

    if (minutes < 60) {
        return `il y a ${minutes}m`;
    }

    const hours = Math.floor(minutes / 60);
    const remMinutes = minutes % 60;

    return `il y a ${hours}h ${remMinutes}m`;
}

export function FleetTrackingBadge({
    state,
    ageSeconds,
    showIcon = true,
    showPulse = true,
    className,
    size = 'md',
}: Props) {
    const meta =
        TRACKING_STATE_META[state] ?? TRACKING_STATE_META.session_closed;
    const Icon = meta.icon;

    const sizeClasses = {
        sm: 'text-[0.625rem] px-2 py-0.5 gap-1.5',
        md: 'text-[0.6875rem] px-2.5 py-1 gap-1.5',
        lg: 'text-xs px-3 py-1.5 gap-2',
    };

    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full border font-bold transition-colors',
                sizeClasses[size],
                meta.badgeClass,
                className,
            )}
            title={meta.description}
        >
            <span className="relative flex size-2 shrink-0 items-center justify-center">
                {showPulse && meta.pulseClass && (
                    <span
                        className={cn(
                            'absolute inline-flex size-full rounded-full',
                            meta.pulseClass,
                        )}
                    />
                )}
                <span
                    className={cn(
                        'relative inline-flex size-1.5 rounded-full',
                        meta.dotClass,
                    )}
                />
            </span>

            {showIcon && <Icon className="size-3 shrink-0 opacity-80" />}

            <span>{meta.label}</span>

            {ageSeconds !== undefined && ageSeconds !== null && (
                <span className="ml-0.5 font-normal opacity-75">
                    ({formatAge(ageSeconds)})
                </span>
            )}
        </span>
    );
}
