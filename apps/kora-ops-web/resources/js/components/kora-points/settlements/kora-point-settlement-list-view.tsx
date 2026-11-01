import {
    AlertTriangle,
    Building2,
    CheckCircle2,
    Clock,
    Coins,
    Eye,
    HelpCircle,
    Info,
    Plus,
    ReceiptText,
    Search,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { KoraTooltip } from '@/components/kora-tooltip';
import { cn } from '@/lib/utils';
import type {
    FinancialHealth,
    KoraPaymentDetails,
    KoraPointProfile,
    PointSettlementMethod,
    PointSettlementRecord,
    PointSettlementStatus,
} from '@/types/kora-points';
import { KoraPointSettlementDetailDrawer } from './kora-point-settlement-detail-drawer';
import { KoraPointSettlementDialog } from './kora-point-settlement-dialog';

type Props = {
    point: KoraPointProfile;
    health: FinancialHealth;
    settlements: PointSettlementRecord[];
    koraPaymentDetails?: KoraPaymentDetails;
    onDeclareSettlement: (data: {
        saleUuids: string[];
        paymentMethod: PointSettlementMethod;
        paymentReference: string;
        proofFile: File;
        bankName?: string;
        notes?: string;
    }) => void;
};

const kpiVariants = {
    navy: 'cash-ledger-card-kpi-navy',
    blue: 'cash-ledger-card-kpi-digital',
    gold: 'cash-ledger-card-kpi-gold',
    emerald: 'cash-ledger-card-kpi-emerald',
};

export function KoraPointSettlementListView({
    point,
    health,
    settlements,
    koraPaymentDetails,
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

    const kpis = [
        {
            id: 'exposure',
            label: 'Total restant dû',
            hint: 'Recettes nettes à reverser',
            value: `${health.current_exposure.toLocaleString('fr-FR')} FCFA`,
            trend: 'À reverser à KORA',
            tone: 'navy' as const,
            icon: ReceiptText,
            formula:
                'Montant net cumulé des billets émis non encore apurés par un versement validé.',
        },
        {
            id: 'available',
            label: 'Capacité disponible',
            hint: `Plafond : ${health.credit_limit.toLocaleString('fr-FR')} FCFA`,
            value: `${health.available_balance.toLocaleString('fr-FR')} FCFA`,
            trend: `${health.usage_percentage} % du plafond utilisé`,
            tone: 'blue' as const,
            icon: Coins,
            formula:
                'Capacité résiduelle d’émission de billets = Plafond autorisé − Encours actuel.',
        },
        {
            id: 'pending',
            label: 'En vérification Trésorerie',
            hint: 'Bordereaux déclarés',
            value: `${totals.declaredPending.toLocaleString('fr-FR')} FCFA`,
            trend: 'En attente de pointage',
            tone: 'gold' as const,
            icon: Clock,
            formula:
                'Montant total des versements déclarés par votre guichet en cours de contrôle par la Trésorerie KORA.',
        },
        {
            id: 'validated',
            label: 'Total apuré & validé',
            hint: 'Fonds reçus et rapprochés',
            value: `${totals.validatedTotal.toLocaleString('fr-FR')} FCFA`,
            trend: 'Rapprochements confirmés',
            tone: 'emerald' as const,
            icon: CheckCircle2,
            formula:
                'Cumul historique des reversements validés et apurés par la Trésorerie KORA.',
        },
    ];

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
                koraPaymentDetails={koraPaymentDetails}
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
                onResubmit={() => {
                    setSelectedSettlement(null);
                    setIsDeclarationModalOpen(true);
                }}
            />

            {/* Header */}
            <div className="kora-page-head cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                        KORA POINT / Espace Agent / Versements
                    </div>
                    <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                        Versements partenaires
                    </h1>
                    <p className="mt-1.5 text-[0.8125rem] leading-6 text-muted-foreground">
                        Déclarations et suivi des apurements de recettes de billetterie vers KORA Exploitation.
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2.5">
                    {isAutonomous ? (
                        <button
                            type="button"
                            onClick={() => setIsDeclarationModalOpen(true)}
                            className="btn btn-primary"
                        >
                            <Plus className="size-4" />
                            <span>Déclarer un versement</span>
                        </button>
                    ) : (
                        <div className="btn btn-ghost opacity-80 cursor-default">
                            <Building2 className="size-4 text-gold" />
                            <span>Versements centralisés ({point.organization?.name})</span>
                        </div>
                    )}
                </div>
            </div>

            {/* Organization notice if attached */}
            {!isAutonomous && (
                <div className="rounded-2xl border border-blue-500/20 bg-blue-500/10 p-4 text-xs text-foreground flex items-start gap-3">
                    <Info className="size-5 shrink-0 text-digital mt-0.5" />
                    <div className="space-y-1">
                        <p className="font-bold">
                            Bureau rattaché à l’organisation {point.organization?.name}
                        </p>
                        <p className="text-muted-foreground leading-relaxed">
                            En tant qu'agent de bureau, cet écran vous permet de consulter l'historique des reversements et les montants apurés. La déclaration et le règlement financier des reversements groupés sont assurés par la direction financière / le trésorier de votre organisation.
                        </p>
                    </div>
                </div>
            )}

            {/* Financial Summary 4 Colored KPIs (Dashboard System Style) */}
            <div className="grid grid-cols-1 gap-3.5 sm:grid-cols-2 xl:grid-cols-4 cash-reveal cash-reveal-delay-1">
                {kpis.map((kpi) => {
                    const Icon = kpi.icon;

                    return (
                        <article
                            key={kpi.id}
                            className={cn(
                                'cash-ledger-card transition-[transform,box-shadow] duration-300 hover:-translate-y-1 hover:shadow-lg',
                                kpiVariants[kpi.tone],
                            )}
                        >
                            <div className="cash-ledger-head">
                                <div className="cash-ledger-identity">
                                    <span className="cash-ledger-brand">
                                        <Icon className="size-4" strokeWidth={1.8} />
                                    </span>
                                    <div>
                                        <div className="flex items-center gap-1.5">
                                            <span className="cash-ledger-title">{kpi.label}</span>
                                            <KoraTooltip content={kpi.formula} side="top">
                                                <button
                                                    type="button"
                                                    aria-label={`Explication du calcul : ${kpi.label}`}
                                                    className="opacity-70 transition-opacity hover:opacity-100 focus:outline-none"
                                                >
                                                    <HelpCircle className="size-3.5" />
                                                </button>
                                            </KoraTooltip>
                                        </div>
                                        <small>{kpi.hint}</small>
                                    </div>
                                </div>
                            </div>

                            <div className="cash-ledger-balance mb-0">
                                <span>{kpi.trend}</span>
                                <strong>{kpi.value}</strong>
                            </div>
                        </article>
                    );
                })}
            </div>

            {/* Filter Bar */}
            <div className="cash-filterbar cash-reveal cash-reveal-delay-2 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                {/* Search */}
                <div className="relative flex-1">
                    <Search className="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                    <input
                        type="text"
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        placeholder="Rechercher par référence, n° d’opération, observation..."
                        className="w-full rounded-xl border border-border bg-muted/40 py-2 pl-9 pr-4 text-xs font-medium text-foreground placeholder:text-muted-foreground focus:bg-card focus:border-gold focus:outline-none"
                    />
                </div>

                {/* Status Pills */}
                <div className="flex flex-wrap gap-1.5">
                    <button
                        type="button"
                        onClick={() => setStatusFilter('ALL')}
                        className={cn(
                            'btn btn-sm',
                            statusFilter === 'ALL' ? 'btn-navy' : 'btn-ghost',
                        )}
                    >
                        Tous ({settlements.length})
                    </button>
                    <button
                        type="button"
                        onClick={() => setStatusFilter('DECLARED')}
                        className={cn(
                            'btn btn-sm',
                            statusFilter === 'DECLARED' ? 'btn-navy' : 'btn-ghost',
                        )}
                    >
                        En vérification
                    </button>
                    <button
                        type="button"
                        onClick={() => setStatusFilter('VALIDATED')}
                        className={cn(
                            'btn btn-sm',
                            statusFilter === 'VALIDATED' ? 'btn-navy' : 'btn-ghost',
                        )}
                    >
                        Validés
                    </button>
                    <button
                        type="button"
                        onClick={() => setStatusFilter('REJECTED')}
                        className={cn(
                            'btn btn-sm',
                            statusFilter === 'REJECTED' ? 'btn-danger' : 'btn-ghost',
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
