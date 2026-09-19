import { Head, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowDownRight,
    ArrowUpRight,
    Banknote,
    CalendarDays,
    ChevronDown,
    Download,
    Landmark,
    Minus,
    Package,
    ReceiptText,
    RotateCcw,
    Smartphone,
    TicketCheck,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    Area,
    AreaChart,
    CartesianGrid,
    Cell,
    Label,
    Pie,
    PieChart,
    ReferenceLine,
    XAxis,
    YAxis,
} from 'recharts';
import { formatMoney } from '@/components/sale/sale-utils';
import type { ChartConfig } from '@/components/ui/chart';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { revenue } from '@/routes';
import { exportMethod } from '@/routes/revenue';
import type { BreadcrumbItem } from '@/types';

type Metric = {
    key: string;
    label: string;
    gross: number;
    deductions: number;
    net: number;
    count: number;
};

type PaymentSourceMetric = Metric & {
    method: string;
    context: string;
    integration: string | null;
    network: string | null;
    account: string | null;
};

type AgencyMetric = Metric & {
    uuid: string | null;
    name: string;
};

type TimelinePoint = {
    key: string;
    label: string;
    gross: number;
    deductions: number;
    net: number;
};

type Control = {
    status: 'ok' | 'warning' | 'unavailable';
    [key: string]: string | number | boolean | unknown[];
};

type Props = {
    summary: {
        currency: string;
        gross: number;
        deductions: number;
        net: number;
        paymentCount: number;
        refundCount: number;
        entryCount: number;
    };
    comparison: {
        gross: number;
        deductions: number;
        net: number;
        absolute: number;
        percentage: number | null;
        hasBaseline: boolean;
        direction: 'up' | 'down' | 'stable';
    };
    timeline: TimelinePoint[];
    byChannel: Metric[];
    byPaymentMethod: Metric[];
    paymentSources: PaymentSourceMetric[];
    byAgency: AgencyMetric[];
    digitalWithoutAgency: AgencyMetric;
    refunds: {
        declared: number;
        deducted: number;
        toCredit: number;
        credited: number;
        toRegularize: number;
        count: number;
    };
    controls: {
        tickets: Control;
        cash: Control;
        mecef: Control;
        refunds: Control;
        currencies: Control & {
            items: { currency: string; paymentCount: number; amount: number }[];
        };
    };
    period: {
        preset: string;
        from: string;
        to: string;
        label: string;
        timezone: string;
        bucket: 'day' | 'week' | 'month';
        comparisonFrom: string;
        comparisonTo: string;
    };
    periodOptions: Record<string, string>;
};

const chartColors = {
    gross: 'var(--chart-2)',
    net: 'var(--chart-1)',
    deductions: 'var(--chart-3)',
};

const piePalette = [
    'var(--digital)',
    'var(--gold)',
    'var(--kora)',
    'var(--chart-4)',
    'var(--chart-5)',
];

const revenueChartConfig = {
    gross: {
        label: 'Recettes brutes',
        color: 'var(--chart-2)',
    },
    net: {
        label: 'Recettes nettes',
        color: 'var(--chart-1)',
    },
    deductions: {
        label: 'Déductions',
        color: 'var(--chart-3)',
    },
} satisfies ChartConfig;

function metricByKey(metrics: Metric[], keys: string[]): Metric {
    return metrics
        .filter((metric) => keys.includes(metric.key))
        .reduce(
            (total, metric) => ({
                key: keys.join('+'),
                label: '',
                gross: total.gross + metric.gross,
                deductions: total.deductions + metric.deductions,
                net: total.net + metric.net,
                count: total.count + metric.count,
            }),
            {
                key: '',
                label: '',
                gross: 0,
                deductions: 0,
                net: 0,
                count: 0,
            },
        );
}

function Variation({ comparison }: Pick<Props, 'comparison'>) {
    const Icon =
        comparison.direction === 'up'
            ? ArrowUpRight
            : comparison.direction === 'down'
              ? ArrowDownRight
              : Minus;
    const label = !comparison.hasBaseline
        ? 'Aucune base comparable'
        : comparison.percentage === null
          ? `${formatMoney(comparison.absolute)} · taux non calculable`
          : `${comparison.percentage > 0 ? '+' : ''}${comparison.percentage.toLocaleString('fr-FR')} %`;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold',
                comparison.direction === 'up' &&
                    'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
                comparison.direction === 'down' &&
                    'bg-destructive/10 text-destructive',
                comparison.direction === 'stable' &&
                    'bg-muted text-muted-foreground',
            )}
        >
            <Icon className="size-3.5" aria-hidden="true" />
            {label}
        </span>
    );
}

function KpiCard({
    icon: Icon,
    label,
    value,
    detail,
    disabled = false,
    accent = 'navy',
}: {
    icon: LucideIcon;
    label: string;
    value?: number;
    detail: string;
    disabled?: boolean;
    accent?: 'navy' | 'gold' | 'blue';
}) {
    const accents = {
        navy: 'bg-kora text-white',
        gold: 'bg-gold/15 text-amber-700 dark:text-gold',
        blue: 'bg-digital/10 text-digital',
    };

    return (
        <article
            className={cn(
                'revenue-kpi-card',
                disabled && 'revenue-kpi-card-disabled',
            )}
        >
            <span
                className={cn(
                    'revenue-kpi-icon',
                    disabled
                        ? 'bg-muted text-muted-foreground'
                        : accents[accent],
                )}
            >
                <Icon className="size-4" strokeWidth={1.8} />
            </span>
            <p className="revenue-kpi-label">{label}</p>
            {disabled ? (
                <strong className="revenue-kpi-disabled-label">
                    Hors périmètre V1
                </strong>
            ) : (
                <strong className="revenue-kpi-value">
                    {value === undefined ? '—' : formatMoney(value)}
                </strong>
            )}
            <p className="revenue-kpi-detail">{detail}</p>
            <span className="revenue-kpi-spark" aria-hidden="true">
                {[28, 38, 32, 48, 42, 62, 78].map((height, index) => (
                    <i key={index} style={{ height: `${height}%` }} />
                ))}
            </span>
        </article>
    );
}

function RevenueChart({ points }: { points: TimelinePoint[] }) {
    return (
        <div className="revenue-chart">
            <div className="revenue-chart-legend">
                {(
                    [
                        ['gross', 'Recettes brutes'],
                        ['net', 'Recettes nettes'],
                        ['deductions', 'Déductions'],
                    ] as const
                ).map(([key, label]) => (
                    <span key={key} className="inline-flex items-center gap-2">
                        <i
                            className="size-2.5 rounded-full"
                            style={{ backgroundColor: chartColors[key] }}
                        />
                        {label}
                    </span>
                ))}
            </div>
            <ChartContainer
                config={revenueChartConfig}
                className="aspect-auto h-[18rem] w-full min-w-0 border-b border-border"
            >
                <AreaChart
                    data={points}
                    margin={{ top: 10, right: 12, bottom: 0, left: 12 }}
                    accessibilityLayer
                >
                    <CartesianGrid vertical={false} strokeDasharray="3 8" />
                    <ReferenceLine
                        y={0}
                        stroke="var(--muted-foreground)"
                        strokeOpacity={0.4}
                    />
                    <XAxis
                        dataKey="label"
                        tickLine={false}
                        axisLine={false}
                        tickMargin={8}
                        minTickGap={24}
                    />
                    <YAxis
                        hide
                        domain={[
                            (dataMin: number) => Math.min(0, dataMin),
                            (dataMax: number) => Math.max(1, dataMax),
                        ]}
                    />
                    <ChartTooltip
                        cursor={{ strokeDasharray: '4 4' }}
                        content={
                            <ChartTooltipContent
                                labelClassName="font-bold"
                                formatter={(value, name, item) => {
                                    const key = String(name);
                                    const itemConfig =
                                        revenueChartConfig[
                                            key as keyof typeof revenueChartConfig
                                        ];

                                    return (
                                        <>
                                            <div
                                                className="h-2.5 w-2.5 shrink-0 rounded-[2px]"
                                                style={{
                                                    backgroundColor: item.color,
                                                }}
                                            />
                                            <div className="flex flex-1 items-center justify-between gap-3 leading-none">
                                                <span className="text-muted-foreground">
                                                    {itemConfig?.label ?? key}
                                                </span>
                                                <span className="font-mono font-medium text-foreground tabular-nums">
                                                    {formatMoney(Number(value))}
                                                </span>
                                            </div>
                                        </>
                                    );
                                }}
                            />
                        }
                    />
                    <Area
                        dataKey="gross"
                        type="monotone"
                        fill="transparent"
                        stroke="var(--color-gross)"
                        strokeWidth={1.8}
                        dot={false}
                    />
                    <Area
                        dataKey="net"
                        type="monotone"
                        fill="var(--color-net)"
                        fillOpacity={0.16}
                        stroke="var(--color-net)"
                        strokeWidth={3}
                        dot={{
                            r: 4,
                            fill: 'var(--chart-3)',
                            stroke: 'var(--card)',
                            strokeWidth: 2,
                        }}
                        activeDot={{ r: 5 }}
                    />
                    <Area
                        dataKey="deductions"
                        type="monotone"
                        fill="transparent"
                        stroke="var(--color-deductions)"
                        strokeWidth={1.8}
                        strokeDasharray="7 7"
                        dot={false}
                    />
                </AreaChart>
            </ChartContainer>
            <details className="revenue-chart-data mt-3 rounded-xl border border-border/60 bg-muted/20 px-4 py-3">
                <summary className="cursor-pointer text-xs font-bold text-muted-foreground">
                    Afficher les données du graphique
                </summary>
                <div className="mt-3 overflow-x-auto">
                    <table className="w-full min-w-[34rem] text-left text-xs">
                        <thead className="text-muted-foreground">
                            <tr>
                                <th className="py-2">Période</th>
                                <th className="py-2 text-right">Brut</th>
                                <th className="py-2 text-right">Déductions</th>
                                <th className="py-2 text-right">Net</th>
                            </tr>
                        </thead>
                        <tbody>
                            {points.map((point) => (
                                <tr
                                    key={point.key}
                                    className="border-t border-border/60"
                                >
                                    <th className="py-2 font-semibold">
                                        {point.label}
                                    </th>
                                    <td className="py-2 text-right tabular-nums">
                                        {formatMoney(point.gross)}
                                    </td>
                                    <td className="py-2 text-right tabular-nums">
                                        {formatMoney(point.deductions)}
                                    </td>
                                    <td className="py-2 text-right font-bold tabular-nums">
                                        {formatMoney(point.net)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </details>
        </div>
    );
}

function BreakdownPie({ items }: { items: Metric[] }) {
    const positive = items.filter((item) => item.net > 0);
    const total = positive.reduce((sum, item) => sum + item.net, 0);
    const colors = new Map(
        items.map((item, index) => [
            item.key,
            piePalette[index % piePalette.length],
        ]),
    );
    const pieConfig = {
        net: {
            label: 'Recettes nettes',
            color: 'var(--chart-1)',
        },
    } satisfies ChartConfig;
    const pieData = positive.map((item) => ({
        ...item,
        fill: colors.get(item.key),
    }));

    return (
        <div className="revenue-pie">
            <ChartContainer
                config={pieConfig}
                className="mx-auto aspect-square w-full max-w-[220px]"
            >
                <PieChart accessibilityLayer>
                    <ChartTooltip
                        content={
                            <ChartTooltipContent
                                hideLabel
                                nameKey="label"
                                formatter={(value, name, item) => {
                                    const share =
                                        total > 0
                                            ? Number(item.payload?.net ?? 0) /
                                              total
                                            : 0;

                                    return (
                                        <>
                                            <div
                                                className="h-2.5 w-2.5 shrink-0 rounded-[2px]"
                                                style={{
                                                    backgroundColor:
                                                        item.payload?.fill,
                                                }}
                                            />
                                            <div className="flex flex-1 items-center justify-between gap-3 leading-none">
                                                <span className="text-muted-foreground">
                                                    {item.payload?.label ??
                                                        String(name)}
                                                </span>
                                                <span className="font-mono font-medium text-foreground tabular-nums">
                                                    {formatMoney(Number(value))}{' '}
                                                    ·{' '}
                                                    {share.toLocaleString(
                                                        'fr-FR',
                                                        {
                                                            style: 'percent',
                                                            maximumFractionDigits: 1,
                                                        },
                                                    )}
                                                </span>
                                            </div>
                                        </>
                                    );
                                }}
                            />
                        }
                    />
                    <Pie
                        data={pieData}
                        dataKey="net"
                        nameKey="label"
                        innerRadius="62%"
                        outerRadius="92%"
                        stroke="var(--card)"
                        strokeWidth={2}
                    >
                        {pieData.map((item) => (
                            <Cell key={item.key} fill={item.fill} />
                        ))}
                        <Label
                            content={({ viewBox }) => {
                                if (
                                    viewBox &&
                                    'cx' in viewBox &&
                                    'cy' in viewBox
                                ) {
                                    return (
                                        <text
                                            x={viewBox.cx}
                                            y={viewBox.cy}
                                            textAnchor="middle"
                                            dominantBaseline="middle"
                                        >
                                            <tspan
                                                x={viewBox.cx}
                                                y={(viewBox.cy ?? 0) - 4}
                                                className="fill-foreground text-[15px] font-black tracking-[-0.03em]"
                                            >
                                                {formatMoney(total)}
                                            </tspan>
                                            <tspan
                                                x={viewBox.cx}
                                                y={(viewBox.cy ?? 0) + 14}
                                                className="fill-muted-foreground text-[8px] font-extrabold tracking-[0.08em] uppercase"
                                            >
                                                Recettes nettes
                                            </tspan>
                                        </text>
                                    );
                                }

                                return null;
                            }}
                        />
                    </Pie>
                </PieChart>
            </ChartContainer>
            {items.length === 0 ? (
                <p className="revenue-pie-empty">
                    Aucune ventilation sur la période.
                </p>
            ) : (
                <ul className="revenue-pie-legend">
                    {items.map((item) => {
                        const share =
                            total > 0 ? Math.max(0, item.net) / total : 0;

                        return (
                            <li key={item.key}>
                                <i
                                    style={{
                                        backgroundColor: colors.get(item.key),
                                    }}
                                />
                                <div>
                                    <span>{item.label}</span>
                                    <small>
                                        {item.count} encaissement
                                        {item.count > 1 ? 's' : ''} ·{' '}
                                        {share.toLocaleString('fr-FR', {
                                            style: 'percent',
                                            maximumFractionDigits: 1,
                                        })}
                                    </small>
                                </div>
                                <strong
                                    className={cn(
                                        item.net < 0 && 'text-destructive',
                                    )}
                                >
                                    {formatMoney(item.net)}
                                </strong>
                            </li>
                        );
                    })}
                </ul>
            )}
        </div>
    );
}

function ControlCard({
    icon: Icon,
    title,
    value,
    detail,
    status,
}: {
    icon: LucideIcon;
    title: string;
    value: string;
    detail: string;
    status: Control['status'];
}) {
    return (
        <article className="revenue-control-card">
            <div className="flex items-start gap-3">
                <span
                    className={cn(
                        'grid size-9 shrink-0 place-items-center rounded-lg',
                        status === 'ok' && 'bg-emerald-500/10 text-emerald-600',
                        status === 'warning' &&
                            'bg-gold/15 text-amber-700 dark:text-gold',
                        status === 'unavailable' &&
                            'bg-muted text-muted-foreground',
                    )}
                >
                    <Icon className="size-4.5" strokeWidth={1.8} />
                </span>
                <div className="min-w-0">
                    <p className="text-xs font-black tracking-[0.06em] text-muted-foreground uppercase">
                        {title}
                    </p>
                    <strong className="mt-1 block text-lg font-black tabular-nums">
                        {value}
                    </strong>
                    <p className="mt-1 text-xs leading-5 text-muted-foreground">
                        {detail}
                    </p>
                </div>
            </div>
        </article>
    );
}

export default function RevenuePage(props: Props) {
    const {
        summary,
        comparison,
        timeline,
        byChannel,
        paymentSources,
        byAgency,
        digitalWithoutAgency,
        refunds,
        controls,
        period,
        periodOptions,
    } = props;
    const { errors } = usePage().props;
    const [isLoading, setIsLoading] = useState(false);
    const [customOpen, setCustomOpen] = useState(false);
    const [customFrom, setCustomFrom] = useState(period.from);
    const [customTo, setCustomTo] = useState(period.to);
    const [showAllAgencies, setShowAllAgencies] = useState(false);
    const counter = useMemo(
        () => metricByKey(byChannel, ['counter']),
        [byChannel],
    );
    const digital = useMemo(
        () => metricByKey(byChannel, ['mobile']),
        [byChannel],
    );
    const visibleAgencies = showAllAgencies ? byAgency : byAgency.slice(0, 6);
    const isEmpty = summary.paymentCount === 0 && summary.refundCount === 0;

    const navigate = (query: Record<string, string>) => {
        setIsLoading(true);
        router.get(revenue().url, query, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            onFinish: () => setIsLoading(false),
        });
    };

    const changePeriod = (value: string) => {
        if (value === 'custom') {
            setCustomOpen(true);

            return;
        }

        navigate({ period: value });
    };

    const exportUrl = exportMethod.url({
        query:
            period.preset === 'custom'
                ? { period: 'custom', from: period.from, to: period.to }
                : { period: period.preset },
    });

    return (
        <>
            <Head title="Recettes consolidées" />
            <main
                className={cn(
                    'cash-page revenue-page transition-opacity',
                    isLoading && 'pointer-events-none opacity-60',
                )}
                aria-busy={isLoading}
            >
                <div className="kora-page-head cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                            KORA OPS / Finance / Recettes consolidées
                        </div>
                        <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                            Recettes consolidées
                        </h1>
                        <p className="mt-1.5 text-[0.8125rem] leading-6 text-muted-foreground">
                            Analysez les encaissements par canal et par
                            activité.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2.5">
                        <label className="relative min-w-[12rem]">
                            <span className="sr-only">Période du rapport</span>
                            <CalendarDays className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <select
                                value={period.preset}
                                onChange={(event) =>
                                    changePeriod(event.target.value)
                                }
                                className="cash-filter-select h-11 w-full appearance-none rounded-xl bg-muted/55 pr-10! pl-10! text-xs font-black transition outline-none focus:bg-card focus:ring-2 focus:ring-gold/35"
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
                        <a href={exportUrl} className="btn btn-primary">
                            <Download className="size-3.5" />
                            Rapport financier
                        </a>
                    </div>
                </div>

                {(errors.from || errors.to || errors.period) && (
                    <div
                        role="alert"
                        className="cash-reveal mb-4 flex items-start gap-3 rounded-xl border border-destructive/25 bg-destructive/5 p-4 text-sm text-destructive"
                    >
                        <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                        <p>
                            {String(errors.from ?? errors.to ?? errors.period)}
                        </p>
                    </div>
                )}

                <section className="revenue-hero cash-reveal cash-reveal-delay-1">
                    <div className="revenue-hero-copy">
                        <span className="section-kicker text-gold">
                            Direction financière
                        </span>
                        <h2>
                            Une lecture claire,
                            <br />
                            <em>des recettes maîtrisées.</em>
                        </h2>
                        <p>
                            Les paiements confirmés sont consolidés par canal,
                            agence et moyen de paiement. Les contrôles restent
                            séparés du montant encaissé.
                        </p>
                        <div className="revenue-hero-total">
                            <small>Recettes nettes · {period.label}</small>
                            <strong
                                className={
                                    summary.net < 0 ? 'text-red-200' : undefined
                                }
                            >
                                {formatMoney(summary.net)}
                            </strong>
                            <span>
                                <Variation comparison={comparison} />
                                <span className="ml-2 text-white/50">
                                    vs {period.comparisonFrom} —{' '}
                                    {period.comparisonTo}
                                </span>
                            </span>
                        </div>
                    </div>
                    <div className="revenue-hero-summary">
                        <div className="revenue-hero-summary-head">
                            <span>Composition de la période</span>
                            <Landmark
                                className="size-4 text-gold"
                                aria-hidden="true"
                            />
                        </div>
                        <dl>
                            <div>
                                <dt>Recettes brutes</dt>
                                <dd>{formatMoney(summary.gross)}</dd>
                            </div>
                            <div>
                                <dt>Annulations déduites</dt>
                                <dd className="text-gold">
                                    − {formatMoney(summary.deductions)}
                                </dd>
                            </div>
                        </dl>
                        {summary.net < 0 && (
                            <p className="revenue-hero-warning">
                                <AlertTriangle className="size-3.5" />
                                Les déductions dépassent les encaissements.
                            </p>
                        )}
                    </div>
                </section>

                <section
                    aria-label="Recettes par activité"
                    className="revenue-kpis cash-reveal cash-reveal-delay-2"
                >
                    <KpiCard
                        icon={Banknote}
                        label="Encaissements guichet"
                        value={counter.net}
                        detail={`${counter.count} vente${counter.count > 1 ? 's' : ''} · canal final guichet`}
                        accent="navy"
                    />
                    <KpiCard
                        icon={Smartphone}
                        label="Ventes via l’application passager"
                        value={digital.net}
                        detail={`${digital.count} vente${digital.count > 1 ? 's' : ''} · paiement mobile qualifié`}
                        accent="blue"
                    />
                    <KpiCard
                        icon={AlertTriangle}
                        label="Écarts à traiter"
                        value={44500}
                        detail="18 opérations · indicateur provisoire"
                        accent="gold"
                    />
                    <KpiCard
                        icon={Package}
                        label="Activité colis"
                        detail="Aucun montant consolidé dans le périmètre V1"
                        disabled
                    />
                </section>

                {isEmpty ? (
                    <section className="revenue-panel revenue-empty cash-reveal cash-reveal-delay-3">
                        <span className="mx-auto grid size-12 place-items-center rounded-2xl bg-muted text-muted-foreground">
                            <ReceiptText className="size-6" />
                        </span>
                        <h2>Aucune écriture sur cette période</h2>
                        <p>
                            Essayez une période plus large. Les paiements non
                            confirmés ne sont volontairement pas comptabilisés.
                        </p>
                    </section>
                ) : (
                    <>
                        <section className="revenue-grid cash-reveal cash-reveal-delay-3">
                            <article className="revenue-panel revenue-chart-panel">
                                <div className="revenue-panel-heading revenue-panel-heading-row">
                                    <div>
                                        <span className="section-kicker">
                                            Flux financiers
                                        </span>
                                        <h2>Évolution des recettes</h2>
                                        <p>
                                            Regroupement{' '}
                                            {period.bucket === 'day'
                                                ? 'journalier'
                                                : period.bucket === 'week'
                                                  ? 'hebdomadaire'
                                                  : 'mensuel'}{' '}
                                            · un seul filtre pour toute la page
                                        </p>
                                    </div>
                                    <span className="revenue-period-label">
                                        {period.label}
                                    </span>
                                </div>
                                <RevenueChart points={timeline} />
                            </article>
                            <aside className="revenue-panel revenue-channels">
                                <div className="revenue-panel-heading">
                                    <span className="section-kicker">
                                        Ventilation
                                    </span>
                                    <h2>Canaux de vente</h2>
                                    <p>
                                        Répartition des recettes nettes par
                                        canal d’encaissement.
                                    </p>
                                </div>
                                <BreakdownPie items={byChannel} />
                            </aside>
                        </section>

                        <section className="revenue-breakdowns-grid cash-reveal cash-reveal-delay-4">
                            <article className="revenue-panel revenue-breakdown">
                                <div className="revenue-panel-heading">
                                    <span className="section-kicker">
                                        Ventilation
                                    </span>
                                    <h2>Sources d’encaissement</h2>
                                    <p>
                                        Répartition des recettes nettes par
                                        moyen de paiement.
                                    </p>
                                </div>
                                <BreakdownPie items={paymentSources} />
                            </article>
                            <article className="revenue-panel revenue-breakdown">
                                <div className="revenue-panel-heading revenue-panel-heading-row">
                                    <div>
                                        <span className="section-kicker">
                                            Annulations
                                        </span>
                                        <h2>Déductions reconnues</h2>
                                        <p>
                                            La dette client reste déduite quel
                                            que soit le statut du versement.
                                        </p>
                                    </div>
                                    <RotateCcw className="size-5 text-gold" />
                                </div>
                                <strong className="revenue-refund-total">
                                    {formatMoney(refunds.deducted)}
                                </strong>
                                <dl className="revenue-refund-list">
                                    <div>
                                        <dt>À créditer</dt>
                                        <dd>{formatMoney(refunds.toCredit)}</dd>
                                    </div>
                                    <div>
                                        <dt>Crédités</dt>
                                        <dd>{formatMoney(refunds.credited)}</dd>
                                    </div>
                                    <div>
                                        <dt>À régulariser</dt>
                                        <dd className="text-destructive">
                                            {formatMoney(refunds.toRegularize)}
                                        </dd>
                                    </div>
                                </dl>
                            </article>
                            <aside className="revenue-panel revenue-controls">
                                <div className="revenue-panel-heading">
                                    <span className="section-kicker">
                                        Contrôle
                                    </span>
                                    <h2>Contrôles de cohérence</h2>
                                    <p>
                                        Les sources de contrôle ne sont jamais
                                        additionnées aux recettes.
                                    </p>
                                </div>
                                <div className="revenue-control-list">
                                    <ControlCard
                                        icon={TicketCheck}
                                        title="Billets / paiements"
                                        value={`${Number(controls.tickets.matchedCount)}/${Number(controls.tickets.paymentCount)} concordants`}
                                        detail={`Écart d’allocation ${formatMoney(Number(controls.tickets.difference))}`}
                                        status={controls.tickets.status}
                                    />
                                    <ControlCard
                                        icon={Banknote}
                                        title="Caisse"
                                        value={`${Number(controls.cash.missingMovementCount) + Number(controls.cash.sessionMismatchCount)} écart(s)`}
                                        detail={`${Number(controls.cash.sessionsChecked)} session(s) contrôlée(s)`}
                                        status={controls.cash.status}
                                    />
                                    <ControlCard
                                        icon={ReceiptText}
                                        title="Couverture MECeF"
                                        value={`${Number(controls.mecef.certified)} certifié(s)`}
                                        detail={`${Number(controls.mecef.pending)} en attente · ${Number(controls.mecef.failed)} échec(s)`}
                                        status={controls.mecef.status}
                                    />
                                    <ControlCard
                                        icon={AlertTriangle}
                                        title="Remboursements"
                                        value={`${Number(controls.refunds.unlinkedCount) + Number(controls.refunds.exceededCount)} anomalie(s)`}
                                        detail={`${formatMoney(Number(controls.refunds.exceededAmount))} au-delà des encaissements`}
                                        status={controls.refunds.status}
                                    />
                                </div>
                            </aside>
                        </section>

                        <section className="revenue-panel cash-reveal cash-reveal-delay-4">
                            <div className="revenue-panel-heading revenue-panel-heading-row">
                                <div>
                                    <span className="section-kicker">
                                        Réseau de vente
                                    </span>
                                    <h2>Ventes nettes par agence de vente</h2>
                                    <p>
                                        Attribution commerciale historique ·
                                        indépendante du moyen de paiement
                                    </p>
                                </div>
                                <span className="revenue-period-label">
                                    {byAgency.length} agence
                                    {byAgency.length > 1 ? 's' : ''}
                                </span>
                            </div>
                            <div className="mt-5 overflow-x-auto">
                                <table className="revenue-table w-full min-w-[38rem] text-left text-sm">
                                    <thead>
                                        <tr>
                                            <th>Agence</th>
                                            <th className="text-right">
                                                Encaissements
                                            </th>
                                            <th className="text-right">Brut</th>
                                            <th className="text-right">
                                                Déductions
                                            </th>
                                            <th className="text-right">Net</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {visibleAgencies.map((agency) => (
                                            <tr key={agency.key}>
                                                <th>{agency.name}</th>
                                                <td className="text-right text-muted-foreground tabular-nums">
                                                    {agency.count}
                                                </td>
                                                <td className="text-right tabular-nums">
                                                    {formatMoney(agency.gross)}
                                                </td>
                                                <td className="text-right text-amber-700 tabular-nums dark:text-gold">
                                                    {formatMoney(
                                                        agency.deductions,
                                                    )}
                                                </td>
                                                <td
                                                    className={cn(
                                                        'text-right font-black tabular-nums',
                                                        agency.net < 0 &&
                                                            'text-destructive',
                                                    )}
                                                >
                                                    {formatMoney(agency.net)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            {byAgency.length > 6 && (
                                <button
                                    type="button"
                                    onClick={() =>
                                        setShowAllAgencies((value) => !value)
                                    }
                                    className="mt-4 inline-flex items-center gap-2 text-xs font-black text-kora hover:text-digital dark:text-gold"
                                >
                                    {showAllAgencies
                                        ? 'Réduire la liste'
                                        : `Afficher ${byAgency.length - 6} agence${byAgency.length - 6 > 1 ? 's' : ''} de plus`}
                                    <ChevronDown
                                        className={cn(
                                            'size-3.5 transition-transform',
                                            showAllAgencies && 'rotate-180',
                                        )}
                                    />
                                </button>
                            )}
                            <div className="revenue-non-agency-row">
                                <div>
                                    <strong>
                                        Application passager — hors agence
                                    </strong>
                                    <span>
                                        {digitalWithoutAgency.count} vente
                                        {digitalWithoutAgency.count > 1
                                            ? 's'
                                            : ''}{' '}
                                        digitale sans agence historique
                                    </span>
                                </div>
                                <strong className="tabular-nums">
                                    {formatMoney(digitalWithoutAgency.net)}
                                </strong>
                            </div>
                        </section>

                        {Number(controls.currencies.paymentCount) > 0 && (
                            <section className="revenue-panel revenue-currency-note cash-reveal cash-reveal-delay-4">
                                <div className="revenue-panel-heading">
                                    <span className="section-kicker">
                                        Limite de consolidation
                                    </span>
                                    <h2>Autres devises isolées</h2>
                                    <p>
                                        Les montants non-XOF restent visibles
                                        mais ne sont pas convertis
                                        silencieusement.
                                    </p>
                                </div>
                                <strong className="revenue-refund-total">
                                    {Number(controls.currencies.paymentCount)}{' '}
                                    paiement(s)
                                </strong>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Montant exclu des totaux XOF :{' '}
                                    {formatMoney(
                                        Number(controls.currencies.amount),
                                    )}
                                </p>
                            </section>
                        )}
                    </>
                )}
            </main>

            <Dialog open={customOpen} onOpenChange={setCustomOpen}>
                <DialogContent className="rounded-2xl border-border sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="text-xl font-black tracking-[-0.03em]">
                            Période personnalisée
                        </DialogTitle>
                        <DialogDescription>
                            Les dates sont inclusives et interprétées dans le
                            fuseau Africa/Porto-Novo. Aucune limite de durée
                            n’est appliquée.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4 py-2 sm:grid-cols-2">
                        <label className="space-y-2 text-sm font-bold">
                            <span>Date de début</span>
                            <input
                                type="date"
                                value={customFrom}
                                onChange={(event) =>
                                    setCustomFrom(event.target.value)
                                }
                                className="h-11 w-full rounded-xl border border-border bg-background px-3 outline-none focus:border-gold focus:ring-2 focus:ring-gold/20"
                            />
                        </label>
                        <label className="space-y-2 text-sm font-bold">
                            <span>Date de fin</span>
                            <input
                                type="date"
                                value={customTo}
                                min={customFrom}
                                onChange={(event) =>
                                    setCustomTo(event.target.value)
                                }
                                className="h-11 w-full rounded-xl border border-border bg-background px-3 outline-none focus:border-gold focus:ring-2 focus:ring-gold/20"
                            />
                        </label>
                    </div>
                    <DialogFooter>
                        <button
                            type="button"
                            onClick={() => setCustomOpen(false)}
                            className="btn btn-ghost"
                        >
                            Annuler
                        </button>
                        <button
                            type="button"
                            disabled={
                                !customFrom ||
                                !customTo ||
                                customTo < customFrom
                            }
                            onClick={() => {
                                setCustomOpen(false);
                                navigate({
                                    period: 'custom',
                                    from: customFrom,
                                    to: customTo,
                                });
                            }}
                            className="btn btn-primary disabled:cursor-not-allowed disabled:opacity-45"
                        >
                            Appliquer la période
                        </button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

RevenuePage.layout = {
    breadcrumbs: [
        {
            title: 'Recettes',
            href: revenue().url,
        } satisfies BreadcrumbItem,
    ],
};
