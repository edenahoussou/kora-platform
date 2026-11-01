import {
    TrendingUp,
    Download,
    FileSpreadsheet,
    FileText,
    HelpCircle,
    Info,
    AlertTriangle,
    RotateCcw,
    Calendar,
    ArrowUpRight,
    CheckCircle2,
} from 'lucide-react';
import { useState, useMemo } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { cn } from '@/lib/utils';
import type {
    KoraPointProfile,
    PointCommissionRecord,
} from '../mock/kora-point-mock-data';
import { KoraPointExportDemoDialog } from './kora-point-export-demo-dialog';

type Props = {
    point: KoraPointProfile;
    commissions: PointCommissionRecord[];
    onViewRefundDetail?: (refundRef: string) => void;
};

export function KoraPointCommissionsView({
    point,
    commissions,
    onViewRefundDetail,
}: Props) {
    const [periodFilter, setPeriodFilter] = useState<'current_month' | 'last_month' | 'all'>(
        'current_month',
    );
    const [exportModalType, setExportModalType] = useState<'EXCEL' | 'PDF' | null>(null);

    const periodLabel =
        periodFilter === 'current_month'
            ? 'Août 2026 (En cours)'
            : periodFilter === 'last_month'
            ? 'Juillet 2026'
            : 'Toutes les périodes';

    // Summary calculations
    const totals = useMemo(() => {
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

    const ratePercent = (point.terms.commission_rate_bps / 100).toLocaleString('fr-FR', {
        minimumFractionDigits: 1,
        maximumFractionDigits: 2,
    });

    return (
        <div className="space-y-6">
            {/* Export Dialog */}
            <KoraPointExportDemoDialog
                isOpen={exportModalType !== null}
                exportType={exportModalType || 'PDF'}
                periodLabel={periodLabel}
                pointName={point.name}
                onClose={() => setExportModalType(null)}
            />

            {/* Header */}
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div className="flex items-center gap-2">
                        <span className="text-[0.625rem] font-black uppercase tracking-wider text-slate-500">
                            Revenus & Rémunération du Point
                        </span>
                        <KoraStatusBadge
                            label={`Taux effectif : ${ratePercent} %`}
                            tone="gold"
                            variant="solid"
                        />
                    </div>
                    <h1 className="text-xl font-black text-slate-900 dark:text-white sm:text-2xl">
                        Suivi des Commissions Partenaire
                    </h1>
                </div>

                {/* Export Buttons */}
                <div className="flex items-center gap-2 shrink-0">
                    <button
                        type="button"
                        onClick={() => setExportModalType('EXCEL')}
                        className="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                    >
                        <FileSpreadsheet className="size-4 text-emerald-600" />
                        <span>Export Excel (Démo)</span>
                    </button>

                    <button
                        type="button"
                        onClick={() => setExportModalType('PDF')}
                        className="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                    >
                        <FileText className="size-4 text-rose-600" />
                        <span>Relevé PDF (Démo)</span>
                    </button>
                </div>
            </div>

            {/* Permanent Callout: Retained at the Source */}
            <div className="rounded-2xl border border-blue-200 bg-gradient-to-r from-blue-50/90 to-indigo-50/70 p-4.5 text-xs text-blue-950 dark:border-blue-900/40 dark:from-slate-900 dark:to-slate-900 dark:text-blue-200">
                <div className="flex items-start gap-3">
                    <div className="grid size-8 place-items-center rounded-xl bg-blue-600 text-white shrink-0 shadow-sm">
                        <Info className="size-4" />
                    </div>
                    <div className="space-y-1">
                        <strong className="block text-sm font-extrabold text-blue-900 dark:text-blue-100">
                            Principe de retenue à la source des commissions
                        </strong>
                        <p className="text-xs leading-relaxed text-blue-800/90 dark:text-blue-300">
                            Votre commission ({ratePercent} %) est <strong>déjà conservée directement dans votre caisse ou compte</strong> au moment où vous encaissez le voyageur. Par conséquent, KORA ne vous verse pas de commission une seconde fois : votre dette de reversement correspond strictement au prix payé diminué de votre commission acquise.
                        </p>
                    </div>
                </div>
            </div>

            {/* 4 Financial Summary Cards */}
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <span className="text-[0.625rem] font-bold uppercase text-slate-400">
                        1. Assiette totale encaissée
                    </span>
                    <strong className="mt-1 block font-mono text-lg font-black text-slate-900 dark:text-white sm:text-xl">
                        {totals.grossSales.toLocaleString('fr-FR')} FCFA
                    </strong>
                    <span className="text-[0.6875rem] text-slate-500">
                        Base de calcul des ventes
                    </span>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <span className="text-[0.625rem] font-bold uppercase text-slate-400">
                        2. Commissions initiales ({ratePercent} %)
                    </span>
                    <strong className="mt-1 block font-mono text-lg font-black text-emerald-600 dark:text-emerald-400 sm:text-xl">
                        +{totals.initialComm.toLocaleString('fr-FR')} FCFA
                    </strong>
                    <span className="text-[0.6875rem] text-slate-500">
                        Taux historique figé
                    </span>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <span className="text-[0.625rem] font-bold uppercase text-slate-400">
                        3. Corrections remboursements
                    </span>
                    <strong
                        className={cn(
                            'mt-1 block font-mono text-lg font-black sm:text-xl',
                            totals.corrections < 0
                                ? 'text-rose-600 dark:text-rose-400'
                                : 'text-slate-400',
                        )}
                    >
                        {totals.corrections.toLocaleString('fr-FR')} FCFA
                    </strong>
                    <span className="text-[0.6875rem] text-slate-500">
                        Reprises sur annulations
                    </span>
                </div>

                <div className="rounded-2xl border border-gold/40 bg-gold/5 p-4 shadow-sm dark:border-gold/30 dark:bg-gold/10">
                    <span className="text-[0.625rem] font-bold uppercase text-kora dark:text-gold">
                        4. Commission nette acquise
                    </span>
                    <strong className="mt-1 block font-mono text-lg font-black text-kora dark:text-gold sm:text-xl">
                        +{totals.netComm.toLocaleString('fr-FR')} FCFA
                    </strong>
                    <span className="text-[0.6875rem] text-slate-600 dark:text-slate-300">
                        Revenu net définitif conservé
                    </span>
                </div>
            </div>

            {/* Filter bar by period */}
            <div className="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800">
                <div className="flex items-center gap-2">
                    <span className="text-xs font-bold text-slate-500">Période :</span>
                    <div className="flex items-center gap-1.5">
                        {[
                            { id: 'current_month', label: 'Ce mois-ci (Août)' },
                            { id: 'last_month', label: 'Mois dernier (Juillet)' },
                            { id: 'all', label: 'Toutes les dates' },
                        ].map((t) => (
                            <button
                                key={t.id}
                                type="button"
                                onClick={() => setPeriodFilter(t.id as any)}
                                className={cn(
                                    'rounded-xl px-3 py-1 text-xs font-bold transition',
                                    periodFilter === t.id
                                        ? 'bg-[var(--kora)] text-white dark:bg-digital dark:text-kora shadow-sm'
                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300',
                                )}
                            >
                                {t.label}
                            </button>
                        ))}
                    </div>
                </div>

                <span className="text-xs text-slate-400">
                    {commissions.length} opérations enregistrées
                </span>
            </div>

            {/* Detailed Commissions Table (Desktop) */}
            <div className="hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:block overflow-hidden dark:border-slate-800 dark:bg-slate-900">
                <table className="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr className="border-b border-slate-200 bg-slate-50 text-[0.625rem] font-black uppercase tracking-wider text-slate-400 dark:border-slate-800 dark:bg-slate-950">
                            <th className="py-3.5 pl-5 pr-3">Billet & Date</th>
                            <th className="py-3.5 px-3">Trajet / Vendeur</th>
                            <th className="py-3.5 px-3 text-right">Prix Payé</th>
                            <th className="py-3.5 px-3 text-center">Taux</th>
                            <th className="py-3.5 px-3 text-right">Comm. Initiale</th>
                            <th className="py-3.5 px-3 text-right">Correction</th>
                            <th className="py-3.5 px-3 text-right">Commission Nette</th>
                            <th className="py-3.5 pl-3 pr-5">Statut</th>
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
                                <td className="py-3.5 pl-5 pr-3">
                                    <div className="font-mono font-bold text-slate-900 dark:text-white">
                                        {row.ticket_number}
                                    </div>
                                    <span className="text-[0.6875rem] text-slate-400">
                                        {row.date_label}
                                    </span>
                                </td>

                                <td className="py-3.5 px-3">
                                    <div className="font-semibold text-slate-800 dark:text-slate-200">
                                        {row.route_label}
                                    </div>
                                    <div className="text-[0.6875rem] text-slate-400">
                                        {row.departure_ref} · {row.seller_name}
                                    </div>
                                </td>

                                <td className="py-3.5 px-3 text-right font-mono font-bold text-slate-900 dark:text-white">
                                    {row.amount_paid.toLocaleString('fr-FR')} FCFA
                                </td>

                                <td className="py-3.5 px-3 text-center font-mono text-slate-600 dark:text-slate-300">
                                    {(row.commission_rate_bps / 100).toFixed(1)} %
                                </td>

                                <td className="py-3.5 px-3 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                    +{row.initial_commission.toLocaleString('fr-FR')} FCFA
                                </td>

                                <td className="py-3.5 px-3 text-right">
                                    {row.has_refund_correction ? (
                                        <div>
                                            <span className="font-mono font-bold text-rose-600 dark:text-rose-400">
                                                {row.correction_amount.toLocaleString('fr-FR')} FCFA
                                            </span>
                                            {row.refund_ref && (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        onViewRefundDetail?.(row.refund_ref!)
                                                    }
                                                    className="mt-0.5 block text-[0.625rem] font-bold text-blue-600 hover:underline dark:text-blue-400 ml-auto"
                                                    title={row.refund_reason}
                                                >
                                                    {row.refund_ref} →
                                                </button>
                                            )}
                                        </div>
                                    ) : (
                                        <span className="text-slate-300 dark:text-slate-600">—</span>
                                    )}
                                </td>

                                <td className="py-3.5 px-3 text-right font-mono font-black text-slate-900 dark:text-white">
                                    {row.net_commission > 0
                                        ? `+${row.net_commission.toLocaleString('fr-FR')}`
                                        : '0'}{' '}
                                    FCFA
                                </td>

                                <td className="py-3.5 pl-3 pr-5">
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
                            'rounded-2xl border p-4 shadow-sm text-xs',
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
                                <KoraStatusBadge label="Annulé / Repris" tone="destructive" variant="solid" />
                            ) : (
                                <KoraStatusBadge label="Acquis" tone="emerald" variant="solid" />
                            )}
                        </div>

                        <div className="my-2.5 space-y-1">
                            <div className="flex justify-between">
                                <span className="text-slate-400">Trajet :</span>
                                <span className="font-bold text-slate-800 dark:text-slate-200">
                                    {row.route_label}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-400">Montant payé :</span>
                                <span className="font-mono font-bold text-slate-900 dark:text-white">
                                    {row.amount_paid.toLocaleString('fr-FR')} FCFA
                                </span>
                            </div>
                            <div className="flex justify-between text-emerald-600 dark:text-emerald-400 font-bold">
                                <span>Commission brute (3 %) :</span>
                                <span>+{row.initial_commission.toLocaleString('fr-FR')} FCFA</span>
                            </div>
                            {row.has_refund_correction && (
                                <div className="flex justify-between text-rose-600 font-bold">
                                    <span>Correction remboursement :</span>
                                    <span>{row.correction_amount.toLocaleString('fr-FR')} FCFA</span>
                                </div>
                            )}
                            <div className="flex justify-between border-t border-slate-100 pt-1.5 font-black text-slate-900 dark:border-slate-800 dark:text-white">
                                <span>Commission nette finale :</span>
                                <span>+{row.net_commission.toLocaleString('fr-FR')} FCFA</span>
                            </div>
                        </div>
                    </article>
                ))}
            </div>
        </div>
    );
}
