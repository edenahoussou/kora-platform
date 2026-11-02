import { Head, Link, useForm } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    CheckCircle2,
    Clock3,
    Coins,
    Info,
    ReceiptText,
    Search,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import {
    Money,
    Pagination,
    tableCellClass,
    tableHeadClass,
} from '@/components/kora-points/partner/partner-ui';
import type { Paginated } from '@/types/kora-point-partner';

type EligibleSale = {
    uuid: string;
    point_name: string;
    point_code: string;
    amount: number;
    paid_at: string | null;
};

type Remittance = {
    uuid: string;
    status: string;
    amount: number;
    received_amount: number | null;
    method: string;
    reference: string;
    submitted_at: string | null;
    validated_at: string | null;
};

type RemittanceForm = {
    sale_uuids: string[];
    method: string;
    reference: string;
};

type RemittanceSummary = {
    outstanding_amount: number;
    eligible_count: number;
    eligible_amount: number;
    pending_amount: number;
    validated_amount: number;
};

type Props = {
    organization_name: string;
    remittances: Paginated<Remittance>;
    eligible_sales: EligibleSale[];
    remittance_summary: RemittanceSummary;
};

const statusLabels: Record<string, string> = {
    draft: 'À compléter',
    submitted: 'En vérification KORA',
    reconciliation: 'À rapprocher',
    validated: 'Validé',
    rejected: 'Rejeté',
};

const statusClasses: Record<string, string> = {
    draft: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    submitted:
        'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200',
    reconciliation:
        'bg-blue-100 text-blue-800 dark:bg-blue-950/50 dark:text-blue-200',
    validated:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200',
    rejected:
        'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-200',
};

const methodLabels: Record<string, string> = {
    bank_transfer: 'Virement bancaire',
    bank_deposit: 'Dépôt bancaire',
    mobile_money: 'Mobile Money',
    cash: 'Espèces',
    check: 'Chèque',
};

function SummaryCard({
    icon: Icon,
    label,
    amount,
    hint,
    tone,
}: {
    icon: LucideIcon;
    label: string;
    amount: number;
    hint: string;
    tone: 'navy' | 'digital' | 'gold' | 'emerald';
}) {
    const toneClasses = {
        navy: 'cash-ledger-card-kpi-navy',
        digital: 'cash-ledger-card-kpi-digital',
        gold: 'cash-ledger-card-kpi-gold',
        emerald: 'cash-ledger-card-kpi-emerald',
    };

    return (
        <article className={`cash-ledger-card ${toneClasses[tone]}`}>
            <div className="cash-ledger-head">
                <div className="cash-ledger-identity">
                    <span className="cash-ledger-brand">
                        <Icon className="size-4" strokeWidth={1.8} />
                    </span>
                    <div>
                        <span className="cash-ledger-title">{label}</span>
                        <small>{hint}</small>
                    </div>
                </div>
            </div>
            <div className="cash-ledger-balance mb-0">
                <span>Réseau</span>
                <strong>
                    <Money amount={amount} />
                </strong>
            </div>
        </article>
    );
}

export default function PartnerRemittancesPage({
    organization_name,
    remittances,
    eligible_sales,
    remittance_summary,
}: Props) {
    const [eligibleSearch, setEligibleSearch] = useState('');
    const [historySearch, setHistorySearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const form = useForm<RemittanceForm>({
        sale_uuids: [],
        method: 'bank_transfer',
        reference: '',
    });

    const visibleEligibleSales = useMemo(() => {
        const query = eligibleSearch.trim().toLocaleLowerCase('fr');

        if (!query) {
            return eligible_sales;
        }

        return eligible_sales.filter((sale) =>
            `${sale.uuid} ${sale.point_name} ${sale.point_code}`
                .toLocaleLowerCase('fr')
                .includes(query),
        );
    }, [eligibleSearch, eligible_sales]);

    const selectedAmount = useMemo(
        () =>
            eligible_sales
                .filter((sale) => form.data.sale_uuids.includes(sale.uuid))
                .reduce((total, sale) => total + sale.amount, 0),
        [eligible_sales, form.data.sale_uuids],
    );

    const visibleRemittances = useMemo(() => {
        const query = historySearch.trim().toLocaleLowerCase('fr');

        return remittances.data.filter((remittance) => {
            const matchesStatus =
                statusFilter === 'all' || remittance.status === statusFilter;
            const matchesSearch =
                !query ||
                `${remittance.reference} ${remittance.method}`
                    .toLocaleLowerCase('fr')
                    .includes(query);

            return matchesStatus && matchesSearch;
        });
    }, [historySearch, remittances.data, statusFilter]);

    const toggleSale = (uuid: string, checked: boolean) => {
        form.setData(
            'sale_uuids',
            checked
                ? [...form.data.sale_uuids, uuid]
                : form.data.sale_uuids.filter((selected) => selected !== uuid),
        );
    };

    const selectVisibleSales = () => {
        const selected = new Set(form.data.sale_uuids);
        visibleEligibleSales.forEach((sale) => selected.add(sale.uuid));
        form.setData('sale_uuids', [...selected]);
    };

    const clearSelection = () => form.setData('sale_uuids', []);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post('/kora-points/remittances', {
            preserveScroll: true,
        });
    };

    const summaryCards: {
        icon: LucideIcon;
        label: string;
        amount: number;
        hint: string;
        tone: 'navy' | 'digital' | 'gold' | 'emerald';
    }[] = [
        {
            icon: ReceiptText,
            label: 'Encours total',
            amount: remittance_summary.outstanding_amount,
            hint: 'À reverser par le réseau',
            tone: 'navy',
        },
        {
            icon: Coins,
            label: 'Ventes à déclarer',
            amount: remittance_summary.eligible_amount,
            hint: `${remittance_summary.eligible_count} vente(s) éligible(s)`,
            tone: 'digital',
        },
        {
            icon: Clock3,
            label: 'En contrôle Finance',
            amount: remittance_summary.pending_amount,
            hint: 'Bordereaux soumis ou à rapprocher',
            tone: 'gold',
        },
        {
            icon: CheckCircle2,
            label: 'Total validé',
            amount: remittance_summary.validated_amount,
            hint: 'Fonds reçus et rapprochés',
            tone: 'emerald',
        },
    ];

    return (
        <>
            <Head title={`Reversements — ${organization_name}`} />
            <div className="cash-page space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <header className="kora-page-head cash-reveal mb-6">
                    <p className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                        KORA POINT / Espace partenaire / Reversements
                    </p>
                    <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                        Reversements de {organization_name}
                    </h1>
                    <p className="mt-1.5 max-w-3xl text-[0.8125rem] leading-6 text-muted-foreground">
                        Regroupez les ventes en encours de plusieurs Points dans
                        un même bordereau. Finance KORA contrôle le justificatif
                        et valide le reversement.
                    </p>
                </header>

                <section
                    className="cash-ledger-grid cash-reveal cash-reveal-delay-1 grid grid-cols-1 gap-3.5 sm:grid-cols-2 xl:grid-cols-4"
                    aria-label="Synthèse des reversements réseau"
                >
                    {summaryCards.map((card) => (
                        <SummaryCard key={card.label} {...card} />
                    ))}
                </section>

                <div className="flex items-start gap-3 rounded-2xl border border-blue-500/20 bg-blue-500/10 p-4 text-xs text-foreground">
                    <Info className="mt-0.5 size-5 shrink-0 text-digital" />
                    <p className="leading-relaxed text-muted-foreground">
                        Ce parcours couvre tout le réseau de {organization_name}
                        . Vous pouvez sélectionner des ventes de Points
                        différents dans le même bordereau; chaque lot accepte
                        jusqu’à 500 ventes. Le montant de l’encours ne baisse
                        qu’après validation par Finance KORA.
                    </p>
                </div>

                <section
                    className="cash-reveal overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    aria-labelledby="eligible-sales-title"
                >
                    <div className="flex flex-col gap-4 border-b border-slate-100 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-slate-800">
                        <div>
                            <h2
                                id="eligible-sales-title"
                                className="text-sm font-extrabold text-slate-900 dark:text-white"
                            >
                                Préparer un reversement groupé
                            </h2>
                            <p className="mt-1 text-xs text-slate-500">
                                Choisissez les ventes éligibles sur l’ensemble
                                des Points.
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                onClick={selectVisibleSales}
                                disabled={visibleEligibleSales.length === 0}
                                className="btn btn-ghost btn-sm disabled:opacity-50"
                            >
                                Tout sélectionner
                            </button>
                            <button
                                type="button"
                                onClick={clearSelection}
                                disabled={form.data.sale_uuids.length === 0}
                                className="btn btn-ghost btn-sm disabled:opacity-50"
                            >
                                Effacer la sélection
                            </button>
                        </div>
                    </div>

                    {eligible_sales.length > 0 ? (
                        <form onSubmit={submit}>
                            <div className="cash-filterbar flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                                <label className="relative block flex-1 sm:max-w-md">
                                    <span className="sr-only">
                                        Rechercher une vente ou un Point
                                    </span>
                                    <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                                    <input
                                        value={eligibleSearch}
                                        onChange={(event) =>
                                            setEligibleSearch(
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Rechercher un Point ou une vente…"
                                        className="w-full rounded-xl border border-border bg-muted/40 py-2 pr-4 pl-9 text-xs font-medium text-foreground placeholder:text-muted-foreground focus:border-gold focus:bg-card focus:outline-none"
                                    />
                                </label>
                                <p className="text-xs text-muted-foreground">
                                    {form.data.sale_uuids.length}{' '}
                                    sélectionnée(s) ·{' '}
                                    <strong className="text-foreground">
                                        <Money amount={selectedAmount} />
                                    </strong>
                                </p>
                            </div>

                            {remittance_summary.eligible_count >
                            eligible_sales.length ? (
                                <p className="border-b border-amber-500/20 bg-amber-500/10 px-5 py-3 text-xs text-amber-900 dark:text-amber-100">
                                    Les {eligible_sales.length} ventes les plus
                                    récentes sont affichées. Créez un premier
                                    bordereau, puis revenez pour traiter les
                                    ventes restantes.
                                </p>
                            ) : null}

                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[760px] border-collapse text-left text-xs">
                                    <thead className="border-b border-slate-100 bg-slate-50/75 text-slate-500 dark:border-slate-800 dark:bg-slate-800/40">
                                        <tr>
                                            <th className="w-14 px-5 py-3.5 font-bold uppercase">
                                                Choisir
                                            </th>
                                            <th className={tableHeadClass}>
                                                Point de vente
                                            </th>
                                            <th className={tableHeadClass}>
                                                Vente
                                            </th>
                                            <th className={tableHeadClass}>
                                                Date
                                            </th>
                                            <th
                                                className={`${tableHeadClass} text-right`}
                                            >
                                                Encours à régler
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                        {visibleEligibleSales.map((sale) => (
                                            <tr
                                                key={sale.uuid}
                                                className="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/30"
                                            >
                                                <td className={tableCellClass}>
                                                    <input
                                                        type="checkbox"
                                                        checked={form.data.sale_uuids.includes(
                                                            sale.uuid,
                                                        )}
                                                        onChange={(event) =>
                                                            toggleSale(
                                                                sale.uuid,
                                                                event.target
                                                                    .checked,
                                                            )
                                                        }
                                                        aria-label={`Sélectionner la vente ${sale.uuid}`}
                                                    />
                                                </td>
                                                <td className={tableCellClass}>
                                                    <span className="block font-semibold">
                                                        {sale.point_name}
                                                    </span>
                                                    <span className="text-[0.6875rem] text-muted-foreground">
                                                        {sale.point_code}
                                                    </span>
                                                </td>
                                                <td
                                                    className={`${tableCellClass} font-mono text-[0.6875rem]`}
                                                >
                                                    {sale.uuid
                                                        .slice(0, 8)
                                                        .toUpperCase()}
                                                </td>
                                                <td className={tableCellClass}>
                                                    {sale.paid_at
                                                        ? new Intl.DateTimeFormat(
                                                              'fr-FR',
                                                              {
                                                                  dateStyle:
                                                                      'medium',
                                                              },
                                                          ).format(
                                                              new Date(
                                                                  sale.paid_at,
                                                              ),
                                                          )
                                                        : '—'}
                                                </td>
                                                <td
                                                    className={`${tableCellClass} text-right font-semibold`}
                                                >
                                                    <Money
                                                        amount={sale.amount}
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                        {visibleEligibleSales.length === 0 ? (
                                            <tr>
                                                <td
                                                    className="px-5 py-8 text-center text-sm text-muted-foreground"
                                                    colSpan={5}
                                                >
                                                    Aucune vente ne correspond à
                                                    cette recherche.
                                                </td>
                                            </tr>
                                        ) : null}
                                    </tbody>
                                </table>
                            </div>

                            {form.errors.sale_uuids ? (
                                <p className="px-5 pt-3 text-sm text-destructive">
                                    {form.errors.sale_uuids}
                                </p>
                            ) : null}
                            <div className="grid gap-4 border-t border-slate-100 p-5 sm:grid-cols-3 dark:border-slate-800">
                                <label className="block text-sm font-medium text-foreground">
                                    Mode de paiement
                                    <select
                                        className="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                        value={form.data.method}
                                        onChange={(event) =>
                                            form.setData(
                                                'method',
                                                event.target.value,
                                            )
                                        }
                                    >
                                        <option value="bank_transfer">
                                            Virement bancaire
                                        </option>
                                        <option value="bank_deposit">
                                            Dépôt bancaire
                                        </option>
                                        <option value="mobile_money">
                                            Mobile Money
                                        </option>
                                        <option value="cash">Espèces</option>
                                        <option value="check">Chèque</option>
                                    </select>
                                    {form.errors.method ? (
                                        <span className="mt-1 block text-xs text-destructive">
                                            {form.errors.method}
                                        </span>
                                    ) : null}
                                </label>
                                <label className="block text-sm font-medium text-foreground">
                                    Référence du paiement
                                    <input
                                        className="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                        value={form.data.reference}
                                        onChange={(event) =>
                                            form.setData(
                                                'reference',
                                                event.target.value,
                                            )
                                        }
                                        required
                                    />
                                    {form.errors.reference ? (
                                        <span className="mt-1 block text-xs text-destructive">
                                            {form.errors.reference}
                                        </span>
                                    ) : null}
                                </label>
                                <div className="flex items-end justify-end">
                                    <button
                                        type="submit"
                                        disabled={
                                            form.processing ||
                                            form.data.sale_uuids.length === 0
                                        }
                                        className="btn btn-primary disabled:opacity-50"
                                    >
                                        {form.processing
                                            ? 'Préparation…'
                                            : 'Préparer le bordereau'}
                                    </button>
                                </div>
                            </div>
                        </form>
                    ) : (
                        <div className="p-10 text-center">
                            <Coins className="mx-auto size-8 text-slate-400" />
                            <h3 className="mt-3 text-sm font-bold text-slate-900 dark:text-white">
                                Aucune vente disponible pour un reversement
                            </h3>
                            <p className="mx-auto mt-1 max-w-xl text-xs leading-5 text-slate-500">
                                Les ventes apparaissent ici lorsqu’elles ont un
                                encours positif et ne sont pas déjà incluses
                                dans un bordereau ou une demande de
                                remboursement en cours.
                            </p>
                        </div>
                    )}
                </section>

                <section
                    className="cash-reveal overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    aria-labelledby="remittance-history-title"
                >
                    <div className="flex flex-col gap-4 border-b border-slate-100 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-slate-800">
                        <div>
                            <h2
                                id="remittance-history-title"
                                className="text-sm font-extrabold text-slate-900 dark:text-white"
                            >
                                Bordereaux du réseau
                            </h2>
                            <p className="mt-1 text-xs text-slate-500">
                                Suivez les déclarations et les validations de
                                Finance KORA.
                            </p>
                        </div>
                        <label className="relative block w-full sm:max-w-xs">
                            <span className="sr-only">
                                Rechercher dans les bordereaux
                            </span>
                            <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                            <input
                                value={historySearch}
                                onChange={(event) =>
                                    setHistorySearch(event.target.value)
                                }
                                placeholder="Référence ou mode de paiement…"
                                className="w-full rounded-xl border border-border bg-muted/40 py-2 pr-4 pl-9 text-xs font-medium text-foreground placeholder:text-muted-foreground focus:border-gold focus:bg-card focus:outline-none"
                            />
                        </label>
                    </div>
                    <div className="flex flex-wrap gap-1.5 border-b border-slate-100 px-5 py-3 dark:border-slate-800">
                        {[
                            {
                                value: 'all',
                                label: `Tous (${remittances.total})`,
                            },
                            { value: 'submitted', label: 'En vérification' },
                            { value: 'reconciliation', label: 'À rapprocher' },
                            { value: 'validated', label: 'Validés' },
                            { value: 'rejected', label: 'Rejetés' },
                        ].map((filter) => (
                            <button
                                key={filter.value}
                                type="button"
                                onClick={() => setStatusFilter(filter.value)}
                                className={`btn btn-sm ${statusFilter === filter.value ? 'btn-navy' : 'btn-ghost'}`}
                            >
                                {filter.label}
                            </button>
                        ))}
                    </div>
                    {visibleRemittances.length > 0 ? (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[760px] border-collapse text-left text-xs">
                                <thead className="border-b border-slate-100 bg-slate-50/75 text-slate-500 dark:border-slate-800 dark:bg-slate-800/40">
                                    <tr>
                                        <th className={tableHeadClass}>
                                            Référence
                                        </th>
                                        <th className={tableHeadClass}>Mode</th>
                                        <th
                                            className={`${tableHeadClass} text-right`}
                                        >
                                            Montant déclaré
                                        </th>
                                        <th
                                            className={`${tableHeadClass} text-right`}
                                        >
                                            Reçu par KORA
                                        </th>
                                        <th className={tableHeadClass}>
                                            Statut
                                        </th>
                                        <th className={tableHeadClass}>
                                            Détail
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {visibleRemittances.map((remittance) => (
                                        <tr
                                            key={remittance.uuid}
                                            className="hover:bg-slate-50/80 dark:hover:bg-slate-800/30"
                                        >
                                            <td className={tableCellClass}>
                                                <span className="block font-mono font-bold">
                                                    {remittance.reference}
                                                </span>
                                                <span className="text-[0.6875rem] text-muted-foreground">
                                                    {remittance.submitted_at
                                                        ? new Intl.DateTimeFormat(
                                                              'fr-FR',
                                                              {
                                                                  dateStyle:
                                                                      'medium',
                                                              },
                                                          ).format(
                                                              new Date(
                                                                  remittance.submitted_at,
                                                              ),
                                                          )
                                                        : 'Brouillon'}
                                                </span>
                                            </td>
                                            <td className={tableCellClass}>
                                                {methodLabels[
                                                    remittance.method
                                                ] ?? remittance.method}
                                            </td>
                                            <td
                                                className={`${tableCellClass} text-right font-semibold`}
                                            >
                                                <Money
                                                    amount={remittance.amount}
                                                />
                                            </td>
                                            <td
                                                className={`${tableCellClass} text-right`}
                                            >
                                                {remittance.received_amount ===
                                                null ? (
                                                    '—'
                                                ) : (
                                                    <Money
                                                        amount={
                                                            remittance.received_amount
                                                        }
                                                    />
                                                )}
                                            </td>
                                            <td className={tableCellClass}>
                                                <span
                                                    className={`inline-flex rounded-full px-2.5 py-1 text-[0.6875rem] font-semibold ${statusClasses[remittance.status] ?? statusClasses.draft}`}
                                                >
                                                    {statusLabels[
                                                        remittance.status
                                                    ] ?? remittance.status}
                                                </span>
                                            </td>
                                            <td className={tableCellClass}>
                                                <Link
                                                    href={`/kora-points/remittances/${remittance.uuid}`}
                                                    className="font-bold text-primary hover:underline"
                                                >
                                                    Ouvrir
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <p className="px-5 py-10 text-center text-sm text-muted-foreground">
                            {remittances.total === 0
                                ? 'Aucun bordereau enregistré pour ce réseau.'
                                : 'Aucun bordereau ne correspond aux filtres.'}
                        </p>
                    )}
                    {remittances.links.length > 3 ? (
                        <Pagination links={remittances.links} />
                    ) : null}
                </section>
            </div>
        </>
    );
}
