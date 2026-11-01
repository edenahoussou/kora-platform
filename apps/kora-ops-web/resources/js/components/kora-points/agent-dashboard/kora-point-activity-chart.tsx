import { Bell, Calendar, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';
import type {
    DailyActivity,
    PointPriorityAlert,
} from '../mock/kora-point-mock-data';

type Props = {
    activity7d: DailyActivity[];
    priorities: PointPriorityAlert[];
    onQuickAction: (actionId: string) => void;
};

export function KoraPointActivityChart({
    activity7d,
    priorities,
    onQuickAction,
}: Props) {
    const maxSales = Math.max(...activity7d.map((a) => a.sales_amount), 1);

    return (
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-[1.2fr_0.8fr]">
            {/* Left: 7 Days Activity Chart */}
            <section
                aria-label="Activité sur les 7 derniers jours"
                className="flex flex-col justify-between rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-6"
            >
                <div className="flex items-center justify-between border-b border-border/60 pb-4">
                    <div>
                        <span className="section-kicker text-gold">
                            Volume de distribution
                        </span>
                        <h2 className="text-base font-extrabold text-foreground">
                            Activité sur les 7 derniers jours
                        </h2>
                    </div>
                    <div className="flex items-center gap-2 text-xs font-semibold text-muted-foreground">
                        <Calendar className="size-3.5 text-muted-foreground" />
                        <span>Semaine en cours</span>
                    </div>
                </div>

                {/* Histogram Bars */}
                <div className="my-6 flex h-48 items-end justify-between gap-2 px-2 sm:gap-4 sm:px-4">
                    {activity7d.map((item) => {
                        const heightPct = Math.round(
                            (item.sales_amount / maxSales) * 100,
                        );

                        return (
                            <div
                                key={item.date}
                                className="group flex h-full flex-1 flex-col items-center justify-end gap-2"
                            >
                                {/* Tooltip on hover */}
                                <div className="pointer-events-none mb-1 rounded-md bg-slate-900 px-2 py-1 text-center text-[0.625rem] font-bold whitespace-nowrap text-white opacity-0 shadow-md transition-opacity group-hover:opacity-100 dark:bg-slate-100 dark:text-slate-900">
                                    <div>
                                        {item.sales_amount.toLocaleString(
                                            'fr-FR',
                                        )}{' '}
                                        FCFA
                                    </div>
                                    <div className="font-normal text-gold">
                                        Comm. +
                                        {item.commission_amount.toLocaleString(
                                            'fr-FR',
                                        )}
                                    </div>
                                </div>

                                <div className="relative flex h-full w-full max-w-[2.75rem] items-end">
                                    <div
                                        style={{ height: `${heightPct}%` }}
                                        className={cn(
                                            'w-full rounded-t-lg transition-all duration-300 group-hover:scale-105',
                                            item.is_peak
                                                ? 'bg-gold shadow-md'
                                                : 'bg-[var(--kora)]/80 hover:bg-[var(--kora)] dark:bg-digital/40 dark:hover:bg-digital/60',
                                        )}
                                    />
                                </div>

                                <span
                                    className={cn(
                                        'text-xs font-bold',
                                        item.is_peak
                                            ? 'text-amber-800 dark:text-gold'
                                            : 'text-slate-500 dark:text-slate-400',
                                    )}
                                >
                                    {item.day_short}
                                </span>
                            </div>
                        );
                    })}
                </div>

                {/* Legend */}
                <div className="flex items-center justify-between border-t border-slate-100 pt-3 text-xs text-slate-500 dark:border-slate-800">
                    <div className="flex items-center gap-4">
                        <span className="flex items-center gap-1.5">
                            <span className="size-2.5 rounded bg-[var(--kora)]/80" />
                            Ventes quotidiennes
                        </span>
                        <span className="flex items-center gap-1.5">
                            <span className="size-2.5 rounded bg-gold" />
                            Meilleure journée
                        </span>
                    </div>
                    <button
                        type="button"
                        onClick={() => onQuickAction('commissions')}
                        className="font-bold text-blue-600 hover:underline dark:text-blue-400"
                    >
                        Voir commissions →
                    </button>
                </div>
            </section>

            {/* Right: Priorities */}
            <section
                aria-label="Priorités du Point"
                className="flex flex-col justify-between rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-6"
            >
                <div>
                    <div className="flex items-center justify-between border-b border-border/60 pb-3">
                        <div className="flex items-center gap-2">
                            <Bell className="size-4 text-gold" />
                            <h2 className="text-sm font-extrabold text-foreground">
                                À suivre maintenant
                            </h2>
                        </div>
                        <span className="text-[0.625rem] font-black text-muted-foreground uppercase">
                            {priorities.length} éléments
                        </span>
                    </div>

                    <div className="mt-3 divide-y divide-border/60">
                        {priorities.map((alert) => (
                            <div
                                key={alert.id}
                                className="py-2.5 first:pt-1 last:pb-1"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <strong className="text-xs font-bold text-foreground">
                                        {alert.title}
                                    </strong>
                                    <span className="shrink-0 text-[0.625rem] font-medium text-muted-foreground">
                                        {alert.time_label}
                                    </span>
                                </div>
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    {alert.description}
                                </p>
                                {alert.action_label && alert.action_target && (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            onQuickAction(alert.action_target!)
                                        }
                                        className="mt-1.5 inline-flex items-center gap-1 text-[0.6875rem] font-bold text-digital hover:underline"
                                    >
                                        <span>{alert.action_label}</span>
                                        <ChevronRight className="size-3" />
                                    </button>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            </section>
        </div>
    );
}
