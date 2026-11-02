import {
    Coins,
    FileSpreadsheet,
    FileText,
    Info,
    RotateCcw,
    TrendingUp,
    Wallet,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { cn } from '@/lib/utils';
import type {
    KoraPointProfile,
    PointCommissionRecord,
} from '@/types/kora-points';

type Period = 'current_month' | 'last_month' | 'all';
type CommissionRow = PointCommissionRecord & {
    point_name?: string;
    point_code?: string;
};

type Props = {
    point: KoraPointProfile;
    commissions: CommissionRow[];
    onViewRefundDetail?: (refundRef: string) => void;
    periodFilter?: Period;
    periodLabel?: string;
    totals?: {
        gross: number;
        initial: number;
        corrections: number;
        net: number;
    };
    operationCount?: number;
    onPeriodChange?: (period: Period) => void;
    exportUrls?: { excel: string; pdf: string };
    scopeLabel?: string;
    showPoint?: boolean;
    toolbarSlot?: ReactNode;
};

export function KoraPointCommissionsView({
    point,
    commissions,
    onViewRefundDetail,
    periodFilter,
    periodLabel: suppliedPeriodLabel,
    totals: suppliedTotals,
    operationCount,
    onPeriodChange,
    exportUrls,
    scopeLabel = 'Espace Agent',
    showPoint = false,
    toolbarSlot,
}: Props) {
    const [localPeriod, setLocalPeriod] = useState<Period>('current_month');
    const activePeriod = periodFilter ?? localPeriod;

    const periodLabel =
        suppliedPeriodLabel ??
        (activePeriod === 'all'
            ? 'Toutes les périodes'
            : activePeriod === 'last_month'
              ? 'Mois dernier'
              : 'Mois en cours');

    // Summary calculations
    const calculatedTotals = useMemo(() => {
        let grossSales = 0;
        let initialComm = 0;
        let corrections = 0;
        let netComm = 0;

        commissions.forEach((c) => {
            grossSales += c.amount_paid;
            initialComm += c.initial_commission;
            corrections += c.correction_amount;
            netComm += c.net_commission;
        });

        return { grossSales, initialComm, corrections, netComm };
    }, [commissions]);
    const totals = suppliedTotals
        ? {
              grossSales: suppliedTotals.gross,
              initialComm: suppliedTotals.initial,
              corrections: suppliedTotals.corrections,
              netComm: suppliedTotals.net,
          }
        : calculatedTotals;

    const ratePercent = (point.terms.commission_rate_bps / 100).toLocaleString(
        'fr-FR',
        {
            minimumFractionDigits: 1,
            maximumFractionDigits: 2,
        },
    );

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="kora-page-head cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                        KORA POINT / {scopeLabel} / Commissions
                    </div>
                    <div className="flex items-center gap-2.5">
                        <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                            Commissions de {point.name}
                        </h1>
                        <KoraStatusBadge
                            label={`Taux effectif : ${ratePercent} %`}
                            tone="gold"
                            variant="solid"
                        />
                    </div>
                    <p className="mt-1.5 text-[0.8125rem] leading-6 text-muted-foreground">
                        Rémunération contractuelle conservée directement à la
                        source sur chaque encaissement.
                    </p>
                </div>

                {/* Export Buttons */}
                {toolbarSlot || exportUrls ? (
                    <div className="flex flex-wrap items-center gap-2.5">
                        {toolbarSlot}
                        {exportUrls ? (
                            <>
                                <a
                                    href={exportUrls.excel}
                                    className="btn btn-ghost"
                                    download
                                >
                                    <FileSpreadsheet className="size-4 text-emerald-600" />
                                    <span>Export Excel</span>
                                </a>
                                <a
                                    href={exportUrls.pdf}
                                    className="btn btn-ghost"
                                    download
                                >
                                    <FileText className="size-4 text-rose-600" />
                                    <span>Relevé PDF</span>
                                </a>
                            </>
                        ) : null}
                    </div>
                ) : null}
            </div>

            {/* Permanent Callout: Retained at the Source */}
            <div className="rounded-2xl border border-blue-500/20 bg-blue-500/10 p-4 text-xs text-foreground">
                <div className="flex items-start gap-3">
                    <div className="grid size-8 shrink-0 place-items-center rounded-xl bg-digital text-white shadow-sm">
                        <Info className="size-4" />
                    </div>
                    <div className="space-y-1">
                        <strong className="block text-sm font-extrabold text-foreground">
                            Principe de retenue à la source des commissions
                        </strong>
                        <p className="text-xs leading-relaxed text-muted-foreground">
                            Votre commission ({ratePercent} %) est{' '}
                            <strong>
                                déjà conservée directement dans votre caisse ou
                                compte
                            </strong>{' '}
                            au moment où vous encaissez le voyageur. Par
                            conséquent, KORA ne vous verse pas de commission une
                            seconde fois : votre dette de reversement correspond
                            strictement au prix payé diminué de votre commission
                            acquise.
                        </p>
                    </div>
                </div>
            </div>

            {/* 4 Financial Summary Cards */}
            <div className="cash-ledger-grid cash-reveal cash-reveal-delay-1 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <article className="cash-ledger-card cash-ledger-card-cash">
                    <div className="cash-ledger-head">
                        <div className="cash-ledger-identity">
                            <span className="cash-ledger-brand bg-digital/10 text-digital">
                                <Wallet className="size-4" strokeWidth={1.7} />
                            </span>
                            <div>
                                <span className="cash-ledger-title">
                                    Assiette encaissée
                                </span>
                                <small>Base de calcul brute</small>
                            </div>
                        </div>
                    </div>
                    <div className="cash-ledger-balance">
                        <span>Total</span>
                        <strong>
                            {totals.grossSales.toLocaleString('fr-FR')} FCFA
                        </strong>
                    </div>
                </article>

                <article className="cash-ledger-card cash-ledger-card-cash">
                    <div className="cash-ledger-head">
                        <div className="cash-ledger-identity">
                            <span className="cash-ledger-brand bg-emerald-500/10 text-emerald-600">
                                <TrendingUp
                                    className="size-4"
                                    strokeWidth={1.7}
                                />
                            </span>
                            <div>
                                <span className="cash-ledger-title">
                                    Commissions ({ratePercent} %)
                                </span>
                                <small>Taux contractuel</small>
                            </div>
                        </div>
                    </div>
                    <div className="cash-ledger-balance">
                        <span>Total</span>
                        <strong>
                            +{totals.initialComm.toLocaleString('fr-FR')} FCFA
                        </strong>
                    </div>
                </article>

                <article className="cash-ledger-card cash-ledger-card-cash">
                    <div className="cash-ledger-head">
                        <div className="cash-ledger-identity">
                            <span className="cash-ledger-brand bg-destructive/10 text-destructive">
                                <RotateCcw
                                    className="size-4"
                                    strokeWidth={1.7}
                                />
                            </span>
                            <div>
                                <span className="cash-ledger-title">
                                    Reprises annulations
                                </span>
                                <small>Remboursements</small>
                            </div>
                        </div>
                    </div>
                    <div className="cash-ledger-balance">
                        <span>Total</span>
                        <strong
                            className={
                                totals.corrections !== 0 ? 'text-rose-600' : ''
                            }
                        >
                            {totals.corrections.toLocaleString('fr-FR')} FCFA
                        </strong>
                    </div>
                </article>

                <article className="cash-ledger-card cash-ledger-card-cash">
                    <div className="cash-ledger-head">
                        <div className="cash-ledger-identity">
                            <span className="cash-ledger-brand bg-gold/15 text-gold">
                                <Coins className="size-4" strokeWidth={1.7} />
                            </span>
                            <div>
                                <span className="cash-ledger-title">
                                    Commission nette
                                </span>
                                <small>Revenu net conservé</small>
                            </div>
                        </div>
                    </div>
                    <div className="cash-ledger-balance">
                        <span>Total</span>
                        <strong className="text-emerald-600">
                            +{totals.netComm.toLocaleString('fr-FR')} FCFA
                        </strong>
                    </div>
                </article>
            </div>

            {/* Filter bar by period */}
            <div className="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800">
                <div className="flex items-center gap-2">
                    <span className="text-xs font-bold text-slate-500">
                        Période :
                    </span>
                    <div className="flex items-center gap-1.5">
                        {[
                            { id: 'current_month', label: 'Ce mois-ci' },
                            { id: 'last_month', label: 'Mois dernier' },
                            { id: 'all', label: 'Toutes les dates' },
                        ].map((t) => (
                            <button
                                key={t.id}
                                type="button"
                                onClick={() => {
                                    const nextPeriod = t.id as Period;
                                    setLocalPeriod(nextPeriod);
                                    onPeriodChange?.(nextPeriod);
                                }}
                                className={cn(
                                    'rounded-xl px-3 py-1 text-xs font-bold transition',
                                    activePeriod === t.id
                                        ? 'bg-[var(--kora)] text-white shadow-sm dark:bg-digital dark:text-kora'
                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300',
                                )}
                            >
                                {t.label}
                            </button>
                        ))}
                    </div>
                </div>

                <span className="text-xs text-slate-400">
                    {operationCount ?? commissions.length} opérations
                    enregistrées · {periodLabel}
                </span>
            </div>

            {/* Detailed Commissions Table (Desktop) */}
            <div className="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:block dark:border-slate-800 dark:bg-slate-900">
                <table className="w-full border-collapse text-left text-xs">
                    <thead>
                        <tr className="border-b border-slate-200 bg-slate-50 text-[0.625rem] font-black tracking-wider text-slate-400 uppercase dark:border-slate-800 dark:bg-slate-950">
                            <th className="py-3.5 pr-3 pl-5">Billet & Date</th>
                            <th className="px-3 py-3.5">Trajet / Vendeur</th>
                            {showPoint ? (
                                <th className="px-3 py-3.5">Point</th>
                            ) : null}
                            <th className="px-3 py-3.5 text-right">
                                Prix Payé
                            </th>
                            <th className="px-3 py-3.5 text-center">Taux</th>
                            <th className="px-3 py-3.5 text-right">
                                Comm. Initiale
                            </th>
                            <th className="px-3 py-3.5 text-right">
                                Correction
                            </th>
                            <th className="px-3 py-3.5 text-right">
                                Commission Nette
                            </th>
                            <th className="py-3.5 pr-5 pl-3">Statut</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                        {commissions.map((row) => (
                            <tr
                                key={row.uuid}
                                className={cn(
                                    'transition',
                                    row.has_refund_correction
                                        ? 'bg-rose-50/40 dark:bg-rose-950/20'
                                        : 'hover:bg-slate-50 dark:hover:bg-slate-800/50',
                                )}
                            >
                                <td className="py-3.5 pr-3 pl-5">
                                    <div className="font-mono font-bold text-slate-900 dark:text-white">
                                        {row.ticket_number}
                                    </div>
                                    <span className="text-[0.6875rem] text-slate-400">
                                        {row.date_label}
                                    </span>
                                </td>

                                <td className="px-3 py-3.5">
                                    <div className="font-semibold text-slate-800 dark:text-slate-200">
                                        {row.route_label}
                                    </div>
                                    <div className="text-[0.6875rem] text-slate-400">
                                        {row.departure_ref} · {row.seller_name}
                                    </div>
                                </td>
                                {showPoint ? (
                                    <td className="px-3 py-3.5">
                                        <span className="font-semibold">
                                            {row.point_name ?? '—'}
                                        </span>
                                        <span className="ml-1 text-[0.6875rem] text-slate-400">
                                            {row.point_code
                                                ? `(${row.point_code})`
                                                : ''}
                                        </span>
                                    </td>
                                ) : null}

                                <td className="px-3 py-3.5 text-right font-mono font-bold text-slate-900 dark:text-white">
                                    {row.amount_paid.toLocaleString('fr-FR')}{' '}
                                    FCFA
                                </td>

                                <td className="px-3 py-3.5 text-center font-mono text-slate-600 dark:text-slate-300">
                                    {(row.commission_rate_bps / 100).toFixed(1)}{' '}
                                    %
                                </td>

                                <td className="px-3 py-3.5 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                    +
                                    {row.initial_commission.toLocaleString(
                                        'fr-FR',
                                    )}{' '}
                                    FCFA
                                </td>

                                <td className="px-3 py-3.5 text-right">
                                    {row.has_refund_correction ? (
                                        <div>
                                            <span className="font-mono font-bold text-rose-600 dark:text-rose-400">
                                                {row.correction_amount.toLocaleString(
                                                    'fr-FR',
                                                )}{' '}
                                                FCFA
                                            </span>
                                            {row.refund_ref && (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        onViewRefundDetail?.(
                                                            row.refund_ref!,
                                                        )
                                                    }
                                                    className="mt-0.5 ml-auto block text-[0.625rem] font-bold text-blue-600 hover:underline dark:text-blue-400"
                                                    title={row.refund_reason}
                                                >
                                                    {row.refund_ref} →
                                                </button>
                                            )}
                                        </div>
                                    ) : (
                                        <span className="text-slate-300 dark:text-slate-600">
                                            —
                                        </span>
                                    )}
                                </td>

                                <td className="px-3 py-3.5 text-right font-mono font-black text-slate-900 dark:text-white">
                                    {row.net_commission > 0
                                        ? `+${row.net_commission.toLocaleString('fr-FR')}`
                                        : '0'}{' '}
                                    FCFA
                                </td>

                                <td className="py-3.5 pr-5 pl-3">
                                    {row.has_refund_correction ? (
                                        <KoraStatusBadge
                                            label="Annulé / Repris"
                                            tone="destructive"
                                            variant="solid"
                                        />
                                    ) : row.settlement_status === 'settled' ? (
                                        <KoraStatusBadge
                                            label="Réglé"
                                            tone="emerald"
                                            variant="solid"
                                        />
                                    ) : (
                                        <KoraStatusBadge
                                            label="Acquis (À reverser)"
                                            tone="amber"
                                            variant="solid"
                                        />
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* Mobile Cards View */}
            <div className="space-y-3 md:hidden">
                {commissions.map((row) => (
                    <article
                        key={row.uuid}
                        className={cn(
                            'rounded-2xl border p-4 text-xs shadow-sm',
                            row.has_refund_correction
                                ? 'border-rose-200 bg-rose-50/50 dark:border-rose-900/40 dark:bg-rose-950/20'
                                : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900',
                        )}
                    >
                        <div className="flex items-center justify-between border-b border-slate-100 pb-2 dark:border-slate-800">
                            <div>
                                <strong className="font-mono text-slate-900 dark:text-white">
                                    {row.ticket_number}
                                </strong>
                                <span className="block text-[0.625rem] text-slate-400">
                                    {row.date_label}
                                </span>
                            </div>
                            {row.has_refund_correction ? (
                                <KoraStatusBadge
                                    label="Annulé / Repris"
                                    tone="destructive"
                                    variant="solid"
                                />
                            ) : (
                                <KoraStatusBadge
                                    label="Acquis"
                                    tone="emerald"
                                    variant="solid"
                                />
                            )}
                        </div>

                        <div className="my-2.5 space-y-1">
                            {showPoint && row.point_name ? (
                                <div className="flex justify-between">
                                    <span className="text-slate-400">
                                        Point :
                                    </span>
                                    <span className="font-bold text-slate-800 dark:text-slate-200">
                                        {row.point_name} ({row.point_code})
                                    </span>
                                </div>
                            ) : null}
                            <div className="flex justify-between">
                                <span className="text-slate-400">Trajet :</span>
                                <span className="font-bold text-slate-800 dark:text-slate-200">
                                    {row.route_label}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-400">
                                    Montant payé :
                                </span>
                                <span className="font-mono font-bold text-slate-900 dark:text-white">
                                    {row.amount_paid.toLocaleString('fr-FR')}{' '}
                                    FCFA
                                </span>
                            </div>
                            <div className="flex justify-between font-bold text-emerald-600 dark:text-emerald-400">
                                <span>
                                    Commission brute (
                                    {(
                                        row.commission_rate_bps / 100
                                    ).toLocaleString('fr-FR')}{' '}
                                    %) :
                                </span>
                                <span>
                                    +
                                    {row.initial_commission.toLocaleString(
                                        'fr-FR',
                                    )}{' '}
                                    FCFA
                                </span>
                            </div>
                            {row.has_refund_correction && (
                                <div className="flex justify-between font-bold text-rose-600">
                                    <span>Correction remboursement :</span>
                                    <span>
                                        {row.correction_amount.toLocaleString(
                                            'fr-FR',
                                        )}{' '}
                                        FCFA
                                    </span>
                                </div>
                            )}
                            <div className="flex justify-between border-t border-slate-100 pt-1.5 font-black text-slate-900 dark:border-slate-800 dark:text-white">
                                <span>Commission nette finale :</span>
                                <span>
                                    +
                                    {row.net_commission.toLocaleString('fr-FR')}{' '}
                                    FCFA
                                </span>
                            </div>
                        </div>
                    </article>
                ))}
            </div>
        </div>
    );
}
