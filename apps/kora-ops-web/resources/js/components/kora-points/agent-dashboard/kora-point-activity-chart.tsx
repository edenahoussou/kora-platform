import { ArrowRight, Bell, Calendar, ChevronRight, Zap } from 'lucide-react';
import { DailyActivity, PointPriorityAlert } from '../mock/kora-point-mock-data';
import { cn } from '@/lib/utils';

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

    const quickShortcuts = [
        {
            id: 'sale',
            num: '01',
            title: 'Nouvelle vente',
            desc: 'Émettre un billet en 4 étapes',
        },
        {
            id: 'cash',
            num: '02',
            title: 'Ma caisse',
            desc: 'Contrôler les encaissements',
        },
        {
            id: 'commissions',
            num: '03',
            title: 'Mes commissions',
            desc: 'Consulter l’historique acquis',
        },
        {
            id: 'settlements',
            num: '04',
            title: 'Reversement',
            desc: 'Préparer ou suivre un versement',
        },
    ];

    return (
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-[1.2fr_0.8fr]">
            {/* Left: 7 Days Activity Chart */}
            <section
                aria-label="Activité sur les 7 derniers jours"
                className="flex flex-col justify-between rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm sm:p-6 dark:border-slate-800 dark:bg-slate-900"
            >
                <div className="flex items-center justify-between border-b border-slate-100 pb-4 dark:border-slate-800">
                    <div>
                        <span className="text-[0.625rem] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            Volume de distribution
                        </span>
                        <h2 className="text-base font-extrabold text-slate-900 dark:text-white">
                            Activité sur les 7 derniers jours
                        </h2>
                    </div>
                    <div className="flex items-center gap-2 text-xs font-semibold text-slate-500">
                        <Calendar className="size-3.5 text-slate-400" />
                        <span>Semaine en cours</span>
                    </div>
                </div>

                {/* Histogram Bars */}
                <div className="my-6 flex h-48 items-end justify-between gap-2 px-2 sm:gap-4 sm:px-4">
                    {activity7d.map((item) => {
                        const heightPct = Math.round((item.sales_amount / maxSales) * 100);
                        return (
                            <div
                                key={item.date}
                                className="group flex flex-1 flex-col items-center gap-2 h-full justify-end"
                            >
                                {/* Tooltip on hover */}
                                <div className="pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity rounded-md bg-slate-900 px-2 py-1 text-[0.625rem] font-bold text-white shadow-md dark:bg-slate-100 dark:text-slate-900 text-center whitespace-nowrap mb-1">
                                    <div>{item.sales_amount.toLocaleString('fr-FR')} FCFA</div>
                                    <div className="text-gold font-normal">
                                        Comm. +{item.commission_amount.toLocaleString('fr-FR')}
                                    </div>
                                </div>

                                <div className="relative w-full max-w-[2.75rem] h-full flex items-end">
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

            {/* Right: Priorities & Quick Actions */}
            <div className="flex flex-col gap-5">
                {/* Priorities to Watch */}
                <section
                    aria-label="Priorités du Point"
                    className="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm sm:p-6 dark:border-slate-800 dark:bg-slate-900"
                >
                    <div className="flex items-center justify-between border-b border-slate-100 pb-3 dark:border-slate-800">
                        <div className="flex items-center gap-2">
                            <Bell className="size-4 text-gold" />
                            <h2 className="text-sm font-extrabold text-slate-900 dark:text-white">
                                À suivre maintenant
                            </h2>
                        </div>
                        <span className="text-[0.625rem] font-black uppercase text-slate-400">
                            {priorities.length} éléments
                        </span>
                    </div>

                    <div className="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                        {priorities.map((alert) => (
                            <div key={alert.id} className="py-2.5 first:pt-1 last:pb-1">
                                <div className="flex items-start justify-between gap-2">
                                    <strong className="text-xs font-bold text-slate-900 dark:text-white">
                                        {alert.title}
                                    </strong>
                                    <span className="shrink-0 text-[0.625rem] font-medium text-slate-400">
                                        {alert.time_label}
                                    </span>
                                </div>
                                <p className="mt-0.5 text-xs text-slate-600 dark:text-slate-300">
                                    {alert.description}
                                </p>
                                {alert.action_label && alert.action_target && (
                                    <button
                                        type="button"
                                        onClick={() => onQuickAction(alert.action_target!)}
                                        className="mt-1.5 inline-flex items-center gap-1 text-[0.6875rem] font-bold text-blue-600 hover:underline dark:text-blue-400"
                                    >
                                        <span>{alert.action_label}</span>
                                        <ChevronRight className="size-3" />
                                    </button>
                                )}
                            </div>
                        ))}
                    </div>
                </section>

                {/* Quick Shortcuts */}
                <section
                    aria-label="Raccourcis rapides"
                    className="grid grid-cols-2 gap-2.5"
                >
                    {quickShortcuts.map((q) => (
                        <button
                            key={q.id}
                            type="button"
                            onClick={() => onQuickAction(q.id)}
                            className="group flex flex-col justify-between rounded-xl border border-slate-200/80 bg-white p-3.5 text-left shadow-sm transition hover:border-gold hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-gold"
                        >
                            <span className="font-mono text-[0.625rem] font-black text-gold">
                                {q.num}
                            </span>
                            <div className="mt-1">
                                <strong className="block text-xs font-extrabold text-slate-900 dark:text-white group-hover:text-gold transition">
                                    {q.title}
                                </strong>
                                <span className="block text-[0.625rem] text-slate-400">
                                    {q.desc}
                                </span>
                            </div>
                        </button>
                    ))}
                </section>
            </div>
        </div>
    );
}
