import {
    metricCards,
    metricOrder,
} from '@/components/dashboard/dashboard-data';
import type {
    DashboardMetric,
    MetricKey,
    MetricTone,
} from '@/components/dashboard/dashboard-data';

type MetricGridProps = {
    metrics: Record<MetricKey, DashboardMetric>;
    periodLabel: string;
};

const toneClasses: Record<MetricTone, string> = {
    gold: 'bg-gold/18 text-[var(--kora)]',
    blue: 'bg-digital/10 text-digital',
    navy: 'bg-[var(--kora)] text-white',
    green: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
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

                    return (
                        <article
                            key={key}
                            className="group relative min-h-[10.5rem] overflow-hidden rounded-[1.125rem] border border-border/65 bg-card p-5 shadow-[0_16px_42px_rgba(0,29,78,0.06)] transition-[transform,box-shadow,border-color] duration-300 hover:-translate-y-1.5 hover:border-gold/60 hover:shadow-[0_25px_55px_rgba(0,29,78,0.12)]"
                        >
                            <span className="absolute top-6 left-0 h-10 w-0.5 rounded-r-full bg-gold" />
                            <div className="flex items-start justify-between gap-3">
                                <span
                                    className={`grid size-8 place-items-center rounded-[0.625rem] ${toneClasses[card.tone]}`}
                                >
                                    <Icon className="size-4" />
                                </span>
                                {spark.length > 0 && (
                                    <span className="flex h-7 items-end gap-1 opacity-80 transition-transform duration-500 group-hover:scale-110">
                                        {spark.map((height, index) => (
                                            <i
                                                key={`${key}-${index}`}
                                                className={`block w-1.5 rounded-full ${index >= spark.length - 2 ? 'bg-gold' : 'bg-digital/20'}`}
                                                style={{ height: `${height}%` }}
                                            />
                                        ))}
                                    </span>
                                )}
                            </div>
                            <span className="mt-4 block text-[0.625rem] font-bold text-muted-foreground">
                                {card.label}
                            </span>
                            <strong className="mt-1 block text-[1.45rem] font-extrabold tracking-[-0.045em] text-foreground tabular-nums">
                                {metric.value}
                            </strong>
                            <span className="mt-1 block text-[0.625rem] font-extrabold text-digital">
                                {metric.trend}
                            </span>
                        </article>
                    );
                })}
            </div>
        </section>
    );
}
