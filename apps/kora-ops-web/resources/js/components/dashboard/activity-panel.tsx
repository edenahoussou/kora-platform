import { Link } from '@inertiajs/react';
import { ArrowRight, BarChart3, Inbox } from 'lucide-react';
import { Bar, BarChart, CartesianGrid, Cell, XAxis, YAxis } from 'recharts';
import type {
    DashboardActivity,
    PriorityItem,
    PriorityTone,
} from '@/components/dashboard/dashboard-data';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import { alerts, reports } from '@/routes';

const activityChartConfig = {
    value: {
        label: 'Recettes nettes',
        color: 'var(--kora)',
    },
} satisfies ChartConfig;

const moneyFormatter = new Intl.NumberFormat('fr-FR', {
    maximumFractionDigits: 0,
});

type ActivityPanelProps = {
    activity: DashboardActivity;
    periodLabel: string;
};

export function ActivityPanel({ activity, periodLabel }: ActivityPanelProps) {
    const title =
        activity.bucket === 'hour'
            ? "Recettes d'aujourd'hui, par heure"
            : 'Recettes nettes par jour';

    return (
        <article className="rounded-[1.125rem] border border-border/65 bg-card p-5 shadow-[0_12px_35px_rgba(0,29,78,0.045)] sm:p-6">
            <div className="mb-8 flex items-start justify-between gap-4">
                <div>
                    <div className="mb-2 flex items-center gap-2 text-[0.5625rem] font-black tracking-[0.13em] text-digital uppercase">
                        <BarChart3 className="size-3.5" />
                        Activité réseau
                    </div>
                    <h2 className="text-[0.9375rem] font-extrabold tracking-[-0.02em] text-foreground">
                        {title}
                    </h2>
                    <p className="mt-1 text-[0.6875rem] text-muted-foreground">
                        {periodLabel}
                    </p>
                </div>
                <Link
                    href={reports()}
                    className="text-[0.625rem] font-extrabold text-digital transition-colors hover:text-[var(--kora)] focus-visible:rounded focus-visible:ring-2 focus-visible:ring-gold"
                >
                    Voir le rapport
                </Link>
            </div>

            <ChartContainer
                config={activityChartConfig}
                className="aspect-auto h-[14.375rem] w-full min-w-0 border-b border-border/80"
            >
                <BarChart
                    data={activity.points}
                    margin={{ top: 8, right: 4, bottom: 0, left: 4 }}
                    accessibilityLayer
                >
                    <CartesianGrid
                        vertical={false}
                        strokeDasharray="3 8"
                        stroke="var(--border)"
                    />
                    <XAxis
                        dataKey="label"
                        tickLine={false}
                        axisLine={false}
                        tickMargin={8}
                        minTickGap={activity.bucket === 'hour' ? 12 : 8}
                        tick={{
                            fontSize: '0.5625rem',
                            fill: 'var(--muted-foreground)',
                        }}
                    />
                    <YAxis
                        hide
                        domain={[0, (dataMax: number) => Math.max(1, dataMax)]}
                    />
                    <ChartTooltip
                        cursor={{ fill: 'var(--muted)', opacity: 0.5 }}
                        content={
                            <ChartTooltipContent
                                formatter={(value) =>
                                    `${moneyFormatter.format(Number(value))} FCFA`
                                }
                            />
                        }
                    />
                    <Bar
                        dataKey="value"
                        radius={[7, 7, 0, 0]}
                        maxBarSize={44}
                        minPointSize={2}
                    >
                        {activity.points.map((point) => (
                            <Cell
                                key={point.label}
                                fill={
                                    point.isBest ? 'var(--gold)' : 'var(--kora)'
                                }
                            />
                        ))}
                    </Bar>
                </BarChart>
            </ChartContainer>

            <div className="mt-8 flex flex-wrap gap-x-5 gap-y-2 text-[0.625rem] text-muted-foreground">
                <span className="inline-flex items-center gap-1.5">
                    <i className="size-2 rounded-[0.1875rem] bg-digital" />
                    Recettes réalisées
                </span>
                <span className="inline-flex items-center gap-1.5">
                    <i className="size-2 rounded-[0.1875rem] bg-gold" />
                    {activity.bucket === 'hour'
                        ? 'Meilleure heure'
                        : 'Meilleure journée'}
                </span>
            </div>
        </article>
    );
}

const activityToneClasses: Record<
    PriorityTone,
    { rail: string; icon: string }
> = {
    urgent: {
        rail: 'bg-gold',
        icon: 'bg-gold/18 text-[var(--kora)]',
    },
    control: {
        rail: 'bg-digital',
        icon: 'bg-digital/10 text-digital',
    },
    success: {
        rail: 'bg-[var(--kora)]',
        icon: 'bg-[var(--kora)] text-white',
    },
    parcel: {
        rail: 'bg-gold',
        icon: 'bg-gold text-[var(--kora)]',
    },
};

type PriorityPanelProps = {
    priorities: PriorityItem[];
};

export function PriorityPanel({ priorities }: PriorityPanelProps) {
    return (
        <aside className="overflow-hidden rounded-[1.125rem] border border-border/65 bg-gradient-to-br from-card to-muted/70 shadow-[0_22px_54px_rgba(0,29,78,0.08)]">
            <div className="border-b border-border/70 px-5 py-5 sm:px-6">
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <span className="mb-2 block text-[0.5625rem] font-black tracking-[0.13em] text-gold uppercase">
                            Fil de contrôle
                        </span>
                        <h2 className="text-[1.0625rem] font-extrabold tracking-[-0.025em] text-foreground">
                            À suivre maintenant
                        </h2>
                        <p className="mt-1 text-[0.6875rem] text-muted-foreground">
                            Priorités de votre périmètre
                        </p>
                    </div>
                    <span className="grid size-8 place-items-center rounded-[0.625rem] bg-gold/15 text-[var(--kora)]">
                        <span className="size-1.5 rounded-full bg-gold shadow-[0_0_0_4px_rgba(246,176,0,0.13)]" />
                    </span>
                </div>
            </div>

            <div className="grid gap-2 px-3.5 py-3.5">
                {priorities.length === 0 && (
                    <p className="flex items-center gap-3 rounded-[0.9375rem] border border-dashed border-border/80 bg-card/60 px-4 py-6 text-[0.6875rem] font-semibold text-muted-foreground">
                        <Inbox className="size-4 text-digital" />
                        Rien à signaler — votre périmètre est à jour.
                    </p>
                )}
                {priorities.map((priority) => {
                    const tone = activityToneClasses[priority.tone];
                    const content = (
                        <>
                            <span
                                className={`absolute top-3.5 bottom-3.5 left-0 w-0.5 rounded-r-full ${tone.rail}`}
                            />
                            <span
                                className={`grid size-10 place-items-center rounded-[0.8125rem] ${tone.icon}`}
                            >
                                <BarChart3 className="size-4" />
                            </span>
                            <span className="min-w-0">
                                <em className="mb-1 block truncate text-[0.5rem] font-black tracking-[0.1em] text-digital uppercase not-italic">
                                    {priority.category}
                                </em>
                                <strong className="block truncate text-[0.6875rem] font-extrabold text-foreground">
                                    {priority.title}
                                </strong>
                                <small className="mt-1 block truncate text-[0.5625rem] text-muted-foreground">
                                    {priority.detail}
                                </small>
                            </span>
                            <span className="grid justify-items-end gap-2 text-[0.5625rem] text-muted-foreground">
                                <time>{priority.time}</time>
                                <ArrowRight className="size-3.5 -translate-x-1 opacity-0 transition-all duration-300 group-hover:translate-x-0 group-hover:opacity-100" />
                            </span>
                        </>
                    );

                    const classes =
                        'group relative grid min-h-[4.75rem] grid-cols-[2.5rem_minmax(0,1fr)_3rem] items-center gap-3 overflow-hidden rounded-[0.9375rem] border border-border/70 bg-card px-3 text-left shadow-[0_7px_20px_rgba(0,29,78,0.045)] transition-[transform,box-shadow,border-color] duration-300 hover:translate-x-1 hover:border-gold/60 hover:shadow-[0_14px_32px_rgba(0,29,78,0.1)] focus-visible:ring-2 focus-visible:ring-gold focus-visible:outline-none';

                    return priority.href ? (
                        <Link
                            key={priority.uuid}
                            href={priority.href}
                            className={classes}
                        >
                            {content}
                        </Link>
                    ) : (
                        <button
                            key={priority.uuid}
                            type="button"
                            className={classes}
                        >
                            {content}
                        </button>
                    );
                })}
            </div>

            <Link
                href={alerts()}
                className="flex min-h-12 items-center justify-between border-t border-border/70 bg-muted/60 px-5 text-[0.625rem] text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-gold focus-visible:ring-inset sm:px-6"
            >
                <span>Centre des alertes</span>
                <span className="font-extrabold text-digital">
                    Tout consulter →
                </span>
            </Link>
        </aside>
    );
}
