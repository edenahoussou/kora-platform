import { Head, router, usePage } from '@inertiajs/react';
import {
    Banknote,
    Building2,
    CheckCircle2,
    ChevronRight,
    Edit3,
    Eye,
    HelpCircle,
    Plus,
    Power,
    RotateCcw,
    Search,
    SlidersHorizontal,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { CompanyAccountDetailPanel } from '@/components/company-accounts/company-account-detail-panel';
import { CompanyAccountFormModal } from '@/components/company-accounts/company-account-form-modal';
import type {
    CompanyAccountRecord,
    CompanyUserCandidate,
} from '@/components/company-accounts/company-account-types';
import { formatFcfa } from '@/components/company-accounts/company-account-types';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { OnboardingTour } from '@/components/onboarding/onboarding-tour';
import type { OnboardingStep } from '@/components/onboarding/onboarding-tour';
import { useOnboarding } from '@/hooks/use-onboarding';
import { cn } from '@/lib/utils';
import companies from '@/routes/companies';
import type { BreadcrumbItem } from '@/types';

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

type Props = {
    accounts: Paginated<CompanyAccountRecord>;
    summary: {
        total: number;
        active: number;
        withAvailableCredit: number;
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

type StatusTone = 'navy' | 'gold' | 'blue' | 'muted';

const steps: OnboardingStep[] = [
    {
        target: '[data-onboarding="companies-heading"]',
        title: 'Un référentiel centralisé',
        body: 'Retrouvez ici les entreprises, ONG et ambassades autorisées à acheter des billets à crédit.',
        side: 'bottom',
    },
    {
        target: '[data-onboarding="companies-filters"]',
        title: 'Retrouvez rapidement une organisation',
        body: 'Recherchez par nom, référence ou contact, puis filtrez par type et statut.',
        side: 'bottom',
    },
    {
        target: '[data-onboarding="companies-add"]',
        title: 'Ajoutez une organisation',
        body: 'La référence est générée automatiquement. Vous pourrez ensuite affecter un référent et des acheteurs.',
        side: 'bottom',
    },
    {
        target: '[data-onboarding="companies-table"]',
        title: 'Suivez le crédit',
        body: 'Le tableau présente le plafond et le disponible lorsque votre rôle dispose de la permission Finance.',
        side: 'top',
    },
];

const typeTones: Record<string, StatusTone> = {
    company: 'navy',
    ngo: 'blue',
    embassy: 'gold',
};

const statusTones: Record<string, StatusTone> = {
    active: 'navy',
    inactive: 'muted',
};

const statusLabels: Record<string, string> = {
    active: 'Active',
    inactive: 'Inactive',
};

function FilterSelect({
    label,
    value,
    onChange,
    options,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: [string, string][];
}) {
    return (
        <label className="cash-filter-field relative block">
            <span className="sr-only">{label}</span>
            <select
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="cash-filter-select h-11 w-full appearance-none rounded-xl bg-muted/55 px-3 pr-8 text-xs font-bold text-foreground ring-1 ring-black/[0.045] transition-[box-shadow,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] outline-none focus:bg-card focus:ring-2 focus:ring-gold/35"
            >
                {options.map(([optionValue, optionLabel]) => (
                    <option key={optionValue} value={optionValue}>
                        {optionLabel}
                    </option>
                ))}
            </select>
            <ChevronRight
                className="pointer-events-none absolute top-1/2 right-3 size-3.5 -translate-y-1/2 rotate-90 text-muted-foreground"
                strokeWidth={1.7}
            />
        </label>
    );
}

function KpiCard({
    icon: Icon,
    label,
    hint,
    value,
    tone,
}: {
    icon: React.ComponentType<{
        className?: string;
        strokeWidth?: number;
    }>;
    label: string;
    hint: string;
    value: string | number;
    tone: 'navy' | 'gold' | 'blue' | 'muted' | 'green';
}) {
    const toneStyles = {
        navy: 'bg-kora text-white',
        gold: 'bg-gold/15 text-gold',
        blue: 'bg-digital/10 text-digital',
        muted: 'bg-muted text-muted-foreground',
        green: 'bg-emerald-500/10 text-emerald-600',
    };
    const iconBg = toneStyles[tone] ?? toneStyles.muted;

    return (
        <article className="cash-ledger-card cash-ledger-card-cash">
            <div className="cash-ledger-head">
                <div className="cash-ledger-identity">
                    <span className={cn('cash-ledger-brand', iconBg)}>
                        <Icon className="size-4" strokeWidth={1.7} />
                    </span>
                    <div>
                        <span className="cash-ledger-title">{label}</span>
                        <small>{hint}</small>
                    </div>
                </div>
            </div>
            <div className="cash-ledger-balance">
                <span>Valeur</span>
                <strong>{value}</strong>
            </div>
        </article>
    );
}

function Pagination({
    currentPage,
    totalPages,
    total,
    from,
    to,
    onPageChange,
}: {
    currentPage: number;
    totalPages: number;
    total: number;
    from: number | null;
    to: number | null;
    onPageChange: (page: number) => void;
}) {
    if (total === 0) {
        return null;
    }

    return (
        <div className="flex items-center justify-between border-t border-black/[0.045] px-4 py-3 text-xs text-muted-foreground">
            <span>
                {from}–{to} sur {total}
            </span>
            <div className="flex items-center gap-1.5">
                <button
                    type="button"
                    className="grid size-[31px] place-items-center rounded-[8px] bg-muted text-foreground transition-[transform,opacity,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:-translate-x-0.5 hover:bg-gold/15 disabled:pointer-events-none disabled:opacity-35"
                    onClick={() => onPageChange(currentPage - 1)}
                    disabled={currentPage <= 1}
                    aria-label="Page précédente"
                >
                    <ChevronRight
                        className="size-3.5 rotate-180"
                        strokeWidth={1.7}
                    />
                </button>
                <span className="px-2 font-bold text-foreground">
                    {currentPage} / {totalPages}
                </span>
                <button
                    type="button"
                    className="grid size-[31px] place-items-center rounded-[8px] bg-muted text-foreground transition-[transform,opacity,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:translate-x-0.5 hover:bg-gold/15 disabled:pointer-events-none disabled:opacity-35"
                    onClick={() => onPageChange(currentPage + 1)}
                    disabled={currentPage >= totalPages}
                    aria-label="Page suivante"
                >
                    <ChevronRight className="size-3.5" strokeWidth={1.7} />
                </button>
            </div>
        </div>
    );
}

export default function CompaniesPage({
    accounts,
    summary,
    types,
    statuses,
    userCandidates,
    permissions,
}: Props) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editingAccount, setEditingAccount] =
        useState<CompanyAccountRecord | null>(null);
    const [selectedAccount, setSelectedAccount] =
        useState<CompanyAccountRecord | null>(null);
    const onboarding = useOnboarding('company-accounts');
    const { shouldAutoStart, start } = onboarding;

    // Same URL-parsing approach as KoraFilterBar: Inertia's page.url is
    // available identically on server and client, unlike window.location.
    const { url: currentUrl } = usePage();
    const urlParams = useMemo(() => {
        const queryString = currentUrl.includes('?')
            ? currentUrl.slice(currentUrl.indexOf('?') + 1)
            : '';

        return new URLSearchParams(queryString);
    }, [currentUrl]);

    const [query, setQuery] = useState(urlParams.get('q') ?? '');
    const [type, setType] = useState(urlParams.get('type') ?? 'all');
    const [status, setStatus] = useState(urlParams.get('status') ?? 'all');

    useEffect(() => {
        if (!shouldAutoStart) {
            return;
        }

        const timer = window.setTimeout(() => start(), 450);

        return () => window.clearTimeout(timer);
    }, [shouldAutoStart, start]);

    const typeOptions: [string, string][] = useMemo(
        () => [
            ['all', 'Tous les types'],
            ...Object.entries(types).map(
                ([value, label]) => [value, label] as [string, string],
            ),
        ],
        [types],
    );

    const statusOptions: [string, string][] = useMemo(
        () => [
            ['all', 'Tous les statuts'],
            ...Object.entries(statuses).map(
                ([value, label]) => [value, label] as [string, string],
            ),
        ],
        [statuses],
    );

    const buildParams = (
        overrides: {
            q?: string;
            type?: string;
            status?: string;
            page?: number;
        } = {},
    ): Record<string, string> => {
        const nextQuery = overrides.q ?? query;
        const nextType = overrides.type ?? type;
        const nextStatus = overrides.status ?? status;
        const params: Record<string, string> = {};

        if (nextQuery.trim() !== '') {
            params.q = nextQuery.trim();
        }

        if (nextType !== 'all') {
            params.type = nextType;
        }

        if (nextStatus !== 'all') {
            params.status = nextStatus;
        }

        if (overrides.page !== undefined && overrides.page > 1) {
            params.page = String(overrides.page);
        }

        return params;
    };

    const visit = (overrides: Parameters<typeof buildParams>[0] = {}) => {
        router.get('/companies', buildParams(overrides), {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    const applyFilters = () => visit({ page: 1 });

    const resetFilters = () => {
        setQuery('');
        setType('all');
        setStatus('all');
        router.get(
            '/companies',
            {},
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const openCreate = () => {
        setEditingAccount(null);
        setModalOpen(true);
    };

    const openEdit = (account: CompanyAccountRecord) => {
        setEditingAccount(account);
        setModalOpen(true);
    };

    const changeStatus = (
        account: CompanyAccountRecord,
        nextStatus: 'active' | 'inactive',
    ) => {
        const action = nextStatus === 'active' ? 'réactiver' : 'désactiver';

        if (
            !window.confirm(
                `Voulez-vous vraiment ${action} ${account.name} ? Les achats à crédit seront ${nextStatus === 'active' ? 'autorisés' : 'bloqués'}.`,
            )
        ) {
            return;
        }

        router.patch(
            companies.status.url({ companyAccount: account.uuid }),
            { status: nextStatus },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Organisations" />
            <div className="cash-page">
                <div
                    data-onboarding="companies-heading"
                    className="kora-page-head cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div>
                        <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                            KORA OPS / Réseau commercial / Organisations
                        </div>
                        <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                            Entreprises & organisations
                        </h1>
                        <p className="mt-1.5 text-[0.8125rem] leading-6 text-muted-foreground">
                            Gérez les organisations autorisées à financer des
                            achats Bus via un crédit mensuel.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2.5">
                        <button
                            type="button"
                            className="btn btn-ghost"
                            onClick={onboarding.reset}
                            aria-label="Revoir le parcours"
                        >
                            <HelpCircle className="size-4" /> Parcours
                        </button>
                        {permissions.canCreate && (
                            <button
                                type="button"
                                data-onboarding="companies-add"
                                className="btn btn-primary"
                                onClick={openCreate}
                            >
                                <Plus className="size-4" /> Ajouter une
                                organisation
                            </button>
                        )}
                    </div>
                </div>

                <section className="cash-ledger-grid companies-ledger-grid cash-reveal cash-reveal-delay-1">
                    <KpiCard
                        icon={Building2}
                        label="Organisations"
                        hint="dans le référentiel"
                        value={summary.total}
                        tone="navy"
                    />
                    <KpiCard
                        icon={CheckCircle2}
                        label="Actives"
                        hint="achats à crédit autorisés"
                        value={summary.active}
                        tone="green"
                    />
                    <KpiCard
                        icon={Banknote}
                        label="Crédit disponible"
                        hint="organisations avec un solde positif"
                        value={summary.withAvailableCredit}
                        tone="gold"
                    />
                </section>

                <div className="cash-reveal cash-reveal-delay-2 cash-section-intro">
                    <div>
                        <span className="section-kicker">Référentiel</span>
                        <h2>Liste des organisations</h2>
                    </div>
                </div>

                <div
                    data-onboarding="companies-filters"
                    className="cash-filterbar cash-reveal cash-reveal-delay-2"
                >
                    <Search className="cash-filter-icon size-4" />
                    <input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                applyFilters();
                            }
                        }}
                        aria-label="Rechercher une organisation"
                        placeholder="Rechercher par nom, référence ou contact…"
                        className="cash-filter-search"
                    />
                    <FilterSelect
                        label="Type"
                        value={type}
                        onChange={(value) => {
                            setType(value);
                            visit({ type: value, page: 1 });
                        }}
                        options={typeOptions}
                    />
                    <FilterSelect
                        label="Statut"
                        value={status}
                        onChange={(value) => {
                            setStatus(value);
                            visit({ status: value, page: 1 });
                        }}
                        options={statusOptions}
                    />
                    <button
                        type="button"
                        className="btn btn-navy cash-filter-submit"
                        onClick={applyFilters}
                    >
                        <SlidersHorizontal className="size-4" /> Filtrer
                    </button>
                    <button
                        type="button"
                        className="btn btn-ghost cash-filter-reset"
                        onClick={resetFilters}
                    >
                        Réinitialiser
                    </button>
                </div>

                <section
                    data-onboarding="companies-table"
                    className="cash-table-card cash-reveal cash-reveal-delay-3"
                >
                    {accounts.data.length === 0 ? (
                        <p className="px-6 py-16 text-center text-sm text-muted-foreground">
                            Aucune organisation trouvée. Ajustez votre recherche
                            ou créez la première organisation du référentiel.
                        </p>
                    ) : (
                        <div className="cash-table-scroll overflow-x-auto">
                            <table className="w-full min-w-[980px] text-left">
                                <thead className="bg-muted/45 text-[0.625rem] font-black tracking-[0.14em] text-muted-foreground uppercase">
                                    <tr>
                                        <th className="px-4 py-3">
                                            Organisation
                                        </th>
                                        <th className="px-4 py-3">Type</th>
                                        <th className="px-4 py-3">Contact</th>
                                        <th className="px-4 py-3 text-right">
                                            Crédit mensuel
                                        </th>
                                        <th className="px-4 py-3">
                                            Contact principal
                                        </th>
                                        <th className="px-4 py-3">Statut</th>
                                        <th className="px-4 py-3 text-right">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {accounts.data.map((account) => (
                                        <tr
                                            key={account.uuid}
                                            className="group cursor-pointer border-b border-black/[0.045] transition-[background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:bg-muted/30"
                                            onClick={() =>
                                                router.get(
                                                    companies.show.url({
                                                        companyAccount:
                                                            account.uuid,
                                                    }),
                                                )
                                            }
                                        >
                                            <td className="px-4 py-3.5">
                                                <div className="flex items-center gap-3">
                                                    <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-gold/12 text-[var(--kora)]">
                                                        <Building2
                                                            className="size-4"
                                                            strokeWidth={1.6}
                                                        />
                                                    </span>
                                                    <div>
                                                        <p className="text-xs font-black text-foreground">
                                                            {account.name}
                                                        </p>
                                                        <p className="mt-0.5 text-[0.625rem] text-muted-foreground">
                                                            {account.reference}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <KoraStatusBadge
                                                    label={
                                                        types[account.type] ??
                                                        account.type
                                                    }
                                                    tone={
                                                        typeTones[
                                                            account.type
                                                        ] ?? 'muted'
                                                    }
                                                />
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <p className="text-xs font-bold text-foreground">
                                                    {account.email ||
                                                        account.phone ||
                                                        'Non renseigné'}
                                                </p>
                                                <p className="mt-0.5 text-[0.625rem] text-muted-foreground">
                                                    {account.city ||
                                                        'Localisation non renseignée'}
                                                </p>
                                            </td>
                                            <td className="px-4 py-3.5 text-right">
                                                {permissions.canViewCredit ? (
                                                    <>
                                                        <p className="text-xs font-black text-foreground">
                                                            {formatFcfa(
                                                                account.availableCredit,
                                                            )}
                                                        </p>
                                                        <p className="mt-0.5 text-[0.625rem] text-muted-foreground">
                                                            sur{' '}
                                                            {formatFcfa(
                                                                account.monthlyCreditLimit,
                                                            )}
                                                        </p>
                                                    </>
                                                ) : (
                                                    <span className="text-[0.625rem] text-muted-foreground italic">
                                                        Accès Finance requis
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5">
                                                {account.referent ? (
                                                    <span className="text-xs font-bold text-foreground">
                                                        {account.referent.name}
                                                    </span>
                                                ) : (
                                                    <span className="text-[0.625rem] text-muted-foreground italic">
                                                        Non affecté
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <KoraStatusBadge
                                                    label={
                                                        statusLabels[
                                                            account.status
                                                        ] ?? account.status
                                                    }
                                                    tone={
                                                        statusTones[
                                                            account.status
                                                        ] ?? 'muted'
                                                    }
                                                />
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <div
                                                    className="flex justify-end gap-1.5"
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                >
                                                    <button
                                                        type="button"
                                                        className="grid size-8 place-items-center rounded-full bg-muted text-foreground transition-[transform,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:scale-105 hover:bg-gold/15"
                                                        onClick={() =>
                                                            router.get(
                                                                companies.show.url(
                                                                    {
                                                                        companyAccount:
                                                                            account.uuid,
                                                                    },
                                                                ),
                                                            )
                                                        }
                                                        aria-label={`Consulter ${account.name}`}
                                                    >
                                                        <Eye
                                                            className="size-3.5"
                                                            strokeWidth={1.7}
                                                        />
                                                    </button>
                                                    {permissions.canUpdate && (
                                                        <button
                                                            type="button"
                                                            className="grid size-8 place-items-center rounded-full bg-muted text-foreground transition-[transform,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:scale-105 hover:bg-gold/15"
                                                            onClick={() =>
                                                                openEdit(
                                                                    account,
                                                                )
                                                            }
                                                            aria-label={`Modifier ${account.name}`}
                                                        >
                                                            <Edit3
                                                                className="size-3.5"
                                                                strokeWidth={
                                                                    1.7
                                                                }
                                                            />
                                                        </button>
                                                    )}
                                                    {permissions.canChangeStatus && (
                                                        <button
                                                            type="button"
                                                            className={cn(
                                                                'grid size-8 place-items-center rounded-full transition-[transform,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:scale-105',
                                                                account.status ===
                                                                    'active'
                                                                    ? 'bg-destructive/10 text-destructive hover:bg-destructive/20'
                                                                    : 'bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500/20',
                                                            )}
                                                            onClick={() =>
                                                                changeStatus(
                                                                    account,
                                                                    account.status ===
                                                                        'active'
                                                                        ? 'inactive'
                                                                        : 'active',
                                                                )
                                                            }
                                                            aria-label={
                                                                account.status ===
                                                                'active'
                                                                    ? `Désactiver ${account.name}`
                                                                    : `Réactiver ${account.name}`
                                                            }
                                                        >
                                                            {account.status ===
                                                            'active' ? (
                                                                <Power
                                                                    className="size-3.5"
                                                                    strokeWidth={
                                                                        1.7
                                                                    }
                                                                />
                                                            ) : (
                                                                <RotateCcw
                                                                    className="size-3.5"
                                                                    strokeWidth={
                                                                        1.7
                                                                    }
                                                                />
                                                            )}
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <Pagination
                        currentPage={accounts.current_page}
                        totalPages={accounts.last_page}
                        total={accounts.total}
                        from={accounts.from}
                        to={accounts.to}
                        onPageChange={(page) => visit({ page })}
                    />
                </section>
            </div>

            <CompanyAccountFormModal
                open={modalOpen}
                account={editingAccount}
                onOpenChange={setModalOpen}
            />
            <CompanyAccountDetailPanel
                account={selectedAccount}
                candidates={userCandidates}
                canManageMembers={permissions.canManageMembers}
                canViewCredit={permissions.canViewCredit}
                onClose={() => setSelectedAccount(null)}
            />
            <OnboardingTour
                steps={steps}
                isActive={onboarding.isActive}
                onComplete={onboarding.complete}
                onDismiss={onboarding.dismiss}
            />
        </>
    );
}

CompaniesPage.layout = {
    breadcrumbs: [
        { title: 'Organisations', href: '/companies' } satisfies BreadcrumbItem,
    ],
};
