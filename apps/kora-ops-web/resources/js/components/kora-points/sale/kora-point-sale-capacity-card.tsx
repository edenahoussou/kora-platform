import { ShieldCheck, AlertTriangle, Wallet, TrendingUp, AlertCircle } from 'lucide-react';
import { FinancialHealth, KoraPointProfile } from '../mock/kora-point-mock-data';
import { cn } from '@/lib/utils';

type Props = {
    point: KoraPointProfile;
    health: FinancialHealth;
    totalSaleAmount: number;
    passengerCount: number;
};

export function KoraPointSaleCapacityCard({
    point,
    health,
    totalSaleAmount,
    passengerCount,
}: Props) {
    const isExceeded = totalSaleAmount > health.available_balance;
    const projectedExposure = health.current_exposure + totalSaleAmount;
    const projectedAvailable = Math.max(0, health.credit_limit - projectedExposure);
    const commissionAmount = Math.round(
        (totalSaleAmount * point.terms.commission_rate_bps) / 10000,
    );
    const ratePercent = (point.terms.commission_rate_bps / 100).toLocaleString('fr-FR', {
        minimumFractionDigits: 1,
        maximumFractionDigits: 2,
    });

    return (
        <aside
            aria-label="Capacité financière et impact sur l'encours"
            className="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        >
            <div className="flex items-center justify-between border-b border-slate-100 pb-3 dark:border-slate-800">
                <span className="text-xs font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                    Capacité du Point
                </span>
                {health.sales_allowed && !isExceeded ? (
                    <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/15 px-2 py-0.5 text-[0.625rem] font-black text-emerald-700 dark:text-emerald-400">
                        <ShieldCheck className="size-3" />
                        Vente autorisée
                    </span>
                ) : (
                    <span className="inline-flex items-center gap-1 rounded-full bg-rose-500/15 px-2 py-0.5 text-[0.625rem] font-black text-rose-700 dark:text-rose-400">
                        <AlertTriangle className="size-3" />
                        Plafond dépassé
                    </span>
                )}
            </div>

            {/* Balances recap */}
            <div className="mt-3 space-y-2 text-xs">
                <div className="flex items-center justify-between">
                    <span className="text-slate-500 dark:text-slate-400">Plafond de crédit :</span>
                    <strong className="font-mono text-slate-700 dark:text-slate-300">
                        {health.credit_limit.toLocaleString('fr-FR')} FCFA
                    </strong>
                </div>

                <div className="flex items-center justify-between">
                    <span className="text-slate-500 dark:text-slate-400">Disponible actuel :</span>
                    <strong className="font-mono text-emerald-600 dark:text-emerald-400">
                        {health.available_balance.toLocaleString('fr-FR')} FCFA
                    </strong>
                </div>

                <div className="flex items-center justify-between border-t border-slate-100 pt-2 font-bold dark:border-slate-800">
                    <span className="text-slate-900 dark:text-white">
                        Total panier ({passengerCount} place{passengerCount > 1 ? 's' : ''}) :
                    </span>
                    <strong className="font-mono text-sm text-slate-900 dark:text-white">
                        {totalSaleAmount.toLocaleString('fr-FR')} FCFA
                    </strong>
                </div>

                <div className="flex items-center justify-between text-slate-500 dark:text-slate-400">
                    <span>Encours après vente :</span>
                    <strong className="font-mono text-slate-800 dark:text-slate-200">
                        {projectedExposure.toLocaleString('fr-FR')} FCFA
                    </strong>
                </div>

                <div className="flex items-center justify-between text-slate-500 dark:text-slate-400">
                    <span>Disponible restant :</span>
                    <strong
                        className={cn(
                            'font-mono',
                            isExceeded
                                ? 'text-rose-600 font-black'
                                : 'text-slate-700 dark:text-slate-300',
                        )}
                    >
                        {projectedAvailable.toLocaleString('fr-FR')} FCFA
                    </strong>
                </div>
            </div>

            {/* Commission calculation preview */}
            <div className="mt-4 rounded-xl border border-amber-200/60 bg-amber-50/70 p-3 text-xs text-amber-950 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">
                <div className="flex items-center justify-between font-bold">
                    <span className="flex items-center gap-1.5">
                        <TrendingUp className="size-3.5 text-amber-600 dark:text-amber-400" />
                        Commission Point ({ratePercent} %) :
                    </span>
                    <strong className="font-mono text-sm text-amber-700 dark:text-gold">
                        +{commissionAmount.toLocaleString('fr-FR')} FCFA
                    </strong>
                </div>
                <p className="mt-1 text-[0.6875rem] text-amber-900/70 dark:text-amber-200/70">
                    Montant retenu directement par le Point sur le montant encaissé.
                </p>
            </div>

            {/* Block Warning if exceeded */}
            {isExceeded && (
                <div className="mt-3 rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-200">
                    <div className="flex items-center gap-1.5 font-bold">
                        <AlertCircle className="size-4 shrink-0 text-rose-600 dark:text-rose-400" />
                        <span>Plafond insuffisant</span>
                    </div>
                    <p className="mt-1 text-[0.6875rem] leading-relaxed">
                        Le panier dépasse votre plafond disponible de{' '}
                        <strong>
                            {(totalSaleAmount - health.available_balance).toLocaleString('fr-FR')} FCFA
                        </strong>
                        . Effectuez ou faites valider un reversement pour libérer du disponible.
                    </p>
                </div>
            )}
        </aside>
    );
}
