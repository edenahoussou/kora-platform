import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Activity,
    ArrowDownLeft,
    ArrowLeft,
    ArrowRight,
    ArrowUpRight,
    Banknote,
    BadgeCheck,
    CalendarClock,
    CalendarDays,
    CheckCircle2,
    ChevronRight,
    CircleDollarSign,
    Coins,
    Gift,
    History,
    LockKeyhole,
    Search,
    ShieldCheck,
    SlidersHorizontal,
    Ticket,
    UserRound,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import { FilterSelect } from '@/components/cash/cash-shared';
import { KoraTable } from '@/components/kora-table';
import type { PaginationMeta, TableColumn } from '@/components/kora-table';
import { Button } from '@/components/ui/button';

type View =
    | 'dashboard'
    | 'accounts'
    | 'account'
    | 'transactions'
    | 'transaction'
    | 'rules';

type LoyaltyRules = {
    loyaltyEarnRateBasisPoints: number;
    pointValueFcfa: number;
    pointsMinSpend: number;
    bonusWelcomePoints: number;
    bonusReferralPoints: number;
    bonusAnniversaryPoints: number;
    bonusUsageNth: number;
    bonusUsagePoints: number;
    pointsExpiryMonths: number;
    pointsExpiryReminderDays: number[];
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

type Holder = {
    uuid: string;
    name: string | null;
    phone: string | null;
    status?: string;
};

type WalletAccount = {
    uuid: string;
    holder: Holder;
    balances: { cash: number; points: number; pointsValue: number };
    pointsExpiresAt: string | null;
    pointsCompensationDue: number;
    lastActivityAt: string | null;
    agency: { uuid: string; name: string } | null;
    createdAt: string;
    recentMovements: WalletMovement[];
};

type WalletMovement = {
    uuid: string;
    walletUuid: string;
    holder: Holder;
    balanceType: 'cash' | 'points';
    direction: 'credit' | 'debit';
    amount: number;
    reason: string;
    reasonLabel: string;
    source: {
        type: string;
        label: string;
        uuid: string;
        url: string | null;
    } | null;
    agency: { uuid: string; name: string } | null;
    loyaltyRuleVersion: { uuid: string; version: number } | null;
    actorName: string | null;
    createdAt: string;
};

type RuleVersion = {
    uuid: string;
    version: number;
    status: 'draft' | 'scheduled' | 'active' | 'superseded';
    rules: LoyaltyRules;
    effectiveAt: string | null;
    publishedAt: string | null;
    activatedAt: string | null;
    createdBy: string | null;
    scheduledBy: string | null;
};

type Filters = {
    search?: string;
    status?: string;
    balance_type?: string;
    expiration?: string;
    direction?: string;
    reason?: string;
    agency_uuid?: string;
    from?: string;
    to?: string;
    page?: number;
    per_page?: number;
};

type Props = {
    view: View;
    urls: {
        dashboard: string;
        accounts: string;
        transactions: string;
        rules: string;
        settings: string;
        restitutions: string;
    };
    dashboard?: {
        accountCount: number;
        cashTotal: number;
        pointsTotal: number;
        pointsValue: number;
        expiringAccountCount: number;
        expiringPoints: number;
        expiringValue: number;
        pendingTopUpCount: number;
        pendingTopUpAmount: number;
        pendingRestitutionCount: number;
        pendingRestitutionAmount: number;
        recentMovements: WalletMovement[];
    };
    accounts?: Paginated<WalletAccount>;
    account?: WalletAccount;
    returnUrl?: string;
    movements?: Paginated<WalletMovement>;
    movement?: WalletMovement;
    filters?: Filters;
    agencyOptions?: { uuid: string; name: string }[];
    activeRules?: LoyaltyRules;
    activeRuleVersion?: number | null;
    versions?: RuleVersion[];
    draft?: { uuid: string; version: number; rules: LoyaltyRules } | null;
    capabilities?: {
        manageRules?: boolean;
        manageSettings?: boolean;
        hasScheduledVersion?: boolean;
    };
};

const defaultRules: LoyaltyRules = {
    loyaltyEarnRateBasisPoints: 200,
    pointValueFcfa: 1,
    pointsMinSpend: 100,
    bonusWelcomePoints: 500,
    bonusReferralPoints: 200,
    bonusAnniversaryPoints: 100,
    bonusUsageNth: 5,
    bonusUsagePoints: 50,
    pointsExpiryMonths: 6,
    pointsExpiryReminderDays: [30, 7, 1],
};

const money = (amount: number) => `${amount.toLocaleString('fr-FR')} FCFA`;
const points = (amount: number) => `${amount.toLocaleString('fr-FR')} pts`;
const date = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('fr-FR', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : '—';

const dateOnly = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium' }).format(
              new Date(value),
          )
        : '—';

const minimumLocalDateTime = () => {
    const value = new Date(Date.now() + 60_000);
    value.setMinutes(value.getMinutes() - value.getTimezoneOffset());

    return value.toISOString().slice(0, 16);
};

const holderStatus: Record<string, string> = {
    active: 'Actif',
    suspended: 'Suspendu',
    locked: 'Verrouillé',
};

const versionStatus: Record<RuleVersion['status'], string> = {
    draft: 'Brouillon',
    scheduled: 'Programmée',
    active: 'Active',
    superseded: 'Remplacée',
};

const versionTone: Record<RuleVersion['status'], string> = {
    draft: 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200',
    scheduled: 'bg-sky-100 text-sky-800 dark:bg-sky-950/50 dark:text-sky-200',
    active: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200',
    superseded: 'bg-muted text-muted-foreground',
};

function pagination<T>(
    page: Paginated<T> | undefined,
    baseUrl: string,
): PaginationMeta | undefined {
    if (!page) {
        return undefined;
    }

    return {
        currentPage: page.current_page,
        lastPage: page.last_page,
        perPage: page.per_page,
        total: page.total,
        from: page.from,
        to: page.to,
        baseUrl,
    };
}

function labelForStatus(status: string | undefined) {
    return status ? (holderStatus[status] ?? status) : '—';
}

function statusPill(status: string | undefined) {
    const tone =
        status === 'active'
            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200'
            : status === 'locked'
              ? 'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-200'
              : 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200';

    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[0.6875rem] font-extrabold ${tone}`}
        >
            <span className="size-1.5 rounded-full bg-current" />
            {labelForStatus(status)}
        </span>
    );
}

function movementHref(
    base: string,
    movement: WalletMovement,
    filters?: Filters,
) {
    const query = new URLSearchParams();

    Object.entries(filters ?? {}).forEach(([key, value]) => {
        if (value !== undefined && value !== '') {
            query.set(key, String(value));
        }
    });

    const search = query.toString();

    return `${base.replace(/\/$/, '')}/${movement.uuid}${search ? `?${search}` : ''}`;
}

function PageFrame({
    title,
    description,
    breadcrumb,
    view,
    urls,
    children,
    actions,
    showDescription = true,
    eyebrow = 'Wallet KORA BUS',
}: {
    title: string;
    description: string;
    breadcrumb?: string;
    view: View;
    urls: Props['urls'];
    children: React.ReactNode;
    actions?: React.ReactNode;
    showDescription?: boolean;
    eyebrow?: string;
}) {
    return (
        <>
            <Head title={title} />
            <main className="cash-page flex flex-col gap-5 pb-8">
                <header className="kora-page-head cash-reveal mb-1 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        {breadcrumb ? (
                            <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                                {breadcrumb}
                            </div>
                        ) : null}
                        <span className="section-kicker">{eyebrow}</span>
                        <h1 className="mt-1 text-2xl font-black tracking-tight text-foreground sm:text-[1.75rem]">
                            {title}
                        </h1>
                        {showDescription ? (
                            <p className="mt-1 max-w-3xl text-sm leading-6 text-muted-foreground">
                                {description}
                            </p>
                        ) : null}
                    </div>
                    {actions ??
                        (view === 'account' || view === 'transaction' ? (
                            <Button variant="outline" asChild>
                                <Link
                                    href={
                                        view === 'account'
                                            ? urls.accounts
                                            : urls.transactions
                                    }
                                >
                                    <ArrowLeft className="size-4" /> Retour à la
                                    liste
                                </Link>
                            </Button>
                        ) : null)}
                </header>
                {children}
            </main>
        </>
    );
}

type WalletDashboardMetric = {
    icon: LucideIcon;
    label: string;
    note: string;
    value: string;
    footer: string;
    tone: 'navy' | 'gold' | 'digital' | 'emerald';
};

function WalletDashboardMetricCard({
    metric,
}: {
    metric: WalletDashboardMetric;
}) {
    const Icon = metric.icon;
    const toneClass = {
        navy: 'cash-ledger-card-kpi-navy',
        gold: 'cash-ledger-card-kpi-gold',
        digital: 'cash-ledger-card-kpi-digital',
        emerald: 'cash-ledger-card-kpi-emerald',
    }[metric.tone];

    return (
        <article className={`cash-ledger-card cash-reveal group ${toneClass}`}>
            <div className="cash-ledger-head">
                <div className="cash-ledger-identity">
                    <span className="cash-ledger-brand">
                        <Icon className="size-4" strokeWidth={1.8} />
                    </span>
                    <div>
                        <span className="cash-ledger-title">
                            {metric.label}
                        </span>
                        <small>{metric.note}</small>
                    </div>
                </div>
            </div>
            <div className="cash-ledger-balance mb-0">
                <span>{metric.footer}</span>
                <strong>{metric.value}</strong>
            </div>
        </article>
    );
}

function MovementLink({
    movement,
    base,
}: {
    movement: WalletMovement;
    base: string;
}) {
    return (
        <Link
            href={movementHref(base, movement)}
            className="group flex min-w-48 items-center gap-2.5"
        >
            <span
                className={`grid size-8 shrink-0 place-items-center rounded-lg ${
                    movement.direction === 'credit'
                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200'
                        : 'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-200'
                }`}
            >
                {movement.direction === 'credit' ? (
                    <ArrowDownLeft className="size-4" />
                ) : (
                    <ArrowUpRight className="size-4" />
                )}
            </span>
            <span className="min-w-0">
                <span className="block truncate font-extrabold text-foreground group-hover:text-[var(--kora)]">
                    {movement.holder.name ?? 'Titulaire non renseigné'}
                </span>
                <span className="block text-xs text-muted-foreground">
                    {date(movement.createdAt)}
                </span>
            </span>
        </Link>
    );
}

function FilterPanel({
    filters,
    baseUrl,
    perPage,
}: {
    filters: Filters;
    baseUrl: string;
    perPage: number;
}) {
    const [values, setValues] = useState<Filters>(() => ({
        search: filters.search ?? '',
        balance_type: filters.balance_type ?? '',
        direction: filters.direction ?? '',
        reason: filters.reason ?? '',
        from: filters.from ?? '',
        to: filters.to ?? '',
    }));

    const update = (field: keyof Filters, value: string) =>
        setValues((current) => ({ ...current, [field]: value }));

    const apply = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const query = Object.fromEntries(
            Object.entries(values).filter(
                ([, value]) => value !== '' && value !== undefined,
            ),
        );
        router.get(
            baseUrl,
            { ...query, page: 1, per_page: perPage },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    };

    const reset = () => {
        setValues({
            search: '',
            balance_type: '',
            direction: '',
            reason: '',
            from: '',
            to: '',
        });
        router.get(
            baseUrl,
            { page: 1, per_page: perPage },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    return (
        <form
            onSubmit={apply}
            className="cash-reveal cash-reveal-delay-2 mb-3 flex flex-col gap-3 rounded-[var(--kora-radius)] border border-border bg-card p-3 sm:p-4"
            aria-label="Filtres des mouvements Wallet"
        >
            <div className="grid min-w-0 gap-3 md:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_minmax(12rem,0.7fr)_minmax(13rem,0.8fr)]">
                <div className="flex min-w-0 items-center gap-2 md:col-span-2 xl:col-span-1">
                    <Search className="cash-filter-icon size-4 shrink-0" />
                    <input
                        type="search"
                        value={values.search ?? ''}
                        onChange={(event) =>
                            update('search', event.target.value)
                        }
                        placeholder="Mouvement, titulaire, Wallet ou source BUS…"
                        aria-label="Rechercher un mouvement, un titulaire, un Wallet ou une source BUS"
                        className="cash-filter-search w-full min-w-0"
                    />
                </div>
                <FilterSelect
                    label="Type de solde Wallet"
                    value={values.balance_type ?? ''}
                    onChange={(value) => update('balance_type', value)}
                    options={[
                        ['', 'Tous les soldes'],
                        ['cash', 'Cash'],
                        ['points', 'Points de fidélité'],
                    ]}
                />
                <FilterSelect
                    label="Motif"
                    value={values.reason ?? ''}
                    onChange={(value) => update('reason', value)}
                    options={[
                        ['', 'Tous les motifs'],
                        ['topup', 'Recharge'],
                        ['purchase', 'Achat de billet BUS'],
                        ['refund_cash', 'Remboursement cash'],
                        ['refund_points', 'Remboursement de points'],
                        ['points_earn', 'Points gagnés'],
                        ['bonus_welcome', 'Bonus de bienvenue'],
                        ['bonus_referral', 'Bonus de parrainage'],
                        ['bonus_anniversary', 'Bonus anniversaire'],
                        ['bonus_usage', 'Bonus de fidélité'],
                        ['points_expire', 'Expiration de points'],
                        ['points_correction', 'Correction de points'],
                        ['compensation_offset', 'Compensation de points'],
                        ['restitution', 'Restitution'],
                        ['adjustment', 'Ajustement audité'],
                    ]}
                />
            </div>
            <div className="grid min-w-0 gap-3 border-t border-border pt-3 md:grid-cols-2 xl:grid-cols-[minmax(12rem,0.65fr)_minmax(27rem,1.4fr)_auto]">
                <FilterSelect
                    label="Sens du mouvement"
                    value={values.direction ?? ''}
                    onChange={(value) => update('direction', value)}
                    options={[
                        ['', 'Tous les sens'],
                        ['credit', 'Crédit'],
                        ['debit', 'Débit'],
                    ]}
                />
                <div className="flex min-w-0 flex-col gap-2 md:col-span-2 xl:col-span-1">
                    <span className="flex items-center gap-1.5 text-xs font-extrabold text-muted-foreground">
                        <CalendarDays className="size-3.5" /> Période
                    </span>
                    <div className="grid min-w-0 gap-2 sm:grid-cols-2">
                        <label className="cash-filter-field relative block min-w-0">
                            <span className="sr-only">Du</span>
                            <input
                                type="date"
                                value={values.from ?? ''}
                                onChange={(event) =>
                                    update('from', event.target.value)
                                }
                                aria-label="Date de début"
                                title="Du"
                                className="cash-filter-select h-11 w-full !min-w-0 rounded-xl bg-muted/55 px-3 text-xs font-bold text-foreground ring-1 ring-black/[0.045] outline-none focus:bg-card focus:ring-2 focus:ring-gold/35"
                            />
                        </label>
                        <label className="cash-filter-field relative block min-w-0">
                            <span className="sr-only">Au</span>
                            <input
                                type="date"
                                value={values.to ?? ''}
                                onChange={(event) =>
                                    update('to', event.target.value)
                                }
                                aria-label="Date de fin"
                                title="Au"
                                className="cash-filter-select h-11 w-full !min-w-0 rounded-xl bg-muted/55 px-3 text-xs font-bold text-foreground ring-1 ring-black/[0.045] outline-none focus:bg-card focus:ring-2 focus:ring-gold/35"
                            />
                        </label>
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-2 md:col-span-2 xl:col-span-1 xl:self-end">
                    <button
                        type="submit"
                        className="btn btn-navy cash-filter-submit"
                    >
                        <SlidersHorizontal className="size-4" /> Filtrer
                    </button>
                    <button
                        type="button"
                        className="btn btn-ghost cash-filter-reset"
                        onClick={reset}
                    >
                        Réinitialiser
                    </button>
                </div>
            </div>
        </form>
    );
}

type WalletAccountFilterState = {
    search: string;
    status: string;
    balance_type: string;
    expiration: string;
    agency_uuid: string;
};

const emptyWalletAccountFilters: WalletAccountFilterState = {
    search: '',
    status: '',
    balance_type: '',
    expiration: '',
    agency_uuid: '',
};

const walletAccountPageSize = 10;

function walletExpiryWindowDays(rules?: LoyaltyRules): number {
    const reminderDays = rules?.pointsExpiryReminderDays ?? [30, 7, 1];

    return reminderDays.length > 0 ? Math.max(...reminderDays) : 30;
}

function isWalletAccountExpiringSoon(
    account: WalletAccount,
    reminderWindowDays: number,
) {
    if (!account.pointsExpiresAt || account.balances.points <= 0) {
        return false;
    }

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const expiryWindowEnd = new Date(today);
    expiryWindowEnd.setDate(expiryWindowEnd.getDate() + reminderWindowDays);
    expiryWindowEnd.setHours(23, 59, 59, 999);

    const expiryDate = new Date(account.pointsExpiresAt);

    return expiryDate >= today && expiryDate <= expiryWindowEnd;
}

function walletAccountFiltersFromProps(
    filters?: Filters,
): WalletAccountFilterState {
    return {
        search: filters?.search ?? '',
        status: filters?.status ?? '',
        balance_type: filters?.balance_type ?? '',
        expiration: filters?.expiration ?? '',
        agency_uuid: filters?.agency_uuid ?? '',
    };
}

function walletAccountQuery(
    filters: WalletAccountFilterState,
    page: number,
    perPage: number,
): Record<string, string | number> {
    const query: Record<string, string | number> = {
        page,
        per_page: perPage,
    };

    if (filters.search.trim() !== '') {
        query.search = filters.search.trim();
    }

    if (filters.status !== '') {
        query.status = filters.status;
    }

    if (filters.balance_type !== '') {
        query.balance_type = filters.balance_type;
    }

    if (filters.expiration !== '') {
        query.expiration = filters.expiration;
    }

    if (filters.agency_uuid !== '') {
        query.agency_uuid = filters.agency_uuid;
    }

    return query;
}

function walletAccountDetailHref(
    base: string,
    accountUuid: string,
    filters?: Filters,
): string {
    const query = walletAccountQuery(
        walletAccountFiltersFromProps(filters),
        filters?.page ?? 1,
        filters?.per_page ?? walletAccountPageSize,
    );
    const search = new URLSearchParams(
        Object.entries(query).map(([key, value]) => [key, String(value)]),
    ).toString();

    return `${base.replace(/\/$/, '')}/${accountUuid}?${search}`;
}

function WalletAccountFilters({
    values,
    onChange,
    onApply,
    onReset,
    agencyOptions,
    expirationWindowDays,
}: {
    values: WalletAccountFilterState;
    onChange: (field: keyof WalletAccountFilterState, value: string) => void;
    onApply: (event: FormEvent<HTMLFormElement>) => void;
    onReset: () => void;
    agencyOptions: NonNullable<Props['agencyOptions']>;
    expirationWindowDays: number;
}) {
    return (
        <form
            onSubmit={onApply}
            className="cash-filterbar cash-reveal cash-reveal-delay-2"
            aria-label="Filtres des comptes Wallet"
        >
            <Search className="cash-filter-icon size-4" />
            <input
                type="search"
                value={values.search}
                onChange={(event) => onChange('search', event.target.value)}
                placeholder="Nom, téléphone ou UUID…"
                aria-label="Rechercher par nom, téléphone ou UUID"
                className="cash-filter-search"
            />
            <FilterSelect
                label="Statut passager"
                value={values.status}
                onChange={(value) => onChange('status', value)}
                options={[
                    ['', 'Tous les statuts'],
                    ['active', 'Actif'],
                    ['suspended', 'Suspendu'],
                    ['locked', 'Verrouillé'],
                ]}
            />
            <FilterSelect
                label="Solde positif"
                value={values.balance_type}
                onChange={(value) => onChange('balance_type', value)}
                options={[
                    ['', 'Cash ou points'],
                    ['cash', 'Cash positif'],
                    ['points', 'Points positifs'],
                ]}
            />
            <FilterSelect
                label="Échéance des points"
                value={values.expiration}
                onChange={(value) => onChange('expiration', value)}
                options={[
                    ['', 'Toutes les échéances'],
                    ['near', `Dans les ${expirationWindowDays} jours`],
                ]}
            />
            <FilterSelect
                label="Agence de la dernière activité"
                value={values.agency_uuid}
                onChange={(value) => onChange('agency_uuid', value)}
                options={[
                    ['', 'Toutes les agences'],
                    ...agencyOptions.map((agency) => [
                        agency.uuid,
                        agency.name,
                    ]),
                ]}
            />
            <button type="submit" className="btn btn-navy cash-filter-submit">
                <SlidersHorizontal className="size-4" /> Filtrer
            </button>
            <button
                type="button"
                className="btn btn-ghost cash-filter-reset"
                onClick={onReset}
            >
                Réinitialiser
            </button>
        </form>
    );
}

function WalletAccountDetailContent({
    account,
    expirationWindowDays,
}: {
    account: WalletAccount;
    expirationWindowDays: number;
}) {
    const movementColumns: TableColumn<WalletMovement>[] = [
        {
            key: 'movement',
            header: 'Mouvement',
            render: (movement) => (
                <span className="block min-w-48">
                    <span className="block font-extrabold text-foreground">
                        {movement.reasonLabel}
                    </span>
                    <span className="mt-1 block text-xs text-muted-foreground">
                        {date(movement.createdAt)}
                    </span>
                </span>
            ),
        },
        {
            key: 'balance',
            header: 'Solde',
            render: (movement) => (
                <span
                    className={`inline-flex rounded-full px-2.5 py-1 text-[0.625rem] font-extrabold ${movement.balanceType === 'cash' ? 'bg-sky-100 text-sky-800 dark:bg-sky-950/50 dark:text-sky-200' : 'bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-200'}`}
                >
                    {movement.balanceType === 'cash' ? 'Cash' : 'Points'}
                </span>
            ),
        },
        {
            key: 'source',
            header: 'Source BUS',
            render: (movement) => (
                <span className="block min-w-40">
                    <span className="block text-xs font-bold text-foreground">
                        {movement.source?.label ?? 'Opération BUS'}
                    </span>
                    <span className="mt-1 block font-mono text-[0.65rem] text-muted-foreground">
                        {movement.source?.uuid ?? 'Référence indisponible'}
                    </span>
                </span>
            ),
        },
        {
            key: 'agency',
            header: 'Agence source',
            render: (movement) => (
                <span className="text-xs text-muted-foreground">
                    {movement.agency?.name ?? '—'}
                </span>
            ),
        },
        {
            key: 'amount',
            header: 'Montant',
            align: 'right',
            render: (movement) => (
                <span
                    className={`font-extrabold tabular-nums ${movement.direction === 'credit' ? 'text-emerald-700 dark:text-emerald-300' : 'text-foreground'}`}
                >
                    {movement.direction === 'credit' ? '+' : '−'}
                    {movement.balanceType === 'cash'
                        ? money(movement.amount)
                        : points(movement.amount)}
                </span>
            ),
        },
    ];

    const isExpiringSoon = isWalletAccountExpiringSoon(
        account,
        expirationWindowDays,
    );

    return (
        <div className="cash-reveal flex flex-col gap-5">
            <section className="grid gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(18rem,0.85fr)]">
                <section className="rounded-2xl border border-border bg-card p-5 shadow-[0_8px_28px_rgba(0,29,78,0.035)]">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div className="flex min-w-0 items-start gap-3">
                            <span className="grid size-12 shrink-0 place-items-center rounded-2xl bg-[var(--kora)]/10 text-[var(--kora)]">
                                <UserRound className="size-5" />
                            </span>
                            <div className="min-w-0">
                                <p className="text-sm font-extrabold text-foreground">
                                    {account.holder.phone ?? 'Téléphone absent'}
                                </p>
                                <p className="mt-1 font-mono text-[0.6875rem] break-all text-muted-foreground">
                                    UUID · {account.uuid}
                                </p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Compte créé le {dateOnly(account.createdAt)}
                                </p>
                            </div>
                        </div>
                        {statusPill(account.holder.status)}
                    </div>

                    <div className="mt-5 grid gap-3 sm:grid-cols-2">
                        <article className="rounded-xl border border-border bg-muted/35 p-4">
                            <div className="flex items-center gap-2 text-xs font-extrabold text-muted-foreground">
                                <Banknote className="size-4" /> Cash
                            </div>
                            <p className="mt-2 text-2xl font-black text-foreground tabular-nums">
                                {money(account.balances.cash)}
                            </p>
                        </article>
                        <article className="rounded-xl border border-border bg-gold/10 p-4">
                            <div className="flex items-center gap-2 text-xs font-extrabold text-amber-900 dark:text-amber-200">
                                <Coins className="size-4" /> Points de fidélité
                            </div>
                            <p className="mt-2 text-2xl font-black text-foreground tabular-nums">
                                {points(account.balances.points)}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Valeur d’usage ·{' '}
                                {money(account.balances.pointsValue)}
                            </p>
                        </article>
                    </div>
                </section>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                    <article className="rounded-2xl border border-border bg-card p-5 shadow-[0_8px_28px_rgba(0,29,78,0.035)]">
                        <div className="flex items-center gap-2 text-xs font-extrabold text-muted-foreground">
                            <CalendarClock className="size-4" /> Échéance des
                            points
                        </div>
                        <div className="mt-3 flex flex-wrap items-center gap-2">
                            <p className="text-lg font-black text-foreground">
                                {account.balances.points > 0
                                    ? dateOnly(account.pointsExpiresAt)
                                    : 'Aucun point actif'}
                            </p>
                            {isExpiringSoon ? (
                                <span className="rounded-full bg-amber-100 px-2.5 py-1 text-[0.625rem] font-extrabold text-amber-800 dark:bg-amber-950/50 dark:text-amber-200">
                                    À surveiller
                                </span>
                            ) : null}
                        </div>
                        <p className="mt-1 text-xs leading-5 text-muted-foreground">
                            L’activité BUS qualifiante renouvelle la durée
                            globale des points.
                        </p>
                    </article>
                    <article className="rounded-2xl border border-border bg-card p-5 shadow-[0_8px_28px_rgba(0,29,78,0.035)]">
                        <div className="flex items-center gap-2 text-xs font-extrabold text-muted-foreground">
                            <ShieldCheck className="size-4" /> Compensation de
                            fidélité
                        </div>
                        <p className="mt-3 text-lg font-black text-foreground">
                            {points(account.pointsCompensationDue)}
                        </p>
                        <p className="mt-1 text-xs leading-5 text-muted-foreground">
                            {account.pointsCompensationDue > 0
                                ? 'À déduire des prochains gains de fidélité.'
                                : 'Aucune compensation en attente.'}
                        </p>
                    </article>
                </div>
            </section>
            <section className="cash-reveal cash-reveal-delay-1 flex flex-col gap-3">
                <div>
                    <p className="section-kicker">Traçabilité KORA BUS</p>
                    <h2 className="mt-1 text-lg font-black text-foreground">
                        Mouvements liés à ce compte
                    </h2>
                </div>
                <KoraTable
                    variant="cash"
                    columns={movementColumns}
                    data={account.recentMovements}
                    rowKey={(movement) => movement.uuid}
                    showHeader={false}
                    emptyTitle="Aucune activité BUS"
                    emptyMessage="Les mouvements de ce compte apparaîtront ici après les premières opérations KORA BUS."
                    emptyIcon={History}
                />
            </section>
        </div>
    );
}

function AccountsList({ props }: { props: Props }) {
    const [draftFilters, setDraftFilters] = useState<WalletAccountFilterState>(
        () => walletAccountFiltersFromProps(props.filters),
    );
    const accountsPage = props.accounts;
    const accounts = accountsPage?.data ?? [];
    const perPage =
        accountsPage?.per_page ??
        props.filters?.per_page ??
        walletAccountPageSize;
    const expirationWindowDays = walletExpiryWindowDays(props.activeRules);

    const updateDraftFilter = (
        field: keyof WalletAccountFilterState,
        value: string,
    ) => {
        setDraftFilters((current) => ({ ...current, [field]: value }));
    };

    const applyFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        router.get(
            props.urls.accounts,
            walletAccountQuery(draftFilters, 1, perPage),
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const resetFilters = () => {
        setDraftFilters(emptyWalletAccountFilters);
        router.get(
            props.urls.accounts,
            { page: 1, per_page: perPage },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const columns: TableColumn<WalletAccount>[] = [
        {
            key: 'account',
            header: 'Compte / titulaire',
            render: (row) => (
                <span className="flex min-w-52 items-center gap-3">
                    <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-[var(--kora)]/10 text-[var(--kora)]">
                        <UserRound className="size-4" />
                    </span>
                    <span className="min-w-0">
                        <span className="block truncate font-extrabold text-foreground">
                            {row.holder.name ?? 'Nom non renseigné'}
                        </span>
                        <span className="mt-0.5 block text-xs text-muted-foreground">
                            {row.holder.phone ?? 'Téléphone absent'}
                        </span>
                        <span
                            title={row.uuid}
                            className="mt-0.5 block truncate font-mono text-[0.625rem] text-muted-foreground"
                        >
                            UUID · {row.uuid.slice(0, 8).toUpperCase()}
                        </span>
                    </span>
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Statut passager',
            render: (row) => statusPill(row.holder.status),
        },
        {
            key: 'cash',
            header: 'Cash',
            align: 'right',
            render: (row) => (
                <span className="font-extrabold text-foreground tabular-nums">
                    {money(row.balances.cash)}
                </span>
            ),
        },
        {
            key: 'points',
            header: 'Points',
            align: 'right',
            render: (row) => (
                <span className="inline-flex flex-col items-end">
                    <span className="font-extrabold text-foreground tabular-nums">
                        {points(row.balances.points)}
                    </span>
                    <span className="text-[0.65rem] text-muted-foreground">
                        {money(row.balances.pointsValue)}
                    </span>
                </span>
            ),
        },
        {
            key: 'expiry',
            header: 'Échéance des points',
            render: (row) => (
                <span className="flex min-w-32 flex-col items-start gap-1 text-xs">
                    <span className="text-muted-foreground">
                        {row.balances.points > 0
                            ? dateOnly(row.pointsExpiresAt)
                            : '—'}
                    </span>
                    {isWalletAccountExpiringSoon(row, expirationWindowDays) ? (
                        <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[0.625rem] font-extrabold text-amber-800 dark:bg-amber-950/50 dark:text-amber-200">
                            À surveiller
                        </span>
                    ) : null}
                </span>
            ),
        },
        {
            key: 'activity',
            header: 'Dernière activité BUS',
            render: (row) => (
                <span className="block min-w-40">
                    <span className="block text-xs text-foreground">
                        {date(row.lastActivityAt)}
                    </span>
                    <span className="mt-1 block text-xs text-muted-foreground">
                        {row.agency?.name ?? 'Aucune activité'}
                    </span>
                </span>
            ),
        },
        {
            key: 'detail',
            header: '',
            align: 'right',
            render: (row) => (
                <Link
                    href={walletAccountDetailHref(
                        props.urls.accounts,
                        row.uuid,
                        props.filters,
                    )}
                    className="btn btn-ghost min-h-9 px-2.5 text-xs"
                >
                    Détail <ChevronRight className="size-3.5" />
                </Link>
            ),
        },
    ];

    return (
        <PageFrame
            title="Comptes Wallet"
            description="Retrouvez les comptes voyageurs, leurs soldes séparés et les échéances de points."
            breadcrumb="KORA OPS / Wallet & fidélité / Comptes"
            view="accounts"
            urls={props.urls}
        >
            <WalletAccountFilters
                values={draftFilters}
                onChange={updateDraftFilter}
                onApply={applyFilters}
                onReset={resetFilters}
                agencyOptions={props.agencyOptions ?? []}
                expirationWindowDays={expirationWindowDays}
            />
            <div className="cash-reveal cash-reveal-delay-1">
                <KoraTable
                    variant="cash"
                    columns={columns}
                    data={accounts}
                    rowKey={(row) => row.uuid}
                    pagination={pagination(accountsPage, props.urls.accounts)}
                    showHeader={false}
                    emptyTitle="Aucun compte Wallet"
                    emptyMessage="Aucun compte ne correspond aux filtres. Modifiez la recherche ou réinitialisez les filtres."
                    emptyIcon={Users}
                />
            </div>
        </PageFrame>
    );
}

function Dashboard({ props }: { props: Props }) {
    const dashboard = props.dashboard;
    const activeRules = props.activeRules;
    const expirationWindowDays = walletExpiryWindowDays(activeRules);
    const recentMovements = dashboard?.recentMovements ?? [];
    const metrics: WalletDashboardMetric[] = dashboard
        ? [
              {
                  icon: Users,
                  label: 'Comptes voyageurs',
                  note: 'Comptes Wallet BUS provisionnés',
                  value: dashboard.accountCount.toLocaleString('fr-FR'),
                  footer: 'Comptes existants',
                  tone: 'navy',
              },
              {
                  icon: CalendarClock,
                  label: 'Points proches de l’échéance',
                  note: 'À moins de ' + expirationWindowDays + ' jours',
                  value: points(dashboard.expiringPoints),
                  footer:
                      dashboard.expiringAccountCount.toLocaleString('fr-FR') +
                      ' comptes · ' +
                      money(dashboard.expiringValue),
                  tone: 'gold',
              },
              {
                  icon: Banknote,
                  label: 'Recharges en attente',
                  note: 'Recharge à confirmer ou traiter',
                  value: money(dashboard.pendingTopUpAmount),
                  footer:
                      dashboard.pendingTopUpCount.toLocaleString('fr-FR') +
                      ' recharge(s)',
                  tone: 'digital',
              },
              {
                  icon: ArrowDownLeft,
                  label: 'Restitutions à traiter',
                  note: 'Demandes ouvertes',
                  value: money(dashboard.pendingRestitutionAmount),
                  footer:
                      dashboard.pendingRestitutionCount.toLocaleString(
                          'fr-FR',
                      ) + ' demande(s)',
                  tone: 'emerald',
              },
          ]
        : [];

    const movementColumns: TableColumn<WalletMovement>[] = [
        {
            key: 'holder',
            header: 'Mouvement',
            render: (movement) => (
                <span className="block min-w-48">
                    <MovementLink
                        movement={movement}
                        base={props.urls.transactions}
                    />
                    <span className="mt-1 block pl-10 text-[0.6875rem] text-muted-foreground">
                        {movement.reasonLabel}
                    </span>
                </span>
            ),
        },
        {
            key: 'balance',
            header: 'Solde',
            render: (movement) => (
                <span
                    className={
                        'inline-flex rounded-full px-2.5 py-1 text-[0.625rem] font-extrabold ' +
                        (movement.balanceType === 'cash'
                            ? 'bg-sky-100 text-sky-800 dark:bg-sky-950/50 dark:text-sky-200'
                            : 'bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-200')
                    }
                >
                    {movement.balanceType === 'cash' ? 'Cash' : 'Points'}
                </span>
            ),
        },
        {
            key: 'agency',
            header: 'Agence source',
            render: (movement) => (
                <span className="text-xs text-muted-foreground">
                    {movement.agency?.name ?? '—'}
                </span>
            ),
        },
        {
            key: 'amount',
            header: 'Montant',
            align: 'right',
            render: (movement) => (
                <span
                    className={
                        'text-xs font-black tabular-nums ' +
                        (movement.direction === 'credit'
                            ? 'text-emerald-700 dark:text-emerald-300'
                            : 'text-foreground')
                    }
                >
                    {movement.direction === 'credit' ? '+' : '−'}
                    {movement.balanceType === 'cash'
                        ? money(movement.amount)
                        : points(movement.amount)}
                </span>
            ),
        },
    ];

    return (
        <PageFrame
            title="Pilotage Wallet BUS"
            description="Suivez les soldes, les points à échéance et les opérations Wallet à traiter."
            breadcrumb="KORA OPS / Wallet & fidélité / Pilotage"
            view="dashboard"
            urls={props.urls}
            actions={
                <Button variant="kora" size="kora" asChild>
                    <Link href={props.urls.transactions}>
                        Contrôler les mouvements
                        <ArrowUpRight className="size-4" />
                    </Link>
                </Button>
            }
        >
            <section
                aria-labelledby="wallet-dashboard-title"
                className="cash-reveal relative isolate grid min-h-[21rem] overflow-hidden rounded-[1.75rem] bg-[var(--kora)] p-6 text-white shadow-[0_28px_65px_rgba(0,29,78,0.18)] ring-1 ring-black/10 sm:p-8 lg:grid-cols-[minmax(0,1.25fr)_minmax(20rem,0.75fr)] lg:items-center lg:gap-8 lg:p-10 dark:ring-white/10"
            >
                <div className="pointer-events-none absolute -right-24 -bottom-48 -z-10 size-[32rem] rounded-full border border-gold/15" />
                <div className="pointer-events-none absolute top-8 -right-10 -z-10 size-52 rounded-full border border-white/10" />

                <div className="relative z-10 flex flex-col justify-between gap-7">
                    <div>
                        <span className="mb-3 block text-[0.625rem] font-black tracking-[0.18em] text-gold uppercase">
                            KORA BUS · Wallet
                        </span>
                        <h2
                            id="wallet-dashboard-title"
                            className="max-w-2xl text-[clamp(2rem,3.5vw,3.375rem)] leading-[1.02] font-extrabold tracking-[-0.045em] text-balance"
                        >
                            Soldes séparés,
                            <br />
                            <em className="font-normal text-gold not-italic">
                                opérations traçables.
                            </em>
                        </h2>
                        <p className="mt-4 max-w-[37.5rem] text-sm leading-[1.6] text-white/65">
                            Consultez la valeur du cash et des points en
                            circulation, puis ouvrez les comptes ou les
                            mouvements associés.
                        </p>
                    </div>

                    <div className="mt-1 flex flex-wrap gap-2.5">
                        <Link
                            href={props.urls.accounts}
                            className="btn btn-primary"
                        >
                            Voir les comptes
                            <ArrowUpRight className="size-4" />
                        </Link>
                        <Link
                            href={props.urls.rules}
                            className="inline-flex min-h-11 items-center gap-2 rounded-[0.75rem] border border-white/20 bg-white/[0.07] px-4 text-[0.75rem] font-extrabold text-white transition-colors duration-300 hover:border-white/40 hover:bg-white/12 focus-visible:ring-2 focus-visible:ring-gold focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--kora)] motion-reduce:transition-none"
                        >
                            Règles de fidélité
                        </Link>
                    </div>
                </div>

                <div className="relative z-10 mt-7 rounded-[1.375rem] border border-white/15 bg-white/[0.075] p-5 shadow-[inset_0_1px_rgba(255,255,255,0.1)] backdrop-blur-md sm:p-6 lg:mt-0">
                    <span className="block text-[0.625rem] font-black tracking-[0.12em] text-white/50 uppercase">
                        Cash en circulation
                    </span>
                    <strong className="mt-2 block text-[clamp(1.75rem,3vw,2.25rem)] leading-tight font-extrabold tracking-[-0.04em] tabular-nums">
                        {dashboard ? money(dashboard.cashTotal) : '—'}
                    </strong>
                    <span className="mt-1 block text-xs text-white/55">
                        Solde cash cumulé des Wallets passagers
                    </span>
                    <div className="mt-5 grid grid-cols-2 gap-4 border-t border-white/12 pt-4">
                        <div>
                            <span className="block text-[0.625rem] font-black tracking-[0.1em] text-white/45 uppercase">
                                Points en circulation
                            </span>
                            <strong className="mt-1.5 block text-sm font-extrabold text-white tabular-nums">
                                {dashboard
                                    ? points(dashboard.pointsTotal)
                                    : '—'}
                            </strong>
                        </div>
                        <div>
                            <span className="block text-[0.625rem] font-black tracking-[0.1em] text-white/45 uppercase">
                                Valeur d’usage
                            </span>
                            <strong className="mt-1.5 block text-sm font-extrabold text-gold tabular-nums">
                                {dashboard ? money(dashboard.pointsValue) : '—'}
                            </strong>
                        </div>
                    </div>
                </div>
            </section>

            <section
                aria-label="Indicateurs Wallet KORA BUS"
                className="grid grid-cols-1 gap-3.5 sm:grid-cols-2 xl:grid-cols-4"
            >
                {metrics.map((metric) => (
                    <WalletDashboardMetricCard
                        key={metric.label}
                        metric={metric}
                    />
                ))}
            </section>

            <section className="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1.55fr)_minmax(18rem,0.85fr)]">
                <div className="cash-reveal cash-reveal-delay-1 min-w-0">
                    <KoraTable
                        title="Derniers mouvements"
                        subtitle="Opérations récentes du ledger Wallet BUS"
                        icon={Activity}
                        actions={
                            <Button
                                variant="kora-secondary"
                                size="kora"
                                asChild
                            >
                                <Link href={props.urls.transactions}>
                                    Tout afficher
                                    <ArrowRight className="size-3.5" />
                                </Link>
                            </Button>
                        }
                        columns={movementColumns}
                        data={recentMovements}
                        rowKey={(movement) => movement.uuid}
                        emptyTitle="Aucun mouvement récent"
                        emptyMessage="Les mouvements du Wallet BUS apparaîtront ici après les premières opérations."
                        emptyIcon={Activity}
                        variant="standard"
                    />
                </div>

                <aside className="cash-reveal cash-reveal-delay-2 rounded-[var(--kora-radius)] border border-border bg-card p-5 shadow-[0_8px_30px_rgba(0,29,78,0.045)]">
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <span className="section-kicker">
                                Fidélité KORA BUS
                            </span>
                            <h2 className="mt-1 text-[0.9375rem] font-extrabold tracking-[-0.025em] text-foreground">
                                Règles actives
                            </h2>
                        </div>
                        {activeRules ? (
                            <span className="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[0.625rem] font-extrabold text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">
                                <BadgeCheck className="size-3.5" />
                                {props.activeRuleVersion
                                    ? 'Version ' +
                                      props.activeRuleVersion +
                                      ' · Active'
                                    : 'Paramètres actifs'}
                            </span>
                        ) : null}
                    </div>

                    {activeRules ? (
                        <dl className="mt-5 divide-y divide-border/70">
                            <div className="flex items-baseline justify-between gap-3 py-3 first:pt-0">
                                <dt className="text-xs text-muted-foreground">
                                    Acquisition
                                </dt>
                                <dd className="text-right text-xs font-extrabold text-foreground">
                                    {(
                                        activeRules.loyaltyEarnRateBasisPoints /
                                        100
                                    ).toLocaleString('fr-FR')}{' '}
                                    % du billet éligible
                                </dd>
                            </div>
                            <div className="flex items-baseline justify-between gap-3 py-3">
                                <dt className="text-xs text-muted-foreground">
                                    Valeur d’un point
                                </dt>
                                <dd className="text-right text-xs font-extrabold text-foreground">
                                    {activeRules.pointValueFcfa.toLocaleString(
                                        'fr-FR',
                                    )}{' '}
                                    FCFA
                                </dd>
                            </div>
                            <div className="flex items-baseline justify-between gap-3 py-3">
                                <dt className="text-xs text-muted-foreground">
                                    Utilisation minimale
                                </dt>
                                <dd className="text-right text-xs font-extrabold text-foreground">
                                    {points(activeRules.pointsMinSpend)}
                                </dd>
                            </div>
                            <div className="flex items-baseline justify-between gap-3 py-3 last:pb-0">
                                <dt className="text-xs text-muted-foreground">
                                    Expiration
                                </dt>
                                <dd className="text-right text-xs font-extrabold text-foreground">
                                    {activeRules.pointsExpiryMonths} mois{' '}
                                    {activeRules.pointsExpiryReminderDays
                                        .length > 0
                                        ? '· J−' +
                                          activeRules.pointsExpiryReminderDays.join(
                                              ' · J−',
                                          )
                                        : '· aucun rappel'}
                                </dd>
                            </div>
                        </dl>
                    ) : (
                        <p className="mt-5 text-sm text-muted-foreground">
                            Les paramètres actifs de fidélité sont
                            indisponibles.
                        </p>
                    )}

                    <Link
                        href={props.urls.rules}
                        className="group mt-5 inline-flex items-center gap-2 border-t border-border pt-4 text-xs font-extrabold text-[var(--kora)] transition-colors hover:text-gold motion-reduce:transition-none"
                    >
                        Consulter les règles
                        <ArrowRight className="size-3.5 transition-transform group-hover:translate-x-1 motion-reduce:transition-none" />
                    </Link>
                </aside>
            </section>
        </PageFrame>
    );
}

function AccountDetail({ props }: { props: Props }) {
    const account = props.account;

    if (!account) {
        return null;
    }

    const holderLabel = account.holder.name ?? 'Compte Wallet';
    const returnUrl = props.returnUrl ?? props.urls.accounts;

    return (
        <PageFrame
            title={holderLabel}
            description="Fiche de compte passager KORA BUS avec soldes séparés, compensation et activité récente."
            breadcrumb={
                'KORA OPS / Wallet & fidélité / Comptes / ' + holderLabel
            }
            view="account"
            urls={props.urls}
            actions={
                <Link href={returnUrl} className="btn btn-ghost">
                    <ArrowLeft className="size-4" />
                    Retour à la liste
                </Link>
            }
        >
            <WalletAccountDetailContent
                account={account}
                expirationWindowDays={walletExpiryWindowDays(props.activeRules)}
            />
        </PageFrame>
    );
}

function TransactionsList({ props }: { props: Props }) {
    const perPage = props.movements?.per_page ?? props.filters?.per_page ?? 25;

    return (
        <PageFrame
            title="Mouvements Wallet"
            description="Journal des écritures effectivement passées sur les soldes cash et points KORA BUS, de la plus récente à la plus ancienne."
            breadcrumb="KORA OPS / Wallet & fidélité / Mouvements"
            view="transactions"
            urls={props.urls}
        >
            <FilterPanel
                key={JSON.stringify(props.filters ?? {})}
                filters={props.filters ?? {}}
                baseUrl={props.urls.transactions}
                perPage={perPage}
            />
            <div className="cash-reveal cash-reveal-delay-1">
                <KoraTable
                    variant="cash"
                    columns={[
                        {
                            key: 'movement',
                            header: 'Mouvement',
                            render: (row) => (
                                <Link
                                    href={movementHref(
                                        props.urls.transactions,
                                        row,
                                        props.filters,
                                    )}
                                    className="group flex min-w-44 flex-col items-start gap-1 text-left"
                                >
                                    <span
                                        title={row.uuid}
                                        className="font-mono text-[0.625rem] font-bold tracking-wide text-muted-foreground group-hover:text-[var(--kora)]"
                                    >
                                        {row.uuid.slice(0, 8).toUpperCase()}
                                    </span>
                                    <span className="text-xs font-extrabold text-foreground group-hover:text-[var(--kora)]">
                                        {row.reasonLabel}
                                    </span>
                                    <span className="text-[0.6875rem] text-muted-foreground">
                                        {date(row.createdAt)}
                                    </span>
                                </Link>
                            ),
                        },
                        {
                            key: 'account',
                            header: 'Compte / titulaire',
                            render: (row) => (
                                <span className="block min-w-44">
                                    <span className="block truncate text-xs font-extrabold text-foreground">
                                        {row.holder.name ??
                                            'Titulaire non renseigné'}
                                    </span>
                                    <span className="mt-0.5 block text-[0.6875rem] text-muted-foreground">
                                        {row.holder.phone ?? 'Téléphone absent'}
                                    </span>
                                    <span
                                        title={row.walletUuid}
                                        className="mt-0.5 block font-mono text-[0.625rem] text-muted-foreground"
                                    >
                                        Wallet ·{' '}
                                        {row.walletUuid
                                            .slice(0, 8)
                                            .toUpperCase()}
                                    </span>
                                </span>
                            ),
                        },
                        {
                            key: 'balance',
                            header: 'Solde / sens',
                            render: (row) => (
                                <span className="flex min-w-24 flex-col items-start gap-1.5">
                                    <span
                                        className={`inline-flex rounded-full px-2.5 py-1 text-[0.625rem] font-extrabold ${row.balanceType === 'cash' ? 'bg-sky-100 text-sky-800 dark:bg-sky-950/50 dark:text-sky-200' : 'bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-200'}`}
                                    >
                                        {row.balanceType === 'cash'
                                            ? 'Cash'
                                            : 'Points'}
                                    </span>
                                    <span
                                        className={`inline-flex items-center gap-1 text-[0.6875rem] font-bold ${row.direction === 'credit' ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300'}`}
                                    >
                                        {row.direction === 'credit' ? (
                                            <ArrowDownLeft className="size-3" />
                                        ) : (
                                            <ArrowUpRight className="size-3" />
                                        )}
                                        {row.direction === 'credit'
                                            ? 'Crédit'
                                            : 'Débit'}
                                    </span>
                                </span>
                            ),
                        },
                        {
                            key: 'amount',
                            header: 'Montant signé',
                            align: 'right',
                            render: (row) => (
                                <span
                                    className={`font-black whitespace-nowrap tabular-nums ${row.direction === 'credit' ? 'text-emerald-700 dark:text-emerald-300' : 'text-foreground'}`}
                                >
                                    {row.direction === 'credit' ? '+' : '−'}
                                    {row.balanceType === 'cash'
                                        ? money(row.amount)
                                        : points(row.amount)}
                                </span>
                            ),
                        },
                        {
                            key: 'source',
                            header: 'Source BUS',
                            render: (row) => (
                                <span className="block min-w-36">
                                    <span className="block text-xs font-bold text-foreground">
                                        {row.source?.label ??
                                            'Aucune source liée'}
                                    </span>
                                    {row.source ? (
                                        <span
                                            title={row.source.uuid}
                                            className="mt-0.5 block font-mono text-[0.625rem] text-muted-foreground"
                                        >
                                            {row.source.uuid
                                                .slice(0, 8)
                                                .toUpperCase()}
                                        </span>
                                    ) : null}
                                </span>
                            ),
                        },
                        {
                            key: 'agency',
                            header: 'Agence source',
                            render: (row) => (
                                <span className="text-xs text-muted-foreground">
                                    {row.agency?.name ?? '—'}
                                </span>
                            ),
                        },
                        {
                            key: 'detail',
                            header: 'Détail',
                            align: 'right',
                            render: (row) => (
                                <Link
                                    href={movementHref(
                                        props.urls.transactions,
                                        row,
                                        props.filters,
                                    )}
                                    aria-label={`Détail du mouvement ${row.uuid}`}
                                    className="grid size-8 place-items-center rounded-lg border border-border text-muted-foreground hover:text-foreground"
                                >
                                    <ChevronRight className="size-4" />
                                </Link>
                            ),
                        },
                    ]}
                    data={props.movements?.data ?? []}
                    rowKey={(row) => row.uuid}
                    pagination={pagination(
                        props.movements,
                        props.urls.transactions,
                    )}
                    showHeader={false}
                    emptyTitle="Aucun mouvement trouvé"
                    emptyMessage="Aucune écriture du ledger ne correspond à ces filtres. Modifiez la recherche ou réinitialisez les filtres."
                    emptyIcon={Activity}
                />
            </div>
        </PageFrame>
    );
}

function MovementDetail({ props }: { props: Props }) {
    const movement = props.movement;

    if (!movement) {
        return null;
    }

    const isCredit = movement.direction === 'credit';
    const returnUrl = props.returnUrl ?? props.urls.transactions;

    return (
        <PageFrame
            title="Détail du mouvement"
            description="Détail d’une écriture du ledger Wallet, de son compte et de sa source métier KORA BUS."
            breadcrumb={`KORA OPS / Wallet & fidélité / Mouvements / ${movement.uuid}`}
            view="transaction"
            urls={props.urls}
            actions={
                <Link href={returnUrl} className="btn btn-ghost">
                    <ArrowLeft className="size-4" />
                    Retour aux mouvements
                </Link>
            }
        >
            <section className="cash-reveal grid gap-4 xl:grid-cols-[1fr_0.85fr]">
                <article className="rounded-2xl border border-border bg-card p-5 shadow-[0_8px_28px_rgba(0,29,78,0.035)] sm:p-6">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <span
                            className={`inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-extrabold ${isCredit ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200' : 'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-200'}`}
                        >
                            {isCredit ? (
                                <ArrowDownLeft className="size-4" />
                            ) : (
                                <ArrowUpRight className="size-4" />
                            )}
                            {isCredit ? 'Crédit' : 'Débit'} ·{' '}
                            {movement.balanceType === 'cash'
                                ? 'Cash'
                                : 'Points'}
                        </span>
                        <span className="font-mono text-[0.68rem] text-muted-foreground">
                            {movement.uuid}
                        </span>
                    </div>
                    <p
                        className={`mt-6 text-4xl font-black tracking-tight tabular-nums ${isCredit ? 'text-emerald-700 dark:text-emerald-300' : 'text-foreground'}`}
                    >
                        {isCredit ? '+' : '−'}
                        {movement.balanceType === 'cash'
                            ? money(movement.amount)
                            : points(movement.amount)}
                    </p>
                    <h2 className="mt-2 text-lg font-extrabold text-foreground">
                        {movement.reasonLabel}
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Enregistré le {date(movement.createdAt)}
                    </p>
                    <div className="mt-6 grid gap-3 border-t border-border pt-5 sm:grid-cols-2">
                        <div>
                            <p className="text-[0.65rem] font-extrabold tracking-wide text-muted-foreground uppercase">
                                Nature du solde
                            </p>
                            <p className="mt-1 text-sm font-bold text-foreground">
                                {movement.balanceType === 'cash'
                                    ? 'Wallet cash'
                                    : 'Points de fidélité BUS'}
                            </p>
                        </div>
                        <div>
                            <p className="text-[0.65rem] font-extrabold tracking-wide text-muted-foreground uppercase">
                                Agence d’origine
                            </p>
                            <p className="mt-1 text-sm font-bold text-foreground">
                                {movement.agency?.name ??
                                    'Aucune agence associée'}
                            </p>
                        </div>
                        <div>
                            <p className="text-[0.65rem] font-extrabold tracking-wide text-muted-foreground uppercase">
                                Version de règle de fidélité
                            </p>
                            <p className="mt-1 text-sm font-bold text-foreground">
                                {movement.balanceType === 'cash'
                                    ? 'Non applicable au solde cash'
                                    : movement.loyaltyRuleVersion
                                      ? `Version ${movement.loyaltyRuleVersion.version}`
                                      : 'Aucune version associée à cette écriture antérieure'}
                            </p>
                        </div>
                    </div>
                </article>
                <div className="grid content-start gap-3">
                    <article className="rounded-2xl border border-border bg-card p-5 shadow-[0_8px_28px_rgba(0,29,78,0.035)]">
                        <p className="section-kicker">Titulaire</p>
                        <p className="mt-2 font-black text-foreground">
                            {movement.holder.name ?? 'Nom non renseigné'}
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {movement.holder.phone ?? 'Téléphone absent'}
                        </p>
                        <p
                            title={movement.walletUuid}
                            className="mt-2 font-mono text-[0.625rem] text-muted-foreground"
                        >
                            Wallet · {movement.walletUuid}
                        </p>
                        <Link
                            href={`${props.urls.accounts}/${movement.walletUuid}`}
                            className="mt-4 inline-flex items-center gap-1 text-xs font-extrabold text-[var(--kora)]"
                        >
                            Ouvrir le compte <ArrowRight className="size-3.5" />
                        </Link>
                    </article>
                    {movement.actorName ? (
                        <article className="rounded-2xl border border-border bg-card p-5 shadow-[0_8px_28px_rgba(0,29,78,0.035)]">
                            <p className="section-kicker">
                                Auteur de l’écriture
                            </p>
                            <p className="mt-2 font-extrabold text-foreground">
                                {movement.actorName}
                            </p>
                        </article>
                    ) : null}
                    <article className="rounded-2xl border border-border bg-card p-5 shadow-[0_8px_28px_rgba(0,29,78,0.035)]">
                        <p className="section-kicker">Rapprochement métier</p>
                        {movement.source ? (
                            <div className="mt-2">
                                <p className="font-extrabold text-foreground">
                                    {movement.source.label}
                                </p>
                                <p className="mt-1 font-mono text-[0.68rem] break-all text-muted-foreground">
                                    {movement.source.uuid}
                                </p>
                                {movement.source.url ? (
                                    <Button
                                        asChild
                                        size="sm"
                                        variant="outline"
                                        className="mt-4"
                                    >
                                        <Link href={movement.source.url}>
                                            <Ticket className="size-3.5" /> Voir
                                            la source BUS{' '}
                                            <ArrowRight className="size-3.5" />
                                        </Link>
                                    </Button>
                                ) : (
                                    <p className="mt-3 text-xs text-muted-foreground">
                                        La source est identifiée; aucun écran de
                                        détail OPS n’est disponible pour ce type
                                        d’objet.
                                    </p>
                                )}
                            </div>
                        ) : (
                            <p className="mt-2 text-sm text-muted-foreground">
                                Cette écriture n’a pas de source métier liée.
                            </p>
                        )}
                    </article>
                </div>
            </section>
        </PageFrame>
    );
}

function Field({
    icon: Icon,
    label,
    required = true,
    hint,
    children,
}: {
    icon: LucideIcon;
    label: string;
    required?: boolean;
    hint?: string;
    children: React.ReactNode;
}) {
    return (
        <label className="block min-w-0">
            <span className="mb-1.5 flex items-center gap-1.5 text-xs font-extrabold text-foreground">
                <Icon className="size-3.5 text-muted-foreground" /> {label}
                {required && (
                    <span className="text-rose-600" aria-hidden="true">
                        *
                    </span>
                )}
            </span>
            {children}
            {hint && (
                <span className="mt-1 block text-[0.68rem] leading-4 text-muted-foreground">
                    {hint}
                </span>
            )}
        </label>
    );
}

function RuleFields({
    value,
    setValue,
    disabled,
}: {
    value: LoyaltyRules;
    setValue: (name: keyof LoyaltyRules, next: number | number[]) => void;
    disabled: boolean;
}) {
    const number = (name: keyof LoyaltyRules, min: number, max: number) => (
        <input
            type="number"
            min={min}
            max={max}
            required
            disabled={disabled}
            value={value[name] as number}
            onChange={(event) => setValue(name, Number(event.target.value))}
            className="cash-filter-select w-full disabled:cursor-not-allowed disabled:opacity-60"
        />
    );

    return (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <Field
                icon={Activity}
                label="Acquisition des points"
                hint={`${(value.loyaltyEarnRateBasisPoints / 100).toFixed(2)} % de la part monétaire éligible`}
            >
                {number('loyaltyEarnRateBasisPoints', 0, 10000)}
            </Field>
            <Field
                icon={CircleDollarSign}
                label="Valeur d’usage d’un point"
                hint="Valeur indicative communiquée au passager, en FCFA."
            >
                {number('pointValueFcfa', 1, 100000)}
            </Field>
            <Field
                icon={Coins}
                label="Minimum d’utilisation"
                hint="Nombre de points requis pour payer avec la fidélité."
            >
                {number('pointsMinSpend', 1, 1000000)}
            </Field>
            <Field icon={Gift} label="Bonus de bienvenue">
                {number('bonusWelcomePoints', 0, 1000000)}
            </Field>
            <Field icon={Users} label="Bonus de parrainage">
                {number('bonusReferralPoints', 0, 1000000)}
            </Field>
            <Field icon={BadgeCheck} label="Bonus anniversaire">
                {number('bonusAnniversaryPoints', 0, 1000000)}
            </Field>
            <Field
                icon={Ticket}
                label="Voyages du mois avant bonus"
                hint="Le bonus s’accorde une fois, au Nᵉ voyage admissible du mois."
            >
                {number('bonusUsageNth', 1, 1000)}
            </Field>
            <Field icon={Gift} label="Bonus de fidélité">
                {number('bonusUsagePoints', 0, 1000000)}
            </Field>
            <Field
                icon={CalendarClock}
                label="Durée avant expiration"
                hint="La durée s’appliquera au prochain événement qualifiant."
            >
                {number('pointsExpiryMonths', 1, 120)}
            </Field>
            <Field
                icon={CalendarDays}
                label="Rappels avant échéance (jours)"
                hint="Séparez les délais par des virgules, par exemple 30, 7, 1."
            >
                <input
                    type="text"
                    required
                    disabled={disabled}
                    value={value.pointsExpiryReminderDays.join(', ')}
                    onChange={(event) => {
                        const next = event.target.value
                            .split(',')
                            .map((item) => Number(item.trim()))
                            .filter(
                                (item) => Number.isFinite(item) && item > 0,
                            );
                        setValue('pointsExpiryReminderDays', next);
                    }}
                    className="cash-filter-select w-full disabled:cursor-not-allowed disabled:opacity-60"
                />
            </Field>
        </div>
    );
}

function RulesPage({ props }: { props: Props }) {
    const activeRules = props.activeRules ?? defaultRules;
    const draft = props.draft ?? null;
    const canManage = Boolean(props.capabilities?.manageRules);
    const [simulation, setSimulation] = useState<{
        ticket_amount: number;
        points_used: number;
        qualifying_trips: number;
        first_usage: boolean;
    }>({
        ticket_amount: 15000,
        points_used: 0,
        qualifying_trips: 0,
        first_usage: false,
    });
    const [simulationResult, setSimulationResult] = useState<Record<
        string,
        number | string | boolean
    > | null>(null);
    const [simulating, setSimulating] = useState(false);
    const [effectiveAt, setEffectiveAt] = useState('');
    const ruleForm = useForm<{ values: LoyaltyRules }>({
        values: draft?.rules ?? activeRules,
    });

    const setRule = (name: keyof LoyaltyRules, next: number | number[]) => {
        ruleForm.setData('values', { ...ruleForm.data.values, [name]: next });
    };

    const saveDraft = (onSuccess?: () => void) => {
        if (!draft) {
            return;
        }

        ruleForm.patch(`${props.urls.rules}/${draft.uuid}`, {
            preserveScroll: true,
            onSuccess,
        });
    };

    const runSimulation = async () => {
        if (!draft) {
            return;
        }

        setSimulating(true);
        setSimulationResult(null);

        try {
            const token = decodeURIComponent(
                document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
            );
            const response = await fetch(
                `${props.urls.rules}/${draft.uuid}/simulate`,
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': token,
                    },
                    body: JSON.stringify(simulation),
                },
            );

            if (!response.ok) {
                throw new Error('La simulation n’a pas abouti.');
            }

            const payload = (await response.json()) as {
                data: Record<string, number | string | boolean>;
            };
            setSimulationResult(payload.data);
            toast.success('Simulation calculée sans écriture dans le Wallet.');
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Erreur de simulation.',
            );
        } finally {
            setSimulating(false);
        }
    };

    const simulateAfterSave = () => {
        if (ruleForm.isDirty) {
            saveDraft(() => void runSimulation());
        } else {
            void runSimulation();
        }
    };

    const scheduleDraft = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!draft || !effectiveAt) {
            return;
        }

        const schedule = () =>
            router.post(
                `${props.urls.rules}/${draft.uuid}/schedule`,
                { effective_at: new Date(effectiveAt).toISOString() },
                { preserveScroll: true },
            );

        if (ruleForm.isDirty) {
            saveDraft(schedule);
        } else {
            schedule();
        }
    };

    const versions = props.versions ?? [];
    const activeVersion = versions.find(
        (version) => version.status === 'active',
    );
    const scheduledVersion = versions.find(
        (version) => version.status === 'scheduled',
    );

    return (
        <PageFrame
            title="Règles de fidélité"
            description="Une version unique gouverne l’acquisition, l’usage, les bonus et l’expiration des points KORA BUS."
            view="rules"
            urls={props.urls}
        >
            <section className="cash-reveal grid gap-4 xl:grid-cols-[0.9fr_1.1fr]">
                <article className="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-5 shadow-[0_8px_28px_rgba(0,29,78,0.035)] dark:border-emerald-900 dark:bg-emerald-950/25">
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <p className="text-[0.7rem] font-extrabold tracking-wide text-emerald-800 uppercase dark:text-emerald-200">
                                Version appliquée
                            </p>
                            <h2 className="mt-1 text-xl font-black text-foreground">
                                {activeVersion
                                    ? `Version ${activeVersion.version}`
                                    : 'Initialisation requise'}
                            </h2>
                        </div>
                        <span className="grid size-10 place-items-center rounded-xl bg-white/80 text-emerald-700 shadow-sm dark:bg-black/10 dark:text-emerald-200">
                            <CheckCircle2 className="size-5" />
                        </span>
                    </div>
                    {activeVersion ? (
                        <p className="mt-2 text-xs text-muted-foreground">
                            Activée le {date(activeVersion.activatedAt)}
                        </p>
                    ) : (
                        <p className="mt-2 text-xs text-muted-foreground">
                            Initialisez la version depuis les paramètres actifs
                            avant toute publication.
                        </p>
                    )}
                    <RuleSummary rules={activeRules} />
                </article>
                <article className="rounded-2xl border border-border bg-card p-5 shadow-[0_8px_28px_rgba(0,29,78,0.035)]">
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <p className="section-kicker">
                                Cycle de publication
                            </p>
                            <h2 className="mt-1 text-lg font-black text-foreground">
                                Brouillon → simulation → programmation
                            </h2>
                        </div>
                        <span className="grid size-10 place-items-center rounded-xl bg-gold/15 text-amber-800 dark:text-amber-200">
                            <SlidersHorizontal className="size-5" />
                        </span>
                    </div>
                    <p className="mt-3 text-sm leading-6 text-muted-foreground">
                        La DAF et l’administration publient. La direction des
                        opérations consulte. Une version programmée s’active
                        automatiquement à sa date d’effet.
                    </p>
                    {scheduledVersion && (
                        <div className="mt-4 flex items-center gap-2 rounded-xl border border-sky-200 bg-sky-50 p-3 text-xs font-bold text-sky-900 dark:border-sky-900 dark:bg-sky-950/30 dark:text-sky-100">
                            <CalendarClock className="size-4 shrink-0" />{' '}
                            Version {scheduledVersion.version} programmée pour
                            le {date(scheduledVersion.effectiveAt)}.
                        </div>
                    )}
                    {!canManage && (
                        <p className="mt-3 inline-flex items-center gap-1.5 text-xs font-bold text-muted-foreground">
                            <LockKeyhole className="size-3.5" /> Consultation en
                            lecture seule.
                        </p>
                    )}
                </article>
            </section>

            {canManage && !draft && (
                <section className="cash-reveal cash-reveal-delay-1 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-dashed border-border bg-card p-5">
                    <div>
                        <h2 className="font-black text-foreground">
                            Préparer une évolution
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Le brouillon repart de la version active. Les
                            versions publiées restent immuables.
                        </p>
                    </div>
                    <Button
                        onClick={() =>
                            router.post(
                                props.urls.rules,
                                {},
                                { preserveScroll: true },
                            )
                        }
                        disabled={!activeVersion}
                        className="bg-[var(--kora)] text-white hover:bg-[var(--kora)]/90"
                    >
                        <SlidersHorizontal className="size-4" /> Créer un
                        brouillon
                    </Button>
                </section>
            )}

            {draft && (
                <section className="cash-reveal cash-reveal-delay-1 grid gap-4 xl:grid-cols-[1.15fr_0.85fr]">
                    <article className="rounded-2xl border border-border bg-card p-5 shadow-[0_8px_28px_rgba(0,29,78,0.035)]">
                        <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p className="section-kicker">
                                    Brouillon · version {draft.version}
                                </p>
                                <h2 className="mt-1 text-lg font-black text-foreground">
                                    Paramètres de fidélité
                                </h2>
                            </div>
                            <span
                                className={`rounded-full px-2.5 py-1 text-[0.6875rem] font-extrabold ${versionTone.draft}`}
                            >
                                Non publiée
                            </span>
                        </div>
                        <RuleFields
                            value={ruleForm.data.values}
                            setValue={setRule}
                            disabled={!canManage || ruleForm.processing}
                        />
                        {Object.keys(ruleForm.errors).length > 0 && (
                            <p className="mt-3 text-xs font-semibold text-rose-700">
                                Vérifiez les champs en erreur avant de
                                continuer.
                            </p>
                        )}
                        {canManage && (
                            <div className="mt-5 flex flex-wrap justify-end gap-2 border-t border-border pt-4">
                                <Button
                                    variant="outline"
                                    disabled={ruleForm.processing}
                                    onClick={() => saveDraft()}
                                >
                                    <CheckCircle2 className="size-3.5" />{' '}
                                    Enregistrer le brouillon
                                </Button>
                            </div>
                        )}
                    </article>

                    <div className="grid content-start gap-4">
                        <article className="rounded-2xl border border-border bg-card p-5 shadow-[0_8px_28px_rgba(0,29,78,0.035)]">
                            <div className="flex items-center gap-2">
                                <Ticket className="size-4 text-[var(--kora)]" />
                                <h2 className="font-black text-foreground">
                                    Simulation d’un billet BUS
                                </h2>
                            </div>
                            <p className="mt-2 text-xs leading-5 text-muted-foreground">
                                Le calcul est temporaire. Il n’écrit ni
                                mouvement, ni solde, ni échéance dans un Wallet.
                            </p>
                            <div className="mt-4 grid gap-3 sm:grid-cols-2">
                                <Field
                                    icon={CircleDollarSign}
                                    label="Montant du billet (FCFA)"
                                >
                                    <input
                                        type="number"
                                        min={1}
                                        required
                                        value={simulation.ticket_amount}
                                        onChange={(event) =>
                                            setSimulation((state) => ({
                                                ...state,
                                                ticket_amount: Number(
                                                    event.target.value,
                                                ),
                                            }))
                                        }
                                        className="cash-filter-select w-full"
                                    />
                                </Field>
                                <Field icon={Coins} label="Points utilisés">
                                    <input
                                        type="number"
                                        min={0}
                                        value={simulation.points_used}
                                        onChange={(event) =>
                                            setSimulation((state) => ({
                                                ...state,
                                                points_used: Number(
                                                    event.target.value,
                                                ),
                                            }))
                                        }
                                        className="cash-filter-select w-full"
                                    />
                                </Field>
                                <Field
                                    icon={History}
                                    label="Voyages qualifiants déjà effectués ce mois"
                                >
                                    <input
                                        type="number"
                                        min={0}
                                        value={simulation.qualifying_trips}
                                        onChange={(event) =>
                                            setSimulation((state) => ({
                                                ...state,
                                                qualifying_trips: Number(
                                                    event.target.value,
                                                ),
                                            }))
                                        }
                                        className="cash-filter-select w-full"
                                    />
                                </Field>
                                <label className="flex items-center gap-2 self-end rounded-xl border border-border px-3 py-2.5 text-sm font-semibold text-foreground">
                                    <input
                                        type="checkbox"
                                        checked={simulation.first_usage}
                                        onChange={(event) =>
                                            setSimulation((state) => ({
                                                ...state,
                                                first_usage:
                                                    event.target.checked,
                                            }))
                                        }
                                        className="size-4 accent-[var(--kora)]"
                                    />
                                    Première utilisation Wallet
                                </label>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                className="mt-4 w-full"
                                disabled={simulating || ruleForm.processing}
                                onClick={simulateAfterSave}
                            >
                                {simulating
                                    ? 'Calcul en cours…'
                                    : 'Enregistrer et simuler'}{' '}
                                <ArrowRight className="size-3.5" />
                            </Button>
                            {simulationResult && (
                                <div className="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900 dark:bg-emerald-950/25">
                                    <p className="text-xs font-extrabold text-emerald-900 dark:text-emerald-100">
                                        Résultat sans écriture
                                    </p>
                                    <div className="mt-2 grid grid-cols-2 gap-2 text-xs">
                                        <span className="text-muted-foreground">
                                            Base éligible
                                        </span>
                                        <strong className="text-right">
                                            {money(
                                                Number(
                                                    simulationResult.eligibleAmount,
                                                ),
                                            )}
                                        </strong>
                                        <span className="text-muted-foreground">
                                            Points acquis
                                        </span>
                                        <strong className="text-right">
                                            {points(
                                                Number(
                                                    simulationResult.pointsEarned,
                                                ),
                                            )}
                                        </strong>
                                        <span className="text-muted-foreground">
                                            Bonus de bienvenue
                                        </span>
                                        <strong className="text-right">
                                            {points(
                                                Number(
                                                    simulationResult.welcomeBonusPoints,
                                                ),
                                            )}
                                        </strong>
                                        <span className="text-muted-foreground">
                                            Bonus d’usage
                                        </span>
                                        <strong className="text-right">
                                            {points(
                                                Number(
                                                    simulationResult.usageBonusPoints,
                                                ),
                                            )}
                                        </strong>
                                        <span className="text-muted-foreground">
                                            Total projeté
                                        </span>
                                        <strong className="text-right">
                                            {points(
                                                Number(
                                                    simulationResult.projectedPoints,
                                                ),
                                            )}
                                        </strong>
                                        <span className="text-muted-foreground">
                                            Échéance projetée
                                        </span>
                                        <strong className="text-right">
                                            {date(
                                                String(
                                                    simulationResult.expiryAt,
                                                ),
                                            )}
                                        </strong>
                                    </div>
                                </div>
                            )}
                        </article>

                        {canManage && (
                            <form
                                onSubmit={scheduleDraft}
                                className="rounded-2xl border border-amber-200 bg-amber-50/70 p-5 shadow-[0_8px_28px_rgba(0,29,78,0.035)] dark:border-amber-900 dark:bg-amber-950/20"
                            >
                                <div className="flex items-center gap-2">
                                    <CalendarClock className="size-4 text-amber-800 dark:text-amber-200" />
                                    <h2 className="font-black text-foreground">
                                        Programmer l’activation
                                    </h2>
                                </div>
                                <p className="mt-2 text-xs leading-5 text-muted-foreground">
                                    À l’échéance, les paramètres actifs seront
                                    remplacés de façon atomique. Les échéances
                                    déjà inscrites sur les comptes ne changent
                                    pas.
                                </p>
                                <Field
                                    icon={CalendarDays}
                                    label="Date et heure d’effet"
                                >
                                    <input
                                        type="datetime-local"
                                        required
                                        min={minimumLocalDateTime()}
                                        value={effectiveAt}
                                        onChange={(event) =>
                                            setEffectiveAt(event.target.value)
                                        }
                                        className="cash-filter-select w-full"
                                    />
                                </Field>
                                <Button
                                    type="submit"
                                    disabled={
                                        !effectiveAt ||
                                        ruleForm.processing ||
                                        props.capabilities?.hasScheduledVersion
                                    }
                                    className="mt-4 w-full bg-[var(--kora)] text-white hover:bg-[var(--kora)]/90"
                                >
                                    Programmer la version{' '}
                                    <ArrowRight className="size-3.5" />
                                </Button>
                                {props.capabilities?.hasScheduledVersion && (
                                    <p className="mt-2 text-xs font-semibold text-amber-900 dark:text-amber-100">
                                        Une autre version est déjà programmée.
                                    </p>
                                )}
                            </form>
                        )}
                    </div>
                </section>
            )}

            <section className="cash-reveal cash-reveal-delay-2 rounded-2xl border border-border bg-card p-4 shadow-[0_8px_28px_rgba(0,29,78,0.035)] sm:p-5">
                <div className="mb-4">
                    <p className="section-kicker">Historique immuable</p>
                    <h2 className="mt-1 text-lg font-black text-foreground">
                        Versions de règle
                    </h2>
                </div>
                <div className="divide-y divide-border">
                    {versions.map((version) => (
                        <details
                            key={version.uuid}
                            className="group py-3 first:pt-0 last:pb-0"
                        >
                            <summary className="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3 rounded-xl px-2 py-2 hover:bg-muted/40 [&::-webkit-details-marker]:hidden">
                                <span className="flex items-center gap-3">
                                    <span className="grid size-9 place-items-center rounded-xl bg-muted text-muted-foreground">
                                        <History className="size-4" />
                                    </span>
                                    <span>
                                        <span className="block font-extrabold text-foreground">
                                            Version {version.version}
                                        </span>
                                        <span className="block text-xs text-muted-foreground">
                                            {version.activatedAt
                                                ? `Activée ${date(version.activatedAt)}`
                                                : version.effectiveAt
                                                  ? `Effet prévu ${date(version.effectiveAt)}`
                                                  : `Créée ${date(version.publishedAt)}`}
                                        </span>
                                    </span>
                                </span>
                                <span className="flex items-center gap-2">
                                    <span
                                        className={`rounded-full px-2.5 py-1 text-[0.6875rem] font-extrabold ${versionTone[version.status]}`}
                                    >
                                        {versionStatus[version.status]}
                                    </span>
                                    <ChevronRight className="size-4 text-muted-foreground transition-transform group-open:rotate-90 motion-reduce:transition-none" />
                                </span>
                            </summary>
                            <div className="mt-2 ml-12 rounded-xl bg-muted/35 p-4">
                                <RuleSummary rules={version.rules} />
                                <p className="mt-3 text-[0.68rem] text-muted-foreground">
                                    Préparée par{' '}
                                    {version.createdBy ?? 'Système'}
                                    {version.scheduledBy
                                        ? ` · programmée par ${version.scheduledBy}`
                                        : ''}
                                </p>
                            </div>
                        </details>
                    ))}
                    {versions.length === 0 && (
                        <div className="py-10 text-center">
                            <History className="mx-auto size-8 text-muted-foreground/50" />
                            <p className="mt-2 font-extrabold text-foreground">
                                Aucune version initialisée
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                La version de départ reprend les paramètres
                                actifs de cet environnement.
                            </p>
                        </div>
                    )}
                </div>
            </section>
        </PageFrame>
    );
}

function RuleSummary({ rules }: { rules: LoyaltyRules }) {
    const rows = [
        [
            'Acquisition',
            `${(rules.loyaltyEarnRateBasisPoints / 100).toFixed(2)} %`,
        ],
        [
            'Valeur d’usage',
            `${rules.pointValueFcfa.toLocaleString('fr-FR')} FCFA / point`,
        ],
        ['Minimum d’usage', points(rules.pointsMinSpend)],
        ['Bonus bienvenue', points(rules.bonusWelcomePoints)],
        ['Bonus parrainage', points(rules.bonusReferralPoints)],
        ['Bonus anniversaire', points(rules.bonusAnniversaryPoints)],
        [
            `Bonus tous les ${rules.bonusUsageNth} voyages`,
            points(rules.bonusUsagePoints),
        ],
        [
            'Expiration',
            `${rules.pointsExpiryMonths} mois après activité qualifiante`,
        ],
        [
            'Rappels',
            rules.pointsExpiryReminderDays.map((day) => `J-${day}`).join(' · '),
        ],
    ];

    return (
        <dl className="mt-4 grid gap-x-4 gap-y-2 sm:grid-cols-2">
            {rows.map(([label, value]) => (
                <div
                    key={label}
                    className="flex items-baseline justify-between gap-2 border-b border-border/70 pb-1.5"
                >
                    <dt className="text-xs text-muted-foreground">{label}</dt>
                    <dd className="text-right text-xs font-extrabold text-foreground">
                        {value}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

export default function WalletOpsPage(props: Props) {
    useEffect(() => {
        if (
            props.view === 'dashboard' &&
            new URLSearchParams(window.location.search).get('focus') ===
                'expiry'
        ) {
            const frame = requestAnimationFrame(() => {
                const reducedMotion = window.matchMedia(
                    '(prefers-reduced-motion: reduce)',
                ).matches;
                document.getElementById('wallet-expiry')?.scrollIntoView({
                    behavior: reducedMotion ? 'auto' : 'smooth',
                    block: 'center',
                });
            });

            return () => cancelAnimationFrame(frame);
        }
    }, [props.view]);

    switch (props.view) {
        case 'dashboard':
            return <Dashboard props={props} />;
        case 'accounts':
            return (
                <AccountsList
                    key={JSON.stringify(props.filters ?? {})}
                    props={props}
                />
            );
        case 'account':
            return <AccountDetail props={props} />;
        case 'transactions':
            return <TransactionsList props={props} />;
        case 'transaction':
            return <MovementDetail props={props} />;
        case 'rules':
            return (
                <RulesPage
                    key={props.draft?.uuid ?? 'no-draft'}
                    props={props}
                />
            );
    }
}
