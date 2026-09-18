import { Head, router } from '@inertiajs/react';
import { Download, FileCheck2, RefreshCw, Search, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { formatMoney } from '@/components/sale/sale-utils';
import { cn } from '@/lib/utils';
import { index, exportMethod, retry } from '@/routes/fiscal-invoices';

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
type InvoiceDetail = Invoice & { attempts: Attempt[]; canViewPayload: boolean };
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

export default function FiscalInvoicesPage({
    invoices,
    filters,
    statuses,
    counters,
    permissions,
    invoiceDetail,
}: Props) {
    const [query, setQuery] = useState(filters.q);
    const submitFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            index.url({
                query: {
                    q: query,
                    status: filters.status,
                    from: filters.from,
                    to: filters.to,
                },
            }),
            {},
            { preserveState: true, preserveScroll: true },
        );
    };
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
            <div className="space-y-6 p-4 md:p-8">
                <header className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="text-[0.65rem] font-black tracking-[0.22em] text-muted-foreground uppercase">
                            Finance / Fiscalisation
                        </p>
                        <h1 className="mt-2 text-3xl font-black tracking-[-0.04em] text-foreground">
                            Facturation
                        </h1>
                        <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
                            Surveillez les transmissions MECeF, identifiez les
                            échecs et relancez-les avec une trace complète.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <a
                            className="inline-flex items-center gap-2 rounded-[0.7rem] border border-border px-3 py-2 text-xs font-bold hover:bg-muted"
                            href={exportMethod.url({
                                query: { ...filters, format: 'csv' },
                            })}
                        >
                            <Download className="size-3.5" /> CSV
                        </a>
                        <a
                            className="inline-flex items-center gap-2 rounded-[0.7rem] bg-gold px-3 py-2 text-xs font-black text-kora hover:bg-gold/90"
                            href={exportMethod.url({
                                query: { ...filters, format: 'xlsx' },
                            })}
                        >
                            <Download className="size-3.5" /> XLSX
                        </a>
                    </div>
                </header>

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    {[
                        ['Total filtré', counters.total, 'muted'],
                        ['En attente', counters.pending, 'gold'],
                        ['Certifiées', counters.certified, 'navy'],
                        ['En échec', counters.failed, 'destructive'],
                        ['Annulées', counters.canceled, 'muted'],
                    ].map(([label, value, tone]) => (
                        <article
                            key={label as string}
                            className="rounded-[1.1rem] border border-border bg-card p-4 shadow-sm"
                        >
                            <p className="text-xs font-bold text-muted-foreground">
                                {label}
                            </p>
                            <strong
                                className={cn(
                                    'mt-2 block text-2xl font-black',
                                    tone === 'destructive' &&
                                        'text-destructive',
                                    tone === 'gold' && 'text-gold',
                                )}
                            >
                                {value}
                            </strong>
                        </article>
                    ))}
                </section>

                <section className="rounded-[1.25rem] border border-border bg-card p-4 shadow-sm">
                    <form
                        onSubmit={submitFilters}
                        className="grid gap-3 md:grid-cols-[minmax(0,1fr)_180px_150px_150px_auto] md:items-end"
                    >
                        <label className="text-xs font-bold text-muted-foreground">
                            Recherche
                            <input
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                                placeholder="KORA, MECeF, réservation…"
                                className="mt-1.5 h-10 w-full rounded-[0.7rem] border border-border bg-background px-3 text-sm outline-none focus:border-gold"
                            />
                        </label>
                        <label className="text-xs font-bold text-muted-foreground">
                            Statut
                            <select
                                value={filters.status}
                                onChange={(event) =>
                                    router.get(
                                        index.url({
                                            query: {
                                                ...filters,
                                                status: event.target.value,
                                            },
                                        }),
                                        {},
                                        { preserveState: true },
                                    )
                                }
                                className="mt-1.5 h-10 w-full rounded-[0.7rem] border border-border bg-background px-3 text-sm"
                            >
                                <option value="all">Tous</option>
                                {Object.entries(statuses).map(
                                    ([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ),
                                )}
                            </select>
                        </label>
                        <label className="text-xs font-bold text-muted-foreground">
                            Du
                            <input
                                type="date"
                                value={filters.from}
                                onChange={(event) =>
                                    router.get(
                                        index.url({
                                            query: {
                                                ...filters,
                                                from: event.target.value,
                                            },
                                        }),
                                        {},
                                        { preserveState: true },
                                    )
                                }
                                className="mt-1.5 h-10 w-full rounded-[0.7rem] border border-border bg-background px-3 text-sm"
                            />
                        </label>
                        <label className="text-xs font-bold text-muted-foreground">
                            Au
                            <input
                                type="date"
                                value={filters.to}
                                onChange={(event) =>
                                    router.get(
                                        index.url({
                                            query: {
                                                ...filters,
                                                to: event.target.value,
                                            },
                                        }),
                                        {},
                                        { preserveState: true },
                                    )
                                }
                                className="mt-1.5 h-10 w-full rounded-[0.7rem] border border-border bg-background px-3 text-sm"
                            />
                        </label>
                        <button
                            type="submit"
                            className="inline-flex h-10 items-center justify-center gap-2 rounded-[0.7rem] bg-kora px-4 text-xs font-black text-white"
                        >
                            <Search className="size-3.5" /> Filtrer
                        </button>
                    </form>
                </section>

                <section className="overflow-hidden rounded-[1.25rem] border border-border bg-card shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[760px] text-left text-sm">
                            <thead className="border-b border-border bg-muted/40 text-[0.65rem] font-black tracking-[0.12em] text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-5 py-4">Facture</th>
                                    <th className="px-5 py-4">Réservation</th>
                                    <th className="px-5 py-4">Montant</th>
                                    <th className="px-5 py-4">Statut</th>
                                    <th className="px-5 py-4">Tentatives</th>
                                    <th className="px-5 py-4">Créée le</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {invoices.data.map((invoice) => (
                                    <tr
                                        key={invoice.uuid}
                                        onClick={() =>
                                            router.get(
                                                index.url({
                                                    query: {
                                                        ...filters,
                                                        invoice: invoice.uuid,
                                                    },
                                                }),
                                                {},
                                                {
                                                    preserveState: true,
                                                    preserveScroll: true,
                                                },
                                            )
                                        }
                                        className="cursor-pointer transition-colors hover:bg-muted/30"
                                    >
                                        <td className="px-5 py-4">
                                            <div className="font-black text-foreground">
                                                {invoice.reference}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {invoice.mecefUid ??
                                                    'UID MECeF à venir'}
                                            </div>
                                        </td>
                                        <td className="px-5 py-4 text-muted-foreground">
                                            {invoice.reservationReference ??
                                                invoice.reservationUuid ??
                                                '—'}
                                        </td>
                                        <td className="px-5 py-4 font-black">
                                            {formatMoney(invoice.amount)}{' '}
                                            {invoice.currency}
                                        </td>
                                        <td className="px-5 py-4">
                                            <KoraStatusBadge
                                                label={invoice.statusLabel}
                                                tone={
                                                    tones[invoice.status] ??
                                                    'blue'
                                                }
                                            />
                                        </td>
                                        <td className="px-5 py-4 text-muted-foreground">
                                            {invoice.attemptCount}
                                        </td>
                                        <td className="px-5 py-4 text-muted-foreground">
                                            {formatDate(invoice.createdAt)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {invoices.data.length === 0 && (
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
                    )}
                    <div className="flex items-center justify-between border-t border-border px-5 py-3 text-xs text-muted-foreground">
                        <span>
                            {invoices.from ?? 0}–{invoices.to ?? 0} sur{' '}
                            {invoices.total}
                        </span>
                        <span>
                            Page {invoices.current_page} / {invoices.last_page}
                        </span>
                    </div>
                </section>
            </div>
            {invoiceDetail && (
                <aside className="fixed inset-y-0 right-0 z-40 w-full max-w-xl overflow-y-auto border-l border-border bg-card p-6 shadow-2xl">
                    <div className="flex items-start justify-between">
                        <div>
                            <p className="text-xs font-black tracking-[0.15em] text-muted-foreground uppercase">
                                Détail fiscal
                            </p>
                            <h2 className="mt-2 text-2xl font-black">
                                {invoiceDetail.reference}
                            </h2>
                        </div>
                        <button
                            type="button"
                            onClick={closeDetail}
                            className="rounded-full p-2 hover:bg-muted"
                            aria-label="Fermer"
                        >
                            <X className="size-5" />
                        </button>
                    </div>
                    <div className="mt-6 grid gap-3 sm:grid-cols-2">
                        <div className="rounded-xl border border-border p-4">
                            <p className="text-xs text-muted-foreground">
                                Statut
                            </p>
                            <KoraStatusBadge
                                label={invoiceDetail.statusLabel}
                                tone={tones[invoiceDetail.status] ?? 'blue'}
                            />
                        </div>
                        <div className="rounded-xl border border-border p-4">
                            <p className="text-xs text-muted-foreground">
                                Montant
                            </p>
                            <strong>
                                {formatMoney(invoiceDetail.amount)}{' '}
                                {invoiceDetail.currency}
                            </strong>
                        </div>
                    </div>
                    {invoiceDetail.status === 'failed' &&
                        permissions.canRetry && (
                            <button
                                type="button"
                                onClick={() =>
                                    router.post(retry.url(invoiceDetail.uuid))
                                }
                                className="mt-5 inline-flex items-center gap-2 rounded-[0.7rem] bg-gold px-4 py-2.5 text-xs font-black text-kora"
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
                                    className="rounded-xl border border-border p-4"
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
