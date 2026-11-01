import { Ticket, TrendingUp, Wallet, ReceiptText, ArrowUpRight } from 'lucide-react';
import type { DailyMetrics, KoraPointTerms } from '../mock/kora-point-mock-data';

type Props = {
    metrics: DailyMetrics;
    terms: KoraPointTerms;
    onKpiClick?: (type: 'sales' | 'tickets' | 'commissions' | 'settlement') => void;
};

export function KoraPointKpiGrid({ metrics, terms, onKpiClick }: Props) {
    const ratePercent = (terms.commission_rate_bps / 100).toLocaleString('fr-FR', {
        minimumFractionDigits: 1,
        maximumFractionDigits: 2,
    });

    const kpis = [
        {
            id: 'sales' as const,
            title: 'Ventes du jour',
            value: `${metrics.today_sales_amount.toLocaleString('fr-FR')} FCFA`,
            subtitle: 'Recettes guichet encaissées aujourd’hui',
            trend: '+ 9,4 %',
            icon: Wallet,
            accent: 'border-l-4 border-l-blue-500',
            iconBg: 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400',
        },
        {
            id: 'tickets' as const,
            title: 'Billets émis',
            value: `${metrics.today_tickets_count}`,
            subtitle: 'Voyageurs enregistrés sur départs actifs',
            trend: '18 aujourd’hui',
            icon: Ticket,
            accent: 'border-l-4 border-l-gold',
            iconBg: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
        },
        {
            id: 'commissions' as const,
            title: 'Commission du jour',
            value: `${metrics.today_commission_amount.toLocaleString('fr-FR')} FCFA`,
            subtitle: `Taux contractuel effectif : ${ratePercent} %`,
            trend: '+ 7,2 %',
            icon: TrendingUp,
            accent: 'border-l-4 border-l-emerald-500',
            iconBg: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400',
        },
        {
            id: 'settlement' as const,
            title: 'Encours total à reverser',
            value: `${metrics.total_due_amount.toLocaleString('fr-FR')} FCFA`,
            subtitle: 'Dette nette cumulée non soldée',
            trend: 'Fenêtre globale',
            icon: ReceiptText,
            accent: 'border-l-4 border-l-kora',
            iconBg: 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200',
        },
    ];

    return (
        <section aria-label="Indicateurs de performance du Point">
            <div className="mb-3 flex items-center justify-between">
                <div>
                    <span className="text-[0.625rem] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        Aujourd'hui vs Encours global
                    </span>
                    <h2 className="text-sm font-extrabold text-slate-900 dark:text-white sm:text-base">
                        Performance instantanée
                    </h2>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {kpis.map((kpi) => {
                    const Icon = kpi.icon;

                    return (
                        <button
                            key={kpi.id}
                            type="button"
                            onClick={() => onKpiClick?.(kpi.id)}
                            className={`group relative flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 ${kpi.accent}`}
                        >
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-bold text-slate-500 dark:text-slate-400">
                                    {kpi.title}
                                </span>
                                <div className={`grid size-8 place-items-center rounded-lg ${kpi.iconBg}`}>
                                    <Icon className="size-4" />
                                </div>
                            </div>

                            <div className="my-3">
                                <strong className="font-mono text-xl font-black tracking-tight text-slate-900 sm:text-2xl dark:text-white">
                                    {kpi.value}
                                </strong>
                                <p className="mt-1 line-clamp-1 text-[0.6875rem] text-slate-500 dark:text-slate-400">
                                    {kpi.subtitle}
                                </p>
                            </div>

                            <div className="flex items-center justify-between border-t border-slate-100 pt-2 text-[0.6875rem] font-semibold text-slate-400 dark:border-slate-800/80">
                                <span>{kpi.trend}</span>
                                <span className="flex items-center gap-0.5 text-slate-600 transition group-hover:text-slate-900 dark:text-slate-300 dark:group-hover:text-white">
                                    Détails <ArrowUpRight className="size-3" />
                                </span>
                            </div>
                        </button>
                    );
                })}
            </div>
        </section>
    );
}
