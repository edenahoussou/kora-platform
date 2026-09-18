import { Head, router, useForm } from '@inertiajs/react';
import {
    Check,
    Download,
    FileText,
    Plus,
    RotateCcw,
    Search,
    X,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { formatMoney } from '@/components/sale/sale-utils';
import {
    exportMethod,
    index,
    store,
    approve,
    reject,
    reverse,
} from '@/routes/expenses';

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
};
type ExpenseDetail = Expense & {
    description: string | null;
    payeeReference: string | null;
    createdBy: string | null;
    approvedBy: string | null;
    rejectionReason: string | null;
    reversalReason: string | null;
    media: { uuid: string; name: string; mimeType: string; url: string }[];
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
    categories: { uuid: string; label: string }[];
    ledgers: { uuid: string; label: string }[];
    counters: Record<string, number>;
    permissions: { canCreate: boolean; canExport: boolean };
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

export default function ExpensesPage({
    expenses,
    filters,
    statuses,
    channels,
    categories,
    ledgers,
    counters,
    permissions,
    expenseDetail,
}: Props) {
    const [query, setQuery] = useState(filters.q);
    const [createOpen, setCreateOpen] = useState(false);
    const form = useForm({
        category_uuid: categories[0]?.uuid ?? '',
        ledger_uuid: '',
        payment_channel: 'cash',
        amount: '',
        payee_label: '',
        payee_reference: '',
        description: '',
        occurred_at: new Date().toISOString().slice(0, 16),
        evidence: null as File | null,
    });
    const applyQuery = (next: Record<string, string>) =>
        router.get(
            index.url({ query: { ...filters, ...next } }),
            {},
            { preserveState: true, preserveScroll: true },
        );
    const submitFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        applyQuery({ q: query });
    };
    const submitExpense = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(store.url(), {
            forceFormData: true,
            onSuccess: () => {
                form.reset();
                setCreateOpen(false);
            },
        });
    };
    const closeDetail = () => applyQuery({ expense: '' });

    return (
        <>
            <Head title="Dépenses & sorties" />
            <div className="space-y-6 p-4 md:p-8">
                <header className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="text-[0.65rem] font-black tracking-[0.22em] text-muted-foreground uppercase">
                            Finance / Pilotage
                        </p>
                        <h1 className="mt-2 text-3xl font-black tracking-[-0.04em]">
                            Dépenses & sorties
                        </h1>
                        <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
                            Contrôlez les dépenses du réseau, leurs
                            justificatifs et leur publication dans les journaux
                            de caisse.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {permissions.canCreate && (
                            <button
                                type="button"
                                onClick={() => setCreateOpen((value) => !value)}
                                className="inline-flex items-center gap-2 rounded-[0.7rem] bg-gold px-4 py-2 text-xs font-black text-kora"
                            >
                                <Plus className="size-3.5" /> Nouvelle dépense
                            </button>
                        )}
                        {permissions.canExport && (
                            <>
                                <a
                                    className="inline-flex items-center gap-2 rounded-[0.7rem] border border-border px-3 py-2 text-xs font-bold hover:bg-muted"
                                    href={exportMethod.url({
                                        query: { ...filters, format: 'csv' },
                                    })}
                                >
                                    <Download className="size-3.5" /> CSV
                                </a>
                                <a
                                    className="inline-flex items-center gap-2 rounded-[0.7rem] border border-border px-3 py-2 text-xs font-bold hover:bg-muted"
                                    href={exportMethod.url({
                                        query: { ...filters, format: 'xlsx' },
                                    })}
                                >
                                    <Download className="size-3.5" /> XLSX
                                </a>
                            </>
                        )}
                    </div>
                </header>

                {createOpen && (
                    <section className="rounded-[1.25rem] border border-gold/40 bg-card p-5 shadow-sm">
                        <div className="flex items-center justify-between">
                            <h2 className="font-black">Saisir une dépense</h2>
                            <button
                                type="button"
                                onClick={() => setCreateOpen(false)}
                            >
                                <X className="size-4" />
                            </button>
                        </div>
                        <form
                            onSubmit={submitExpense}
                            className="mt-4 grid gap-3 md:grid-cols-3"
                        >
                            <label className="text-xs font-bold text-muted-foreground">
                                Catégorie
                                <select
                                    value={form.data.category_uuid}
                                    onChange={(event) =>
                                        form.setData(
                                            'category_uuid',
                                            event.target.value,
                                        )
                                    }
                                    className="mt-1.5 h-10 w-full rounded-[0.7rem] border border-border bg-background px-3 text-sm"
                                >
                                    {categories.map((category) => (
                                        <option
                                            key={category.uuid}
                                            value={category.uuid}
                                        >
                                            {category.label}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label className="text-xs font-bold text-muted-foreground">
                                Moyen
                                <select
                                    value={form.data.payment_channel}
                                    onChange={(event) =>
                                        form.setData(
                                            'payment_channel',
                                            event.target.value,
                                        )
                                    }
                                    className="mt-1.5 h-10 w-full rounded-[0.7rem] border border-border bg-background px-3 text-sm"
                                >
                                    {Object.entries(channels).map(
                                        ([value, label]) => (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        ),
                                    )}
                                </select>
                            </label>
                            <label className="text-xs font-bold text-muted-foreground">
                                Ledger (optionnel)
                                <select
                                    value={form.data.ledger_uuid}
                                    onChange={(event) =>
                                        form.setData(
                                            'ledger_uuid',
                                            event.target.value,
                                        )
                                    }
                                    className="mt-1.5 h-10 w-full rounded-[0.7rem] border border-border bg-background px-3 text-sm"
                                >
                                    <option value="">
                                        À rapprocher plus tard
                                    </option>
                                    {ledgers.map((ledger) => (
                                        <option
                                            key={ledger.uuid}
                                            value={ledger.uuid}
                                        >
                                            {ledger.label}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label className="text-xs font-bold text-muted-foreground">
                                Montant XOF
                                <input
                                    required
                                    type="number"
                                    min="1"
                                    value={form.data.amount}
                                    onChange={(event) =>
                                        form.setData(
                                            'amount',
                                            event.target.value,
                                        )
                                    }
                                    className="mt-1.5 h-10 w-full rounded-[0.7rem] border border-border bg-background px-3 text-sm"
                                />
                            </label>
                            <label className="text-xs font-bold text-muted-foreground">
                                Bénéficiaire
                                <input
                                    required
                                    value={form.data.payee_label}
                                    onChange={(event) =>
                                        form.setData(
                                            'payee_label',
                                            event.target.value,
                                        )
                                    }
                                    className="mt-1.5 h-10 w-full rounded-[0.7rem] border border-border bg-background px-3 text-sm"
                                />
                            </label>
                            <label className="text-xs font-bold text-muted-foreground">
                                Date
                                <input
                                    required
                                    type="datetime-local"
                                    value={form.data.occurred_at}
                                    onChange={(event) =>
                                        form.setData(
                                            'occurred_at',
                                            event.target.value,
                                        )
                                    }
                                    className="mt-1.5 h-10 w-full rounded-[0.7rem] border border-border bg-background px-3 text-sm"
                                />
                            </label>
                            <label className="text-xs font-bold text-muted-foreground md:col-span-2">
                                Description
                                <textarea
                                    value={form.data.description}
                                    onChange={(event) =>
                                        form.setData(
                                            'description',
                                            event.target.value,
                                        )
                                    }
                                    className="mt-1.5 min-h-10 w-full rounded-[0.7rem] border border-border bg-background px-3 py-2 text-sm"
                                />
                            </label>
                            <label className="text-xs font-bold text-muted-foreground">
                                Justificatif
                                <input
                                    required
                                    type="file"
                                    accept="image/*,.pdf"
                                    onChange={(event) =>
                                        form.setData(
                                            'evidence',
                                            event.target.files?.[0] ?? null,
                                        )
                                    }
                                    className="mt-1.5 block w-full text-xs"
                                />
                            </label>
                            <div className="md:col-span-3">
                                <button
                                    disabled={form.processing}
                                    className="rounded-[0.7rem] bg-kora px-4 py-2 text-xs font-black text-white"
                                >
                                    {form.processing
                                        ? 'Enregistrement…'
                                        : 'Envoyer pour validation'}
                                </button>
                            </div>
                        </form>
                    </section>
                )}

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    {[
                        ['Total filtré', counters.total],
                        ['À valider', counters.pending],
                        ['Publié', counters.approvedAmount, 'amount'],
                        ['Rejeté', counters.rejected],
                        ['Contrepassé', counters.reversed],
                    ].map(([label, value, kind]) => (
                        <article
                            key={label as string}
                            className="rounded-[1.1rem] border border-border bg-card p-4 shadow-sm"
                        >
                            <p className="text-xs font-bold text-muted-foreground">
                                {label}
                            </p>
                            <strong className="mt-2 block text-2xl font-black">
                                {kind === 'amount'
                                    ? `${formatMoney(Number(value))} XOF`
                                    : value}
                            </strong>
                        </article>
                    ))}
                </section>

                <section className="rounded-[1.25rem] border border-border bg-card p-4 shadow-sm">
                    <form
                        onSubmit={submitFilters}
                        className="grid gap-3 md:grid-cols-[minmax(0,1fr)_180px_180px_auto] md:items-end"
                    >
                        <label className="text-xs font-bold text-muted-foreground">
                            Recherche
                            <input
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                                placeholder="Référence, bénéficiaire, catégorie…"
                                className="mt-1.5 h-10 w-full rounded-[0.7rem] border border-border bg-background px-3 text-sm"
                            />
                        </label>
                        <label className="text-xs font-bold text-muted-foreground">
                            Statut
                            <select
                                value={filters.status}
                                onChange={(event) =>
                                    applyQuery({ status: event.target.value })
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
                            Catégorie
                            <select
                                value={filters.category}
                                onChange={(event) =>
                                    applyQuery({ category: event.target.value })
                                }
                                className="mt-1.5 h-10 w-full rounded-[0.7rem] border border-border bg-background px-3 text-sm"
                            >
                                <option value="">Toutes</option>
                                {categories.map((category) => (
                                    <option
                                        key={category.uuid}
                                        value={category.uuid}
                                    >
                                        {category.label}
                                    </option>
                                ))}
                            </select>
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
                        <table className="w-full min-w-[900px] text-left text-sm">
                            <thead className="border-b border-border bg-muted/40 text-[0.65rem] font-black tracking-[0.12em] text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-5 py-4">Dépense</th>
                                    <th className="px-5 py-4">Catégorie</th>
                                    <th className="px-5 py-4">Moyen</th>
                                    <th className="px-5 py-4">Montant</th>
                                    <th className="px-5 py-4">Statut</th>
                                    <th className="px-5 py-4">Date</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {expenses.data.map((expense) => (
                                    <tr
                                        key={expense.uuid}
                                        onClick={() =>
                                            applyQuery({
                                                expense: expense.uuid,
                                            })
                                        }
                                        className="cursor-pointer transition-colors hover:bg-muted/30"
                                    >
                                        <td className="px-5 py-4">
                                            <div className="font-black">
                                                {expense.reference}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {expense.payeeLabel} ·{' '}
                                                {expense.agency ?? 'Réseau'}
                                            </div>
                                        </td>
                                        <td className="px-5 py-4">
                                            {expense.category ?? '—'}
                                        </td>
                                        <td className="px-5 py-4 text-muted-foreground">
                                            {expense.paymentChannelLabel}
                                            {!expense.isReconciled && (
                                                <span className="ml-2 text-[0.65rem] text-gold">
                                                    À rapprocher
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-5 py-4 font-black">
                                            {formatMoney(expense.amount)}{' '}
                                            {expense.currency}
                                        </td>
                                        <td className="px-5 py-4">
                                            <KoraStatusBadge
                                                label={expense.statusLabel}
                                                tone={
                                                    tones[expense.status] ??
                                                    'blue'
                                                }
                                            />
                                        </td>
                                        <td className="px-5 py-4 text-muted-foreground">
                                            {formatDate(expense.occurredAt)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {expenses.data.length === 0 && (
                        <div className="px-6 py-16 text-center">
                            <FileText className="mx-auto size-8 text-muted-foreground/50" />
                            <h2 className="mt-3 font-black">
                                Aucune dépense dans ce périmètre
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Créez une dépense ou ajustez vos filtres.
                            </p>
                        </div>
                    )}
                    <div className="flex items-center justify-between border-t border-border px-5 py-3 text-xs text-muted-foreground">
                        <span>
                            {expenses.from ?? 0}–{expenses.to ?? 0} sur{' '}
                            {expenses.total}
                        </span>
                        <span>
                            Page {expenses.current_page} / {expenses.last_page}
                        </span>
                    </div>
                </section>
            </div>
            {expenseDetail && (
                <aside className="fixed inset-y-0 right-0 z-40 w-full max-w-xl overflow-y-auto border-l border-border bg-card p-6 shadow-2xl">
                    <div className="flex items-start justify-between">
                        <div>
                            <p className="text-xs font-black tracking-[0.15em] text-muted-foreground uppercase">
                                Détail dépense
                            </p>
                            <h2 className="mt-2 text-2xl font-black">
                                {expenseDetail.reference}
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
                                    className="inline-flex items-center gap-2 rounded-[0.7rem] bg-kora px-4 py-2 text-xs font-black text-white"
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
                                    className="rounded-[0.7rem] border border-destructive/40 px-4 py-2 text-xs font-black text-destructive"
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
                                className="mt-6 inline-flex items-center gap-2 rounded-[0.7rem] border border-border px-4 py-2 text-xs font-black"
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
                                    className="flex items-center gap-2 rounded-lg border border-border p-3 text-sm font-bold hover:bg-muted"
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
