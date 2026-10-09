import { Head } from '@inertiajs/react';
import {
    Activity,
    Globe2,
    ListChecks,
    Server,
    TriangleAlert,
} from 'lucide-react';
import { HealthCheckCard } from '@/components/monitoring/health-check-card';
import { LogBrowser } from '@/components/monitoring/log-browser';
import type { HealthCheckItem } from '@/components/monitoring/monitoring-types';
import { index as horizonIndex } from '@/routes/horizon';

type PageProps = {
    app: {
        name: string;
        environment: string;
        debug: boolean;
        url: string;
        laravel: string;
        php: string;
    };
    checks: HealthCheckItem[];
    queues: {
        failedJobs: number;
    };
    logViewer: {
        apiBase: string;
    };
    generatedAt: string;
};

const environmentLabels: Record<string, string> = {
    production: 'Production',
    staging: 'Staging',
    local: 'Local',
    testing: 'Test',
};

export default function MonitoringPage({
    app,
    checks,
    queues,
    generatedAt,
}: PageProps) {
    const degraded = checks.filter(
        (check) => check.status === 'failed' || check.status === 'crashed',
    );

    return (
        <>
            <Head title="Monitoring" />
            <div className="mx-auto w-full max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-[0.625rem] font-extrabold tracking-[0.16em] text-muted-foreground uppercase">
                            Administration
                        </p>
                        <h1 className="mt-1 text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                            Monitoring
                        </h1>
                        <p className="mt-1 text-xs font-medium text-muted-foreground">
                            Santé des services et journaux applicatifs —{' '}
                            {new Date(generatedAt).toLocaleString('fr-FR')}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <span className="inline-flex items-center gap-2 rounded-full bg-card px-4 py-2 text-xs font-bold text-foreground ring-1 ring-black/[0.045] dark:ring-white/10">
                            <Globe2 className="size-3.5 text-digital" />
                            {environmentLabels[app.environment] ??
                                app.environment}
                        </span>
                        <span className="inline-flex items-center gap-2 rounded-full bg-card px-4 py-2 text-xs font-bold text-foreground ring-1 ring-black/[0.045] dark:ring-white/10">
                            <Server className="size-3.5 text-digital" />
                            Laravel {app.laravel} · PHP {app.php}
                        </span>
                        {app.debug && (
                            <span className="inline-flex items-center gap-2 rounded-full bg-gold/15 px-4 py-2 text-xs font-bold text-gold ring-1 ring-gold/25">
                                <TriangleAlert className="size-3.5" />
                                Debug activé
                            </span>
                        )}
                        <a
                            href={horizonIndex.url()}
                            className="inline-flex items-center gap-2 rounded-full bg-kora px-4 py-2 text-xs font-bold text-white ring-1 ring-kora/20 transition-colors hover:bg-kora/90"
                        >
                            <ListChecks className="size-3.5" />
                            Queues (Horizon)
                            {queues.failedJobs > 0 && (
                                <span className="rounded-full bg-destructive px-2 py-0.5 text-[0.625rem] font-extrabold text-white">
                                    {queues.failedJobs} échoué
                                    {queues.failedJobs > 1 ? 's' : ''}
                                </span>
                            )}
                        </a>
                    </div>
                </header>

                {degraded.length > 0 && (
                    <div className="flex items-start gap-3 rounded-2xl bg-destructive/10 px-5 py-4 ring-1 ring-destructive/25">
                        <TriangleAlert className="mt-0.5 size-5 shrink-0 text-destructive" />
                        <div>
                            <p className="text-sm font-extrabold text-destructive">
                                Santé dégradée — {degraded.length} check
                                {degraded.length > 1 ? 's' : ''} en échec
                            </p>
                            <p className="mt-0.5 text-xs font-medium text-destructive/80">
                                {degraded
                                    .map((check) => check.label)
                                    .join(', ')}
                            </p>
                        </div>
                    </div>
                )}

                <section className="space-y-4">
                    <div className="flex items-center gap-2">
                        <Activity className="size-4 text-digital" />
                        <h2 className="text-sm font-extrabold tracking-[-0.01em] text-foreground">
                            État des services
                        </h2>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {checks.map((check) => (
                            <HealthCheckCard key={check.name} check={check} />
                        ))}
                    </div>
                </section>

                <section className="space-y-4">
                    <div className="flex items-center gap-2">
                        <Server className="size-4 text-digital" />
                        <h2 className="text-sm font-extrabold tracking-[-0.01em] text-foreground">
                            Journaux applicatifs
                        </h2>
                    </div>
                    <LogBrowser />
                </section>
            </div>
        </>
    );
}
