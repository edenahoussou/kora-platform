import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Ban,
    CheckCircle2,
    ChevronRight,
    FileCheck2,
    FileSpreadsheet,
    RefreshCw,
    Search,
    SlidersHorizontal,
    Table,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { formatMoney } from '@/components/sale/sale-utils';
import { cn } from '@/lib/utils';
import { exportMethod, index, retry } from '@/routes/fiscal-invoices';
import type { BreadcrumbItem } from '@/types';

type Invoice = {
    uuid: string;
    reference: string;
    reservationReference: string | null;
    reservationUuid: string | null;
    mecefUid: string | null;
    status: string;
    statusLabel: string;
    lastError: string | null;
    amount: number;
    currency: string;
    attemptCount: number;
    createdAt: string | null;
};
type Attempt = {
    uuid: string;
    operation: string;
    number: number;
    status: string;
    responseCode: string | null;
    errorCode: string | null;
    errorMessage: string | null;
    summary: Record<string, unknown> | null;
    payload: Record<string, unknown> | null;
    attemptedAt: string | null;
    completedAt: string | null;
    canViewPayload?: boolean;
};
type InvoiceDetail = Invoice & {
    attempts: Attempt[];
    canViewPayload: boolean;
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
    invoices: Paginated<Invoice>;
    filters: { q: string; status: string; from: string; to: string };
    statuses: Record<string, string>;
    counters: Record<string, number>;
    permissions: { canRetry: boolean };
    invoiceDetail?: InvoiceDetail | null;
};

const tones: Record<
    string,
    'navy' | 'gold' | 'blue' | 'muted' | 'destructive'
> = {
    certified: 'navy',
    pending: 'gold',
    failed: 'destructive',
    canceled: 'muted',
};

function formatDate(value: string | null): string {
    return value
        ? new Intl.DateTimeFormat('fr-FR', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
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
                        <small>Factures</small>
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

export default function FiscalInvoicesPage({
    invoices,
    filters,
    statuses,
    counters,
    permissions,
    invoiceDetail,
}: Props) {
    const [query, setQuery] = useState(filters.q);
    const [status, setStatus] = useState(filters.status);
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);

    const statusOptions: [string, string][] = useMemo(
        () => [
            ['all', 'Tous les statuts'],
            ...(Object.entries(statuses) as [string, string][]),
        ],
        [statuses],
    );

    const applyFilters = () => {
        const params: Record<string, string> = {};

        if (query.trim() !== '') {
            params.q = query.trim();
        }

        if (status !== 'all') {
            params.status = status;
        }

        if (from !== '') {
            params.from = from;
        }

        if (to !== '') {
            params.to = to;
        }

        router.get(
            index.url({ query: params }),
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
        setFrom('');
        setTo('');
        router.get(
            index.url(),
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    const goToPage = (page: number) => {
        const params: Record<string, string> = { page: String(page) };

        if (query.trim() !== '') {
            params.q = query.trim();
        }

        if (status !== 'all') {
            params.status = status;
        }

        if (from !== '') {
            params.from = from;
        }

        if (to !== '') {
            params.to = to;
        }

        router.get(
            index.url({ query: params }),
            {},
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    };

    const exportQuery = (format: 'csv' | 'xlsx') =>
        exportMethod.url({
            query: {
                q: filters.q,
                status: filters.status,
                from: filters.from,
                to: filters.to,
                format,
            },
        });

    const closeDetail = () =>
        router.get(
            index.url({
                query: {
                    q: filters.q,
                    status: filters.status,
                    from: filters.from,
                    to: filters.to,
                },
            }),
            {},
            { preserveState: true, preserveScroll: true },
        );

    return (
        <>
            <Head title="Facturation" />
            <div className="cash-page">
                <div className="kora-page-head cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                            KORA OPS / Finance / Facturation
                        </div>
                        <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                            Facturation
                        </h1>
                        <p className="mt-1.5 text-[0.8125rem] leading-6 text-muted-foreground">
                            Surveillez les transmissions MECeF, identifiez les
                            échecs et relancez-les avec une trace complète.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <a className="btn btn-ghost" href={exportQuery('csv')}>
                            <Table className="size-3.5" /> Exporter en CSV
                        </a>
                        <a
                            className="btn btn-primary"
                            href={exportQuery('xlsx')}
                        >
                            <FileSpreadsheet className="size-3.5" /> Exporter en
                            Excel
                        </a>
                    </div>
                </div>

                <section className="cash-ledger-grid cash-reveal cash-reveal-delay-1">
                    <KpiCard
                        icon={FileCheck2}
                        label="Total filtré"
                        value={counters.total}
                        tone="muted"
                    />
                    <KpiCard
                        icon={CheckCircle2}
                        label="Certifiées"
                        value={counters.certified}
                        tone="navy"
                    />
                    <KpiCard
                        icon={AlertTriangle}
                        label="En échec"
                        value={counters.failed}
                        tone="destructive"
                    />
                    <KpiCard
                        icon={Ban}
                        label="Annulées"
                        value={counters.canceled}
                        tone="muted"
                    />
                </section>

                <div className="cash-reveal cash-reveal-delay-2 cash-section-intro">
                    <div>
                        <span className="section-kicker">
                            Journal de fiscalisation
                        </span>
                        <h2>Liste des factures</h2>
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
                        placeholder="KORA, MECeF, réservation…"
                        className="cash-filter-search"
                    />
                    <FilterSelect
                        label="Statut"
                        value={status}
                        onChange={setStatus}
                        options={statusOptions}
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
                    {invoices.data.length === 0 ? (
                        <div className="px-6 py-16 text-center">
                            <FileCheck2 className="mx-auto size-8 text-muted-foreground/50" />
                            <h2 className="mt-3 font-black">
                                Aucune facture dans ce périmètre
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Modifiez les filtres ou attendez la prochaine
                                vente confirmée.
                            </p>
                        </div>
                    ) : (
                        <div className="cash-table-scroll overflow-x-auto">
                            <table className="w-full min-w-[860px] text-left">
                                <thead className="bg-muted/45 text-[0.625rem] font-black tracking-[0.14em] text-muted-foreground uppercase">
                                    <tr>
                                        <th className="px-4 py-3">Facture</th>
                                        <th className="px-4 py-3">
                                            Réservation
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Montant
                                        </th>
                                        <th className="px-4 py-3">Statut</th>
                                        <th className="px-4 py-3">
                                            Tentatives
                                        </th>
                                        <th className="px-4 py-3">Créée le</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {invoices.data.map((invoice) => (
                                        <tr
                                            key={invoice.uuid}
                                            className="group cursor-pointer border-b border-black/[0.045] transition-[background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:bg-muted/30"
                                            onClick={() =>
                                                router.get(
                                                    index.url({
                                                        query: {
                                                            q: filters.q,
                                                            status: filters.status,
                                                            from: filters.from,
                                                            to: filters.to,
                                                            invoice:
                                                                invoice.uuid,
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
                                                        <FileCheck2
                                                            className="size-4"
                                                            strokeWidth={1.6}
                                                        />
                                                    </span>
                                                    <div>
                                                        <p className="text-xs font-black text-foreground">
                                                            {invoice.reference}
                                                        </p>
                                                        <p className="mt-0.5 text-[0.625rem] text-muted-foreground">
                                                            {invoice.mecefUid ??
                                                                'UID MECeF à venir'}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5 text-xs font-bold text-foreground">
                                                {invoice.reservationReference ??
                                                    invoice.reservationUuid ??
                                                    '—'}
                                            </td>
                                            <td className="px-4 py-3.5 text-right text-xs font-black text-foreground">
                                                {formatMoney(invoice.amount)}{' '}
                                                {invoice.currency}
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <KoraStatusBadge
                                                    label={invoice.statusLabel}
                                                    tone={
                                                        tones[invoice.status] ??
                                                        'blue'
                                                    }
                                                />
                                            </td>
                                            <td className="px-4 py-3.5 text-xs text-muted-foreground">
                                                {invoice.attemptCount}
                                            </td>
                                            <td className="px-4 py-3.5 text-xs text-muted-foreground">
                                                {formatDate(invoice.createdAt)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <Pagination
                        currentPage={invoices.current_page}
                        totalPages={invoices.last_page}
                        total={invoices.total}
                        from={invoices.from}
                        to={invoices.to}
                        onPageChange={goToPage}
                    />
                </section>
            </div>

            {invoiceDetail && (
                <aside className="fixed inset-y-0 right-0 z-40 w-full max-w-xl overflow-y-auto border-l border-border bg-card p-6 shadow-2xl">
                    <div className="flex items-start justify-between">
                        <div>
                            <span className="section-kicker">
                                Détail fiscal
                            </span>
                            <h2 className="mt-2 text-[1.8125rem] font-extrabold tracking-[-0.035em]">
                                {invoiceDetail.reference}
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
                    <div className="mt-6 grid gap-3 sm:grid-cols-2">
                        <div className="rounded-xl border border-black/[0.045] p-4">
                            <p className="text-xs text-muted-foreground">
                                Statut
                            </p>
                            <div className="mt-1.5">
                                <KoraStatusBadge
                                    label={invoiceDetail.statusLabel}
                                    tone={tones[invoiceDetail.status] ?? 'blue'}
                                />
                            </div>
                        </div>
                        <div className="rounded-xl border border-black/[0.045] p-4">
                            <p className="text-xs text-muted-foreground">
                                Montant
                            </p>
                            <strong className="mt-1.5 block">
                                {formatMoney(invoiceDetail.amount)}{' '}
                                {invoiceDetail.currency}
                            </strong>
                        </div>
                    </div>
                    {invoiceDetail.lastError && (
                        <p className="mt-4 rounded-xl bg-destructive/10 px-4 py-3 text-sm font-bold text-destructive">
                            {invoiceDetail.lastError}
                        </p>
                    )}
                    {invoiceDetail.status === 'failed' &&
                        permissions.canRetry && (
                            <button
                                type="button"
                                onClick={() =>
                                    router.post(retry.url(invoiceDetail.uuid))
                                }
                                className="btn btn-primary mt-5"
                            >
                                <RefreshCw className="size-3.5" /> Relancer
                            </button>
                        )}
                    <section className="mt-8">
                        <h3 className="font-black">
                            Historique des tentatives
                        </h3>
                        <div className="mt-3 space-y-3">
                            {invoiceDetail.attempts.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    Aucune tentative enregistrée.
                                </p>
                            )}
                            {invoiceDetail.attempts.map((attempt) => (
                                <article
                                    key={attempt.uuid}
                                    className="rounded-xl border border-black/[0.045] p-4"
                                >
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs font-black uppercase">
                                            {attempt.operation} #
                                            {attempt.number}
                                        </span>
                                        <KoraStatusBadge
                                            label={attempt.status}
                                            tone={
                                                attempt.status === 'failed'
                                                    ? 'destructive'
                                                    : 'blue'
                                            }
                                        />
                                    </div>
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        {formatDate(attempt.attemptedAt)}
                                    </p>
                                    {attempt.errorMessage && (
                                        <p className="mt-2 text-sm text-destructive">
                                            {attempt.errorMessage}
                                        </p>
                                    )}
                                    {attempt.canViewPayload &&
                                        attempt.payload && (
                                            <pre className="mt-3 max-h-64 overflow-auto rounded-lg bg-muted p-3 text-[0.65rem]">
                                                {JSON.stringify(
                                                    attempt.payload,
                                                    null,
                                                    2,
                                                )}
                                            </pre>
                                        )}
                                </article>
                            ))}
                        </div>
                    </section>
                </aside>
            )}
        </>
    );
}

FiscalInvoicesPage.layout = {
    breadcrumbs: [
        {
            title: 'Facturation',
            href: '/fiscal-invoices',
        } satisfies BreadcrumbItem,
    ],
};
