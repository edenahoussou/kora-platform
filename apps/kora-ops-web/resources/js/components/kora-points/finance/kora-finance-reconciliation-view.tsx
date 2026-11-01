import { useState, useMemo } from 'react';
import {
    Receipt,
    RotateCcw,
    CheckCircle2,
    Clock,
    AlertCircle,
    Search,
    Filter,
    ArrowUpRight,
    Building2,
    FileText,
    Calendar,
    Banknote,
    TrendingUp,
    Check,
    Eye,
    ShieldCheck,
    Layers,
    UserCheck,
    DollarSign,
} from 'lucide-react';
import {
    PointSettlementRecord,
    PointRefundRecord,
    GroupSettlementRecord,
} from '../mock/kora-point-mock-data';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { KoraFinanceSettlementActionDialog } from './kora-finance-settlement-action-dialog';
import { KoraFinanceRefundActionDialog } from './kora-finance-refund-action-dialog';

type Props = {
    settlements: PointSettlementRecord[];
    refunds: PointRefundRecord[];
    groupSettlements?: GroupSettlementRecord[];
    onReconcileSettlement: (
        settlementUuid: string,
        params: {
            bankAmount: number;
            valueDate: string;
            bankReference?: string;
            notes?: string;
        },
    ) => void;
    onRejectSettlement: (settlementUuid: string, params: { reason: string }) => void;
    onApproveRefund: (
        refundUuid: string,
        params: {
            payoutMode: 'POINT_CASH' | 'KORA_DIRECT';
            notes?: string;
        },
    ) => void;
    onRejectRefund: (refundUuid: string, params: { reason: string }) => void;
};

export function KoraFinanceReconciliationView({
    settlements,
    refunds,
    groupSettlements = [],
    onReconcileSettlement,
    onRejectSettlement,
    onApproveRefund,
    onRejectRefund,
}: Props) {
    const [activeTab, setActiveTab] = useState<'SETTLEMENTS' | 'REFUNDS' | 'HISTORY'>('SETTLEMENTS');
    const [searchQuery, setSearchQuery] = useState('');
    const [methodFilter, setMethodFilter] = useState<string>('ALL');

    // Modals state
    const [selectedSettlement, setSelectedSettlement] = useState<PointSettlementRecord | null>(null);
    const [selectedRefund, setSelectedRefund] = useState<PointRefundRecord | null>(null);

    // Compute metrics
    const pendingSettlements = useMemo(
        () => settlements.filter((s) => s.status === 'DECLARED'),
        [settlements],
    );

    const pendingSettlementsTotal = useMemo(
        () => pendingSettlements.reduce((sum, s) => sum + s.amount_declared, 0),
        [pendingSettlements],
    );

    const pendingRefunds = useMemo(
        () => refunds.filter((r) => r.status === 'REQUESTED'),
        [refunds],
    );

    const pendingRefundsTotal = useMemo(
        () => pendingRefunds.reduce((sum, r) => sum + r.refund_amount, 0),
        [pendingRefunds],
    );

    const validatedSettlements = useMemo(
        () => settlements.filter((s) => s.status === 'VALIDATED'),
        [settlements],
    );

    const validatedSettlementsTotal = useMemo(
        () => validatedSettlements.reduce((sum, s) => sum + (s.amount_validated ?? s.amount_declared), 0),
        [validatedSettlements],
    );

    // Filter settlements
    const filteredSettlements = useMemo(() => {
        let list = settlements;
        if (activeTab === 'SETTLEMENTS') {
            list = list.filter((s) => s.status === 'DECLARED');
        } else if (activeTab === 'HISTORY') {
            list = list.filter((s) => s.status === 'VALIDATED' || s.status === 'REJECTED');
        }

        if (searchQuery.trim()) {
            const q = searchQuery.toLowerCase();
            list = list.filter(
                (s) =>
                    s.reference.toLowerCase().includes(q) ||
                    s.point_name.toLowerCase().includes(q) ||
                    s.created_by_name.toLowerCase().includes(q) ||
                    s.payment_reference.toLowerCase().includes(q),
            );
        }

        if (methodFilter !== 'ALL') {
            list = list.filter((s) => s.payment_method === methodFilter);
        }

        return list;
    }, [settlements, activeTab, searchQuery, methodFilter]);

    // Filter refunds
    const filteredRefunds = useMemo(() => {
        let list = refunds;
        if (searchQuery.trim()) {
            const q = searchQuery.toLowerCase();
            list = list.filter(
                (r) =>
                    r.reference.toLowerCase().includes(q) ||
                    r.passenger_name.toLowerCase().includes(q) ||
                    r.ticket_number.toLowerCase().includes(q) ||
                    r.route_label.toLowerCase().includes(q),
            );
        }
        return list;
    }, [refunds, searchQuery]);

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div className="flex items-center gap-2.5">
                        <h1 className="text-xl font-black text-slate-900 sm:text-2xl dark:text-white">
                            Pôle Finance KORA — Rapprochements & Contentieux
                        </h1>
                        <KoraStatusBadge
                            label="Pôle Finance & Trésorerie"
                            tone="emerald"
                            variant="solid"
                            className="hidden sm:inline-flex"
                        />
                    </div>
                    <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Vérification interbancaire des déclarations de reversement, saisie des dates de valeur et arbitrage des remboursements passagers.
                    </p>
                </div>
            </div>

            {/* KPI Cards Grid */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {/* Pending Settlements */}
                <div className="rounded-2xl border border-amber-200/80 bg-gradient-to-br from-amber-50 to-white p-5 shadow-sm dark:border-amber-900/40 dark:from-slate-900 dark:to-slate-900">
                    <div className="flex items-center justify-between text-amber-700 dark:text-amber-400">
                        <span className="text-xs font-black uppercase tracking-wider">
                            À Rapprocher en banque
                        </span>
                        <Clock className="size-4" />
                    </div>
                    <div className="mt-3 font-mono text-xl font-black text-slate-900 sm:text-2xl dark:text-white">
                        {pendingSettlementsTotal.toLocaleString('fr-FR')}{' '}
                        <span className="text-xs font-bold text-slate-500">FCFA</span>
                    </div>
                    <div className="mt-1 flex items-center gap-1.5 text-xs text-amber-800 dark:text-amber-300 font-semibold">
                        <span className="size-1.5 rounded-full bg-amber-500" />
                        <span>{pendingSettlements.length} bordereaux en attente d'imputation</span>
                    </div>
                </div>

                {/* Pending Refunds */}
                <div className="rounded-2xl border border-blue-200/80 bg-gradient-to-br from-blue-50 to-white p-5 shadow-sm dark:border-blue-900/40 dark:from-slate-900 dark:to-slate-900">
                    <div className="flex items-center justify-between text-blue-700 dark:text-blue-400">
                        <span className="text-xs font-black uppercase tracking-wider">
                            Remboursements à instruire
                        </span>
                        <RotateCcw className="size-4" />
                    </div>
                    <div className="mt-3 font-mono text-xl font-black text-slate-900 sm:text-2xl dark:text-white">
                        {pendingRefundsTotal.toLocaleString('fr-FR')}{' '}
                        <span className="text-xs font-bold text-slate-500">FCFA</span>
                    </div>
                    <div className="mt-1 flex items-center gap-1.5 text-xs text-blue-800 dark:text-blue-300 font-semibold">
                        <span className="size-1.5 rounded-full bg-blue-500" />
                        <span>{pendingRefunds.length} dossiers passagers en arbitrage</span>
                    </div>
                </div>

                {/* Validated Settlements */}
                <div className="rounded-2xl border border-emerald-200/80 bg-gradient-to-br from-emerald-50 to-white p-5 shadow-sm dark:border-emerald-900/40 dark:from-slate-900 dark:to-slate-900">
                    <div className="flex items-center justify-between text-emerald-700 dark:text-emerald-400">
                        <span className="text-xs font-black uppercase tracking-wider">
                            Reversements validés
                        </span>
                        <CheckCircle2 className="size-4" />
                    </div>
                    <div className="mt-3 font-mono text-xl font-black text-slate-900 sm:text-2xl dark:text-white">
                        {validatedSettlementsTotal.toLocaleString('fr-FR')}{' '}
                        <span className="text-xs font-bold text-slate-500">FCFA</span>
                    </div>
                    <div className="mt-1 flex items-center gap-1.5 text-xs text-emerald-800 dark:text-emerald-300 font-semibold">
                        <span className="size-1.5 rounded-full bg-emerald-500" />
                        <span>{validatedSettlements.length} bordereaux apurés ce mois</span>
                    </div>
                </div>

                {/* Audit & Compliance */}
                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between text-slate-500 dark:text-slate-400">
                        <span className="text-xs font-black uppercase tracking-wider">
                            Rapprochement bancaire
                        </span>
                        <ShieldCheck className="size-4 text-emerald-500" />
                    </div>
                    <div className="mt-3 font-mono text-xl font-black text-emerald-600 sm:text-2xl dark:text-emerald-400">
                        100 %
                    </div>
                    <div className="mt-1 text-xs text-slate-500">
                        Traçabilité complète avec date de valeur et quittance
                    </div>
                </div>
            </div>

            {/* Navigation Tabs */}
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-3 dark:border-slate-800">
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => setActiveTab('SETTLEMENTS')}
                        className={`inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-black transition ${
                            activeTab === 'SETTLEMENTS'
                                ? 'bg-[var(--kora)] text-white shadow-md'
                                : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'
                        }`}
                    >
                        <Receipt className="size-4" />
                        <span>Reversements à rapprocher</span>
                        {pendingSettlements.length > 0 && (
                            <span className="rounded-full bg-amber-500 px-2 py-0.5 text-[0.625rem] font-black text-white">
                                {pendingSettlements.length}
                            </span>
                        )}
                    </button>

                    <button
                        type="button"
                        onClick={() => setActiveTab('REFUNDS')}
                        className={`inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-black transition ${
                            activeTab === 'REFUNDS'
                                ? 'bg-[var(--kora)] text-white shadow-md'
                                : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'
                        }`}
                    >
                        <RotateCcw className="size-4" />
                        <span>Dossiers de remboursement</span>
                        {pendingRefunds.length > 0 && (
                            <span className="rounded-full bg-blue-500 px-2 py-0.5 text-[0.625rem] font-black text-white">
                                {pendingRefunds.length}
                            </span>
                        )}
                    </button>

                    <button
                        type="button"
                        onClick={() => setActiveTab('HISTORY')}
                        className={`inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-black transition ${
                            activeTab === 'HISTORY'
                                ? 'bg-[var(--kora)] text-white shadow-md'
                                : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'
                        }`}
                    >
                        <CheckCircle2 className="size-4" />
                        <span>Historique & Audit clôturé</span>
                    </button>
                </div>

                {/* Filter and Search Input */}
                <div className="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    <div className="relative flex-1 sm:w-64">
                        <Search className="absolute left-3 top-2.5 size-4 text-slate-400" />
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder={
                                activeTab === 'REFUNDS'
                                    ? 'Rechercher passager, billet...'
                                    : 'Rechercher bordereau, point...'
                            }
                            className="w-full rounded-xl border border-slate-300 bg-white pl-9 pr-3 py-2 text-xs font-medium text-slate-900 shadow-sm focus:border-gold focus:outline-none focus:ring-1 focus:ring-gold dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                        />
                    </div>

                    {activeTab !== 'REFUNDS' && (
                        <select
                            value={methodFilter}
                            onChange={(e) => setMethodFilter(e.target.value)}
                            className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm focus:border-gold focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                        >
                            <option value="ALL">Tous les modes</option>
                            <option value="BANK_TRANSFER">Virement bancaire</option>
                            <option value="BANK_DEPOSIT">Versement espèces</option>
                            <option value="MOBILE_MONEY">Mobile Money B2B</option>
                        </select>
                    )}
                </div>
            </div>

            {/* Content: SETTLEMENTS or HISTORY */}
            {activeTab !== 'REFUNDS' && (
                <div className="space-y-4">
                    {filteredSettlements.length === 0 ? (
                        <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center dark:border-slate-800 dark:bg-slate-900">
                            <Receipt className="mx-auto size-12 text-slate-300 dark:text-slate-600" />
                            <h3 className="mt-3 text-sm font-bold text-slate-800 dark:text-slate-200">
                                Aucun bordereau de reversement trouvé
                            </h3>
                            <p className="mt-1 text-xs text-slate-500">
                                Aucun enregistrement ne correspond aux filtres de recherche sélectionnés.
                            </p>
                        </div>
                    ) : (
                        <>
                            {/* Desktop Table View */}
                            <div className="hidden lg:block overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                                <table className="w-full text-left text-xs">
                                    <thead className="border-b border-slate-200 bg-slate-50/75 text-[0.6875rem] font-black uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
                                        <tr>
                                            <th className="px-5 py-3.5">Référence</th>
                                            <th className="px-4 py-3.5">Émetteur & Réseau</th>
                                            <th className="px-4 py-3.5">Date déclaration</th>
                                            <th className="px-4 py-3.5">Canal de versement</th>
                                            <th className="px-4 py-3.5">Montant déclaré</th>
                                            <th className="px-4 py-3.5">Justificatif</th>
                                            <th className="px-4 py-3.5">Statut</th>
                                            <th className="px-5 py-3.5 text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                        {filteredSettlements.map((s) => (
                                            <tr
                                                key={s.uuid}
                                                className="transition hover:bg-slate-50/75 dark:hover:bg-slate-800/40"
                                            >
                                                <td className="px-5 py-4 font-mono font-bold text-kora dark:text-gold">
                                                    {s.reference}
                                                </td>
                                                <td className="px-4 py-4">
                                                    <div className="font-bold text-slate-900 dark:text-white">
                                                        {s.point_name}
                                                    </div>
                                                    <div className="text-[0.6875rem] text-slate-500">
                                                        Déclaré par {s.created_by_name}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-4 text-slate-600 dark:text-slate-300">
                                                    {s.declared_at || s.created_at}
                                                </td>
                                                <td className="px-4 py-4">
                                                    <div className="font-semibold text-slate-800 dark:text-slate-200">
                                                        {s.payment_method === 'BANK_TRANSFER'
                                                            ? 'Virement bancaire'
                                                            : s.payment_method === 'BANK_DEPOSIT'
                                                            ? 'Versement espèces'
                                                            : 'Mobile Money B2B'}
                                                    </div>
                                                    <div className="font-mono text-[0.625rem] text-slate-400">
                                                        {s.payment_reference || 'Sans réf'}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-4 font-mono font-black text-slate-900 dark:text-white">
                                                    {s.amount_declared.toLocaleString('fr-FR')} FCFA
                                                    {s.amount_validated !== undefined && s.amount_validated !== s.amount_declared && (
                                                        <div className="text-[0.625rem] text-amber-600 font-bold">
                                                            Validé : {s.amount_validated.toLocaleString('fr-FR')} FCFA
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="px-4 py-4">
                                                    {s.proof_file_name ? (
                                                        <span className="inline-flex items-center gap-1 text-[0.6875rem] font-bold text-blue-600 dark:text-blue-400">
                                                            <FileText className="size-3.5" />
                                                            <span className="max-w-[120px] truncate">
                                                                {s.proof_file_name}
                                                            </span>
                                                        </span>
                                                    ) : (
                                                        <span className="text-slate-400 text-[0.625rem]">
                                                            Aucun fichier
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-4">
                                                    <KoraStatusBadge
                                                        label={
                                                            s.status === 'DECLARED'
                                                                ? 'À rapprocher'
                                                                : s.status === 'VALIDATED'
                                                                ? 'Validé & Apuré'
                                                                : s.status === 'REJECTED'
                                                                ? 'Rejeté'
                                                                : 'Brouillon'
                                                        }
                                                        tone={
                                                            s.status === 'DECLARED'
                                                                ? 'amber'
                                                                : s.status === 'VALIDATED'
                                                                ? 'emerald'
                                                                : s.status === 'REJECTED'
                                                                ? 'destructive'
                                                                : 'muted'
                                                        }
                                                        variant="solid"
                                                    />
                                                </td>
                                                <td className="px-5 py-4 text-right">
                                                    {s.status === 'DECLARED' ? (
                                                        <button
                                                            type="button"
                                                            onClick={() => setSelectedSettlement(s)}
                                                            className="inline-flex items-center gap-1.5 rounded-xl bg-gold px-3 py-1.5 text-xs font-black text-kora shadow-sm hover:bg-gold/90 transition"
                                                        >
                                                            <ShieldCheck className="size-3.5" />
                                                            <span>Rapprocher</span>
                                                        </button>
                                                    ) : (
                                                        <button
                                                            type="button"
                                                            onClick={() => setSelectedSettlement(s)}
                                                            className="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 transition"
                                                        >
                                                            <Eye className="size-3.5" />
                                                            <span>Consulter</span>
                                                        </button>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {/* Mobile Card View */}
                            <div className="grid grid-cols-1 gap-3 lg:hidden">
                                {filteredSettlements.map((s) => (
                                    <div
                                        key={s.uuid}
                                        className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                                    >
                                        <div className="flex items-center justify-between">
                                            <span className="font-mono text-xs font-black text-kora dark:text-gold">
                                                {s.reference}
                                            </span>
                                            <KoraStatusBadge
                                                label={
                                                    s.status === 'DECLARED'
                                                        ? 'À rapprocher'
                                                        : s.status === 'VALIDATED'
                                                        ? 'Validé'
                                                        : s.status === 'REJECTED'
                                                        ? 'Rejeté'
                                                        : 'Brouillon'
                                                }
                                                tone={
                                                    s.status === 'DECLARED'
                                                        ? 'amber'
                                                        : s.status === 'VALIDATED'
                                                        ? 'emerald'
                                                        : s.status === 'destructive'
                                                        ? 'destructive'
                                                        : 'muted'
                                                }
                                                variant="solid"
                                            />
                                        </div>

                                        <div className="mt-2 text-xs font-black text-slate-900 dark:text-white">
                                            {s.point_name}
                                        </div>
                                        <div className="text-[0.6875rem] text-slate-500">
                                            Déclaré par {s.created_by_name} le {s.declared_at || s.created_at}
                                        </div>

                                        <div className="mt-3 flex items-center justify-between border-t border-slate-100 pt-3 dark:border-slate-800">
                                            <div>
                                                <span className="block text-[0.625rem] uppercase text-slate-400">
                                                    Montant déclaré
                                                </span>
                                                <span className="font-mono text-sm font-black text-slate-900 dark:text-white">
                                                    {s.amount_declared.toLocaleString('fr-FR')} FCFA
                                                </span>
                                            </div>

                                            {s.status === 'DECLARED' ? (
                                                <button
                                                    type="button"
                                                    onClick={() => setSelectedSettlement(s)}
                                                    className="inline-flex items-center gap-1.5 rounded-xl bg-gold px-4 py-2 text-xs font-black text-kora shadow-md"
                                                >
                                                    <ShieldCheck className="size-3.5" />
                                                    <span>Rapprocher</span>
                                                </button>
                                            ) : (
                                                <button
                                                    type="button"
                                                    onClick={() => setSelectedSettlement(s)}
                                                    className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-bold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                                >
                                                    <Eye className="size-3.5" />
                                                    <span>Consulter</span>
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </>
                    )}
                </div>
            )}

            {/* Content: REFUNDS TAB */}
            {activeTab === 'REFUNDS' && (
                <div className="space-y-4">
                    {filteredRefunds.length === 0 ? (
                        <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center dark:border-slate-800 dark:bg-slate-900">
                            <RotateCcw className="mx-auto size-12 text-slate-300 dark:text-slate-600" />
                            <h3 className="mt-3 text-sm font-bold text-slate-800 dark:text-slate-200">
                                Aucun dossier de remboursement trouvé
                            </h3>
                            <p className="mt-1 text-xs text-slate-500">
                                Toutes les demandes de remboursement passagers ont été traitées ou aucune ne correspond à la recherche.
                            </p>
                        </div>
                    ) : (
                        <>
                            {/* Desktop Table View */}
                            <div className="hidden lg:block overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                                <table className="w-full text-left text-xs">
                                    <thead className="border-b border-slate-200 bg-slate-50/75 text-[0.6875rem] font-black uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
                                        <tr>
                                            <th className="px-5 py-3.5">Réf. Dossier</th>
                                            <th className="px-4 py-3.5">Passager & Billet</th>
                                            <th className="px-4 py-3.5">Trajet Bus</th>
                                            <th className="px-4 py-3.5">Motif</th>
                                            <th className="px-4 py-3.5">Montant restitution</th>
                                            <th className="px-4 py-3.5">Reprise com.</th>
                                            <th className="px-4 py-3.5">Statut</th>
                                            <th className="px-5 py-3.5 text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                        {filteredRefunds.map((r) => (
                                            <tr
                                                key={r.uuid}
                                                className="transition hover:bg-slate-50/75 dark:hover:bg-slate-800/40"
                                            >
                                                <td className="px-5 py-4 font-mono font-bold text-kora dark:text-gold">
                                                    {r.reference}
                                                </td>
                                                <td className="px-4 py-4">
                                                    <div className="font-bold text-slate-900 dark:text-white">
                                                        {r.passenger_name}
                                                    </div>
                                                    <div className="font-mono text-[0.6875rem] text-slate-500">
                                                        {r.ticket_number}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-4 text-slate-700 dark:text-slate-300">
                                                    <div className="font-medium">{r.route_label}</div>
                                                    <div className="font-mono text-[0.625rem] text-slate-400">
                                                        {r.departure_ref}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-4">
                                                    <span className="text-xs text-amber-800 dark:text-amber-300 font-medium">
                                                        {r.reason_label}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-4 font-mono font-black text-emerald-600 dark:text-emerald-400">
                                                    {r.refund_amount.toLocaleString('fr-FR')} FCFA
                                                </td>
                                                <td className="px-4 py-4 font-mono text-xs font-bold text-rose-600 dark:text-rose-400">
                                                    {r.commission_clawback} FCFA
                                                </td>
                                                <td className="px-4 py-4">
                                                    <KoraStatusBadge
                                                        label={
                                                            r.status === 'REQUESTED'
                                                                ? 'À instruire'
                                                                : r.status === 'APPROVED_BY_FINANCE'
                                                                ? 'Approuvé'
                                                                : r.status === 'FUNDS_RESTITUTED'
                                                                ? 'Restitué'
                                                                : 'Refusé'
                                                        }
                                                        tone={
                                                            r.status === 'REQUESTED'
                                                                ? 'blue'
                                                                : r.status === 'APPROVED_BY_FINANCE'
                                                                ? 'amber'
                                                                : r.status === 'FUNDS_RESTITUTED'
                                                                ? 'emerald'
                                                                : 'destructive'
                                                        }
                                                        variant="solid"
                                                    />
                                                </td>
                                                <td className="px-5 py-4 text-right">
                                                    {r.status === 'REQUESTED' ? (
                                                        <button
                                                            type="button"
                                                            onClick={() => setSelectedRefund(r)}
                                                            className="inline-flex items-center gap-1.5 rounded-xl bg-gold px-3 py-1.5 text-xs font-black text-kora shadow-sm hover:bg-gold/90 transition"
                                                        >
                                                            <UserCheck className="size-3.5" />
                                                            <span>Instruire</span>
                                                        </button>
                                                    ) : (
                                                        <button
                                                            type="button"
                                                            onClick={() => setSelectedRefund(r)}
                                                            className="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 transition"
                                                        >
                                                            <Eye className="size-3.5" />
                                                            <span>Détail</span>
                                                        </button>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {/* Mobile Card View */}
                            <div className="grid grid-cols-1 gap-3 lg:hidden">
                                {filteredRefunds.map((r) => (
                                    <div
                                        key={r.uuid}
                                        className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                                    >
                                        <div className="flex items-center justify-between">
                                            <span className="font-mono text-xs font-black text-kora dark:text-gold">
                                                {r.reference}
                                            </span>
                                            <KoraStatusBadge
                                                label={
                                                    r.status === 'REQUESTED'
                                                        ? 'À instruire'
                                                        : r.status === 'APPROVED_BY_FINANCE'
                                                        ? 'Approuvé'
                                                        : r.status === 'FUNDS_RESTITUTED'
                                                        ? 'Restitué'
                                                        : 'Refusé'
                                                }
                                                tone={
                                                    r.status === 'REQUESTED'
                                                        ? 'blue'
                                                        : r.status === 'APPROVED_BY_FINANCE'
                                                        ? 'amber'
                                                        : r.status === 'FUNDS_RESTITUTED'
                                                        ? 'emerald'
                                                        : 'destructive'
                                                }
                                                variant="solid"
                                            />
                                        </div>

                                        <div className="mt-2 text-xs font-black text-slate-900 dark:text-white">
                                            {r.passenger_name}
                                        </div>
                                        <div className="font-mono text-[0.6875rem] text-slate-500">
                                            Billet {r.ticket_number} • {r.route_label}
                                        </div>

                                        <div className="mt-3 flex items-center justify-between border-t border-slate-100 pt-3 dark:border-slate-800">
                                            <div>
                                                <span className="block text-[0.625rem] uppercase text-slate-400">
                                                    Restitution
                                                </span>
                                                <span className="font-mono text-sm font-black text-emerald-600 dark:text-emerald-400">
                                                    {r.refund_amount.toLocaleString('fr-FR')} FCFA
                                                </span>
                                            </div>

                                            {r.status === 'REQUESTED' ? (
                                                <button
                                                    type="button"
                                                    onClick={() => setSelectedRefund(r)}
                                                    className="inline-flex items-center gap-1.5 rounded-xl bg-gold px-4 py-2 text-xs font-black text-kora shadow-md"
                                                >
                                                    <UserCheck className="size-3.5" />
                                                    <span>Instruire</span>
                                                </button>
                                            ) : (
                                                <button
                                                    type="button"
                                                    onClick={() => setSelectedRefund(r)}
                                                    className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-bold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                                >
                                                    <Eye className="size-3.5" />
                                                    <span>Détail</span>
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </>
                    )}
                </div>
            )}

            {/* Settlement Action Dialog */}
            <KoraFinanceSettlementActionDialog
                isOpen={Boolean(selectedSettlement)}
                settlement={selectedSettlement}
                onClose={() => setSelectedSettlement(null)}
                onReconcile={onReconcileSettlement}
                onReject={onRejectSettlement}
            />

            {/* Refund Action Dialog */}
            <KoraFinanceRefundActionDialog
                isOpen={Boolean(selectedRefund)}
                refund={selectedRefund}
                onClose={() => setSelectedRefund(null)}
                onApprove={onApproveRefund}
                onReject={onRejectRefund}
            />
        </div>
    );
}
