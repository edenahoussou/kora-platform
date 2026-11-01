import {
    AlertTriangle,
    CheckCircle2,
    Clock,
    Coins,
    FileText,
    Plus,
    Receipt,
    RotateCcw,
    Search,
} from 'lucide-react';
import { useState, useMemo } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { cn } from '@/lib/utils';
import type {
    KoraPointProfile,
    PointRefundRecord,
    PointRefundStatus,
    PointSaleRecord,
} from '../mock/kora-point-mock-data';
import { KoraPointRefundRequestDialog } from './kora-point-refund-request-dialog';
import { KoraPointRefundRestitutionDialog } from './kora-point-refund-restitution-dialog';

type Props = {
    point: KoraPointProfile;
    refunds: PointRefundRecord[];
    sales: PointSaleRecord[];
    onInitiateRefund: (data: any) => void;
    onConfirmRestitution: (refundUuid: string, signatoryName: string, receiptNote?: string) => void;
};

export function KoraPointRefundListView({
    point,
    refunds,
    sales,
    onInitiateRefund,
    onConfirmRestitution,
}: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState<'ALL' | PointRefundStatus>('ALL');
    const [isRequestModalOpen, setIsRequestModalOpen] = useState(false);
    const [activeRestitutionRefund, setActiveRestitutionRefund] = useState<PointRefundRecord | null>(
        null,
    );

    // Filtered refunds
    const filteredRefunds = useMemo(() => {
        return refunds.filter((r) => {
            const matchesSearch =
                r.reference.toLowerCase().includes(searchQuery.toLowerCase()) ||
                r.ticket_number.toLowerCase().includes(searchQuery.toLowerCase()) ||
                r.passenger_name.toLowerCase().includes(searchQuery.toLowerCase());

            const matchesStatus = statusFilter === 'ALL' || r.status === statusFilter;

            return matchesSearch && matchesStatus;
        });
    }, [refunds, searchQuery, statusFilter]);

    // Summary metrics
    const metrics = useMemo(() => {
        let pendingFinance = 0;
        let readyForRestitution = 0;
        let totalRefunded = 0;
        let totalClawback = 0;

        refunds.forEach((r) => {
            if (r.status === 'REQUESTED') {
pendingFinance += 1;
}

            if (r.status === 'APPROVED_BY_FINANCE') {
readyForRestitution += 1;
}

            if (r.status === 'FUNDS_RESTITUTED') {
                totalRefunded += r.refund_amount;
                totalClawback += Math.abs(r.commission_clawback);
            }
        });

        return { pendingFinance, readyForRestitution, totalRefunded, totalClawback };
    }, [refunds]);

    const getStatusBadge = (status: PointRefundStatus) => {
        switch (status) {
            case 'FUNDS_RESTITUTED':
                return (
                    <KoraStatusBadge
                        variant="solid"
                        colorScheme="emerald"
                        icon={<CheckCircle2 className="h-3 w-3" />}
                    >
                        Restitué au passager
                    </KoraStatusBadge>
                );
            case 'APPROVED_BY_FINANCE':
                return (
                    <KoraStatusBadge
                        variant="solid"
                        colorScheme="amber"
                        icon={<Clock className="h-3 w-3" />}
                    >
                        Prêt pour restitution guichet
                    </KoraStatusBadge>
                );
            case 'REQUESTED':
                return (
                    <KoraStatusBadge
                        variant="solid"
                        colorScheme="blue"
                        icon={<Clock className="h-3 w-3" />}
                    >
                        Instruction Finance KORA
                    </KoraStatusBadge>
                );
            case 'REJECTED':
                return (
                    <KoraStatusBadge
                        variant="solid"
                        colorScheme="destructive"
                        icon={<AlertTriangle className="h-3 w-3" />}
                    >
                        Rejeté
                    </KoraStatusBadge>
                );
        }
    };

    return (
        <div className="space-y-6">
            {/* Modal d'initiation d'un remboursement */}
            <KoraPointRefundRequestDialog
                isOpen={isRequestModalOpen}
                point={point}
                eligibleSales={sales.filter((s) => s.refundable)}
                onClose={() => setIsRequestModalOpen(false)}
                onSubmit={onInitiateRefund}
            />

            {/* Modal de restitution d'espèces */}
            <KoraPointRefundRestitutionDialog
                isOpen={activeRestitutionRefund !== null}
                refund={activeRestitutionRefund}
                pointName={point.name}
                operatorName={point.manager_name}
                onClose={() => setActiveRestitutionRefund(null)}
                onConfirmRestitution={(uuid, signatory, note) => {
                    onConfirmRestitution(uuid, signatory, note);
                }}
            />

            {/* Header */}
            <div className="kora-page-head cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                        KORA POINT / Espace Agent / Remboursements
                    </div>
                    <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                        Remboursements & Restitutions passagers
                    </h1>
                    <p className="mt-1.5 text-[0.8125rem] leading-6 text-muted-foreground">
                        Gestion des annulations, décomposition des reprises de commissions et quittances d'émargement.
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2.5">
                    <button
                        type="button"
                        onClick={() => setIsRequestModalOpen(true)}
                        className="btn btn-primary"
                    >
                        <Plus className="size-4" />
                        <span>Initier un remboursement</span>
                    </button>
                </div>
            </div>

            {/* Top KPI Cards */}
            <div className="cash-ledger-grid cash-reveal cash-reveal-delay-1 grid grid-cols-1 gap-4 sm:grid-cols-4">
                <article className="cash-ledger-card cash-ledger-card-cash">
                    <div className="cash-ledger-head">
                        <div className="cash-ledger-identity">
                            <span className="cash-ledger-brand bg-gold/15 text-gold">
                                <Coins className="size-4" strokeWidth={1.7} />
                            </span>
                            <div>
                                <span className="cash-ledger-title">À restituer</span>
                                <small>Accord Finance KORA obtenu</small>
                            </div>
                        </div>
                    </div>
                    <div className="cash-ledger-balance">
                        <span>Dossiers</span>
                        <strong className="text-gold">{metrics.readyForRestitution} dossier(s)</strong>
                    </div>
                </article>

                <article className="cash-ledger-card cash-ledger-card-cash">
                    <div className="cash-ledger-head">
                        <div className="cash-ledger-identity">
                            <span className="cash-ledger-brand bg-digital/10 text-digital">
                                <Clock className="size-4" strokeWidth={1.7} />
                            </span>
                            <div>
                                <span className="cash-ledger-title">En instruction</span>
                                <small>Vérification des justificatifs</small>
                            </div>
                        </div>
                    </div>
                    <div className="cash-ledger-balance">
                        <span>Dossiers</span>
                        <strong className="text-digital">{metrics.pendingFinance} dossier(s)</strong>
                    </div>
                </article>

                <article className="cash-ledger-card cash-ledger-card-cash">
                    <div className="cash-ledger-head">
                        <div className="cash-ledger-identity">
                            <span className="cash-ledger-brand bg-emerald-500/10 text-emerald-600">
                                <CheckCircle2 className="size-4" strokeWidth={1.7} />
                            </span>
                            <div>
                                <span className="cash-ledger-title">Fonds restitués</span>
                                <small>Remis en mains propres</small>
                            </div>
                        </div>
                    </div>
                    <div className="cash-ledger-balance">
                        <span>Total</span>
                        <strong className="text-emerald-600">{metrics.totalRefunded.toLocaleString('fr-FR')} FCFA</strong>
                    </div>
                </article>

                <article className="cash-ledger-card cash-ledger-card-cash">
                    <div className="cash-ledger-head">
                        <div className="cash-ledger-identity">
                            <span className="cash-ledger-brand bg-destructive/10 text-destructive">
                                <RotateCcw className="size-4" strokeWidth={1.7} />
                            </span>
                            <div>
                                <span className="cash-ledger-title">Commissions reprises</span>
                                <small>Régularisées à la source</small>
                            </div>
                        </div>
                    </div>
                    <div className="cash-ledger-balance">
                        <span>Total</span>
                        <strong className="text-destructive">- {metrics.totalClawback.toLocaleString('fr-FR')} FCFA</strong>
                    </div>
                </article>
            </div>

            {/* Filter Bar */}
            <div className="cash-filterbar cash-reveal cash-reveal-delay-2 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="relative flex-1">
                    <Search className="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                    <input
                        type="text"
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        placeholder="Rechercher par référence, n° de billet, nom du voyageur..."
                        className="w-full rounded-xl border border-border bg-muted/40 py-2 pl-9 pr-4 text-xs font-medium text-foreground placeholder:text-muted-foreground focus:bg-card focus:border-gold focus:outline-none"
                    />
                </div>

                <div className="flex flex-wrap gap-1.5">
                    <button
                        type="button"
                        onClick={() => setStatusFilter('ALL')}
                        className={cn(
                            'btn btn-sm',
                            statusFilter === 'ALL' ? 'btn-navy' : 'btn-ghost',
                        )}
                    >
                        Tous ({refunds.length})
                    </button>
                    <button
                        type="button"
                        onClick={() => setStatusFilter('APPROVED_BY_FINANCE')}
                        className={cn(
                            'btn btn-sm',
                            statusFilter === 'APPROVED_BY_FINANCE' ? 'btn-navy' : 'btn-ghost',
                        )}
                    >
                        Prêts pour restitution
                    </button>
                    <button
                        type="button"
                        onClick={() => setStatusFilter('REQUESTED')}
                        className={cn(
                            'btn btn-sm',
                            statusFilter === 'REQUESTED' ? 'btn-navy' : 'btn-ghost',
                        )}
                    >
                        En instruction
                    </button>
                    <button
                        type="button"
                        onClick={() => setStatusFilter('FUNDS_RESTITUTED')}
                        className={cn(
                            'btn btn-sm',
                            statusFilter === 'FUNDS_RESTITUTED' ? 'btn-navy' : 'btn-ghost',
                        )}
                    >
                        Restitués
                    </button>
                </div>
            </div>

            {/* List */}
            {filteredRefunds.length === 0 ? (
                <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center dark:border-slate-700 dark:bg-slate-900">
                    <RotateCcw className="mx-auto h-8 w-8 text-slate-400" />
                    <h3 className="mt-2 text-sm font-bold text-slate-900 dark:text-white">
                        Aucun dossier de remboursement
                    </h3>
                    <p className="mt-1 text-xs text-slate-500">
                        Aucune demande ne correspond aux critères de filtre.
                    </p>
                </div>
            ) : (
                <>
                    {/* Desktop Table View */}
                    <div className="hidden lg:block overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <table className="w-full text-left text-xs">
                            <thead className="border-b border-slate-100 bg-slate-50/75 text-slate-500 dark:border-slate-800 dark:bg-slate-800/40">
                                <tr>
                                    <th className="px-5 py-3.5 font-bold uppercase tracking-wider">
                                        Dossier & Date
                                    </th>
                                    <th className="px-5 py-3.5 font-bold uppercase tracking-wider">
                                        Billet & Passager
                                    </th>
                                    <th className="px-5 py-3.5 font-bold uppercase tracking-wider">
                                        Motif
                                    </th>
                                    <th className="px-5 py-3.5 font-bold uppercase tracking-wider text-right">
                                        Net remboursé
                                    </th>
                                    <th className="px-5 py-3.5 font-bold uppercase tracking-wider text-right">
                                        Reprise com (-3%)
                                    </th>
                                    <th className="px-5 py-3.5 font-bold uppercase tracking-wider">
                                        Statut
                                    </th>
                                    <th className="px-5 py-3.5 font-bold uppercase tracking-wider text-right">
                                        Action guichet
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {filteredRefunds.map((r) => (
                                    <tr
                                        key={r.uuid}
                                        className="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/30"
                                    >
                                        <td className="px-5 py-4">
                                            <span className="font-mono font-bold text-slate-900 dark:text-white block">
                                                {r.reference}
                                            </span>
                                            <span className="text-[11px] text-slate-500">
                                                {r.requested_at}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4">
                                            <span className="font-bold text-slate-800 dark:text-slate-200 block">
                                                {r.passenger_name}
                                            </span>
                                            <span className="font-mono text-[11px] text-slate-500">
                                                {r.ticket_number} ({r.route_label})
                                            </span>
                                        </td>
                                        <td className="px-5 py-4">
                                            <span className="text-slate-700 dark:text-slate-300 line-clamp-1">
                                                {r.reason_label}
                                            </span>
                                            {r.proof_document && (
                                                <span className="text-[11px] text-blue-600 dark:text-blue-400 flex items-center gap-1 mt-0.5">
                                                    <FileText className="h-3 w-3" /> Justificatif joint
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-5 py-4 text-right font-black text-slate-900 dark:text-white">
                                            {r.refund_amount.toLocaleString()} FCFA
                                        </td>
                                        <td className="px-5 py-4 text-right font-black text-rose-600 dark:text-rose-400">
                                            {r.commission_clawback.toLocaleString()} FCFA
                                        </td>
                                        <td className="px-5 py-4">{getStatusBadge(r.status)}</td>
                                        <td className="px-5 py-4 text-right">
                                            {r.status === 'APPROVED_BY_FINANCE' && (
                                                <button
                                                    type="button"
                                                    onClick={() => setActiveRestitutionRefund(r)}
                                                    className="inline-flex items-center gap-1 rounded-xl bg-gold px-3 py-1.5 text-xs font-black uppercase text-kora shadow-sm hover:bg-gold-light"
                                                >
                                                    <Coins className="h-3.5 w-3.5" />
                                                    Restituer espèces →
                                                </button>
                                            )}
                                            {r.status === 'FUNDS_RESTITUTED' && (
                                                <button
                                                    type="button"
                                                    onClick={() => setActiveRestitutionRefund(r)}
                                                    className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                                >
                                                    <Receipt className="h-3.5 w-3.5 text-emerald-600" />
                                                    Quittance
                                                </button>
                                            )}
                                            {r.status === 'REQUESTED' && (
                                                <span className="text-[11px] text-slate-400 italic">
                                                    Instruction en cours
                                                </span>
                                            )}
                                            {r.status === 'REJECTED' && (
                                                <span className="text-[11px] text-rose-500 font-bold">
                                                    Non éligible
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Mobile Card Stack */}
                    <div className="space-y-3 lg:hidden">
                        {filteredRefunds.map((r) => (
                            <div
                                key={r.uuid}
                                className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 space-y-3"
                            >
                                <div className="flex items-center justify-between">
                                    <div>
                                        <span className="font-mono text-xs font-black text-slate-900 dark:text-white block">
                                            {r.reference}
                                        </span>
                                        <span className="text-[11px] text-slate-400">
                                            {r.ticket_number}
                                        </span>
                                    </div>
                                    {getStatusBadge(r.status)}
                                </div>

                                <div className="text-xs space-y-1">
                                    <p className="font-bold text-slate-900 dark:text-white">
                                        {r.passenger_name} ({r.route_label})
                                    </p>
                                    <p className="text-slate-500 text-[11px]">{r.reason_label}</p>
                                </div>

                                <div className="grid grid-cols-2 gap-2 text-xs pt-1 border-t border-slate-100 dark:border-slate-800">
                                    <div>
                                        <span className="text-[11px] text-slate-400 block">
                                            Net restitué passager
                                        </span>
                                        <span className="font-black text-slate-900 dark:text-white">
                                            {r.refund_amount.toLocaleString()} FCFA
                                        </span>
                                    </div>
                                    <div>
                                        <span className="text-[11px] text-slate-400 block">
                                            Reprise com (-3%)
                                        </span>
                                        <span className="font-black text-rose-600 dark:text-rose-400">
                                            {r.commission_clawback.toLocaleString()} FCFA
                                        </span>
                                    </div>
                                </div>

                                {r.status === 'REJECTED' && r.rejection_reason && (
                                    <div className="rounded-lg bg-rose-50 p-2.5 text-[11px] text-rose-800 dark:bg-rose-950/40 dark:text-rose-300">
                                        <strong>Motif de rejet :</strong> {r.rejection_reason}
                                    </div>
                                )}

                                <div className="flex justify-end pt-1">
                                    {r.status === 'APPROVED_BY_FINANCE' && (
                                        <button
                                            type="button"
                                            onClick={() => setActiveRestitutionRefund(r)}
                                            className="w-full inline-flex items-center justify-center gap-1.5 rounded-xl bg-gold px-3 py-2 text-xs font-black uppercase text-kora"
                                        >
                                            <Coins className="h-4 w-4" />
                                            Restituer espèces →
                                        </button>
                                    )}
                                    {r.status === 'FUNDS_RESTITUTED' && (
                                        <button
                                            type="button"
                                            onClick={() => setActiveRestitutionRefund(r)}
                                            className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-bold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                        >
                                            <Receipt className="h-3.5 w-3.5 text-emerald-600" />
                                            Revoir la quittance
                                        </button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}
