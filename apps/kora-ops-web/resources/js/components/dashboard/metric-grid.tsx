import {
    metricCards,
    metricOrder,
} from '@/components/dashboard/dashboard-data';
import type {
    DashboardMetric,
    MetricKey,
    MetricTone,
} from '@/components/dashboard/dashboard-data';
import { cn } from '@/lib/utils';

type MetricGridProps = {
    metrics: Record<MetricKey, DashboardMetric>;
    periodLabel: string;
};

const kpiVariants: Record<MetricTone, string> = {
    gold: 'cash-ledger-card-kpi-gold',
    blue: 'cash-ledger-card-kpi-digital',
    navy: 'cash-ledger-card-kpi-navy',
    green: 'cash-ledger-card-kpi-emerald',
};

/** Normalise une série brute en pourcentages de hauteur pour les sparklines. */
function normalizeSpark(values: number[]): number[] {
    const max = Math.max(...values, 1);

    return values.map((value) => Math.max(8, Math.round((value / max) * 100)));
}

export function MetricGrid({ metrics, periodLabel }: MetricGridProps) {
    return (
        <section aria-labelledby="performance-title">
            <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <span className="mb-1 block text-[0.5625rem] font-black tracking-[0.15em] text-gold uppercase">
                        {periodLabel}
                    </span>
                    <h2
                        id="performance-title"
                        className="text-[1.25rem] font-extrabold tracking-[-0.035em] text-foreground"
                    >
                        Performance instantanée
                    </h2>
                </div>
            </div>

            <div className="grid grid-flow-row-dense grid-cols-1 gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
                {metricOrder.map((key) => {
                    const metric = metrics[key];
                    const card = metricCards[key];
                    const Icon = card.icon;
                    const spark = normalizeSpark(metric.spark);
                    const onGold = card.tone === 'gold';

                    return (
                        <article
                            key={key}
                            className={cn(
                                'cash-ledger-card group transition-[transform,box-shadow] duration-300 hover:-translate-y-1.5 hover:shadow-[0_25px_55px_rgba(0,29,78,0.14)]',
                                kpiVariants[card.tone],
                            )}
                        >
                            <div className="cash-ledger-head">
                                <div className="cash-ledger-identity">
                                    <span className="cash-ledger-brand">
                                        <Icon
                                            className="size-4"
                                            strokeWidth={1.8}
                                        />
                                    </span>
                                    <div>
                                        <span className="cash-ledger-title">
                                            {card.label}
                                        </span>
                                        <small>{card.hint}</small>
                                    </div>
                                </div>
                                {spark.length > 0 && (
                                    <span className="flex h-7 items-end gap-1 opacity-90 transition-transform duration-500 group-hover:scale-110">
                                        {spark.map((height, index) => (
                                            <i
                                                key={`${key}-${index}`}
                                                className={cn(
                                                    'block w-1.5 rounded-full',
                                                    index >= spark.length - 2
                                                        ? onGold
                                                            ? 'bg-[var(--kora)]'
                                                            : 'bg-gold'
                                                        : onGold
                                                          ? 'bg-[var(--kora)]/25'
                                                          : 'bg-white/25',
                                                )}
                                                style={{ height: `${height}%` }}
                                            />
                                        ))}
                                    </span>
                                )}
                            </div>
                            <div className="cash-ledger-balance mb-0">
                                <span>{metric.trend}</span>
                                <strong>{metric.value}</strong>
                            </div>
                        </article>
                    );
                })}
            </div>
        </section>
    );
}
