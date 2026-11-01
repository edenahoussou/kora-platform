import {
    HelpCircle,
    ReceiptText,
    Ticket,
    TrendingUp,
    Wallet,
} from 'lucide-react';
import { KoraTooltip } from '@/components/kora-tooltip';
import { cn } from '@/lib/utils';
import type {
    DailyMetrics,
    KoraPointTerms,
} from '../mock/kora-point-mock-data';

type Props = {
    metrics: DailyMetrics;
    terms: KoraPointTerms;
    onKpiClick?: (
        type: 'sales' | 'tickets' | 'commissions' | 'settlement',
    ) => void;
};

const kpiVariants = {
    blue: 'cash-ledger-card-kpi-digital',
    gold: 'cash-ledger-card-kpi-gold',
    emerald: 'cash-ledger-card-kpi-emerald',
    navy: 'cash-ledger-card-kpi-navy',
};

/** Normalise une série d'activité en pourcentages de hauteur pour les sparklines. */
function normalizeSpark(values: number[]): number[] {
    const max = Math.max(...values, 1);

    return values.map((value) => Math.max(10, Math.round((value / max) * 100)));
}

export function KoraPointKpiGrid({ metrics, terms, onKpiClick }: Props) {
    const ratePercent = (terms.commission_rate_bps / 100).toLocaleString(
        'fr-FR',
        {
            minimumFractionDigits: 1,
            maximumFractionDigits: 2,
        },
    );

    const salesSpark = normalizeSpark(
        metrics.activity_7d.map((d) => d.sales_amount),
    );
    const ticketsSpark = normalizeSpark(
        metrics.activity_7d.map((d) => d.tickets_count),
    );
    const commissionSpark = normalizeSpark(
        metrics.activity_7d.map((d) => d.commission_amount),
    );

    const kpis = [
        {
            id: 'sales' as const,
            label: 'Ventes du jour',
            hint: 'Recettes brutes encaissées',
            value: `${metrics.today_sales_amount.toLocaleString('fr-FR')} FCFA`,
            trend: "Aujourd'hui",
            tone: 'blue' as const,
            icon: Wallet,
            spark: salesSpark,
            formula:
                'Somme cumulée des règlements confirmés (espèces et mobile money) perçus pour des réservations de billets Bus aujourd’hui.',
        },
        {
            id: 'tickets' as const,
            label: 'Billets émis',
            hint: 'Passagers du jour',
            value: `${metrics.today_tickets_count}`,
            trend: 'Départs actifs',
            tone: 'gold' as const,
            icon: Ticket,
            spark: ticketsSpark,
            formula:
                'Nombre total de titres de transport émis aujourd’hui avec attribution de sièges confirmée par votre guichet.',
        },
        {
            id: 'commissions' as const,
            label: 'Commission du jour',
            hint: `Taux effectif : ${ratePercent} %`,
            value: `${metrics.today_commission_amount.toLocaleString('fr-FR')} FCFA`,
            trend: 'Acquise en direct',
            tone: 'emerald' as const,
            icon: TrendingUp,
            spark: commissionSpark,
            formula: `Commission nette acquise = Ventes du jour × Taux contractuel (${ratePercent} %). Ce montant est déduit de votre versement dû à KORA.`,
        },
        {
            id: 'settlement' as const,
            label: 'Encours à reverser',
            hint: 'Dette nette non soldée',
            value: `${metrics.total_due_amount.toLocaleString('fr-FR')} FCFA`,
            trend: 'Dû à KORA',
            tone: 'navy' as const,
            icon: ReceiptText,
            spark: [] as number[],
            formula:
                'Montant net à reverser à KORA = Ventes brutes − Commissions acquises − Versements déjà validés.',
        },
    ];

    return (
        <section aria-labelledby="point-performance-title">
            <div className="mb-3.5 flex items-end justify-between">
                <div>
                    <span className="mb-0.5 block text-[0.5625rem] font-black tracking-[0.15em] text-gold uppercase">
                        Activité temps réel
                    </span>
                    <h2
                        id="point-performance-title"
                        className="text-[1.125rem] font-extrabold tracking-[-0.03em] text-foreground sm:text-[1.25rem]"
                    >
                        Performance instantanée du guichet
                    </h2>
                </div>
            </div>

            <div className="grid grid-flow-row-dense grid-cols-1 gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
                {kpis.map((kpi) => {
                    const Icon = kpi.icon;
                    const onGold = kpi.tone === 'gold';

                    return (
                        <article
                            key={kpi.id}
                            tabIndex={0}
                            role="button"
                            onClick={() => onKpiClick?.(kpi.id)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' || e.key === ' ') {
                                    e.preventDefault();
                                    onKpiClick?.(kpi.id);
                                }
                            }}
                            className={cn(
                                'cash-ledger-card group cursor-pointer transition-[transform,box-shadow] duration-300 hover:-translate-y-1.5 hover:shadow-[0_25px_55px_rgba(0,29,78,0.14)] focus:outline-none focus-visible:ring-2 focus-visible:ring-gold',
                                kpiVariants[kpi.tone],
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
                                        <div className="flex items-center gap-1.5">
                                            <span className="cash-ledger-title">
                                                {kpi.label}
                                            </span>
                                            <KoraTooltip
                                                content={kpi.formula}
                                                side="top"
                                            >
                                                <button
                                                    type="button"
                                                    aria-label={`Explication du calcul : ${kpi.label}`}
                                                    onClick={(e) =>
                                                        e.stopPropagation()
                                                    }
                                                    className="opacity-70 transition-opacity hover:opacity-100 focus:outline-none"
                                                >
                                                    <HelpCircle className="size-3.5" />
                                                </button>
                                            </KoraTooltip>
                                        </div>
                                        <small>{kpi.hint}</small>
                                    </div>
                                </div>

                                {kpi.spark.length > 0 && (
                                    <span className="flex h-7 items-end gap-1 opacity-90 transition-transform duration-500 group-hover:scale-110">
                                        {kpi.spark.map((height, index) => (
                                            <i
                                                key={`${kpi.id}-${index}`}
                                                className={cn(
                                                    'block w-1.5 rounded-full',
                                                    index >=
                                                        kpi.spark.length - 2
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
                                <span>{kpi.trend}</span>
                                <strong>{kpi.value}</strong>
                            </div>
                        </article>
                    );
                })}
            </div>
        </section>
    );
}
