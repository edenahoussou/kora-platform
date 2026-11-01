import {
    Coins,
    Building2,
    Calendar,
    Clock,
    CheckCircle2,
    AlertTriangle,
    Plus,
    FileText,
    Eye,
    TrendingUp,
    Landmark,
    Search,
    ChevronDown,
    ChevronUp,
    Shield,
} from 'lucide-react';
import { useState, useMemo } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { cn } from '@/lib/utils';
import type {
    GroupSettlementRecord,
    OrganizationSubPoint,
    PointRefundRecord,
} from '../mock/kora-point-mock-data';
import { KoraOrgGroupSettlementDialog } from './kora-org-group-settlement-dialog';

type Props = {
    organizationName: string;
    subpoints: OrganizationSubPoint[];
    groupSettlements: GroupSettlementRecord[];
    refunds: PointRefundRecord[];
    onDeclareGroupSettlement: (data: any) => void;
};

export function KoraOrgTreasuryView({
    organizationName,
    subpoints,
    groupSettlements,
    refunds,
    onDeclareGroupSettlement,
}: Props) {
    const [isGroupModalOpen, setIsGroupModalOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [expandedSettlementUuid, setExpandedSettlementUuid] = useState<string | null>(null);

    // Financial aggregates
    const treasurySummary = useMemo(() => {
        let totalExposure = 0;
        let totalLimit = 0;
        let activeDebtsCount = 0;

        subpoints.forEach((sp) => {
            totalExposure += sp.current_exposure;
            totalLimit += sp.credit_limit;

            if (sp.current_exposure > 0) {
activeDebtsCount += 1;
}
        });

        const availableBalance = Math.max(0, totalLimit - totalExposure);
        const usagePct = totalLimit > 0 ? Math.round((totalExposure / totalLimit) * 100) : 0;

        return { totalExposure, totalLimit, availableBalance, usagePct, activeDebtsCount };
    }, [subpoints]);

    const getStatusBadge = (status: GroupSettlementRecord['status']) => {
        switch (status) {
            case 'VALIDATED':
                return (
                    <KoraStatusBadge
                        variant="solid"
                        colorScheme="emerald"
                        icon={<CheckCircle2 className="h-3 w-3" />}
                    >
                        Rapproché & Validé
                    </KoraStatusBadge>
                );
            case 'DECLARED':
                return (
                    <KoraStatusBadge
                        variant="solid"
                        colorScheme="amber"
                        icon={<Clock className="h-3 w-3" />}
                    >
                        En vérification Trésorerie
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
            {/* Modal de reversement groupé */}
            <KoraOrgGroupSettlementDialog
                isOpen={isGroupModalOpen}
                organizationName={organizationName}
                subpoints={subpoints}
                onClose={() => setIsGroupModalOpen(false)}
                onSubmit={(data) => {
                    onDeclareGroupSettlement(data);
                }}
            />

            {/* Grand bandeau Trésorier */}
            <div className="relative overflow-hidden rounded-3xl bg-navy p-6 sm:p-8 text-white shadow-xl">
                <div className="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-gold">
                            <Coins className="h-4 w-4" />
                            Pôle Trésorerie & Règlements Groupés
                        </div>
                        <h1 className="mt-2 text-2xl sm:text-3xl font-black text-white">
                            {organizationName}
                        </h1>
                        <p className="mt-1 text-xs sm:text-sm text-slate-300 max-w-xl">
                            Consolidation financière des dettes de billetterie, apurement multi-points et ordres de virements interbancaires.
                        </p>
                    </div>

                    <button
                        type="button"
                        onClick={() => setIsGroupModalOpen(true)}
                        className="inline-flex items-center justify-center gap-2 rounded-2xl bg-gold px-6 py-3.5 text-xs font-black uppercase tracking-wider text-kora shadow-lg hover:bg-gold-light focus:outline-none focus:ring-2 focus:ring-gold"
                    >
                        <Plus className="h-4 w-4" />
                        Préparer un reversement groupé +
                    </button>
                </div>
            </div>

            {/* Financial Overview Cards */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
                {/* Encours consolidé */}
                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">
                        Encours total à reverser
                    </span>
                    <div className="mt-3">
                        <span className="text-2xl font-black text-slate-900 dark:text-white">
                            {treasurySummary.totalExposure.toLocaleString()} FCFA
                        </span>
                        <p className="text-xs text-slate-500 mt-1">
                            {treasurySummary.activeDebtsCount} bureau(x) avec encours échu
                        </p>
                    </div>
                </div>

                {/* Plafond global */}
                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">
                        Plafond global réseau
                    </span>
                    <div className="mt-3">
                        <span className="text-2xl font-black text-slate-900 dark:text-white">
                            {treasurySummary.totalLimit.toLocaleString()} FCFA
                        </span>
                        <p className="text-xs text-slate-500 mt-1">
                            {treasurySummary.usagePct} % du plafond consommé
                        </p>
                    </div>
                </div>

                {/* Disponible réseau */}
                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">
                        Capacité disponible
                    </span>
                    <div className="mt-3">
                        <span className="text-2xl font-black text-emerald-600 dark:text-emerald-400">
                            {treasurySummary.availableBalance.toLocaleString()} FCFA
                        </span>
                        <p className="text-xs text-slate-500 mt-1">
                            Marge avant blocage des guichets
                        </p>
                    </div>
                </div>

                {/* Échéance globale */}
                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">
                        Prochaine échéance globale
                    </span>
                    <div className="mt-3">
                        <span className="text-base font-black text-slate-900 dark:text-white">
                            28 août 2026
                        </span>
                        <p className="text-xs text-amber-600 font-bold mt-1">
                            Règlement mensuel contractuel
                        </p>
                    </div>
                </div>
            </div>

            {/* Group Settlements Ledger */}
            <div className="space-y-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                            <Landmark className="h-5 w-5 text-gold-dark dark:text-gold" />
                            Dossiers de reversements groupés de l'organisation
                        </h2>
                        <p className="text-xs text-slate-500">
                            Bordereaux de règlements interbancaires et répartition comptable par bureau
                        </p>
                    </div>
                </div>

                <div className="space-y-3">
                    {groupSettlements.map((grp) => {
                        const isExpanded = expandedSettlementUuid === grp.uuid;

                        return (
                            <div
                                key={grp.uuid}
                                className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 space-y-4"
                            >
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-navy text-gold">
                                            <Coins className="h-5 w-5" />
                                        </div>
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <span className="font-mono text-sm font-black text-slate-900 dark:text-white">
                                                    {grp.reference}
                                                </span>
                                                {getStatusBadge(grp.status)}
                                            </div>
                                            <p className="text-xs text-slate-500">
                                                Transmis par {grp.created_by_name} ({grp.created_at})
                                            </p>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-4">
                                        <div className="text-right">
                                            <span className="text-[11px] text-slate-400 block">
                                                Montant global versé
                                            </span>
                                            <span className="text-base font-black text-slate-900 dark:text-white">
                                                {grp.total_amount_declared.toLocaleString()} FCFA
                                            </span>
                                        </div>

                                        <button
                                            type="button"
                                            onClick={() =>
                                                setExpandedSettlementUuid(
                                                    isExpanded ? null : grp.uuid,
                                                )
                                            }
                                            className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                        >
                                            <Eye className="h-3.5 w-3.5 text-slate-500" />
                                            {isExpanded ? 'Masquer' : 'Voir ventilation'}
                                            {isExpanded ? (
                                                <ChevronUp className="h-3.5 w-3.5" />
                                            ) : (
                                                <ChevronDown className="h-3.5 w-3.5" />
                                            )}
                                        </button>
                                    </div>
                                </div>

                                {/* Operation Details */}
                                <div className="flex flex-wrap gap-4 text-xs text-slate-600 dark:text-slate-400 border-t border-slate-100 pt-3 dark:border-slate-800">
                                    <span>
                                        <strong>Mode :</strong> {grp.payment_method}
                                    </span>
                                    <span>
                                        <strong>Réf bancaire :</strong>{' '}
                                        <span className="font-mono">{grp.payment_reference}</span>
                                    </span>
                                    {grp.proof_file_name && (
                                        <span className="text-blue-600 dark:text-blue-400 flex items-center gap-1">
                                            <FileText className="h-3 w-3" /> {grp.proof_file_name}
                                        </span>
                                    )}
                                </div>

                                {/* Expanded Breakdown Table */}
                                {isExpanded && (
                                    <div className="rounded-xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-800/40 space-y-3">
                                        <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500">
                                            Ventilation comptable par bureau ({grp.breakdown.length} bureaux couverts)
                                        </h4>
                                        <div className="rounded-lg border border-slate-200 bg-white overflow-hidden text-xs dark:border-slate-800 dark:bg-slate-900">
                                            <table className="w-full text-left">
                                                <thead className="bg-slate-50 text-slate-500 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-800">
                                                    <tr>
                                                        <th className="px-3 py-2 font-bold uppercase">Bureau</th>
                                                        <th className="px-3 py-2 font-bold uppercase text-right">Encours initial</th>
                                                        <th className="px-3 py-2 font-bold uppercase text-right">Montant apuré</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                                    {grp.breakdown.map((b) => (
                                                        <tr key={b.subpoint_uuid}>
                                                            <td className="px-3 py-2 font-bold text-slate-900 dark:text-white">
                                                                {b.subpoint_name}
                                                            </td>
                                                            <td className="px-3 py-2 text-right text-slate-500">
                                                                {b.current_exposure.toLocaleString()} FCFA
                                                            </td>
                                                            <td className="px-3 py-2 text-right font-black text-emerald-600 dark:text-emerald-400">
                                                                +{b.amount_allocated.toLocaleString()} FCFA
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}
