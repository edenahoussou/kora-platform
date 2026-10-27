import { Head, Link, router } from '@inertiajs/react';
import {
    Check,
    ChevronRight,
    Clock3,
    FileSpreadsheet,
    FileText,
    Plus,
    RotateCcw,
    Search,
    SlidersHorizontal,
    Table,
    Wallet,
    X,
    XCircle,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { ExpenseFormDialog } from '@/components/expenses/expense-form-dialog';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { formatMoney } from '@/components/sale/sale-utils';
import { cn } from '@/lib/utils';
import {
    approve,
    exportMethod,
    index,
    reject,
    reverse,
} from '@/routes/expenses';
import type { BreadcrumbItem } from '@/types';

type Expense = {
    uuid: string;
    reference: string;
    agency: string | null;
    category: string | null;
    categoryUuid: string | null;
    paymentChannel: string;
    paymentChannelLabel: string;
    amount: number;
    currency: string;
    status: string;
    statusLabel: string;
    payeeLabel: string;
    occurredAt: string | null;
    hasEvidence: boolean;
    isReconciled: boolean;
    costCenterType: 'departure' | 'bus' | 'line' | 'agency';
    costCenter: string | null;
    fromMobile: boolean;
};

type ExpenseDetail = Expense & {
    description: string | null;
    payeeReference: string | null;
    createdBy: string | null;
    approvedBy: string | null;
    rejectionReason: string | null;
    reversalReason: string | null;
    media: { uuid: string; name: string; mimeType: string; url: string }[];
    costCenterUuid: string | null;
    maintenance: {
        type: string;
        typeLabel: string;
        garageLabel: string | null;
        partsAmount: number | null;
        laborAmount: number | null;
        immobilizedFrom: string | null;
        immobilizedTo: string | null;
    } | null;
    trip: {
        kind: 'fuel' | 'toll' | 'border_tax';
        kindLabel: string;
        liters: number | null;
        unitPrice: number | null;
        stationLabel: string | null;
        checkpointLabel: string | null;
        countryCode: string | null;
        odometerKm: number | null;
        submittedBy: string | null;
        busUuid: string | null;
    } | null;
    permissions: { canApprove: boolean; canReverse: boolean };
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

type Props = {
    expenses: Paginated<Expense>;
    filters: {
        q: string;
        status: string;
        channel: string;
        category: string;
        agency: string;
        from: string;
        to: string;
    };
    statuses: Record<string, string>;
    channels: Record<string, string>;
    categories: { uuid: string; code: string; label: string }[];
    ledgers: { uuid: string; label: string }[];
    costCenters: {
        buses: { uuid: string; label: string }[];
        lines: { uuid: string; label: string }[];
        departures: { uuid: string; label: string }[];
        agencies: { uuid: string; label: string }[];
    };
    counters: Record<string, number>;
    permissions: {
        canCreate: boolean;
        canExport: boolean;
        canAttachCostCenter: boolean;
        canChooseAgency: boolean;
    };
    expenseDetail?: ExpenseDetail | null;
};

const tones: Record<
    string,
    'navy' | 'gold' | 'blue' | 'muted' | 'destructive'
> = {
    approved: 'navy',
    pending: 'gold',
    rejected: 'destructive',
    reversed: 'muted',
};

function formatDate(value: string | null): string {
    return value
        ? new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium' }).format(
              new Date(value),
          )
        : '—';
}

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

function FilterDate({
    label,
    value,
    onChange,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <label className="cash-filter-field relative block">
            <span className="sr-only">{label}</span>
            <input
                type="date"
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="cash-filter-select h-11 w-full rounded-xl bg-muted/55 px-3 text-xs font-bold text-foreground ring-1 ring-black/[0.045] transition-[box-shadow,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] outline-none focus:bg-card focus:ring-2 focus:ring-gold/35"
            />
        </label>
    );
}

function KpiCard({
    icon: Icon,
    label,
    value,
    tone,
}: {
    icon: React.ComponentType<{ className?: string; strokeWidth?: number }>;
    label: string;
    value: string | number;
    tone: 'navy' | 'gold' | 'blue' | 'muted' | 'green' | 'destructive';
}) {
    const toneStyles = {
        navy: 'bg-kora text-white',
        gold: 'bg-gold/15 text-gold',
        blue: 'bg-digital/10 text-digital',
        muted: 'bg-muted text-muted-foreground',
        green: 'bg-emerald-500/10 text-emerald-600',
        destructive: 'bg-destructive/10 text-destructive',
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
                        <small>Dépenses</small>
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

export default function ExpensesPage({
    expenses,
    filters,
    statuses,
    channels,
    categories,
    ledgers,
    costCenters,
    counters,
    permissions,
    expenseDetail,
}: Props) {
    const [query, setQuery] = useState(filters.q);
    const [status, setStatus] = useState(filters.status);
    const [channel, setChannel] = useState(filters.channel);
    const [category, setCategory] = useState(filters.category);
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [createOpen, setCreateOpen] = useState(false);

    const statusOptions: [string, string][] = useMemo(
        () => [
            ['all', 'Tous les statuts'],
            ...(Object.entries(statuses) as [string, string][]),
        ],
        [statuses],
    );

    const channelOptions: [string, string][] = useMemo(
        () => [
            ['all', 'Tous les moyens'],
            ...(Object.entries(channels) as [string, string][]),
        ],
        [channels],
    );

    const categoryOptions: [string, string][] = useMemo(
        () => [
            ['', 'Toutes les catégories'],
            ...categories.map((item): [string, string] => [
                item.uuid,
                item.label,
            ]),
        ],
        [categories],
    );

    const buildParams = (extra: Record<string, string> = {}) => {
        const params: Record<string, string> = { ...extra };

        if (query.trim() !== '') {
            params.q = query.trim();
        }

        if (status !== 'all') {
            params.status = status;
        }

        if (channel !== 'all') {
            params.channel = channel;
        }

        if (category !== '') {
            params.category = category;
        }

        if (from !== '') {
            params.from = from;
        }

        if (to !== '') {
            params.to = to;
        }

        return params;
    };

    const applyFilters = () => {
        router.get(
            index.url({ query: buildParams() }),
            {},
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    };

    const resetFilters = () => {
        setQuery('');
        setStatus('all');
        setChannel('all');
        setCategory('');
        setFrom('');
        setTo('');
        router.get(
            index.url(),
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    const goToPage = (page: number) => {
        router.get(
            index.url({ query: buildParams({ page: String(page) }) }),
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    const exportQuery = (format: 'csv' | 'xlsx') =>
        exportMethod.url({
            query: { ...filters, format },
        });

    const closeDetail = () =>
        router.get(
            index.url({ query: { ...filters } }),
            {},
            { preserveState: true, preserveScroll: true },
        );

    return (
        <>
            <Head title="Dépenses" />
            <div className="cash-page">
                <div className="kora-page-head cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                            KORA OPS / Finance / Dépenses
                        </div>
                        <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                            Dépenses
                        </h1>
                        <p className="mt-1.5 text-[0.8125rem] leading-6 text-muted-foreground">
                            Contrôlez les dépenses du réseau, leurs
                            justificatifs et leur publication dans les journaux
                            de caisse.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {permissions.canExport && (
                            <>
                                <a
                                    className="btn btn-ghost"
                                    href={exportQuery('csv')}
                                >
                                    <Table className="size-3.5" /> Exporter en
                                    CSV
                                </a>
                                <a
                                    className="btn btn-ghost"
                                    href={exportQuery('xlsx')}
                                >
                                    <FileSpreadsheet className="size-3.5" />{' '}
                                    Exporter en Excel
                                </a>
                            </>
                        )}
                        {permissions.canCreate && (
                            <button
                                type="button"
                                onClick={() => setCreateOpen(true)}
                                className="btn btn-primary"
                            >
                                <Plus className="size-3.5" /> Nouvelle dépense
                            </button>
                        )}
                    </div>
                </div>

                <section className="cash-ledger-grid cash-reveal cash-reveal-delay-1">
                    <KpiCard
                        icon={FileText}
                        label="Total filtré"
                        value={counters.total}
                        tone="muted"
                    />
                    <KpiCard
                        icon={Clock3}
                        label="À valider"
                        value={counters.pending}
                        tone="gold"
                    />
                    <KpiCard
                        icon={Wallet}
                        label="Publié"
                        value={`${formatMoney(counters.approvedAmount)} XOF`}
                        tone="navy"
                    />
                    <KpiCard
                        icon={XCircle}
                        label="Rejeté"
                        value={counters.rejected}
                        tone="destructive"
                    />
                </section>

                <div className="cash-reveal cash-reveal-delay-2 cash-section-intro">
                    <div>
                        <span className="section-kicker">
                            Journal des dépenses
                        </span>
                        <h2>Liste des dépenses</h2>
                    </div>
                </div>

                <form
                    className="cash-filterbar cash-reveal cash-reveal-delay-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        applyFilters();
                    }}
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
                        placeholder="Référence, bénéficiaire, catégorie…"
                        className="cash-filter-search"
                    />
                    <FilterSelect
                        label="Statut"
                        value={status}
                        onChange={setStatus}
                        options={statusOptions}
                    />
                    <FilterSelect
                        label="Moyen"
                        value={channel}
                        onChange={setChannel}
                        options={channelOptions}
                    />
                    <FilterSelect
                        label="Catégorie"
                        value={category}
                        onChange={setCategory}
                        options={categoryOptions}
                    />
                    <FilterDate label="Du" value={from} onChange={setFrom} />
                    <FilterDate label="Au" value={to} onChange={setTo} />
                    <button
                        type="submit"
                        className="btn btn-navy cash-filter-submit"
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
                </form>

                <section className="cash-table-card cash-reveal cash-reveal-delay-3">
                    {expenses.data.length === 0 ? (
                        <div className="px-6 py-16 text-center">
                            <FileText className="mx-auto size-8 text-muted-foreground/50" />
                            <h2 className="mt-3 font-black">
                                Aucune dépense dans ce périmètre
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Créez une dépense ou ajustez vos filtres.
                            </p>
                        </div>
                    ) : (
                        <div className="cash-table-scroll overflow-x-auto">
                            <table className="w-full min-w-[1020px] text-left">
                                <thead className="bg-muted/45 text-[0.625rem] font-black tracking-[0.14em] text-muted-foreground uppercase">
                                    <tr>
                                        <th className="px-4 py-3">Dépense</th>
                                        <th className="px-4 py-3">Catégorie</th>
                                        <th className="px-4 py-3">
                                            Centre de coût
                                        </th>
                                        <th className="px-4 py-3">Moyen</th>
                                        <th className="px-4 py-3 text-right">
                                            Montant
                                        </th>
                                        <th className="px-4 py-3">Statut</th>
                                        <th className="px-4 py-3">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {expenses.data.map((expense) => (
                                        <tr
                                            key={expense.uuid}
                                            className="group cursor-pointer border-b border-black/[0.045] transition-[background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:bg-muted/30"
                                            onClick={() =>
                                                router.get(
                                                    index.url({
                                                        query: {
                                                            ...filters,
                                                            expense:
                                                                expense.uuid,
                                                        },
                                                    }),
                                                    {},
                                                    {
                                                        preserveState: true,
                                                        preserveScroll: true,
                                                    },
                                                )
                                            }
                                        >
                                            <td className="px-4 py-3.5">
                                                <div className="flex items-center gap-3">
                                                    <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-gold/12 text-[var(--kora)]">
                                                        <FileText
                                                            className="size-4"
                                                            strokeWidth={1.6}
                                                        />
                                                    </span>
                                                    <div>
                                                        <p className="text-xs font-black text-foreground">
                                                            {expense.reference}
                                                            {expense.fromMobile && (
                                                                <span className="ml-2 rounded-full bg-[var(--digital)]/10 px-2 py-0.5 text-[0.5625rem] font-bold text-[var(--digital)]">
                                                                    OPS Mobile
                                                                </span>
                                                            )}
                                                        </p>
                                                        <p className="mt-0.5 text-[0.625rem] text-muted-foreground">
                                                            {expense.payeeLabel}{' '}
                                                            ·{' '}
                                                            {expense.agency ??
                                                                'Réseau'}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5 text-xs font-medium text-foreground">
                                                {expense.category ?? '—'}
                                            </td>
                                            <td className="px-4 py-3.5 text-xs text-muted-foreground">
                                                {expense.costCenter ?? (
                                                    <span className="text-[0.625rem] font-bold text-gold">
                                                        Non affecté
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5 text-xs text-muted-foreground">
                                                {expense.paymentChannelLabel}
                                                {!expense.isReconciled && (
                                                    <span className="ml-2 text-[0.625rem] font-bold text-gold">
                                                        À rapprocher
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5 text-right text-xs font-black text-foreground">
                                                {formatMoney(expense.amount)}{' '}
                                                {expense.currency}
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <KoraStatusBadge
                                                    label={expense.statusLabel}
                                                    tone={
                                                        tones[expense.status] ??
                                                        'blue'
                                                    }
                                                />
                                            </td>
                                            <td className="px-4 py-3.5 text-xs text-muted-foreground">
                                                {formatDate(expense.occurredAt)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <Pagination
                        currentPage={expenses.current_page}
                        totalPages={expenses.last_page}
                        total={expenses.total}
                        from={expenses.from}
                        to={expenses.to}
                        onPageChange={goToPage}
                    />
                </section>
            </div>

            {/* Modale de saisie de dépense (Style Caisse) */}
            <ExpenseFormDialog
                open={createOpen}
                onOpenChange={setCreateOpen}
                categories={categories}
                channels={channels}
                ledgers={ledgers}
                costCenters={costCenters}
                permissions={permissions}
            />

            {expenseDetail && (
                <aside className="fixed inset-y-0 right-0 z-40 w-full max-w-xl overflow-y-auto border-l border-border bg-card p-6 shadow-2xl">
                    <div className="flex items-start justify-between">
                        <div>
                            <span className="section-kicker">
                                Détail dépense
                            </span>
                            <h2 className="mt-2 text-[1.8125rem] font-extrabold tracking-[-0.035em]">
                                {expenseDetail.reference}
                            </h2>
                        </div>
                        <button
                            type="button"
                            onClick={closeDetail}
                            className="grid size-8 place-items-center rounded-full bg-muted text-foreground transition-[background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:bg-gold/15"
                            aria-label="Fermer"
                        >
                            <X className="size-4" strokeWidth={1.7} />
                        </button>
                    </div>
                    <div className="mt-6 flex items-center justify-between">
                        <KoraStatusBadge
                            label={expenseDetail.statusLabel}
                            tone={tones[expenseDetail.status] ?? 'blue'}
                        />
                        <strong>
                            {formatMoney(expenseDetail.amount)}{' '}
                            {expenseDetail.currency}
                        </strong>
                    </div>
                    <dl className="mt-6 space-y-3 text-sm">
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">Catégorie</dt>
                            <dd className="font-bold">
                                {expenseDetail.category}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">
                                Centre de coût
                            </dt>
                            <dd className="text-right font-bold">
                                {expenseDetail.costCenter ?? 'Non affecté'}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">
                                Bénéficiaire
                            </dt>
                            <dd className="font-bold">
                                {expenseDetail.payeeLabel}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">Créée par</dt>
                            <dd className="font-bold">
                                {expenseDetail.createdBy ?? '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                Description
                            </dt>
                            <dd className="mt-1">
                                {expenseDetail.description ?? '—'}
                            </dd>
                        </div>
                        {expenseDetail.maintenance && (
                            <div>
                                <dt className="text-muted-foreground">
                                    Maintenance
                                </dt>
                                <dd className="mt-1 space-y-1">
                                    <p className="font-bold">
                                        {expenseDetail.maintenance.typeLabel}
                                        {expenseDetail.maintenance.garageLabel
                                            ? ` · ${expenseDetail.maintenance.garageLabel}`
                                            : ''}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Pièces{' '}
                                        {formatMoney(
                                            expenseDetail.maintenance
                                                .partsAmount ?? 0,
                                        )}{' '}
                                        · Main-d’œuvre{' '}
                                        {formatMoney(
                                            expenseDetail.maintenance
                                                .laborAmount ?? 0,
                                        )}
                                    </p>
                                    {expenseDetail.maintenance
                                        .immobilizedFrom && (
                                        <p className="text-xs text-muted-foreground">
                                            Immobilisé du{' '}
                                            {
                                                expenseDetail.maintenance
                                                    .immobilizedFrom
                                            }{' '}
                                            au{' '}
                                            {expenseDetail.maintenance
                                                .immobilizedTo ?? '—'}
                                        </p>
                                    )}
                                </dd>
                            </div>
                        )}
                        {expenseDetail.trip && (
                            <div className="rounded-[0.9rem] bg-muted p-3.5">
                                <dt className="text-[0.625rem] font-black tracking-[0.1em] text-[var(--digital)] uppercase">
                                    Carnet de bord — OPS Mobile
                                </dt>
                                <dd className="mt-2 space-y-1.5 text-xs">
                                    <p className="font-bold">
                                        {expenseDetail.trip.kindLabel}
                                        {expenseDetail.trip.submittedBy
                                            ? ` · saisi par ${expenseDetail.trip.submittedBy}`
                                            : ''}
                                    </p>
                                    {expenseDetail.trip.kind === 'fuel' ? (
                                        <>
                                            <p>
                                                {expenseDetail.trip.liters?.toLocaleString(
                                                    'fr-FR',
                                                    {
                                                        maximumFractionDigits: 2,
                                                    },
                                                ) ?? '—'}{' '}
                                                L ×{' '}
                                                {formatMoney(
                                                    expenseDetail.trip
                                                        .unitPrice ?? 0,
                                                    expenseDetail.currency,
                                                )}
                                                /L
                                            </p>
                                            <p className="text-muted-foreground">
                                                Station :{' '}
                                                {expenseDetail.trip
                                                    .stationLabel ?? '—'}
                                            </p>
                                        </>
                                    ) : (
                                        <p className="text-muted-foreground">
                                            Poste :{' '}
                                            {expenseDetail.trip
                                                .checkpointLabel ?? '—'}
                                            {expenseDetail.trip.countryCode
                                                ? ` (${expenseDetail.trip.countryCode})`
                                                : ''}
                                        </p>
                                    )}
                                    {expenseDetail.trip.odometerKm !== null && (
                                        <p className="text-muted-foreground">
                                            Compteur :{' '}
                                            {new Intl.NumberFormat(
                                                'fr-FR',
                                            ).format(
                                                expenseDetail.trip.odometerKm,
                                            )}{' '}
                                            km
                                        </p>
                                    )}
                                    {expenseDetail.trip.busUuid && (
                                        <Link
                                            href={`/fleet/${expenseDetail.trip.busUuid}`}
                                            className="inline-block font-bold text-[var(--digital)] hover:underline"
                                        >
                                            Voir la fiche du bus
                                        </Link>
                                    )}
                                </dd>
                            </div>
                        )}
                    </dl>
                    {expenseDetail.permissions.canApprove &&
                        expenseDetail.status === 'pending' && (
                            <div className="mt-6 flex gap-2">
                                <button
                                    type="button"
                                    onClick={() =>
                                        router.post(
                                            approve.url(expenseDetail.uuid),
                                        )
                                    }
                                    className="btn btn-primary"
                                >
                                    <Check className="size-3.5" /> Approuver
                                </button>
                                <button
                                    type="button"
                                    onClick={() => {
                                        const value =
                                            window.prompt('Motif du rejet');

                                        if (value) {
                                            router.post(
                                                reject.url(expenseDetail.uuid),
                                                { reason: value },
                                            );
                                        }
                                    }}
                                    className="btn btn-ghost border-destructive/40 text-destructive"
                                >
                                    Rejeter
                                </button>
                            </div>
                        )}
                    {expenseDetail.permissions.canReverse &&
                        expenseDetail.status === 'approved' && (
                            <button
                                type="button"
                                onClick={() => {
                                    const value = window.prompt(
                                        'Motif de la contrepassation',
                                    );

                                    if (value) {
                                        router.post(
                                            reverse.url(expenseDetail.uuid),
                                            { reason: value },
                                        );
                                    }
                                }}
                                className="btn btn-ghost mt-6"
                            >
                                <RotateCcw className="size-3.5" /> Contrepasser
                            </button>
                        )}
                    <section className="mt-8">
                        <h3 className="font-black">Justificatifs</h3>
                        <div className="mt-3 space-y-2">
                            {expenseDetail.media.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    Aucun justificatif.
                                </p>
                            )}
                            {expenseDetail.media.map((media) => (
                                <a
                                    key={media.uuid}
                                    href={media.url}
                                    className="flex items-center gap-2 rounded-lg border border-black/[0.045] p-3 text-sm font-bold transition-colors hover:bg-muted"
                                >
                                    <FileText className="size-4 text-gold" />{' '}
                                    {media.name}
                                </a>
                            ))}
                        </div>
                    </section>
                </aside>
            )}
        </>
    );
}

ExpensesPage.layout = {
    breadcrumbs: [
        { title: 'Dépenses', href: '/expenses' } satisfies BreadcrumbItem,
    ],
};
