import {
    Coins,
    Search,
    Filter,
    Plus,
    Clock,
    CheckCircle2,
    AlertTriangle,
    Eye,
    Landmark,
    Building2,
    Info,
    Calendar,
    ArrowUpRight,
    TrendingUp,
    FileText,
} from 'lucide-react';
import { useState, useMemo } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { cn } from '@/lib/utils';
import type {
    FinancialHealth,
    KoraPointProfile,
    PointSettlementRecord,
    PointSettlementStatus,
} from '../mock/kora-point-mock-data';
import { KoraPointSettlementDetailDrawer } from './kora-point-settlement-detail-drawer';
import { KoraPointSettlementDialog } from './kora-point-settlement-dialog';

type Props = {
    point: KoraPointProfile;
    health: FinancialHealth;
    settlements: PointSettlementRecord[];
    onDeclareSettlement: (data: {
        amountDeclared: number;
        paymentMethod: any;
        paymentReference: string;
        proofFileName?: string;
        notes?: string;
    }) => void;
};

export function KoraPointSettlementListView({
    point,
    health,
    settlements,
    onDeclareSettlement,
}: Props) {
    const isAutonomous = !point.organization;
    const [searchQuery, setSearchQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState<'ALL' | PointSettlementStatus>('ALL');
    const [selectedSettlement, setSelectedSettlement] = useState<PointSettlementRecord | null>(null);
    const [isDeclarationModalOpen, setIsDeclarationModalOpen] = useState(false);

    // Filtered list
    const filteredSettlements = useMemo(() => {
        return settlements.filter((s) => {
            const matchesSearch =
                s.reference.toLowerCase().includes(searchQuery.toLowerCase()) ||
                s.payment_reference.toLowerCase().includes(searchQuery.toLowerCase()) ||
                (s.notes && s.notes.toLowerCase().includes(searchQuery.toLowerCase()));

            const matchesStatus = statusFilter === 'ALL' || s.status === statusFilter;

            return matchesSearch && matchesStatus;
        });
    }, [settlements, searchQuery, statusFilter]);

    // Financial calculations
    const totals = useMemo(() => {
        let declaredPending = 0;
        let validatedTotal = 0;

        settlements.forEach((s) => {
            if (s.status === 'DECLARED') {
                declaredPending += s.amount_declared;
            }

            if (s.status === 'VALIDATED') {
                validatedTotal += s.amount_validated ?? s.amount_declared;
            }
        });

        return { declaredPending, validatedTotal };
    }, [settlements]);

    const getStatusBadge = (status: PointSettlementRecord['status']) => {
        switch (status) {
            case 'VALIDATED':
                return (
                    <KoraStatusBadge
                        variant="solid"
                        colorScheme="emerald"
                        icon={<CheckCircle2 className="h-3 w-3" />}
                    >
                        Validé & Rapproché
                    </KoraStatusBadge>
                );
            case 'DECLARED':
                return (
                    <KoraStatusBadge
                        variant="solid"
                        colorScheme="amber"
                        icon={<Clock className="h-3 w-3" />}
                    >
                        En vérification
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
            case 'PARTIALLY_VALIDATED':
                return (
                    <KoraStatusBadge variant="solid" colorScheme="blue">
                        Partiel
                    </KoraStatusBadge>
                );
            default:
                return (
                    <KoraStatusBadge variant="solid" colorScheme="navy">
                        Brouillon
                    </KoraStatusBadge>
                );
        }
    };

    return (
        <div className="space-y-6">
            {/* Modal de déclaration */}
            <KoraPointSettlementDialog
                isOpen={isDeclarationModalOpen}
                health={health}
                pointName={point.name}
                onClose={() => setIsDeclarationModalOpen(false)}
                onSubmit={(data) => {
                    onDeclareSettlement(data);
                }}
            />

            {/* Tiroir de détail */}
            <KoraPointSettlementDetailDrawer
                settlement={selectedSettlement}
                isOpen={selectedSettlement !== null}
                onClose={() => setSelectedSettlement(null)}
                onResubmit={(s) => {
                    setSelectedSettlement(null);
                    setIsDeclarationModalOpen(true);
                }}
            />

            {/* Page Header */}
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-xl font-black text-slate-900 dark:text-white flex items-center gap-2">
                        <Coins className="h-6 w-6 text-gold-dark dark:text-gold" />
                        Reversements partenaires
                    </h1>
                    <p className="text-xs text-slate-500 mt-1">
                        Déclarations et suivi des apurements de recettes de billetterie vers KORA Exploitation
                    </p>
                </div>

                {isAutonomous ? (
                    <button
                        type="button"
                        onClick={() => setIsDeclarationModalOpen(true)}
                        className="inline-flex items-center justify-center gap-2 rounded-xl bg-gold px-5 py-2.5 text-xs font-black uppercase tracking-wider text-kora shadow-sm hover:bg-gold-light focus:outline-none focus:ring-2 focus:ring-gold"
                    >
                        <Plus className="h-4 w-4" />
                        Déclarer un reversement +
                    </button>
                ) : (
                    <div className="inline-flex items-center gap-2 rounded-xl bg-slate-100 px-4 py-2 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                        <Building2 className="h-4 w-4 text-slate-400" />
                        Versements centralisés par le Trésorier
                    </div>
                )}
            </div>

            {/* Organization notice if attached */}
            {!isAutonomous && (
                <div className="rounded-xl border border-blue-200 bg-blue-50/80 p-4 text-xs text-blue-900 dark:border-blue-900/60 dark:bg-blue-950/40 dark:text-blue-200 flex items-start gap-3">
                    <Info className="h-5 w-5 shrink-0 text-blue-600 mt-0.5" />
                    <div className="space-y-1">
                        <p className="font-bold">
                            Bureau rattaché à l’organisation {point.organization?.name}
                        </p>
                        <p className="text-blue-800 dark:text-blue-300">
                            En tant qu'agent de bureau, cet écran vous permet de consulter l'historique des reversements et l'encours apuré. La déclaration et le règlement financier des reversements groupés sont assurés par la direction financière / le trésorier de votre organisation.
                        </p>
                    </div>
                </div>
            )}

            {/* Financial Summary KPIs */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                {/* Encours actuel */}
                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">
                            Encours total à reverser
                        </span>
                        <div className="rounded-lg bg-amber-500/10 p-2 text-amber-600 dark:text-amber-400">
                            <Clock className="h-4 w-4" />
                        </div>
                    </div>
                    <div className="mt-3">
                        <span className="text-2xl font-black text-slate-900 dark:text-white">
                            {health.current_exposure.toLocaleString()} FCFA
                        </span>
                        <p className="text-xs text-slate-500 mt-1">
                            Plafond : {health.credit_limit.toLocaleString()} FCFA ({health.usage_percentage} % utilisé)
                        </p>
                    </div>
                </div>

                {/* En cours de vérification */}
                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">
                            En vérification Trésorerie
                        </span>
                        <div className="rounded-lg bg-blue-500/10 p-2 text-blue-600 dark:text-blue-400">
                            <Coins className="h-4 w-4" />
                        </div>
                    </div>
                    <div className="mt-3">
                        <span className="text-2xl font-black text-blue-600 dark:text-blue-400">
                            {totals.declaredPending.toLocaleString()} FCFA
                        </span>
                        <p className="text-xs text-slate-500 mt-1">
                            Déclaré par le Point, en attente de pointage bancaire
                        </p>
                    </div>
                </div>

                {/* Validé & Rapproché */}
                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">
                            Total apuré & validé
                        </span>
                        <div className="rounded-lg bg-emerald-500/10 p-2 text-emerald-600 dark:text-emerald-400">
                            <CheckCircle2 className="h-4 w-4" />
                        </div>
                    </div>
                    <div className="mt-3">
                        <span className="text-2xl font-black text-emerald-600 dark:text-emerald-400">
                            {totals.validatedTotal.toLocaleString()} FCFA
                        </span>
                        <p className="text-xs text-slate-500 mt-1">
                            Fonds reçus et rapprochés par KORA Exploitation
                        </p>
                    </div>
                </div>
            </div>

            {/* Filter Bar */}
            <div className="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:flex-row sm:items-center sm:justify-between">
                {/* Search */}
                <div className="relative flex-1">
                    <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                    <input
                        type="text"
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        placeholder="Rechercher par référence, n° d’opération, observation..."
                        className="w-full rounded-xl border border-slate-200 bg-slate-50 py-2 pl-9 pr-4 text-xs font-medium text-slate-900 placeholder:text-slate-400 focus:border-gold focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    />
                </div>

                {/* Status Pills */}
                <div className="flex flex-wrap gap-1.5">
                    <button
                        type="button"
                        onClick={() => setStatusFilter('ALL')}
                        className={cn(
                            'rounded-xl px-3 py-1.5 text-xs font-bold transition-colors',
                            statusFilter === 'ALL'
                                ? 'bg-navy text-white dark:bg-gold dark:text-kora'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300',
                        )}
                    >
                        Tous ({settlements.length})
                    </button>
                    <button
                        type="button"
                        onClick={() => setStatusFilter('DECLARED')}
                        className={cn(
                            'rounded-xl px-3 py-1.5 text-xs font-bold transition-colors',
                            statusFilter === 'DECLARED'
                                ? 'bg-amber-600 text-white'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300',
                        )}
                    >
                        En vérification
                    </button>
                    <button
                        type="button"
                        onClick={() => setStatusFilter('VALIDATED')}
                        className={cn(
                            'rounded-xl px-3 py-1.5 text-xs font-bold transition-colors',
                            statusFilter === 'VALIDATED'
                                ? 'bg-emerald-600 text-white'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300',
                        )}
                    >
                        Validés
                    </button>
                    <button
                        type="button"
                        onClick={() => setStatusFilter('REJECTED')}
                        className={cn(
                            'rounded-xl px-3 py-1.5 text-xs font-bold transition-colors',
                            statusFilter === 'REJECTED'
                                ? 'bg-rose-600 text-white'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300',
                        )}
                    >
                        Rejetés
                    </button>
                </div>
            </div>

            {/* Content List: Desktop Table & Mobile Cards */}
            {filteredSettlements.length === 0 ? (
                <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center dark:border-slate-700 dark:bg-slate-900">
                    <Coins className="mx-auto h-8 w-8 text-slate-400" />
                    <h3 className="mt-2 text-sm font-bold text-slate-900 dark:text-white">
                        Aucun reversement trouvé
                    </h3>
                    <p className="mt-1 text-xs text-slate-500">
                        Modifiez vos critères de recherche ou déclarez un nouveau versement.
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
                                        Référence & Date
                                    </th>
                                    <th className="px-5 py-3.5 font-bold uppercase tracking-wider">
                                        Mode & Opération
                                    </th>
                                    <th className="px-5 py-3.5 font-bold uppercase tracking-wider">
                                        Ventes couvertes
                                    </th>
                                    <th className="px-5 py-3.5 font-bold uppercase tracking-wider text-right">
                                        Montant déclaré
                                    </th>
                                    <th className="px-5 py-3.5 font-bold uppercase tracking-wider text-right">
                                        Validé KORA
                                    </th>
                                    <th className="px-5 py-3.5 font-bold uppercase tracking-wider">
                                        Statut
                                    </th>
                                    <th className="px-5 py-3.5 font-bold uppercase tracking-wider text-right">
                                        Action
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {filteredSettlements.map((s) => (
                                    <tr
                                        key={s.uuid}
                                        onClick={() => setSelectedSettlement(s)}
                                        className="cursor-pointer transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/30"
                                    >
                                        <td className="px-5 py-4">
                                            <span className="font-mono font-bold text-slate-900 dark:text-white block">
                                                {s.reference}
                                            </span>
                                            <span className="text-[11px] text-slate-500">
                                                {s.created_at} par {s.created_by_name}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4">
                                            <span className="font-bold text-slate-800 dark:text-slate-200 block">
                                                {s.payment_method === 'BANK_DEPOSIT' && 'Dépôt bancaire'}
                                                {s.payment_method === 'BANK_TRANSFER' && 'Virement'}
                                                {s.payment_method === 'MOBILE_MONEY' && 'Mobile Money'}
                                            </span>
                                            <span className="font-mono text-[11px] text-slate-500">
                                                {s.payment_reference}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4">
                                            <span className="font-semibold text-slate-700 dark:text-slate-300">
                                                {s.sales_count} billets
                                            </span>
                                            <span className="text-[11px] text-slate-400 block">
                                                Du {s.period_start} au {s.period_end}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4 text-right font-black text-slate-900 dark:text-white">
                                            {s.amount_declared.toLocaleString()} FCFA
                                        </td>
                                        <td className="px-5 py-4 text-right">
                                            {s.amount_validated ? (
                                                <span className="font-black text-emerald-600 dark:text-emerald-400">
                                                    {s.amount_validated.toLocaleString()} FCFA
                                                </span>
                                            ) : (
                                                <span className="text-slate-400 italic">En attente</span>
                                            )}
                                        </td>
                                        <td className="px-5 py-4">{getStatusBadge(s.status)}</td>
                                        <td className="px-5 py-4 text-right">
                                            <button
                                                type="button"
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    setSelectedSettlement(s);
                                                }}
                                                className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold text-slate-700 shadow-xs hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                            >
                                                <Eye className="h-3.5 w-3.5 text-slate-500" />
                                                Dossier
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Mobile Card Stack */}
                    <div className="space-y-3 lg:hidden">
                        {filteredSettlements.map((s) => (
                            <div
                                key={s.uuid}
                                onClick={() => setSelectedSettlement(s)}
                                className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 space-y-3 cursor-pointer"
                            >
                                <div className="flex items-center justify-between">
                                    <div>
                                        <span className="font-mono text-xs font-black text-slate-900 dark:text-white block">
                                            {s.reference}
                                        </span>
                                        <span className="text-[11px] text-slate-400">
                                            {s.created_at}
                                        </span>
                                    </div>
                                    {getStatusBadge(s.status)}
                                </div>

                                <div className="grid grid-cols-2 gap-2 text-xs pt-1 border-t border-slate-100 dark:border-slate-800">
                                    <div>
                                        <span className="text-[11px] text-slate-400 block">
                                            Montant déclaré
                                        </span>
                                        <span className="font-black text-slate-900 dark:text-white">
                                            {s.amount_declared.toLocaleString()} FCFA
                                        </span>
                                    </div>
                                    <div>
                                        <span className="text-[11px] text-slate-400 block">
                                            Mode / Réf
                                        </span>
                                        <span className="font-mono text-slate-700 dark:text-slate-300 truncate block">
                                            {s.payment_reference}
                                        </span>
                                    </div>
                                </div>

                                {s.status === 'REJECTED' && s.rejection_reason && (
                                    <div className="rounded-lg bg-rose-50 p-2.5 text-[11px] text-rose-800 dark:bg-rose-950/40 dark:text-rose-300">
                                        <strong>Rejet :</strong> {s.rejection_reason}
                                    </div>
                                )}

                                <div className="flex justify-end pt-1">
                                    <button
                                        type="button"
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            setSelectedSettlement(s);
                                        }}
                                        className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-bold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                    >
                                        <Eye className="h-3.5 w-3.5" />
                                        Consulter le dossier
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}
