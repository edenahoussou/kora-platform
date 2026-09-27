import { Head, router } from '@inertiajs/react';
import { CalendarDays, ChevronDown } from 'lucide-react';
import { useState } from 'react';
import {
    ActivityPanel,
    PriorityPanel,
} from '@/components/dashboard/activity-panel';
import type {
    DashboardActivity,
    DashboardMetric,
    DashboardPeriod,
    HeroAction,
    MetricKey,
    NetworkReadings,
    PriorityItem,
    QuickAction,
} from '@/components/dashboard/dashboard-data';
import { DashboardHero } from '@/components/dashboard/dashboard-hero';
import { MetricGrid } from '@/components/dashboard/metric-grid';
import { QuickActions } from '@/components/dashboard/quick-actions';
import { dashboard } from '@/routes';

type PageProps = {
    metrics: Record<MetricKey, DashboardMetric>;
    activity: DashboardActivity;
    priorities: PriorityItem[];
    network: NetworkReadings;
    heroAction: HeroAction;
    quickActions: QuickAction[];
    period: DashboardPeriod;
    periodOptions: Record<string, string>;
};

const reloadableProps = [
    'metrics',
    'activity',
    'priorities',
    'network',
    'quickActions',
    'period',
];

export default function Dashboard({
    metrics,
    activity,
    priorities,
    network,
    heroAction,
    quickActions,
    period,
    periodOptions,
}: PageProps) {
    const [customFrom, setCustomFrom] = useState(period.from);
    const [customTo, setCustomTo] = useState(period.to);
    const [isLoading, setIsLoading] = useState(false);

    const navigate = (filters: Record<string, string>) => {
        router.get(dashboard().url, filters, {
            preserveState: true,
            only: reloadableProps,
            onStart: () => setIsLoading(true),
            onFinish: () => setIsLoading(false),
        });
    };

    const changePeriod = (preset: string) => {
        if (preset === 'custom') {
            navigate({
                period: 'custom',
                from: customFrom,
                to: customTo,
            });

            return;
        }

        navigate({ period: preset });
    };

    const applyCustomRange = () => {
        navigate({ period: 'custom', from: customFrom, to: customTo });
    };

    return (
        <>
            <Head title="Centre de pilotage" />

            <main
                className={`w-full max-w-full overflow-x-hidden transition-opacity ${isLoading ? 'pointer-events-none opacity-60' : ''}`}
                aria-busy={isLoading}
            >
                <div className="flex flex-col gap-5 pb-8">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="kora-breadcrumb text-[0.6875rem] text-muted-foreground">
                            KORA OPS / Centre de pilotage
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <label className="relative min-w-[11rem]">
                                <span className="sr-only">
                                    Période du tableau de bord
                                </span>
                                <CalendarDays className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <select
                                    value={period.preset}
                                    onChange={(event) =>
                                        changePeriod(event.target.value)
                                    }
                                    className="h-10 w-full appearance-none rounded-[0.75rem] border border-border/70 bg-card pr-9 pl-9 text-[0.6875rem] font-bold text-foreground shadow-sm transition outline-none focus:border-gold/50 focus:ring-2 focus:ring-gold/35"
                                >
                                    {Object.entries(periodOptions).map(
                                        ([value, label]) => (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        ),
                                    )}
                                </select>
                                <ChevronDown className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            </label>
                            {period.preset === 'custom' && (
                                <form
                                    className="flex items-center gap-2"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        applyCustomRange();
                                    }}
                                >
                                    <input
                                        type="date"
                                        value={customFrom}
                                        onChange={(event) =>
                                            setCustomFrom(event.target.value)
                                        }
                                        className="h-10 rounded-[0.75rem] border border-border/70 bg-card px-3 text-[0.6875rem] font-bold text-foreground shadow-sm outline-none focus:border-gold/50 focus:ring-2 focus:ring-gold/35"
                                        aria-label="Début de la période"
                                    />
                                    <span className="text-[0.6875rem] font-bold text-muted-foreground">
                                        →
                                    </span>
                                    <input
                                        type="date"
                                        value={customTo}
                                        onChange={(event) =>
                                            setCustomTo(event.target.value)
                                        }
                                        className="h-10 rounded-[0.75rem] border border-border/70 bg-card px-3 text-[0.6875rem] font-bold text-foreground shadow-sm outline-none focus:border-gold/50 focus:ring-2 focus:ring-gold/35"
                                        aria-label="Fin de la période"
                                    />
                                    <button
                                        type="submit"
                                        className="h-10 rounded-[0.75rem] bg-[var(--kora)] px-4 text-[0.6875rem] font-extrabold text-white transition hover:opacity-90 focus-visible:ring-2 focus-visible:ring-gold"
                                    >
                                        Appliquer
                                    </button>
                                </form>
                            )}
                        </div>
                    </div>

                    <DashboardHero heroAction={heroAction} network={network} />

                    <MetricGrid metrics={metrics} periodLabel={period.label} />

                    <section className="grid grid-flow-row-dense grid-cols-1 gap-4 lg:grid-cols-5">
                        <div className="lg:col-span-3">
                            <ActivityPanel
                                activity={activity}
                                periodLabel={period.label}
                            />
                        </div>
                        <div className="lg:col-span-2">
                            <PriorityPanel priorities={priorities} />
                        </div>
                    </section>

                    <QuickActions actions={quickActions} />
                </div>
            </main>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
