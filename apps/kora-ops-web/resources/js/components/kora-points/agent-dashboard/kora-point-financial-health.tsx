import { AlertCircle, ArrowRight, CheckCircle2, Clock } from 'lucide-react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { cn } from '@/lib/utils';
import type {
    FinancialHealth,
    KoraPointProfile,
} from '../mock/kora-point-mock-data';

type Props = {
    point: KoraPointProfile;
    health: FinancialHealth;
    onPrepareSettlement: () => void;
};

export function KoraPointFinancialHealth({
    point,
    health,
    onPrepareSettlement,
}: Props) {
    const isAutonomous = !point.organization;
    const usage = health.usage_percentage;

    // Determine status badge and visual tone
    let statusTone: 'emerald' | 'amber' | 'destructive' = 'emerald';
    let statusLabel = 'Prêt à vendre';
    let statusDescription =
        'Encours normal. Vous pouvez émettre des billets sans restriction.';

    if (health.is_overdue) {
        statusTone = 'destructive';
        statusLabel = 'Échéance en retard';
        statusDescription =
            'La date de reversement hebdomadaire est dépassée. Vos ventes sont temporairement suspendues.';
    } else if (usage >= 100) {
        statusTone = 'destructive';
        statusLabel = 'Plafond 100 % atteint';
        statusDescription =
            'Votre encours a atteint le plafond autorisé. Les nouvelles ventes sont bloquées jusqu’à validation d’un reversement.';
    } else if (usage >= 80) {
        statusTone = 'amber';
        statusLabel = 'Seuil d’alerte 80 %';
        statusDescription =
            'Attention : vous approchez du plafond de crédit. Anticipez votre reversement pour éviter tout blocage.';
    }

    return (
        <section
            aria-label="Santé financière et disponibilité des ventes"
            className="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm sm:p-6 dark:border-slate-800 dark:bg-slate-900"
        >
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <div className="flex items-center gap-2">
                        <span className="text-[0.625rem] font-black tracking-wider text-slate-500 uppercase dark:text-slate-400">
                            Disponibilité des ventes & Encours
                        </span>
                        <KoraStatusBadge
                            label={statusLabel}
                            tone={statusTone}
                            variant="solid"
                        />
                    </div>
                    <h2 className="mt-1 text-base font-extrabold text-slate-900 sm:text-lg dark:text-white">
                        Situation financière du Point
                    </h2>
                </div>

                {/* Next due date */}
                <div className="flex items-center gap-2 rounded-xl bg-slate-100/80 px-3.5 py-2 text-xs font-semibold text-slate-700 dark:bg-slate-800/80 dark:text-slate-200">
                    <Clock className="size-4 shrink-0 text-slate-500 dark:text-slate-400" />
                    <div>
                        <div className="text-[0.625rem] font-bold text-slate-400 uppercase">
                            Prochaine échéance
                        </div>
                        <div>{health.next_due_label}</div>
                    </div>
                </div>
            </div>

            {/* Gauge and numbers */}
            <div className="mt-5 grid gap-4 lg:grid-cols-[1.2fr_0.8fr] lg:items-center">
                {/* Visual Progress Bar */}
                <div>
                    <div className="flex items-center justify-between text-xs">
                        <span className="font-semibold text-slate-600 dark:text-slate-300">
                            Consommation du plafond :{' '}
                            <strong className="font-mono text-slate-900 dark:text-white">
                                {usage} %
                            </strong>
                        </span>
                        <span className="text-[0.6875rem] font-medium text-slate-400">
                            Alerte à 80 % · Blocage à 100 %
                        </span>
                    </div>

                    <div className="relative mt-2 h-3.5 w-full overflow-hidden rounded-full bg-slate-100 p-0.5 dark:bg-slate-800">
                        <div
                            style={{ width: `${Math.min(usage, 100)}%` }}
                            className={cn(
                                'h-full rounded-full transition-all duration-500',
                                usage >= 100
                                    ? 'bg-rose-500'
                                    : usage >= 80
                                      ? 'bg-amber-500'
                                      : 'bg-emerald-500',
                            )}
                        />
                        {/* 80% marker */}
                        <div
                            style={{ left: '80%' }}
                            className="absolute top-0 bottom-0 w-0.5 bg-slate-400/60"
                            title="Seuil d'alerte à 80 %"
                        />
                    </div>

                    <p className="mt-2 text-xs text-slate-500 dark:text-slate-400">
                        {statusDescription}
                    </p>
                </div>

                {/* Key Financial figures */}
                <div className="grid grid-cols-3 gap-2 rounded-xl border border-slate-100 bg-slate-50/70 p-3 text-center sm:gap-3 dark:border-slate-800/60 dark:bg-slate-800/40">
                    <div>
                        <span className="block text-[0.625rem] font-bold text-slate-500 uppercase">
                            Encours actuel
                        </span>
                        <strong className="mt-0.5 block font-mono text-xs font-black text-slate-900 sm:text-sm dark:text-white">
                            {health.current_exposure.toLocaleString('fr-FR')}
                        </strong>
                        <small className="text-[0.5625rem] text-slate-400">
                            FCFA
                        </small>
                    </div>

                    <div className="border-x border-slate-200/60 dark:border-slate-700/60">
                        <span className="block text-[0.625rem] font-bold text-slate-500 uppercase">
                            Plafond
                        </span>
                        <strong className="mt-0.5 block font-mono text-xs font-black text-slate-700 sm:text-sm dark:text-slate-300">
                            {health.credit_limit.toLocaleString('fr-FR')}
                        </strong>
                        <small className="text-[0.5625rem] text-slate-400">
                            FCFA
                        </small>
                    </div>

                    <div>
                        <span className="block text-[0.625rem] font-bold text-slate-500 uppercase">
                            Disponible
                        </span>
                        <strong
                            className={cn(
                                'mt-0.5 block font-mono text-xs font-black sm:text-sm',
                                health.available_balance > 0
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-rose-600 dark:text-rose-400',
                            )}
                        >
                            {health.available_balance.toLocaleString('fr-FR')}
                        </strong>
                        <small className="text-[0.5625rem] text-slate-400">
                            FCFA
                        </small>
                    </div>
                </div>
            </div>

            {/* Bottom Context Banner / Action Button */}
            <div className="mt-4 flex flex-col items-start justify-between gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center dark:border-slate-800">
                <div className="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                    {isAutonomous ? (
                        <>
                            <CheckCircle2 className="size-4 shrink-0 text-emerald-500" />
                            <span>
                                Point autonome : vous préparez et déclarez
                                directement vos reversements de recettes.
                            </span>
                        </>
                    ) : (
                        <>
                            <AlertCircle className="size-4 shrink-0 text-slate-400" />
                            <span>
                                Bureau rattaché à{' '}
                                <strong>{point.organization?.name}</strong> :
                                les reversements sont groupés et traités par le
                                trésorier.
                            </span>
                        </>
                    )}
                </div>

                {isAutonomous ? (
                    <button
                        type="button"
                        onClick={onPrepareSettlement}
                        className="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-slate-900 px-3.5 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100"
                    >
                        <span>Préparer un reversement</span>
                        <ArrowRight className="size-3" />
                    </button>
                ) : (
                    <button
                        type="button"
                        onClick={onPrepareSettlement}
                        className="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                    >
                        <span>Consulter les dossiers</span>
                        <ArrowRight className="size-3" />
                    </button>
                )}
            </div>
        </section>
    );
}
