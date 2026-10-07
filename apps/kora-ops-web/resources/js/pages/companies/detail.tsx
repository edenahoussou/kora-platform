import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowDownLeft,
    ArrowLeft,
    ArrowUpRight,
    Building2,
    CheckCircle2,
    CreditCard,
    Download,
    Edit3,
    FileText,
    History,
    Mail,
    MapPin,
    MoveRight,
    Phone,
    Power,
    Receipt,
    RotateCcw,
    Search,
    SlidersHorizontal,
    Ticket,
    User,
    Users,
    Wallet,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import { CompanyAccountFormModal } from '@/components/company-accounts/company-account-form-modal';
import type {
    CompanyAccountRecord,
    CompanyUserCandidate,
} from '@/components/company-accounts/company-account-types';
import { formatFcfa } from '@/components/company-accounts/company-account-types';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { PanelAlert } from '@/components/panel-alert';
import { cn } from '@/lib/utils';
import companies from '@/routes/companies';

type CreditSummary = {
    monthlyLimit: number;
    consumed: number;
    reserved: number;
    available: number;
    percentage: number;
};

type CreditHold = {
    uuid: string;
    reference: string;
    amount: number;
    status: string;
    statusLabel: string;
    periodKey: string;
    expiresAt: string;
    createdAt: string;
};

type CreditTransaction = {
    uuid: string;
    type: 'debit' | 'refund';
    amount: number;
    reference: string;
    periodKey: string;
    createdAt: string;
};

type CreditData = {
    monthlyLimit: number;
    consumed: number;
    reserved: number;
    available: number;
    percentage: number;
    holds: CreditHold[];
    transactions: CreditTransaction[];
};

type ReservationItem = {
    uuid: string;
    reference: string;
    createdAt: string;
    status: string;
    statusLabel: string;
    purchaseType: string;
    passengerCount: number;
    passengers: string[];
    totalAmount: number;
    currency: string;
    itinerary: {
        label: string;
        travelDate: string | null;
        roundTrip: boolean;
    };
};

type DeplacementsData = {
    reservations: ReservationItem[];
    filters: {
        q: string;
        status: string;
        month: string;
    };
    statuses: Record<string, string>;
    months: Record<string, string>;
};

type BillingStatementItem = {
    uuid: string;
    reference: string;
    periodKey: string;
    periodLabel: string;
    status: string;
    statusLabel: string;
    currency: string;
    debitTotal: number;
    refundTotal: number;
    netTotal: number;
    reservationsCount: number;
    invoicesCount: number;
    generatedAt: string | null;
    emailedAt: string | null;
};

type FiscalInvoiceItem = {
    uuid: string;
    reference: string;
    koraReference: string;
    reservationReference: string;
    totalWithTax: number;
    status: string;
    statusLabel: string;
    issuedAt: string | null;
};

type FacturesData = {
    statements: BillingStatementItem[];
    fiscalInvoices: FiscalInvoiceItem[];
};

type ReferentProfile = {
    name: string;
    email: string;
    phone: string;
    status: string;
    role: string;
    invitedAt: string;
};

type CompanyTravelerItem = {
    uuid: string;
    firstName: string;
    lastName: string;
    fullName: string;
    phone: string | null;
    documentType: string;
    documentNumber: string;
    status: string;
    statusLabel: string;
    createdAt: string;
};

type ReferentData = {
    profile: ReferentProfile;
    travelers: CompanyTravelerItem[];
};

type TimelineEvent = {
    uuid: string;
    event: string;
    label: string;
    actor: string;
    actorRole: string;
    date: string;
    relativeDate: string;
};

type HistoriqueData = {
    events: TimelineEvent[];
};

type Props = {
    account: CompanyAccountRecord & {
        typeLabel: string;
        statusLabel: string;
        travelersCount: number;
        reservationsCountThisMonth: number;
    };
    activeTab:
        'credit' | 'deplacements' | 'factures' | 'referent' | 'historique';
    credit: CreditData;
    deplacements: DeplacementsData;
    factures: FacturesData;
    referent: ReferentData;
    historique: HistoriqueData;
    aside: {
        creditSummary: CreditSummary;
        recentEvents: TimelineEvent[];
    };
    types: Record<string, string>;
    statuses: Record<string, string>;
    userCandidates: CompanyUserCandidate[];
    permissions: {
        canCreate: boolean;
        canUpdate: boolean;
        canManageMembers: boolean;
        canChangeStatus: boolean;
        canViewCredit: boolean;
    };
};

type TabKey =
    'credit' | 'deplacements' | 'factures' | 'referent' | 'historique';

const tabDefinitions: { key: TabKey; label: string; icon: LucideIcon }[] = [
    { key: 'credit', label: 'Crédit', icon: Wallet },
    { key: 'deplacements', label: 'Déplacements', icon: Ticket },
    { key: 'factures', label: 'Factures', icon: Receipt },
    { key: 'referent', label: 'Référent & Voyageurs', icon: Users },
    { key: 'historique', label: 'Historique', icon: History },
];

function formatDate(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

function formatDateTime(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function CompanyAccountDetail({
    account,
    activeTab,
    credit,
    deplacements,
    factures,
    referent,
    historique,
    aside,
    permissions,
}: Props) {
    const { url: currentUrl } = usePage();
    const urlParams = useMemo(() => {
        const queryString = currentUrl.includes('?')
            ? currentUrl.slice(currentUrl.indexOf('?') + 1)
            : '';

        return new URLSearchParams(queryString);
    }, [currentUrl]);

    const [currentTab, setCurrentTab] = useState<TabKey>(activeTab);
    const [editModalOpen, setEditModalOpen] = useState(false);
    const [statusProcessing, setStatusProcessing] = useState(false);

    // Deplacements filters state
    const [query, setQuery] = useState(
        urlParams.get('q') ?? deplacements.filters.q ?? '',
    );
    const [statusFilter, setStatusFilter] = useState(
        urlParams.get('status') ?? deplacements.filters.status ?? 'all',
    );
    const [monthFilter, setMonthFilter] = useState(
        urlParams.get('month') ?? deplacements.filters.month ?? 'all',
    );

    const switchTab = (tab: TabKey) => {
        setCurrentTab(tab);
        const params = new URLSearchParams(window.location.search);
        params.set('tab', tab);
        router.get(
            `/companies/${account.uuid}?${params.toString()}`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    };

    const applyDeplacementFilters = () => {
        const params: Record<string, string> = { tab: 'deplacements' };

        if (query.trim() !== '') {
            params.q = query.trim();
        }

        if (statusFilter !== 'all') {
            params.status = statusFilter;
        }

        if (monthFilter !== 'all') {
            params.month = monthFilter;
        }

        router.get(`/companies/${account.uuid}`, params, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const resetDeplacementFilters = () => {
        setQuery('');
        setStatusFilter('all');
        setMonthFilter('all');
        router.get(
            `/companies/${account.uuid}`,
            { tab: 'deplacements' },
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    };

    const toggleStatus = () => {
        if (!permissions.canChangeStatus) {
            return;
        }

        const nextStatus = account.status === 'active' ? 'inactive' : 'active';
        const label = nextStatus === 'active' ? 'réactiver' : 'désactiver';

        if (
            !window.confirm(
                `Voulez-vous vraiment ${label} cette organisation ?`,
            )
        ) {
            return;
        }

        setStatusProcessing(true);
        router.patch(
            companies.status.url({ companyAccount: account.uuid }),
            { status: nextStatus },
            {
                preserveScroll: true,
                onFinish: () => setStatusProcessing(false),
            },
        );
    };

    const exportUrl = companies.reservations.export.url(
        { companyAccount: account.uuid },
        {
            query: {
                q: query.trim() || undefined,
                status: statusFilter !== 'all' ? statusFilter : undefined,
                month: monthFilter !== 'all' ? monthFilter : undefined,
            },
        },
    );

    const gaugeTone =
        aside.creditSummary.percentage >= 90
            ? 'destructive'
            : aside.creditSummary.percentage >= 70
              ? 'gold'
              : 'navy';

    return (
        <>
            <Head title={`${account.name} — Organisation`} />

            <div className="cash-page space-y-6">
                {/* Page Head & Breadcrumb */}
                <div className="cash-reveal flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                            <Link
                                href={companies.index.url()}
                                className="transition-colors hover:text-foreground"
                            >
                                KORA OPS / Réseau commercial / Organisations
                            </Link>{' '}
                            / {account.name}
                        </div>
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-[clamp(1.8rem,3vw,2.5rem)] font-extrabold tracking-[-0.055em] text-foreground">
                                {account.name}
                            </h1>
                            <span className="rounded-full bg-gold/15 px-2.5 py-1 text-xs font-black text-foreground">
                                {account.reference}
                            </span>
                            <KoraStatusBadge
                                label={
                                    account.status === 'active'
                                        ? 'Active'
                                        : 'Inactive'
                                }
                                tone={
                                    account.status === 'active'
                                        ? 'navy'
                                        : 'muted'
                                }
                            />
                        </div>
                        <p className="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-[0.8125rem] text-muted-foreground">
                            <span className="inline-flex items-center gap-1.5">
                                <Building2 className="size-3.5" />
                                {account.typeLabel}
                            </span>
                            {account.phone && (
                                <span className="inline-flex items-center gap-1.5">
                                    <Phone className="size-3.5" />
                                    {account.phone}
                                </span>
                            )}
                            {account.email && (
                                <span className="inline-flex items-center gap-1.5">
                                    <Mail className="size-3.5" />
                                    {account.email}
                                </span>
                            )}
                            {account.city && (
                                <span className="inline-flex items-center gap-1.5">
                                    <MapPin className="size-3.5" />
                                    {account.city}, {account.country ?? 'BJ'}
                                </span>
                            )}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2.5">
                        <Link
                            href={companies.index.url()}
                            className="btn btn-ghost"
                        >
                            <ArrowLeft className="size-4" />
                            Retour
                        </Link>

                        {permissions.canUpdate && (
                            <button
                                type="button"
                                className="btn btn-ghost"
                                onClick={() => setEditModalOpen(true)}
                            >
                                <Edit3 className="size-4" />
                                Modifier
                            </button>
                        )}

                        {permissions.canChangeStatus && (
                            <button
                                type="button"
                                className={cn(
                                    'btn',
                                    account.status === 'active'
                                        ? 'btn-ghost text-destructive'
                                        : 'btn-navy',
                                )}
                                onClick={toggleStatus}
                                disabled={statusProcessing}
                            >
                                <Power className="size-4" />
                                {account.status === 'active'
                                    ? 'Désactiver'
                                    : 'Activer'}
                            </button>
                        )}
                    </div>
                </div>

                {/* KPI Ribbon (style Caisse) */}
                <section className="cash-ledger-grid cash-reveal cash-reveal-delay-1">
                    <article className="cash-ledger-card">
                        <div className="cash-ledger-head">
                            <div className="cash-ledger-identity">
                                <span className="cash-ledger-brand bg-primary/10 text-primary">
                                    <Wallet
                                        className="size-4"
                                        strokeWidth={1.8}
                                    />
                                </span>
                                <div>
                                    <span className="cash-ledger-title">
                                        Plafond mensuel
                                    </span>
                                    <small>Autorisé au contrat</small>
                                </div>
                            </div>
                        </div>
                        <div className="cash-ledger-balance">
                            <span>Limite</span>
                            <strong>
                                {formatFcfa(account.monthlyCreditLimit)}
                            </strong>
                        </div>
                    </article>

                    <article className="cash-ledger-card">
                        <div className="cash-ledger-head">
                            <div className="cash-ledger-identity">
                                <span className="cash-ledger-brand bg-gold/15 text-gold">
                                    <CreditCard
                                        className="size-4"
                                        strokeWidth={1.8}
                                    />
                                </span>
                                <div>
                                    <span className="cash-ledger-title">
                                        Consommé
                                    </span>
                                    <small>Débits nets du mois</small>
                                </div>
                            </div>
                        </div>
                        <div className="cash-ledger-balance">
                            <span>Ce mois</span>
                            <strong className="text-foreground">
                                {formatFcfa(account.consumedCredit)}
                            </strong>
                        </div>
                    </article>

                    <article className="cash-ledger-card">
                        <div className="cash-ledger-head">
                            <div className="cash-ledger-identity">
                                <span className="cash-ledger-brand bg-emerald-500/10 text-emerald-600">
                                    <CheckCircle2
                                        className="size-4"
                                        strokeWidth={1.8}
                                    />
                                </span>
                                <div>
                                    <span className="cash-ledger-title">
                                        Disponible
                                    </span>
                                    <small>Restant à consommer</small>
                                </div>
                            </div>
                        </div>
                        <div className="cash-ledger-balance">
                            <span>Solde</span>
                            <strong className="text-emerald-600 dark:text-emerald-400">
                                {formatFcfa(account.availableCredit)}
                            </strong>
                        </div>
                    </article>

                    <article className="cash-ledger-card">
                        <div className="cash-ledger-head">
                            <div className="cash-ledger-identity">
                                <span className="cash-ledger-brand bg-blue-500/10 text-blue-600">
                                    <Ticket
                                        className="size-4"
                                        strokeWidth={1.8}
                                    />
                                </span>
                                <div>
                                    <span className="cash-ledger-title">
                                        Déplacements
                                    </span>
                                    <small>Ce mois-ci</small>
                                </div>
                            </div>
                        </div>
                        <div className="cash-ledger-balance">
                            <span>Achats</span>
                            <strong>
                                {account.reservationsCountThisMonth}
                            </strong>
                        </div>
                    </article>
                </section>

                {/* Tab Navigation */}
                <div className="cash-reveal cash-reveal-delay-2 flex gap-1 overflow-x-auto border-b border-border/60 pb-px">
                    {tabDefinitions.map(({ key, label, icon: Icon }) => (
                        <button
                            key={key}
                            type="button"
                            onClick={() => switchTab(key)}
                            className={cn(
                                'inline-flex items-center gap-2 border-b-2 px-4 py-3 text-xs font-black tracking-[0.035em] whitespace-nowrap transition-colors',
                                currentTab === key
                                    ? 'border-[var(--kora)] font-extrabold text-foreground'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <Icon
                                className={cn(
                                    'size-4',
                                    currentTab === key
                                        ? 'text-[var(--kora)]'
                                        : '',
                                )}
                                strokeWidth={2}
                            />
                            {label}
                        </button>
                    ))}
                </div>

                {/* 2-Column Grid: Main Tab Panel (1.45fr) + Permanent Aside (0.55fr) */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-[1.45fr_0.55fr]">
                    {/* Main Tab Panel */}
                    <main className="space-y-6">
                        {/* TAB 1: CREDIT */}
                        {currentTab === 'credit' && (
                            <div className="space-y-6">
                                {/* Holds en cours */}
                                <section className="cash-table-card rounded-2xl border border-border/50 bg-card p-5 shadow-sm">
                                    <div className="mb-4 flex items-center justify-between">
                                        <div>
                                            <h3 className="text-sm font-black text-foreground">
                                                Réservations & options posées
                                                (Holds)
                                            </h3>
                                            <p className="text-xs text-muted-foreground">
                                                Montants temporairement bloqués
                                                avant émission définitive
                                            </p>
                                        </div>
                                        <span className="rounded-full bg-muted px-2 py-0.5 text-[0.6875rem] font-bold text-muted-foreground">
                                            {credit.holds.length} actif
                                            {credit.holds.length > 1 ? 's' : ''}
                                        </span>
                                    </div>

                                    {credit.holds.length === 0 ? (
                                        <div className="rounded-xl border border-dashed border-border py-8 text-center text-xs text-muted-foreground">
                                            Aucun hold ou blocage de crédit en
                                            cours pour ce compte.
                                        </div>
                                    ) : (
                                        <div className="overflow-x-auto">
                                            <table className="w-full text-left text-xs">
                                                <thead className="bg-muted/40 text-[0.625rem] font-black tracking-wider text-muted-foreground uppercase">
                                                    <tr>
                                                        <th className="px-3 py-2.5">
                                                            Référence
                                                        </th>
                                                        <th className="px-3 py-2.5">
                                                            Montant
                                                        </th>
                                                        <th className="px-3 py-2.5">
                                                            Statut
                                                        </th>
                                                        <th className="px-3 py-2.5">
                                                            Période
                                                        </th>
                                                        <th className="px-3 py-2.5 text-right">
                                                            Expiration
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-border/40">
                                                    {credit.holds.map(
                                                        (hold) => (
                                                            <tr
                                                                key={hold.uuid}
                                                                className="hover:bg-muted/30"
                                                            >
                                                                <td className="px-3 py-2.5 font-bold text-foreground">
                                                                    {
                                                                        hold.reference
                                                                    }
                                                                </td>
                                                                <td className="px-3 py-2.5 font-extrabold text-foreground">
                                                                    {formatFcfa(
                                                                        hold.amount,
                                                                    )}
                                                                </td>
                                                                <td className="px-3 py-2.5">
                                                                    <span className="inline-flex rounded-full bg-gold/15 px-2 py-0.5 text-[0.6875rem] font-bold text-foreground">
                                                                        {
                                                                            hold.statusLabel
                                                                        }
                                                                    </span>
                                                                </td>
                                                                <td className="px-3 py-2.5 text-muted-foreground">
                                                                    {
                                                                        hold.periodKey
                                                                    }
                                                                </td>
                                                                <td className="px-3 py-2.5 text-right text-muted-foreground">
                                                                    {formatDateTime(
                                                                        hold.expiresAt,
                                                                    )}
                                                                </td>
                                                            </tr>
                                                        ),
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </section>

                                {/* Journal des mouvements de crédit */}
                                <section className="cash-table-card rounded-2xl border border-border/50 bg-card p-5 shadow-sm">
                                    <div className="mb-4 flex items-center justify-between">
                                        <div>
                                            <h3 className="text-sm font-black text-foreground">
                                                Mouvements de crédit récents
                                            </h3>
                                            <p className="text-xs text-muted-foreground">
                                                Historique chronologique des
                                                débits d'achats et
                                                remboursements
                                            </p>
                                        </div>
                                    </div>

                                    {credit.transactions.length === 0 ? (
                                        <div className="rounded-xl border border-dashed border-border py-8 text-center text-xs text-muted-foreground">
                                            Aucun mouvement de crédit enregistré
                                            pour cette organisation.
                                        </div>
                                    ) : (
                                        <div className="overflow-x-auto">
                                            <table className="w-full text-left text-xs">
                                                <thead className="bg-muted/40 text-[0.625rem] font-black tracking-wider text-muted-foreground uppercase">
                                                    <tr>
                                                        <th className="px-3 py-2.5">
                                                            Date
                                                        </th>
                                                        <th className="px-3 py-2.5">
                                                            Type
                                                        </th>
                                                        <th className="px-3 py-2.5">
                                                            Référence
                                                        </th>
                                                        <th className="px-3 py-2.5">
                                                            Période
                                                        </th>
                                                        <th className="px-3 py-2.5 text-right">
                                                            Montant
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-border/40">
                                                    {credit.transactions.map(
                                                        (tx) => (
                                                            <tr
                                                                key={tx.uuid}
                                                                className="hover:bg-muted/30"
                                                            >
                                                                <td className="px-3 py-2.5 text-muted-foreground">
                                                                    {formatDateTime(
                                                                        tx.createdAt,
                                                                    )}
                                                                </td>
                                                                <td className="px-3 py-2.5">
                                                                    {tx.type ===
                                                                    'debit' ? (
                                                                        <span className="inline-flex items-center gap-1 rounded-md bg-destructive/10 px-2 py-0.5 text-[0.6875rem] font-bold text-destructive">
                                                                            <ArrowDownLeft className="size-3" />
                                                                            Débit
                                                                        </span>
                                                                    ) : (
                                                                        <span className="inline-flex items-center gap-1 rounded-md bg-emerald-500/10 px-2 py-0.5 text-[0.6875rem] font-bold text-emerald-600">
                                                                            <ArrowUpRight className="size-3" />
                                                                            Remboursement
                                                                        </span>
                                                                    )}
                                                                </td>
                                                                <td className="px-3 py-2.5 font-bold text-foreground">
                                                                    {
                                                                        tx.reference
                                                                    }
                                                                </td>
                                                                <td className="px-3 py-2.5 text-muted-foreground">
                                                                    {
                                                                        tx.periodKey
                                                                    }
                                                                </td>
                                                                <td className="px-3 py-2.5 text-right font-extrabold text-foreground">
                                                                    {tx.type ===
                                                                    'debit'
                                                                        ? '-'
                                                                        : '+'}
                                                                    {formatFcfa(
                                                                        tx.amount,
                                                                    )}
                                                                </td>
                                                            </tr>
                                                        ),
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </section>
                            </div>
                        )}

                        {/* TAB 2: DEPLACEMENTS */}
                        {currentTab === 'deplacements' && (
                            <div className="space-y-4">
                                {/* Filter bar */}
                                <div className="cash-filterbar flex flex-wrap items-center gap-2.5 rounded-2xl border border-border/50 bg-card p-3 shadow-sm">
                                    <div className="relative min-w-[200px] flex-1">
                                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                        <input
                                            type="search"
                                            value={query}
                                            onChange={(e) =>
                                                setQuery(e.target.value)
                                            }
                                            onKeyDown={(e) =>
                                                e.key === 'Enter' &&
                                                applyDeplacementFilters()
                                            }
                                            placeholder="Rechercher par référence, voyageur…"
                                            className="h-10 w-full rounded-xl bg-muted/50 pr-3 pl-9 text-xs outline-none focus:bg-card focus:ring-2 focus:ring-gold/30"
                                        />
                                    </div>

                                    <select
                                        value={statusFilter}
                                        onChange={(e) =>
                                            setStatusFilter(e.target.value)
                                        }
                                        className="h-10 rounded-xl bg-muted/50 px-3 text-xs font-bold text-foreground outline-none"
                                    >
                                        <option value="all">
                                            Tous les statuts
                                        </option>
                                        {Object.entries(
                                            deplacements.statuses,
                                        ).map(([val, label]) => (
                                            <option key={val} value={val}>
                                                {label}
                                            </option>
                                        ))}
                                    </select>

                                    <select
                                        value={monthFilter}
                                        onChange={(e) =>
                                            setMonthFilter(e.target.value)
                                        }
                                        className="h-10 rounded-xl bg-muted/50 px-3 text-xs font-bold text-foreground outline-none"
                                    >
                                        {Object.entries(
                                            deplacements.months,
                                        ).map(([val, label]) => (
                                            <option key={val} value={val}>
                                                {label}
                                            </option>
                                        ))}
                                    </select>

                                    <button
                                        type="button"
                                        onClick={applyDeplacementFilters}
                                        className="btn btn-navy h-10 px-3 text-xs"
                                    >
                                        <SlidersHorizontal className="size-3.5" />
                                        Filtrer
                                    </button>

                                    <button
                                        type="button"
                                        onClick={resetDeplacementFilters}
                                        className="btn btn-ghost h-10 px-2.5 text-xs text-muted-foreground"
                                        title="Réinitialiser les filtres"
                                    >
                                        <RotateCcw className="size-3.5" />
                                    </button>

                                    <a
                                        href={exportUrl}
                                        download
                                        className="btn btn-ghost ml-auto h-10 px-3 text-xs"
                                    >
                                        <Download className="size-3.5" />
                                        Exporter CSV
                                    </a>
                                </div>

                                {/* Table */}
                                <section className="cash-table-card rounded-2xl border border-border/50 bg-card shadow-sm">
                                    {deplacements.reservations.length === 0 ? (
                                        <div className="py-16 text-center">
                                            <p className="text-sm font-bold text-foreground">
                                                Aucun déplacement trouvé
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Ajustez vos filtres de recherche
                                                ou sélectionnez une autre
                                                période.
                                            </p>
                                        </div>
                                    ) : (
                                        <div className="overflow-x-auto">
                                            <table className="w-full text-left text-xs">
                                                <thead className="bg-muted/40 text-[0.625rem] font-black tracking-wider text-muted-foreground uppercase">
                                                    <tr>
                                                        <th className="px-4 py-3">
                                                            Référence
                                                        </th>
                                                        <th className="px-4 py-3">
                                                            Itinéraire
                                                        </th>
                                                        <th className="px-4 py-3">
                                                            Départ
                                                        </th>
                                                        <th className="px-4 py-3">
                                                            Voyageurs
                                                        </th>
                                                        <th className="px-4 py-3 text-right">
                                                            Montant
                                                        </th>
                                                        <th className="px-4 py-3">
                                                            Statut
                                                        </th>
                                                        <th className="px-4 py-3 text-right">
                                                            Détail
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-border/40">
                                                    {deplacements.reservations.map(
                                                        (res) => (
                                                            <tr
                                                                key={res.uuid}
                                                                className="group cursor-pointer hover:bg-muted/40"
                                                                onClick={() =>
                                                                    router.get(
                                                                        companies.reservations.show.url(
                                                                            {
                                                                                companyAccount:
                                                                                    account.uuid,
                                                                                reservation:
                                                                                    res.uuid,
                                                                            },
                                                                        ),
                                                                    )
                                                                }
                                                            >
                                                                <td className="px-4 py-3.5">
                                                                    <span className="font-extrabold text-foreground group-hover:text-[var(--kora)]">
                                                                        {
                                                                            res.reference
                                                                        }
                                                                    </span>
                                                                    <p className="text-[0.6875rem] text-muted-foreground">
                                                                        {formatDate(
                                                                            res.createdAt,
                                                                        )}
                                                                    </p>
                                                                </td>
                                                                <td className="px-4 py-3.5">
                                                                    <span className="font-bold text-foreground">
                                                                        {
                                                                            res
                                                                                .itinerary
                                                                                .label
                                                                        }
                                                                    </span>
                                                                    {res
                                                                        .itinerary
                                                                        .roundTrip && (
                                                                        <span className="ml-2 rounded-full bg-gold/15 px-1.5 py-0.5 text-[0.625rem] font-black text-foreground">
                                                                            A/R
                                                                        </span>
                                                                    )}
                                                                </td>
                                                                <td className="px-4 py-3.5 text-muted-foreground">
                                                                    {res
                                                                        .itinerary
                                                                        .travelDate ??
                                                                        '—'}
                                                                </td>
                                                                <td className="px-4 py-3.5">
                                                                    <span className="font-bold text-foreground">
                                                                        {
                                                                            res.passengerCount
                                                                        }{' '}
                                                                        pers.
                                                                    </span>
                                                                    <p className="max-w-[180px] truncate text-[0.6875rem] text-muted-foreground">
                                                                        {res.passengers.join(
                                                                            ', ',
                                                                        )}
                                                                    </p>
                                                                </td>
                                                                <td className="px-4 py-3.5 text-right font-extrabold text-foreground">
                                                                    {formatFcfa(
                                                                        res.totalAmount,
                                                                    )}
                                                                </td>
                                                                <td className="px-4 py-3.5">
                                                                    <KoraStatusBadge
                                                                        label={
                                                                            res.statusLabel
                                                                        }
                                                                        tone={
                                                                            res.status ===
                                                                                'ticketed' ||
                                                                            res.status ===
                                                                                'boarded'
                                                                                ? 'blue'
                                                                                : res.status ===
                                                                                        'paid' ||
                                                                                    res.status ===
                                                                                        'confirmed'
                                                                                  ? 'navy'
                                                                                  : res.status ===
                                                                                      'held'
                                                                                    ? 'gold'
                                                                                    : 'muted'
                                                                        }
                                                                    />
                                                                </td>
                                                                <td className="px-4 py-3.5 text-right">
                                                                    <span className="inline-flex size-7 items-center justify-center rounded-lg bg-muted/60 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-foreground">
                                                                        <MoveRight className="size-3.5" />
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        ),
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </section>
                            </div>
                        )}

                        {/* TAB 3: FACTURES & RELEVES */}
                        {currentTab === 'factures' && (
                            <div className="space-y-6">
                                {/* Relevés mensuels consolidés */}
                                <section className="cash-table-card rounded-2xl border border-border/50 bg-card p-5 shadow-sm">
                                    <div className="mb-4 flex items-center justify-between">
                                        <div>
                                            <h3 className="text-sm font-black text-foreground">
                                                Relevés mensuels consolidés
                                                (MEZ-118)
                                            </h3>
                                            <p className="text-xs text-muted-foreground">
                                                Relevés d'achats périodiques
                                                pour règlement à terme
                                            </p>
                                        </div>
                                    </div>

                                    {factures.statements.length === 0 ? (
                                        <div className="rounded-xl border border-dashed border-border py-8 text-center text-xs text-muted-foreground">
                                            Aucun relevé mensuel généré à ce
                                            jour.
                                        </div>
                                    ) : (
                                        <div className="overflow-x-auto">
                                            <table className="w-full text-left text-xs">
                                                <thead className="bg-muted/40 text-[0.625rem] font-black tracking-wider text-muted-foreground uppercase">
                                                    <tr>
                                                        <th className="px-3 py-2.5">
                                                            Référence
                                                        </th>
                                                        <th className="px-3 py-2.5">
                                                            Période
                                                        </th>
                                                        <th className="px-3 py-2.5">
                                                            Achats
                                                        </th>
                                                        <th className="px-3 py-2.5">
                                                            Débit
                                                        </th>
                                                        <th className="px-3 py-2.5">
                                                            Avoirs
                                                        </th>
                                                        <th className="px-3 py-2.5">
                                                            Net à régler
                                                        </th>
                                                        <th className="px-3 py-2.5">
                                                            Statut
                                                        </th>
                                                        <th className="px-3 py-2.5 text-right">
                                                            Action
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-border/40">
                                                    {factures.statements.map(
                                                        (stmt) => (
                                                            <tr
                                                                key={stmt.uuid}
                                                                className="group cursor-pointer hover:bg-muted/30"
                                                                onClick={() =>
                                                                    router.get(
                                                                        companies.statements.show.url(
                                                                            {
                                                                                companyAccount:
                                                                                    account.uuid,
                                                                                statement:
                                                                                    stmt.uuid,
                                                                            },
                                                                        ),
                                                                    )
                                                                }
                                                            >
                                                                <td className="px-3 py-2.5 font-bold text-foreground group-hover:text-[var(--kora)]">
                                                                    {
                                                                        stmt.reference
                                                                    }
                                                                </td>
                                                                <td className="px-3 py-2.5 font-semibold text-foreground">
                                                                    {
                                                                        stmt.periodLabel
                                                                    }
                                                                </td>
                                                                <td className="px-3 py-2.5 text-muted-foreground">
                                                                    {
                                                                        stmt.reservationsCount
                                                                    }{' '}
                                                                    réservations
                                                                </td>
                                                                <td className="px-3 py-2.5 text-muted-foreground">
                                                                    {formatFcfa(
                                                                        stmt.debitTotal,
                                                                    )}
                                                                </td>
                                                                <td className="px-3 py-2.5 font-semibold text-emerald-600">
                                                                    {stmt.refundTotal >
                                                                    0
                                                                        ? `-${formatFcfa(stmt.refundTotal)}`
                                                                        : '0 FCFA'}
                                                                </td>
                                                                <td className="px-3 py-2.5 font-extrabold text-foreground">
                                                                    {formatFcfa(
                                                                        stmt.netTotal,
                                                                    )}
                                                                </td>
                                                                <td className="px-3 py-2.5">
                                                                    <span className="inline-flex rounded-full bg-blue-500/10 px-2 py-0.5 text-[0.6875rem] font-bold text-blue-600">
                                                                        {
                                                                            stmt.statusLabel
                                                                        }
                                                                    </span>
                                                                </td>
                                                                <td className="px-3 py-2.5 text-right">
                                                                    <span className="text-[0.6875rem] font-bold text-muted-foreground group-hover:text-foreground">
                                                                        Consulter
                                                                        →
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        ),
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </section>

                                {/* Factures normalisées certifiées */}
                                <section className="cash-table-card rounded-2xl border border-border/50 bg-card p-5 shadow-sm">
                                    <div className="mb-4 flex items-center justify-between">
                                        <div>
                                            <h3 className="text-sm font-black text-foreground">
                                                Factures normalisées certifiées
                                                (MECeF)
                                            </h3>
                                            <p className="text-xs text-muted-foreground">
                                                Factures et avoirs fiscaux émis
                                                pour les achats de cette
                                                organisation
                                            </p>
                                        </div>
                                    </div>

                                    {factures.fiscalInvoices.length === 0 ? (
                                        <div className="rounded-xl border border-dashed border-border py-8 text-center text-xs text-muted-foreground">
                                            Aucune facture normalisée certifiée
                                            pour le moment.
                                        </div>
                                    ) : (
                                        <div className="overflow-x-auto">
                                            <table className="w-full text-left text-xs">
                                                <thead className="bg-muted/40 text-[0.625rem] font-black tracking-wider text-muted-foreground uppercase">
                                                    <tr>
                                                        <th className="px-3 py-2.5">
                                                            Réf. MECeF
                                                        </th>
                                                        <th className="px-3 py-2.5">
                                                            Réf. KORA
                                                        </th>
                                                        <th className="px-3 py-2.5">
                                                            Réservation
                                                        </th>
                                                        <th className="px-3 py-2.5">
                                                            Date
                                                        </th>
                                                        <th className="px-3 py-2.5 text-right">
                                                            Montant TTC
                                                        </th>
                                                        <th className="px-3 py-2.5">
                                                            Statut
                                                        </th>
                                                        <th className="px-3 py-2.5 text-right">
                                                            Document
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-border/40">
                                                    {factures.fiscalInvoices.map(
                                                        (inv) => (
                                                            <tr
                                                                key={inv.uuid}
                                                                className="hover:bg-muted/30"
                                                            >
                                                                <td className="px-3 py-2.5 font-bold text-foreground">
                                                                    {
                                                                        inv.reference
                                                                    }
                                                                </td>
                                                                <td className="px-3 py-2.5 text-muted-foreground">
                                                                    {
                                                                        inv.koraReference
                                                                    }
                                                                </td>
                                                                <td className="px-3 py-2.5 text-foreground">
                                                                    {
                                                                        inv.reservationReference
                                                                    }
                                                                </td>
                                                                <td className="px-3 py-2.5 text-muted-foreground">
                                                                    {formatDate(
                                                                        inv.issuedAt,
                                                                    )}
                                                                </td>
                                                                <td className="px-3 py-2.5 text-right font-extrabold text-foreground">
                                                                    {formatFcfa(
                                                                        inv.totalWithTax,
                                                                    )}
                                                                </td>
                                                                <td className="px-3 py-2.5">
                                                                    <span className="inline-flex rounded-full bg-emerald-500/10 px-2 py-0.5 text-[0.6875rem] font-bold text-emerald-600">
                                                                        {
                                                                            inv.statusLabel
                                                                        }
                                                                    </span>
                                                                </td>
                                                                <td className="px-3 py-2.5 text-right">
                                                                    <Link
                                                                        href={`/fiscal-invoices/${inv.uuid}`}
                                                                        className="inline-flex items-center gap-1 font-bold text-muted-foreground hover:text-foreground"
                                                                    >
                                                                        <FileText className="size-3.5" />
                                                                        Ouvrir
                                                                    </Link>
                                                                </td>
                                                            </tr>
                                                        ),
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </section>
                            </div>
                        )}

                        {/* TAB 4: REFERENT & VOYAGEURS */}
                        {currentTab === 'referent' && (
                            <div className="space-y-6">
                                {/* Fiche référent */}
                                <section className="rounded-2xl border border-border/50 bg-card p-5 shadow-sm">
                                    <div className="mb-4 flex items-center justify-between">
                                        <div>
                                            <h3 className="text-sm font-black text-foreground">
                                                Fiche du responsable de compte
                                                (Référent)
                                            </h3>
                                            <p className="text-xs text-muted-foreground">
                                                Utilisateur autorisé à
                                                administrer le portail et
                                                valider les achats
                                            </p>
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div className="flex items-center gap-3.5 rounded-xl bg-muted/30 p-3.5">
                                            <span className="grid size-10 place-items-center rounded-xl bg-gold/15 text-gold">
                                                <User className="size-5" />
                                            </span>
                                            <div>
                                                <span className="text-[0.6875rem] font-bold text-muted-foreground uppercase">
                                                    Nom & Prénom
                                                </span>
                                                <p className="text-sm font-extrabold text-foreground">
                                                    {referent.profile.name}
                                                </p>
                                                <span className="text-[0.6875rem] text-muted-foreground">
                                                    {referent.profile.role}
                                                </span>
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-3.5 rounded-xl bg-muted/30 p-3.5">
                                            <span className="grid size-10 place-items-center rounded-xl bg-blue-500/10 text-blue-600">
                                                <Mail className="size-5" />
                                            </span>
                                            <div>
                                                <span className="text-[0.6875rem] font-bold text-muted-foreground uppercase">
                                                    Email & Contact
                                                </span>
                                                <p className="text-sm font-extrabold text-foreground">
                                                    {referent.profile.email}
                                                </p>
                                                <span className="text-[0.6875rem] text-muted-foreground">
                                                    {referent.profile.phone}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </section>

                                {/* Roster des voyageurs rattachés */}
                                <section className="cash-table-card rounded-2xl border border-border/50 bg-card p-5 shadow-sm">
                                    <div className="mb-4 flex items-center justify-between">
                                        <div>
                                            <h3 className="text-sm font-black text-foreground">
                                                Personnes rattachées au compte (
                                                {referent.travelers.length})
                                            </h3>
                                            <p className="text-xs text-muted-foreground">
                                                Collaborateurs et bénéficiaires
                                                éligibles aux réservations au
                                                débit du compte
                                            </p>
                                        </div>
                                    </div>

                                    {referent.travelers.length === 0 ? (
                                        <div className="rounded-xl border border-dashed border-border py-8 text-center text-xs text-muted-foreground">
                                            Aucun voyageur rattaché à ce compte
                                            pour l'instant.
                                        </div>
                                    ) : (
                                        <div className="overflow-x-auto">
                                            <table className="w-full text-left text-xs">
                                                <thead className="bg-muted/40 text-[0.625rem] font-black tracking-wider text-muted-foreground uppercase">
                                                    <tr>
                                                        <th className="px-3 py-2.5">
                                                            Nom complet
                                                        </th>
                                                        <th className="px-3 py-2.5">
                                                            Téléphone
                                                        </th>
                                                        <th className="px-3 py-2.5">
                                                            Pièce d'identité
                                                        </th>
                                                        <th className="px-3 py-2.5">
                                                            Statut
                                                        </th>
                                                        <th className="px-3 py-2.5 text-right">
                                                            Ajouté le
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-border/40">
                                                    {referent.travelers.map(
                                                        (t) => (
                                                            <tr
                                                                key={t.uuid}
                                                                className="hover:bg-muted/30"
                                                            >
                                                                <td className="px-3 py-2.5 font-bold text-foreground">
                                                                    {t.fullName}
                                                                </td>
                                                                <td className="px-3 py-2.5 text-muted-foreground">
                                                                    {t.phone ??
                                                                        '—'}
                                                                </td>
                                                                <td className="px-3 py-2.5 text-muted-foreground">
                                                                    {
                                                                        t.documentType
                                                                    }{' '}
                                                                    ·{' '}
                                                                    {
                                                                        t.documentNumber
                                                                    }
                                                                </td>
                                                                <td className="px-3 py-2.5">
                                                                    <span
                                                                        className={cn(
                                                                            'inline-flex rounded-full px-2 py-0.5 text-[0.6875rem] font-bold',
                                                                            t.status ===
                                                                                'active'
                                                                                ? 'bg-emerald-500/10 text-emerald-600'
                                                                                : 'bg-muted text-muted-foreground',
                                                                        )}
                                                                    >
                                                                        {
                                                                            t.statusLabel
                                                                        }
                                                                    </span>
                                                                </td>
                                                                <td className="px-3 py-2.5 text-right text-muted-foreground">
                                                                    {formatDate(
                                                                        t.createdAt,
                                                                    )}
                                                                </td>
                                                            </tr>
                                                        ),
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </section>
                            </div>
                        )}

                        {/* TAB 5: HISTORIQUE */}
                        {currentTab === 'historique' && (
                            <section className="rounded-2xl border border-border/50 bg-card p-6 shadow-sm">
                                <div className="mb-6">
                                    <h3 className="text-sm font-black text-foreground">
                                        Journal d'audit & chronologie du compte
                                    </h3>
                                    <p className="text-xs text-muted-foreground">
                                        Traçabilité immuable des modifications
                                        de plafond, statut et habilitations
                                    </p>
                                </div>

                                <div className="relative space-y-6 border-l border-border/60 pl-6">
                                    {historique.events.map((evt) => (
                                        <div
                                            key={evt.uuid}
                                            className="relative"
                                        >
                                            <span className="absolute top-1 -left-[31px] grid size-4 place-items-center rounded-full bg-card ring-2 ring-border">
                                                <span className="size-2 rounded-full bg-[var(--kora)]" />
                                            </span>
                                            <div>
                                                <p className="text-xs font-black text-foreground">
                                                    {evt.label}
                                                </p>
                                                <p className="mt-0.5 text-[0.6875rem] text-muted-foreground">
                                                    Par{' '}
                                                    <strong className="text-foreground">
                                                        {evt.actor}
                                                    </strong>{' '}
                                                    ({evt.actorRole}) ·{' '}
                                                    {evt.date}
                                                    {evt.relativeDate &&
                                                        ` (${evt.relativeDate})`}
                                                </p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </section>
                        )}
                    </main>

                    {/* Permanent Aside (Synthèse Crédit & Mini Timeline) */}
                    <aside className="space-y-6">
                        {/* Synthèse crédit permanente */}
                        <div className="rounded-2xl border border-border/50 bg-card p-5 shadow-sm">
                            <div className="mb-3 flex items-center justify-between">
                                <span className="text-[0.625rem] font-black tracking-widest text-muted-foreground uppercase">
                                    Synthèse crédit
                                </span>
                                <span
                                    className={cn(
                                        'rounded-full px-2 py-0.5 text-[0.6875rem] font-bold',
                                        gaugeTone === 'destructive'
                                            ? 'bg-destructive/10 text-destructive'
                                            : gaugeTone === 'gold'
                                              ? 'bg-gold/15 text-gold'
                                              : 'bg-emerald-500/10 text-emerald-600',
                                    )}
                                >
                                    {aside.creditSummary.percentage} % consommé
                                </span>
                            </div>

                            {/* Jauge visuelle */}
                            <div className="mb-4 space-y-1.5">
                                <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                                    <div
                                        className={cn(
                                            'h-full transition-all duration-500',
                                            gaugeTone === 'destructive'
                                                ? 'bg-destructive'
                                                : gaugeTone === 'gold'
                                                  ? 'bg-gold'
                                                  : 'bg-[var(--kora)]',
                                        )}
                                        style={{
                                            width: `${Math.min(100, aside.creditSummary.percentage)}%`,
                                        }}
                                    />
                                </div>
                                <div className="flex justify-between text-[0.6875rem] font-semibold text-muted-foreground">
                                    <span>0 FCFA</span>
                                    <span>
                                        {formatFcfa(
                                            aside.creditSummary.monthlyLimit,
                                        )}
                                    </span>
                                </div>
                            </div>

                            <div className="divide-y divide-border/40 text-xs">
                                <div className="flex justify-between py-2 text-muted-foreground">
                                    <span>Consommé</span>
                                    <strong className="text-foreground">
                                        {formatFcfa(
                                            aside.creditSummary.consumed,
                                        )}
                                    </strong>
                                </div>
                                <div className="flex justify-between py-2 text-muted-foreground">
                                    <span>Réservé (holds)</span>
                                    <strong className="text-foreground">
                                        {formatFcfa(
                                            aside.creditSummary.reserved,
                                        )}
                                    </strong>
                                </div>
                                <div className="flex justify-between py-2 text-muted-foreground">
                                    <span>Disponible</span>
                                    <strong className="font-black text-emerald-600 dark:text-emerald-400">
                                        {formatFcfa(
                                            aside.creditSummary.available,
                                        )}
                                    </strong>
                                </div>
                            </div>

                            {aside.creditSummary.percentage >= 80 && (
                                <div className="mt-3">
                                    <PanelAlert
                                        tone="warning"
                                        icon={AlertTriangle}
                                    >
                                        Seuil de vigilance : 80 % du plafond
                                        dépassé.
                                    </PanelAlert>
                                </div>
                            )}
                        </div>

                        {/* Mini-timeline (3 derniers événements) */}
                        <div className="rounded-2xl border border-border/50 bg-card p-5 shadow-sm">
                            <div className="mb-4 flex items-center justify-between">
                                <span className="text-[0.625rem] font-black tracking-widest text-muted-foreground uppercase">
                                    Dernières activités
                                </span>
                                <button
                                    type="button"
                                    onClick={() => switchTab('historique')}
                                    className="text-[0.6875rem] font-bold text-muted-foreground hover:text-foreground"
                                >
                                    Tout voir →
                                </button>
                            </div>

                            <div className="space-y-3.5">
                                {aside.recentEvents.map((evt) => (
                                    <div key={evt.uuid} className="text-xs">
                                        <p className="font-bold text-foreground">
                                            {evt.label}
                                        </p>
                                        <p className="text-[0.6875rem] text-muted-foreground">
                                            {evt.actor} ·{' '}
                                            {evt.relativeDate || evt.date}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Rappel des conditions commerciales */}
                        <div className="space-y-2 rounded-2xl border border-border/50 bg-card p-5 text-xs shadow-sm">
                            <span className="text-[0.625rem] font-black tracking-widest text-muted-foreground uppercase">
                                Paramétrage commercial
                            </span>
                            <div className="space-y-1 text-muted-foreground">
                                <p>
                                    Remise contractuelle :{' '}
                                    <strong className="text-foreground">
                                        {account.discountType === 'percentage'
                                            ? `${account.discountValue} %`
                                            : account.discountType === 'amount'
                                              ? `${formatFcfa(account.discountValue ?? 0)} / billet`
                                              : 'Aucune remise'}
                                    </strong>
                                </p>
                                <p>
                                    Règlement : Facturation mensuelle consolidée
                                    à terme échu.
                                </p>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>

            {/* Modal d'édition du compte */}
            <CompanyAccountFormModal
                open={editModalOpen}
                account={account}
                onOpenChange={setEditModalOpen}
            />
        </>
    );
}
