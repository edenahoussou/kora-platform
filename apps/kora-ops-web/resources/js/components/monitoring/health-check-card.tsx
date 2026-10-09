import type { LucideIcon } from 'lucide-react';
import {
    Activity,
    AlertTriangle,
    CheckCircle2,
    Database,
    HardDrive,
    MemoryStick,
    MinusCircle,
    XCircle,
} from 'lucide-react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import type {
    HealthCheckItem,
    HealthCheckStatus,
} from '@/components/monitoring/monitoring-types';
import { cn } from '@/lib/utils';

const checkIcons: Record<string, LucideIcon> = {
    database: Database,
    cache: MemoryStick,
    redis: MemoryStick,
    usedDiskSpace: HardDrive,
    debugMode: Activity,
};

const statusMeta: Record<
    HealthCheckStatus,
    {
        label: string;
        tone: 'navy' | 'gold' | 'blue' | 'muted' | 'destructive';
        Icon: LucideIcon;
    }
> = {
    ok: { label: 'OK', tone: 'blue', Icon: CheckCircle2 },
    warning: { label: 'Avertissement', tone: 'gold', Icon: AlertTriangle },
    failed: { label: 'Échec', tone: 'destructive', Icon: XCircle },
    crashed: { label: 'Planté', tone: 'destructive', Icon: XCircle },
    skipped: { label: 'Ignoré', tone: 'muted', Icon: MinusCircle },
};

export function HealthCheckCard({ check }: { check: HealthCheckItem }) {
    const meta = statusMeta[check.status] ?? statusMeta.skipped;
    const Icon = checkIcons[check.name] ?? Activity;

    return (
        <article className="rounded-2xl bg-card p-5 ring-1 ring-black/[0.045] dark:ring-white/10">
            <div className="flex items-start justify-between gap-3">
                <span
                    className={cn(
                        'inline-flex size-10 items-center justify-center rounded-xl',
                        check.status === 'ok'
                            ? 'bg-digital/10 text-digital'
                            : check.status === 'warning'
                              ? 'bg-gold/15 text-gold'
                              : check.status === 'skipped'
                                ? 'bg-muted text-muted-foreground'
                                : 'bg-destructive/10 text-destructive',
                    )}
                >
                    <Icon className="size-5" strokeWidth={1.7} />
                </span>
                <KoraStatusBadge label={meta.label} tone={meta.tone} />
            </div>
            <h3 className="mt-4 text-sm font-extrabold tracking-[-0.01em] text-foreground">
                {check.label}
            </h3>
            <p className="mt-1 text-xs leading-relaxed font-medium text-muted-foreground">
                {check.message || check.summary || '—'}
            </p>
            {check.endedAt && (
                <p className="mt-3 text-[0.625rem] font-bold tracking-[0.08em] text-muted-foreground/70 uppercase">
                    {new Date(check.endedAt).toLocaleTimeString('fr-FR')}
                </p>
            )}
        </article>
    );
}
