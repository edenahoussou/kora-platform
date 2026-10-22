import {
    Area,
    AreaChart,
    CartesianGrid,
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
import type { ProfitabilityTimeline } from './profitability-types';

const trendColors = {
    revenueCum: 'var(--chart-1)',
    costCum: 'var(--chart-2)',
    prevRevenueCum: 'var(--muted-foreground)',
};

const trendChartConfig = {
    revenueCum: {
        label: 'Recette cumulée',
        color: 'var(--chart-1)',
    },
    costCum: {
        label: 'Coûts cumulés',
        color: 'var(--chart-2)',
    },
    prevRevenueCum: {
        label: 'Période précédente',
        color: 'var(--muted-foreground)',
    },
} satisfies ChartConfig;

type Props = {
    timeline: ProfitabilityTimeline;
    filtered: boolean;
};

export function ProfitabilityTrend({ timeline, filtered }: Props) {
    if (timeline.points.length < 3) {
        return null;
    }

    return (
        <div className="profit-trend">
            <span className="section-kicker">Trajectoire</span>
            <h2>Trajectoire de la période</h2>
            <p className="profit-trend-sub">
                Recette cumulée par date de départ vs coûts engagés
                {filtered ? ' — périmètre filtré' : ''}
                {timeline.breakEvenDay !== null
                    ? ` · rentable à partir du ${timeline.breakEvenDay}`
                    : ''}
                .
            </p>
            <div className="profit-trend-legend">
                {(
                    [
                        ['revenueCum', 'Recette cumulée'],
                        ['costCum', 'Coûts cumulés'],
                        ['prevRevenueCum', 'Période précédente'],
                    ] as const
                ).map(([key, label]) => (
                    <span key={key} className="inline-flex items-center gap-2">
                        <i
                            className="size-2.5 rounded-full"
                            style={{ backgroundColor: trendColors[key] }}
                        />
                        {label}
                    </span>
                ))}
            </div>
            <ChartContainer
                config={trendChartConfig}
                className="aspect-auto h-[15rem] w-full min-w-0"
            >
                <AreaChart
                    data={timeline.points}
                    margin={{ top: 10, right: 12, bottom: 0, left: 12 }}
                    accessibilityLayer
                >
                    <CartesianGrid vertical={false} strokeDasharray="3 8" />
                    <ReferenceLine
                        y={0}
                        stroke="var(--muted-foreground)"
                        strokeOpacity={0.4}
                    />
                    {timeline.breakEvenDay !== null && (
                        <ReferenceLine
                            x={timeline.breakEvenDay}
                            stroke="var(--chart-1)"
                            strokeDasharray="4 4"
                            strokeOpacity={0.5}
                        />
                    )}
                    <XAxis
                        dataKey="label"
                        tickLine={false}
                        axisLine={false}
                        tickMargin={8}
                        minTickGap={24}
                    />
                    <YAxis hide domain={[0, 'dataMax']} />
                    <ChartTooltip
                        cursor={{ strokeDasharray: '4 4' }}
                        content={
                            <ChartTooltipContent
                                labelClassName="font-bold"
                                formatter={(value, name, item) => {
                                    const key = String(name);
                                    const itemConfig =
                                        trendChartConfig[
                                            key as keyof typeof trendChartConfig
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
                        dataKey="prevRevenueCum"
                        type="monotone"
                        fill="transparent"
                        stroke="var(--color-prevRevenueCum)"
                        strokeWidth={1.6}
                        strokeDasharray="6 6"
                        dot={false}
                    />
                    <Area
                        dataKey="costCum"
                        type="monotone"
                        fill="transparent"
                        stroke="var(--color-costCum)"
                        strokeWidth={1.8}
                        dot={false}
                    />
                    <Area
                        dataKey="revenueCum"
                        type="monotone"
                        fill="var(--color-revenueCum)"
                        fillOpacity={0.14}
                        stroke="var(--color-revenueCum)"
                        strokeWidth={3}
                        dot={false}
                        activeDot={{ r: 5 }}
                    />
                </AreaChart>
            </ChartContainer>
            <details className="profit-trend-data mt-3 rounded-xl border border-border/60 bg-muted/20 px-4 py-3">
                <summary className="cursor-pointer text-xs font-bold text-muted-foreground">
                    Afficher les données du graphique
                </summary>
                <div className="mt-3 overflow-x-auto">
                    <table className="w-full min-w-[34rem] text-left text-xs">
                        <thead className="text-muted-foreground">
                            <tr>
                                <th className="py-2">Période</th>
                                <th className="py-2 text-right">Recette</th>
                                <th className="py-2 text-right">Coûts</th>
                                <th className="py-2 text-right">
                                    Recette cumulée
                                </th>
                                <th className="py-2 text-right">
                                    Coûts cumulés
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {timeline.points.map((point) => (
                                <tr
                                    key={point.key}
                                    className="border-t border-border/60"
                                >
                                    <td className="py-2 font-medium">
                                        {point.label}
                                    </td>
                                    <td className="py-2 text-right tabular-nums">
                                        {formatMoney(point.revenue)}
                                    </td>
                                    <td className="py-2 text-right tabular-nums">
                                        {formatMoney(point.cost)}
                                    </td>
                                    <td className="py-2 text-right tabular-nums">
                                        {formatMoney(point.revenueCum)}
                                    </td>
                                    <td className="py-2 text-right tabular-nums">
                                        {formatMoney(point.costCum)}
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
