import {
    Wallet,
    Banknote,
    ArrowRight,
    Clock,
    Lock,
    Unlock,
    ReceiptText,
    TrendingUp,
} from 'lucide-react';
import { useState } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { cn } from '@/lib/utils';
import type {
    FinancialHealth,
    KoraPointProfile,
    PointCashSession,
} from '../mock/kora-point-mock-data';
import { KoraPointCashSessionDialog } from './kora-point-cash-session-dialog';

type Props = {
    point: KoraPointProfile;
    health: FinancialHealth;
    cashSession: PointCashSession;
    onOpenSession: (amount: number) => void;
    onCloseSession: (actualCash: number, note?: string) => void;
    onNavigateToSettlements: () => void;
};

export function KoraPointCashView({
    point,
    health,
    cashSession,
    onOpenSession,
    onCloseSession,
    onNavigateToSettlements,
}: Props) {
    const [dialogMode, setDialogMode] = useState<'OPEN' | 'CLOSE' | null>(null);
    const isOpen = cashSession.status === 'OPEN';
    const isAutonomous = !point.organization;

    return (
        <div className="space-y-6">
            {/* Modal Open/Close */}
            <KoraPointCashSessionDialog
                isOpen={dialogMode !== null}
                mode={dialogMode || 'OPEN'}
                session={cashSession}
                onClose={() => setDialogMode(null)}
                onConfirmOpen={onOpenSession}
                onConfirmClose={onCloseSession}
            />

            {/* Header */}
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div className="flex items-center gap-2">
                        <span className="text-[0.625rem] font-black uppercase tracking-wider text-slate-500">
                            Gestion financière locale
                        </span>
                        <KoraStatusBadge
                            label={isOpen ? 'Session ouverte' : 'Session clôturée'}
                            tone={isOpen ? 'emerald' : 'muted'}
                            variant="solid"
                        />
                    </div>
                    <h1 className="text-xl font-black text-slate-900 dark:text-white sm:text-2xl">
                        Caisse du Guichet & Dette de Reversement
                    </h1>
                </div>

                {isOpen ? (
                    <button
                        type="button"
                        onClick={() => setDialogMode('CLOSE')}
                        className="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-xs font-bold text-slate-800 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 shrink-0"
                    >
                        <Lock className="size-4 text-rose-500" />
                        <span>Clôturer la caisse</span>
                    </button>
                ) : (
                    <button
                        type="button"
                        onClick={() => setDialogMode('OPEN')}
                        className="inline-flex h-10 items-center gap-2 rounded-xl bg-gold px-4 text-xs font-black uppercase tracking-wider text-kora shadow-md transition hover:bg-gold/90 shrink-0"
                    >
                        <Unlock className="size-4" />
                        <span>Ouvrir une session de caisse</span>
                    </button>
                )}
            </div>

            {/* Dual Panel Layout: Local Cash Till vs KORA Remittance Debt */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {/* LEFT: Local Physical Cash Session */}
                <section
                    aria-label="Session de caisse guichet"
                    className="flex flex-col justify-between rounded-3xl border border-slate-200/90 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <div>
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3 dark:border-slate-800">
                            <div className="flex items-center gap-2.5">
                                <div className="grid size-9 place-items-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400">
                                    <Wallet className="size-5" />
                                </div>
                                <div>
                                    <h2 className="text-sm font-extrabold text-slate-900 dark:text-white">
                                        Session de caisse guichet (Tiroir physique)
                                    </h2>
                                    <span className="text-[0.6875rem] text-slate-400">
                                        {cashSession.opened_at} · Opérateur : {cashSession.operator_name}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Breakdown Cards */}
                        <div className="mt-5 grid grid-cols-2 gap-3">
                            <div className="rounded-2xl border border-slate-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950">
                                <div className="flex items-center gap-1.5 text-xs text-slate-500">
                                    <Banknote className="size-4 text-slate-400" />
                                    <span>Fond de caisse initial</span>
                                </div>
                                <strong className="mt-2 block font-mono text-lg font-black text-slate-900 dark:text-white">
                                    {cashSession.opening_amount.toLocaleString('fr-FR')} FCFA
                                </strong>
                            </div>

                            <div className="rounded-2xl border border-slate-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950">
                                <div className="flex items-center gap-1.5 text-xs text-emerald-600 dark:text-emerald-400 font-bold">
                                    <TrendingUp className="size-4" />
                                    <span>Espèces perçues (Ventes)</span>
                                </div>
                                <strong className="mt-2 block font-mono text-lg font-black text-emerald-600 dark:text-emerald-400">
                                    +{cashSession.cash_collected.toLocaleString('fr-FR')} FCFA
                                </strong>
                            </div>
                        </div>

                        {/* Total in Register */}
                        <div className="mt-4 rounded-2xl bg-gradient-to-br from-slate-900 to-slate-800 p-5 text-white shadow-md">
                            <span className="block text-[0.625rem] font-bold text-slate-400 uppercase tracking-wider">
                                Espèces théoriques dans le tiroir
                            </span>
                            <div className="mt-1 flex items-baseline justify-between">
                                <strong className="font-mono text-2xl font-black text-gold sm:text-3xl">
                                    {cashSession.expected_closing_cash.toLocaleString('fr-FR')}{' '}
                                    <span className="text-sm font-normal text-white">FCFA</span>
                                </strong>
                                <span className="text-xs text-slate-400">
                                    Mobile Money : {cashSession.mobile_money_collected.toLocaleString('fr-FR')} FCFA
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="mt-5 border-t border-slate-100 pt-3 text-xs text-slate-500 dark:border-slate-800">
                        Cette caisse enregistre les flux de paiement physiques des billets du jour pour le rendu de monnaie.
                    </div>
                </section>

                {/* RIGHT: KORA Remittance Debt */}
                <section
                    aria-label="Dette de reversement KORA"
                    className="flex flex-col justify-between rounded-3xl border border-amber-200/80 bg-gradient-to-br from-amber-500/5 via-white to-amber-500/10 p-6 shadow-sm dark:border-amber-500/20 dark:from-slate-900 dark:via-slate-900 dark:to-slate-900"
                >
                    <div>
                        <div className="flex items-center justify-between border-b border-amber-200/40 pb-3 dark:border-slate-800">
                            <div className="flex items-center gap-2.5">
                                <div className="grid size-9 place-items-center rounded-xl bg-gold text-kora font-black">
                                    <ReceiptText className="size-5" />
                                </div>
                                <div>
                                    <h2 className="text-sm font-extrabold text-slate-900 dark:text-white">
                                        Solde de reversement dû à KORA
                                    </h2>
                                    <span className="text-[0.6875rem] text-slate-500 dark:text-slate-400">
                                        Dette contractuelle cumulée nette de commissions
                                    </span>
                                </div>
                            </div>

                            <span className="rounded-full bg-gold/25 px-2.5 py-0.5 text-xs font-black text-kora dark:text-gold">
                                {health.usage_percentage} % du plafond
                            </span>
                        </div>

                        {/* Financial Debt Figures */}
                        <div className="mt-5 space-y-3">
                            <div className="flex items-center justify-between">
                                <span className="text-xs text-slate-600 dark:text-slate-300">
                                    Encours total net à reverser :
                                </span>
                                <strong className="font-mono text-xl font-black text-slate-900 dark:text-white">
                                    {health.current_exposure.toLocaleString('fr-FR')} FCFA
                                </strong>
                            </div>

                            <div className="flex items-center justify-between text-xs">
                                <span className="text-slate-500">Plafond contractuel :</span>
                                <span className="font-mono font-bold text-slate-700 dark:text-slate-300">
                                    {health.credit_limit.toLocaleString('fr-FR')} FCFA
                                </span>
                            </div>

                            <div className="flex items-center justify-between text-xs">
                                <span className="text-slate-500">Disponible avant blocage :</span>
                                <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                    {health.available_balance.toLocaleString('fr-FR')} FCFA
                                </span>
                            </div>

                            <div className="flex items-center justify-between rounded-xl bg-white p-3 border border-amber-200/60 dark:border-slate-800 dark:bg-slate-800/80 text-xs">
                                <span className="flex items-center gap-1.5 font-bold text-slate-700 dark:text-slate-200">
                                    <Clock className="size-3.5 text-gold" />
                                    <span>Échéance de reversement :</span>
                                </span>
                                <span className="font-bold text-slate-900 dark:text-white">
                                    {health.next_due_label}
                                </span>
                            </div>
                        </div>

                        {/* Clarification Callout */}
                        <div className="mt-4 rounded-xl border border-blue-200/80 bg-blue-50/70 p-3 text-xs text-blue-900 dark:border-blue-900/40 dark:bg-blue-950/30 dark:text-blue-300 leading-relaxed">
                            <strong className="block font-bold">Règle de séparation des comptes :</strong>
                            Les recettes encaissées restent dans vos comptes ou dans votre coffre local jusqu'à l'exécution d'un bordereau de reversement vers KORA.
                        </div>
                    </div>

                    <div className="mt-5 flex justify-end border-t border-amber-200/40 pt-3 dark:border-slate-800">
                        {isAutonomous ? (
                            <button
                                type="button"
                                onClick={onNavigateToSettlements}
                                className="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-slate-800 dark:bg-white dark:text-slate-900"
                            >
                                <span>Préparer le reversement périodique</span>
                                <ArrowRight className="size-3.5" />
                            </button>
                        ) : (
                            <span className="text-xs text-slate-500 italic">
                                Reversement groupé supervisé par le trésorier de l’organisation.
                            </span>
                        )}
                    </div>
                </section>
            </div>

            {/* Cash Movements Ledger */}
            <section
                aria-label="Mouvements de caisse de la journée"
                className="rounded-3xl border border-slate-200/90 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                <div className="flex items-center justify-between border-b border-slate-100 pb-3 dark:border-slate-800">
                    <div>
                        <span className="text-[0.625rem] font-black uppercase tracking-wider text-slate-400">
                            Journal de caisse
                        </span>
                        <h3 className="text-sm font-extrabold text-slate-900 dark:text-white sm:text-base">
                            Mouvements enregistrés aujourd'hui
                        </h3>
                    </div>
                    <span className="text-xs font-semibold text-slate-500">
                        {cashSession.movements.length} opérations
                    </span>
                </div>

                <div className="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                    {cashSession.movements.map((m) => (
                        <div
                            key={m.id}
                            className="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between text-xs"
                        >
                            <div className="flex items-center gap-3">
                                <span className="font-mono text-xs font-bold text-slate-400">
                                    {m.time_label}
                                </span>
                                <div>
                                    <div className="font-bold text-slate-800 dark:text-slate-200">
                                        {m.label}
                                    </div>
                                    <div className="text-[0.6875rem] text-slate-400">
                                        Moyen : {m.method === 'CASH' ? 'Espèces guichet' : 'Mobile Money'}
                                    </div>
                                </div>
                            </div>

                            <div className="flex items-center justify-between gap-4 sm:justify-end">
                                <div className="text-right">
                                    <span
                                        className={cn(
                                            'font-mono font-black',
                                            m.type === 'OPENING'
                                                ? 'text-slate-700 dark:text-slate-300'
                                                : m.method === 'CASH'
                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                : 'text-blue-600 dark:text-blue-400',
                                        )}
                                    >
                                        +{m.amount.toLocaleString('fr-FR')} FCFA
                                    </span>
                                    <span className="block text-[0.625rem] text-slate-400 font-mono">
                                        Solde caisse : {m.balance_after.toLocaleString('fr-FR')} FCFA
                                    </span>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </section>
        </div>
    );
}
