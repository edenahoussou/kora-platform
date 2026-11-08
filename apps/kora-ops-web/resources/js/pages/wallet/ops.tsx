import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
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
    Filter,
    Gift,
    History,
    Landmark,
    LockKeyhole,
    Search,
    ShieldCheck,
    SlidersHorizontal,
    Ticket,
    UserRound,
    Users,
    WalletCards,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
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
    movements?: Paginated<WalletMovement>;
    movement?: WalletMovement;
    filters?: Filters;
    agencyOptions?: { uuid: string; name: string }[];
    activeRules?: LoyaltyRules;
    versions?: RuleVersion[];
    draft?: { uuid: string; version: number; rules: LoyaltyRules } | null;
    capabilities?: {
        manageRules?: boolean;
        manageSettings?: boolean;
        hasScheduledVersion?: boolean;
    };
};

type WalletCapabilities = {
    walletOpsView?: boolean;
    walletRulesView?: boolean;
    walletSettingsView?: boolean;
    walletRestitutionQueueView?: boolean;
    walletRestitutionOwnView?: boolean;
    walletRestitutionRequest?: boolean;
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
    const isActive = status === 'active';

    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[0.6875rem] font-extrabold ${
                isActive
                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200'
                    : 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200'
            }`}
        >
            <span className="size-1.5 rounded-full bg-current" />
            {labelForStatus(status)}
        </span>
    );
}

function movementHref(base: string, movement: WalletMovement) {
    return `${base.replace(/\/$/, '')}/${movement.uuid}`;
}

function WalletNavigation({
    active,
    urls,
}: {
    active: View;
    urls: Props['urls'];
}) {
    const { auth } = usePage().props as {
        auth?: { user?: { walletCapabilities?: WalletCapabilities } };
    };
    const links = [
        {
            label: 'Pilotage',
            href: urls.dashboard,
            active: active === 'dashboard',
            capability: 'walletOpsView' as const,
        },
        {
            label: 'Comptes',
            href: urls.accounts,
            active: active === 'accounts' || active === 'account',
            capability: 'walletOpsView' as const,
        },
        {
            label: 'Mouvements',
            href: urls.transactions,
            active: active === 'transactions' || active === 'transaction',
            capability: 'walletOpsView' as const,
        },
        {
            label: 'Règles de fidélité',
            href: urls.rules,
            active: active === 'rules',
            capability: 'walletRulesView' as const,
        },
        {
            label: 'Paramètres',
            href: urls.settings,
            active: false,
            capability: 'walletSettingsView' as const,
        },
        {
            label: 'Restitutions',
            href: urls.restitutions,
            active: false,
            capability: 'walletRestitutionAccess' as const,
        },
    ];
    const capabilities = auth?.user?.walletCapabilities;
    const visibleLinks = links.filter((link) => {
        if (link.capability === 'walletRestitutionAccess') {
            return Boolean(
                capabilities?.walletRestitutionQueueView ||
                capabilities?.walletRestitutionOwnView ||
                capabilities?.walletRestitutionRequest,
            );
        }

        return Boolean(capabilities?.[link.capability]);
    });

    return (
        <nav
            aria-label="Navigation Wallet"
            className="cash-reveal flex gap-1 overflow-x-auto border-b border-border pb-1"
        >
            {visibleLinks.map((link) => (
                <Link
                    key={link.label}
                    href={link.href}
                    className={`shrink-0 rounded-t-xl px-3.5 py-2.5 text-xs font-extrabold transition-colors motion-reduce:transition-none sm:px-4 ${
                        link.active
                            ? 'border-b-2 border-gold bg-gold/10 text-foreground'
                            : 'text-muted-foreground hover:bg-muted/70 hover:text-foreground'
                    }`}
                >
                    {link.label}
                </Link>
            ))}
        </nav>
    );
}

function PageFrame({
    title,
    description,
    view,
    urls,
    children,
    eyebrow = 'Wallet KORA BUS',
}: {
    title: string;
    description: string;
    view: View;
    urls: Props['urls'];
    children: React.ReactNode;
    eyebrow?: string;
}) {
    return (
        <>
            <Head title={title} />
            <main className="cash-page flex flex-col gap-5 pb-8">
                <header className="kora-page-head cash-reveal mb-1 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <span className="section-kicker">{eyebrow}</span>
                        <h1 className="mt-1 text-2xl font-black tracking-tight text-foreground sm:text-[1.75rem]">
                            {title}
                        </h1>
                        <p className="mt-1 max-w-3xl text-sm leading-6 text-muted-foreground">
                            {description}
                        </p>
                    </div>
                    {view === 'account' || view === 'transaction' ? (
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
                    ) : null}
                </header>
                <WalletNavigation active={view} urls={urls} />
                {children}
            </main>
        </>
    );
}

function StatCard({
    icon: Icon,
    label,
    value,
    detail,
    tone,
}: {
    icon: LucideIcon;
    label: string;
    value: string;
    detail: string;
    tone: 'blue' | 'green' | 'gold' | 'purple' | 'amber' | 'rose';
}) {
    const tones = {
        blue: 'border-sky-200/80 bg-sky-50/80 text-sky-800 dark:border-sky-900 dark:bg-sky-950/30 dark:text-sky-200',
        green: 'border-emerald-200/80 bg-emerald-50/80 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200',
        gold: 'border-amber-200/80 bg-amber-50/90 text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200',
        purple: 'border-violet-200/80 bg-violet-50/80 text-violet-800 dark:border-violet-900 dark:bg-violet-950/30 dark:text-violet-200',
        amber: 'border-orange-200/80 bg-orange-50/80 text-orange-800 dark:border-orange-900 dark:bg-orange-950/30 dark:text-orange-200',
        rose: 'border-rose-200/80 bg-rose-50/80 text-rose-800 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-200',
    };

    return (
        <article
            className={`cash-reveal rounded-2xl border p-4 shadow-[0_7px_24px_rgba(0,29,78,0.035)] sm:p-5 ${tones[tone]}`}
        >
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-[0.7rem] font-extrabold tracking-wide uppercase opacity-75">
                        {label}
                    </p>
                    <p className="mt-2 text-2xl font-black tracking-tight tabular-nums sm:text-[1.7rem]">
                        {value}
                    </p>
                    <p className="mt-1 text-xs font-semibold opacity-75">
                        {detail}
                    </p>
                </div>
                <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-white/70 shadow-sm dark:bg-black/10">
                    <Icon className="size-5" />
                </span>
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

function MovementTable({
    data,
    urls,
    emptyTitle = 'Aucun mouvement Wallet',
}: {
    data: WalletMovement[];
    urls: Props['urls'];
    emptyTitle?: string;
}) {
    const columns: TableColumn<WalletMovement>[] = [
        {
            key: 'holder',
            header: 'Titulaire',
            render: (row) => (
                <MovementLink movement={row} base={urls.transactions} />
            ),
        },
        {
            key: 'account',
            header: 'Compte',
            render: (row) => (
                <Link
                    href={`${urls.accounts}/${row.walletUuid}`}
                    className="font-mono text-[0.7rem] text-muted-foreground hover:text-foreground"
                >
                    {row.walletUuid.slice(0, 8)}…
                </Link>
            ),
        },
        {
            key: 'type',
            header: 'Solde',
            render: (row) => (
                <span className="inline-flex items-center gap-1.5 rounded-full bg-muted px-2.5 py-1 text-[0.6875rem] font-extrabold text-foreground">
                    {row.balanceType === 'cash' ? (
                        <Banknote className="size-3.5" />
                    ) : (
                        <Coins className="size-3.5" />
                    )}
                    {row.balanceType === 'cash' ? 'Cash' : 'Points'}
                </span>
            ),
        },
        {
            key: 'reason',
            header: 'Motif',
            render: (row) => (
                <span className="text-xs font-bold text-foreground">
                    {row.reasonLabel}
                </span>
            ),
        },
        {
            key: 'amount',
            header: 'Montant',
            align: 'right',
            render: (row) => (
                <span
                    className={`font-black tabular-nums ${row.direction === 'credit' ? 'text-emerald-700 dark:text-emerald-300' : 'text-foreground'}`}
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
            render: (row) =>
                row.source ? (
                    <span className="flex max-w-44 flex-col">
                        <span className="truncate text-xs font-bold text-foreground">
                            {row.source.label}
                        </span>
                        <span className="font-mono text-[0.65rem] text-muted-foreground">
                            {row.source.uuid.slice(0, 8)}…
                        </span>
                    </span>
                ) : (
                    <span className="text-xs text-muted-foreground">—</span>
                ),
        },
        {
            key: 'agency',
            header: 'Agence',
            render: (row) => (
                <span className="text-xs text-muted-foreground">
                    {row.agency?.name ?? '—'}
                </span>
            ),
        },
    ];

    return (
        <KoraTable
            variant="cash"
            columns={columns}
            data={data}
            rowKey={(row) => row.uuid}
            showHeader={false}
            emptyTitle={emptyTitle}
            emptyMessage="Les opérations BUS apparaîtront ici après les premiers mouvements du Wallet."
            emptyIcon={Activity}
        />
    );
}

function FilterField({
    icon: Icon,
    label,
    children,
}: {
    icon: LucideIcon;
    label: string;
    children: React.ReactNode;
}) {
    return (
        <label className="block min-w-0">
            <span className="mb-1.5 flex items-center gap-1.5 text-xs font-extrabold text-foreground">
                <Icon className="size-3.5 text-muted-foreground" /> {label}
            </span>
            {children}
        </label>
    );
}

function FilterPanel({
    kind,
    filters,
    agencyOptions,
    baseUrl,
}: {
    kind: 'accounts' | 'transactions';
    filters: Filters;
    agencyOptions: NonNullable<Props['agencyOptions']>;
    baseUrl: string;
}) {
    const [values, setValues] = useState<Filters>(filters);

    const update = (field: keyof Filters, value: string) =>
        setValues((current) => ({ ...current, [field]: value }));

    const apply = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const query = Object.fromEntries(
            Object.entries(values).filter(
                ([, value]) => value !== '' && value !== undefined,
            ),
        );
        router.get(baseUrl, query, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    const reset = () => {
        setValues({});
        router.get(
            baseUrl,
            {},
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    return (
        <form
            onSubmit={apply}
            className="cash-filterbar cash-reveal flex flex-col gap-3 rounded-2xl border border-border bg-card p-4 shadow-[0_8px_28px_rgba(0,29,78,0.035)]"
            aria-label={
                kind === 'accounts'
                    ? 'Filtres des comptes Wallet'
                    : 'Filtres des mouvements Wallet'
            }
        >
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <FilterField icon={Search} label="Titulaire, téléphone ou UUID">
                    <span className="relative block">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            value={values.search ?? ''}
                            onChange={(event) =>
                                update('search', event.target.value)
                            }
                            placeholder="Rechercher…"
                            className="cash-filter-select w-full pl-9"
                        />
                    </span>
                </FilterField>
                {kind === 'accounts' ? (
                    <>
                        <FilterField icon={ShieldCheck} label="Statut passager">
                            <select
                                value={values.status ?? ''}
                                onChange={(event) =>
                                    update('status', event.target.value)
                                }
                                className="cash-filter-select w-full"
                            >
                                <option value="">Tous les statuts</option>
                                <option value="active">Actif</option>
                                <option value="suspended">Suspendu</option>
                                <option value="locked">Verrouillé</option>
                            </select>
                        </FilterField>
                        <FilterField icon={WalletCards} label="Type de solde">
                            <select
                                value={values.balance_type ?? ''}
                                onChange={(event) =>
                                    update('balance_type', event.target.value)
                                }
                                className="cash-filter-select w-full"
                            >
                                <option value="">Tous les comptes</option>
                                <option value="cash">Cash positif</option>
                                <option value="points">Points positifs</option>
                            </select>
                        </FilterField>
                        <FilterField
                            icon={CalendarClock}
                            label="Échéance des points"
                        >
                            <select
                                value={values.expiration ?? ''}
                                onChange={(event) =>
                                    update('expiration', event.target.value)
                                }
                                className="cash-filter-select w-full"
                            >
                                <option value="">Toutes les échéances</option>
                                <option value="near">Proche échéance</option>
                            </select>
                        </FilterField>
                    </>
                ) : (
                    <>
                        <FilterField icon={WalletCards} label="Type de solde">
                            <select
                                value={values.balance_type ?? ''}
                                onChange={(event) =>
                                    update('balance_type', event.target.value)
                                }
                                className="cash-filter-select w-full"
                            >
                                <option value="">Tous les soldes</option>
                                <option value="cash">Cash</option>
                                <option value="points">Points</option>
                            </select>
                        </FilterField>
                        <FilterField
                            icon={ArrowUpRight}
                            label="Sens du mouvement"
                        >
                            <select
                                value={values.direction ?? ''}
                                onChange={(event) =>
                                    update('direction', event.target.value)
                                }
                                className="cash-filter-select w-full"
                            >
                                <option value="">Tous les sens</option>
                                <option value="credit">Crédit</option>
                                <option value="debit">Débit</option>
                            </select>
                        </FilterField>
                        <FilterField icon={Filter} label="Motif">
                            <select
                                value={values.reason ?? ''}
                                onChange={(event) =>
                                    update('reason', event.target.value)
                                }
                                className="cash-filter-select w-full"
                            >
                                <option value="">Tous les motifs</option>
                                {[
                                    ['topup', 'Recharge'],
                                    ['purchase', 'Achat de billet BUS'],
                                    ['refund_cash', 'Remboursement cash'],
                                    [
                                        'refund_points',
                                        'Remboursement de points',
                                    ],
                                    ['points_earn', 'Points gagnés'],
                                    ['bonus_welcome', 'Bonus de bienvenue'],
                                    ['bonus_referral', 'Bonus de parrainage'],
                                    ['bonus_anniversary', 'Bonus anniversaire'],
                                    ['bonus_usage', 'Bonus de fidélité'],
                                    ['points_expire', 'Expiration de points'],
                                    [
                                        'points_correction',
                                        'Correction de points',
                                    ],
                                    [
                                        'compensation_offset',
                                        'Compensation de points',
                                    ],
                                    ['restitution', 'Restitution'],
                                    ['adjustment', 'Ajustement audité'],
                                ].map(([value, label]) => (
                                    <option key={value} value={value}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                        </FilterField>
                    </>
                )}
                <FilterField icon={Landmark} label="Agence">
                    <select
                        value={values.agency_uuid ?? ''}
                        onChange={(event) =>
                            update('agency_uuid', event.target.value)
                        }
                        className="cash-filter-select w-full"
                    >
                        <option value="">Toutes les agences</option>
                        {agencyOptions.map((agency) => (
                            <option key={agency.uuid} value={agency.uuid}>
                                {agency.name}
                            </option>
                        ))}
                    </select>
                </FilterField>
                <FilterField icon={CalendarDays} label="Depuis le">
                    <input
                        type="date"
                        value={values.from ?? ''}
                        onChange={(event) => update('from', event.target.value)}
                        className="cash-filter-select w-full"
                    />
                </FilterField>
                <FilterField icon={CalendarDays} label="Jusqu’au">
                    <input
                        type="date"
                        value={values.to ?? ''}
                        onChange={(event) => update('to', event.target.value)}
                        className="cash-filter-select w-full"
                    />
                </FilterField>
            </div>
            <div className="flex flex-wrap items-center justify-end gap-2 border-t border-border/70 pt-3">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={reset}
                >
                    Réinitialiser
                </Button>
                <Button
                    type="submit"
                    size="sm"
                    className="bg-[var(--kora)] text-white hover:bg-[var(--kora)]/90"
                >
                    <Search className="size-3.5" /> Appliquer les filtres
                </Button>
            </div>
        </form>
    );
}

function AccountsList({ props }: { props: Props }) {
    const accounts = props.accounts;
    const filters = props.filters ?? {};
    const agencies = props.agencyOptions ?? [];
    const columns: TableColumn<WalletAccount>[] = [
        {
            key: 'holder',
            header: 'Titulaire',
            render: (row) => (
                <Link
                    href={`${props.urls.accounts}/${row.uuid}`}
                    className="group flex min-w-48 items-center gap-3"
                >
                    <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-[var(--kora)]/10 text-[var(--kora)]">
                        <UserRound className="size-4" />
                    </span>
                    <span className="min-w-0">
                        <span className="block truncate font-extrabold text-foreground group-hover:text-[var(--kora)]">
                            {row.holder.name ?? 'Nom non renseigné'}
                        </span>
                        <span className="block text-xs text-muted-foreground">
                            {row.holder.phone ?? 'Téléphone absent'}
                        </span>
                    </span>
                </Link>
            ),
        },
        {
            key: 'status',
            header: 'Passager',
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
            header: 'Échéance',
            render: (row) => (
                <span className="text-xs text-muted-foreground">
                    {date(row.pointsExpiresAt)}
                </span>
            ),
        },
        {
            key: 'agency',
            header: 'Dernière agence',
            render: (row) => (
                <span className="text-xs text-muted-foreground">
                    {row.agency?.name ?? '—'}
                </span>
            ),
        },
        {
            key: 'activity',
            header: 'Dernière activité',
            render: (row) => (
                <span className="text-xs text-muted-foreground">
                    {date(row.lastActivityAt)}
                </span>
            ),
        },
    ];

    return (
        <>
            <FilterPanel
                key={JSON.stringify(filters)}
                kind="accounts"
                filters={filters}
                agencyOptions={agencies}
                baseUrl={props.urls.accounts}
            />
            <div className="cash-reveal cash-reveal-delay-1">
                <KoraTable
                    variant="cash"
                    columns={columns}
                    data={accounts?.data ?? []}
                    rowKey={(row) => row.uuid}
                    pagination={pagination(accounts, props.urls.accounts)}
                    showHeader={false}
                    emptyTitle="Aucun compte Wallet"
                    emptyMessage="Aucun compte ne correspond aux filtres. Les Wallets sont créés lors du premier événement admissible."
                    emptyIcon={Users}
                />
            </div>
        </>
    );
}

function Dashboard({ props }: { props: Props }) {
    const data = props.dashboard;

    if (!data) {
        return null;
    }

    const movementColumns: TableColumn<WalletMovement>[] = [
        {
            key: 'holder',
            header: 'Titulaire',
            render: (row) => (
                <MovementLink movement={row} base={props.urls.transactions} />
            ),
        },
        {
            key: 'reason',
            header: 'Motif',
            render: (row) => (
                <span className="text-xs font-bold text-foreground">
                    {row.reasonLabel}
                </span>
            ),
        },
        {
            key: 'balance',
            header: 'Solde',
            render: (row) => (
                <span className="text-xs font-semibold text-muted-foreground">
                    {row.balanceType === 'cash' ? 'Cash' : 'Points'}
                </span>
            ),
        },
        {
            key: 'amount',
            header: 'Montant',
            align: 'right',
            render: (row) => (
                <span className="font-black tabular-nums">
                    {row.direction === 'credit' ? '+' : '−'}
                    {row.balanceType === 'cash'
                        ? money(row.amount)
                        : points(row.amount)}
                </span>
            ),
        },
        {
            key: 'agency',
            header: 'Agence',
            render: (row) => (
                <span className="text-xs text-muted-foreground">
                    {row.agency?.name ?? '—'}
                </span>
            ),
        },
        {
            key: 'detail',
            header: '',
            align: 'right',
            render: (row) => (
                <Link
                    href={movementHref(props.urls.transactions, row)}
                    className="inline-flex items-center gap-1 text-xs font-extrabold text-[var(--kora)]"
                >
                    Détail <ChevronRight className="size-3.5" />
                </Link>
            ),
        },
    ];

    return (
        <PageFrame
            title="Pilotage Wallet BUS"
            description="Vue d’ensemble des comptes voyageurs, des soldes, des échéances de points et des opérations en attente."
            view="dashboard"
            urls={props.urls}
        >
            <section
                className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
                aria-label="Indicateurs Wallet BUS"
            >
                <StatCard
                    icon={Users}
                    label="Comptes voyageurs"
                    value={data.accountCount.toLocaleString('fr-FR')}
                    detail="Comptes Wallet provisionnés"
                    tone="blue"
                />
                <StatCard
                    icon={Banknote}
                    label="Cash en circulation"
                    value={money(data.cashTotal)}
                    detail="Solde cash cumulé"
                    tone="green"
                />
                <StatCard
                    icon={Coins}
                    label="Points en circulation"
                    value={points(data.pointsTotal)}
                    detail={`Valeur d’usage : ${money(data.pointsValue)}`}
                    tone="gold"
                />
                <Link
                    id="wallet-expiry"
                    href={`${props.urls.accounts}?expiration=near`}
                    className="block rounded-2xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold"
                >
                    <StatCard
                        icon={CalendarClock}
                        label="Points proches d’expirer"
                        value={points(data.expiringPoints)}
                        detail={`${data.expiringAccountCount} comptes · ${money(data.expiringValue)} de valeur d’usage`}
                        tone="purple"
                    />
                </Link>
                <StatCard
                    icon={CircleDollarSign}
                    label="Recharges en attente"
                    value={data.pendingTopUpCount.toLocaleString('fr-FR')}
                    detail={money(data.pendingTopUpAmount)}
                    tone="amber"
                />
                <Link
                    href={props.urls.restitutions}
                    className="block rounded-2xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold"
                >
                    <StatCard
                        icon={WalletCards}
                        label="Restitutions à traiter"
                        value={data.pendingRestitutionCount.toLocaleString(
                            'fr-FR',
                        )}
                        detail={money(data.pendingRestitutionAmount)}
                        tone="rose"
                    />
                </Link>
            </section>

            <section className="cash-reveal cash-reveal-delay-1 rounded-2xl border border-border bg-card p-4 shadow-[0_8px_28px_rgba(0,29,78,0.035)] sm:p-5">
                <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p className="section-kicker">Activité récente</p>
                        <h2 className="mt-1 text-lg font-black text-foreground">
                            Derniers mouvements BUS
                        </h2>
                    </div>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={props.urls.transactions}>
                            Tous les mouvements{' '}
                            <ArrowRight className="size-3.5" />
                        </Link>
                    </Button>
                </div>
                <KoraTable
                    variant="cash"
                    columns={movementColumns}
                    data={data.recentMovements}
                    rowKey={(row) => row.uuid}
                    showHeader={false}
                    emptyTitle="Aucune activité pour le moment"
                    emptyMessage="Les mouvements Wallet apparaîtront ici au fil des opérations KORA BUS."
                    emptyIcon={History}
                />
            </section>
        </PageFrame>
    );
}

function AccountDetail({ props }: { props: Props }) {
    const account = props.account;

    if (!account) {
        return null;
    }

    return (
        <PageFrame
            title={account.holder.name ?? 'Compte Wallet'}
            description="Fiche de compte passager KORA BUS avec soldes séparés, compensation et activité récente."
            view="account"
            urls={props.urls}
        >
            <section className="cash-reveal grid gap-4 xl:grid-cols-[1.15fr_0.85fr]">
                <div className="rounded-2xl border border-border bg-card p-5 shadow-[0_8px_28px_rgba(0,29,78,0.035)]">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div className="flex items-start gap-3">
                            <span className="grid size-12 place-items-center rounded-2xl bg-[var(--kora)]/10 text-[var(--kora)]">
                                <UserRound className="size-5" />
                            </span>
                            <div>
                                <p className="text-lg font-black text-foreground">
                                    {account.holder.name ?? 'Nom non renseigné'}
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {account.holder.phone ?? 'Téléphone absent'}
                                </p>
                                <p className="mt-2 font-mono text-[0.6875rem] text-muted-foreground">
                                    Compte {account.uuid}
                                </p>
                            </div>
                        </div>
                        {statusPill(account.holder.status)}
                    </div>
                    <div className="mt-5 grid gap-3 sm:grid-cols-2">
                        <div className="rounded-xl border border-border bg-muted/35 p-4">
                            <div className="flex items-center gap-2 text-xs font-extrabold text-muted-foreground">
                                <Banknote className="size-4" /> Solde cash
                            </div>
                            <p className="mt-2 text-2xl font-black text-foreground tabular-nums">
                                {money(account.balances.cash)}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Fonds utilisables sur les achats KORA BUS
                            </p>
                        </div>
                        <div className="rounded-xl border border-border bg-violet-50/60 p-4 dark:bg-violet-950/20">
                            <div className="flex items-center gap-2 text-xs font-extrabold text-violet-800 dark:text-violet-200">
                                <Coins className="size-4" /> Solde fidélité
                            </div>
                            <p className="mt-2 text-2xl font-black text-foreground tabular-nums">
                                {points(account.balances.points)}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Valeur d’usage{' '}
                                {money(account.balances.pointsValue)}
                            </p>
                        </div>
                    </div>
                </div>
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                    <article className="rounded-2xl border border-border bg-card p-5 shadow-[0_8px_28px_rgba(0,29,78,0.035)]">
                        <div className="flex items-center gap-2 text-xs font-extrabold text-muted-foreground">
                            <CalendarClock className="size-4" /> Échéance des
                            points
                        </div>
                        <p className="mt-3 text-lg font-black text-foreground">
                            {date(account.pointsExpiresAt)}
                        </p>
                        <p className="mt-1 text-xs leading-5 text-muted-foreground">
                            Une nouvelle durée s’applique au prochain événement
                            qualifiant. Une échéance déjà enregistrée reste
                            inchangée.
                        </p>
                    </article>
                    <article className="rounded-2xl border border-border bg-card p-5 shadow-[0_8px_28px_rgba(0,29,78,0.035)]">
                        <div className="flex items-center gap-2 text-xs font-extrabold text-muted-foreground">
                            <ShieldCheck className="size-4" /> Compensation
                            fidélité
                        </div>
                        <p className="mt-3 text-lg font-black text-foreground">
                            {points(account.pointsCompensationDue)}
                        </p>
                        <p className="mt-1 text-xs leading-5 text-muted-foreground">
                            Points à compenser sur les prochains gains après
                            correction d’un voyage.
                        </p>
                    </article>
                </div>
            </section>

            <section className="cash-reveal cash-reveal-delay-1 rounded-2xl border border-border bg-card p-4 shadow-[0_8px_28px_rgba(0,29,78,0.035)] sm:p-5">
                <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p className="section-kicker">Traçabilité BUS</p>
                        <h2 className="mt-1 text-lg font-black text-foreground">
                            Activité récente
                        </h2>
                    </div>
                    <Link
                        href={`${props.urls.transactions}?search=${account.uuid}`}
                        className="inline-flex items-center gap-1 text-xs font-extrabold text-[var(--kora)]"
                    >
                        Tous les mouvements <ArrowRight className="size-3.5" />
                    </Link>
                </div>
                <MovementTable
                    data={account.recentMovements}
                    urls={props.urls}
                    emptyTitle="Aucun mouvement enregistré"
                />
            </section>
        </PageFrame>
    );
}

function TransactionsList({ props }: { props: Props }) {
    return (
        <PageFrame
            title="Mouvements Wallet"
            description="Journal consolidé des crédits et débits KORA BUS, avec accès au compte et à la source métier de chaque opération."
            view="transactions"
            urls={props.urls}
        >
            <FilterPanel
                key={JSON.stringify(props.filters ?? {})}
                kind="transactions"
                filters={props.filters ?? {}}
                agencyOptions={props.agencyOptions ?? []}
                baseUrl={props.urls.transactions}
            />
            <div className="cash-reveal cash-reveal-delay-1">
                <KoraTable
                    variant="cash"
                    columns={[
                        {
                            key: 'holder',
                            header: 'Titulaire',
                            render: (row) => (
                                <MovementLink
                                    movement={row}
                                    base={props.urls.transactions}
                                />
                            ),
                        },
                        {
                            key: 'balance',
                            header: 'Solde',
                            render: (row) => (
                                <span className="text-xs font-bold">
                                    {row.balanceType === 'cash'
                                        ? 'Cash'
                                        : 'Points'}
                                </span>
                            ),
                        },
                        {
                            key: 'direction',
                            header: 'Sens',
                            render: (row) => (
                                <span
                                    className={`inline-flex items-center gap-1 rounded-full px-2 py-1 text-[0.6875rem] font-extrabold ${row.direction === 'credit' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200' : 'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-200'}`}
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
                            ),
                        },
                        {
                            key: 'reason',
                            header: 'Motif',
                            render: (row) => (
                                <span className="text-xs font-bold text-foreground">
                                    {row.reasonLabel}
                                </span>
                            ),
                        },
                        {
                            key: 'amount',
                            header: 'Montant',
                            align: 'right',
                            render: (row) => (
                                <span className="font-black tabular-nums">
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
                                <span className="text-xs text-muted-foreground">
                                    {row.source?.label ?? '—'}
                                </span>
                            ),
                        },
                        {
                            key: 'agency',
                            header: 'Agence',
                            render: (row) => (
                                <span className="text-xs text-muted-foreground">
                                    {row.agency?.name ?? '—'}
                                </span>
                            ),
                        },
                        {
                            key: 'date',
                            header: 'Date',
                            render: (row) => (
                                <span className="text-xs text-muted-foreground">
                                    {date(row.createdAt)}
                                </span>
                            ),
                        },
                        {
                            key: 'detail',
                            header: '',
                            align: 'right',
                            render: (row) => (
                                <Link
                                    href={movementHref(
                                        props.urls.transactions,
                                        row,
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
                    emptyMessage="Modifiez les filtres ou revenez plus tard. Les données listées sont limitées au Wallet KORA BUS."
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

    return (
        <PageFrame
            title="Détail du mouvement"
            description="Écriture append-only du journal Wallet, reliée au compte, à son agence et à la source BUS connue."
            view="transaction"
            urls={props.urls}
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
                                {movement.loyaltyRuleVersion
                                    ? `Version ${movement.loyaltyRuleVersion.version}`
                                    : 'Non attribuée (mouvement antérieur)'}
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
                        <Link
                            href={`${props.urls.accounts}/${movement.walletUuid}`}
                            className="mt-4 inline-flex items-center gap-1 text-xs font-extrabold text-[var(--kora)]"
                        >
                            Ouvrir le compte <ArrowRight className="size-3.5" />
                        </Link>
                    </article>
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
                                        <a href={movement.source.url}>
                                            <Ticket className="size-3.5" /> Voir
                                            la source BUS{' '}
                                            <ArrowRight className="size-3.5" />
                                        </a>
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
                <PageFrame
                    title="Comptes Wallet"
                    description="Consultez les comptes provisionnés par l’activité passager, leurs soldes séparés et les échéances de fidélité."
                    view="accounts"
                    urls={props.urls}
                >
                    <AccountsList props={props} />
                </PageFrame>
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
