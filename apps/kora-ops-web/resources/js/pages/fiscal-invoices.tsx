import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Ban,
    CheckCircle2,
    ChevronRight,
    Download,
    Eye,
    FileCheck2,
    FileSpreadsheet,
    FileText,
    RefreshCw,
    Search,
    SlidersHorizontal,
    Table,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { formatMoney } from '@/components/sale/sale-utils';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import {
    credit,
    exportMethod,
    index,
    pdf,
    retry,
} from '@/routes/fiscal-invoices';

type Client = { name: string | null; contact: string | null };
type Invoice = {
    uuid: string;
    reference: string;
    koraReference: string;
    mecefUid: string | null;
    documentKind: string;
    documentType: string;
    documentTypeLabel: string;
    reservationReference: string | null;
    reservationUuid: string | null;
    client: Client;
    status: string;
    statusLabel: string;
    mecefStatus: string | null;
    lastError: string | null;
    amount: number;
    currency: string;
    attemptCount: number;
    creditNotesCount: number;
    createdAt: string | null;
    finalizedAt: string | null;
};
type Item = {
    lineKey: string;
    code: string | null;
    name: string;
    price: number;
    quantity: number;
    taxGroup: string | null;
    taxSpecific: number | null;
    originalPrice: number | null;
    priceModification: string | null;
    creditedQuantity: number;
    remainingQuantity: number;
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
};
type Fiscal = {
    uid: string | null;
    ifu: string | null;
    aib: string | null;
    type: string | null;
    reference: string | null;
    operatorId: string | null;
    operatorName: string | null;
    clientIfu: string | null;
    clientName: string | null;
    clientContact: string | null;
    clientAddress: string | null;
    taxes: Record<string, string | number>;
    status: string | null;
    statusLabel: string | null;
    codeMecEfDgi: string | null;
    qrCode: string | null;
    dateTime: string | null;
    counters: string | null;
    nim: string | null;
    errorCode: string | null;
    errorDescription: string | null;
    submittedAt: string | null;
    finalizedAt: string | null;
};
type InvoiceDetail = Invoice & {
    fiscal: Fiscal;
    items: Item[];
    payments: { name: string; label: string; amount: number }[];
    attempts: Attempt[];
    creditNotes: Invoice[];
    originalInvoice: Invoice | null;
    reason: string | null;
    requestedAt: string | null;
    requestedBy: string | null;
    sourceSnapshot: Record<string, unknown> | null;
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
    permissions: { canRetry: boolean; canCredit: boolean };
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

function Field({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="flex items-start justify-between gap-4 border-b border-border/60 py-2.5 last:border-0">
            <span className="text-xs text-muted-foreground">{label}</span>
            <strong className="max-w-[65%] text-right text-xs text-foreground">
                {value || '—'}
            </strong>
        </div>
    );
}

function Section({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <section className="rounded-xl border border-border/70 bg-card">
            <h3 className="border-b border-border/70 bg-muted/35 px-4 py-3 text-[0.625rem] font-black tracking-[0.14em] text-muted-foreground uppercase">
                {title}
            </h3>
            <div className="p-4">{children}</div>
        </section>
    );
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
                className="cash-filter-select h-11 w-full appearance-none rounded-xl bg-muted/55 px-3 pr-8 text-xs font-bold text-foreground ring-1 ring-black/[0.045] outline-none focus:bg-card focus:ring-2 focus:ring-gold/35"
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

function DetailDialog({
    invoice,
    open,
    onClose,
    permissions,
    onCredit,
}: {
    invoice: InvoiceDetail | null | undefined;
    open: boolean;
    onClose: () => void;
    permissions: Props['permissions'];
    onCredit: () => void;
}) {
    if (!invoice) {
        return null;
    }

    const taxes = invoice.fiscal.taxes ?? {};

    return (
        <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
            <DialogContent className="max-h-[92vh] max-w-6xl overflow-y-auto p-0">
                <DialogHeader className="border-b border-border/70 bg-kora px-6 py-5 text-white">
                    <div className="flex flex-wrap items-start justify-between gap-4 pr-8">
                        <div>
                            <span className="text-[0.625rem] font-black tracking-[0.18em] text-gold uppercase">
                                Détail de facturation
                            </span>
                            <DialogTitle className="mt-2 text-2xl font-extrabold tracking-[-0.04em] text-white">
                                {invoice.reference}
                            </DialogTitle>
                            <DialogDescription className="mt-1 text-xs text-white/65">
                                {invoice.documentTypeLabel} ·{' '}
                                {invoice.koraReference}
                            </DialogDescription>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <KoraStatusBadge
                                label={invoice.statusLabel}
                                tone={tones[invoice.status] ?? 'blue'}
                            />
                            <a
                                href={pdf.url(invoice.uuid)}
                                className="btn btn-primary inline-flex items-center gap-2"
                                onClick={(event) => event.stopPropagation()}
                            >
                                <Download className="size-3.5" /> PDF A4
                            </a>
                        </div>
                    </div>
                </DialogHeader>

                <div className="grid gap-4 p-6 lg:grid-cols-2">
                    <Section title="Identification fiscale">
                        <Field
                            label="Référence fiscale / DGI"
                            value={invoice.reference}
                        />
                        <Field
                            label="Référence facture API"
                            value={invoice.fiscal.reference}
                        />
                        <Field
                            label="UID e‑MECeF"
                            value={invoice.fiscal.uid ?? invoice.mecefUid}
                        />
                        <Field
                            label="Type"
                            value={`${invoice.documentType} · ${invoice.documentTypeLabel}`}
                        />
                        <Field
                            label="Statut e‑MECeF"
                            value={invoice.fiscal.statusLabel}
                        />
                        <Field
                            label="Réservation"
                            value={invoice.reservationReference}
                        />
                        <Field
                            label="Client"
                            value={
                                invoice.fiscal.clientName ?? invoice.client.name
                            }
                        />
                    </Section>
                    <Section title="Vendeur, opérateur et client">
                        <Field label="IFU vendeur" value={invoice.fiscal.ifu} />
                        <Field label="AIB" value={invoice.fiscal.aib} />
                        <Field
                            label="Opérateur"
                            value={invoice.fiscal.operatorName}
                        />
                        <Field
                            label="IFU client"
                            value={invoice.fiscal.clientIfu}
                        />
                        <Field
                            label="Contact client"
                            value={
                                invoice.fiscal.clientContact ??
                                invoice.client.contact
                            }
                        />
                        <Field
                            label="Adresse client"
                            value={invoice.fiscal.clientAddress}
                        />
                    </Section>

                    <Section title="Items fiscaux">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[780px] text-left text-xs">
                                <thead className="text-[0.625rem] font-black tracking-[0.12em] text-muted-foreground uppercase">
                                    <tr>
                                        <th className="pb-2">Item</th>
                                        <th className="pb-2">Taxe</th>
                                        <th className="pb-2 text-right">Qté</th>
                                        <th className="pb-2 text-right">
                                            Déjà avoir
                                        </th>
                                        <th className="pb-2 text-right">
                                            Reste
                                        </th>
                                        <th className="pb-2 text-right">
                                            Prix
                                        </th>
                                        <th className="pb-2 text-right">
                                            Total
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {invoice.items.map((item) => (
                                        <tr
                                            key={item.lineKey}
                                            className="border-t border-border/60"
                                        >
                                            <td className="py-2.5">
                                                <strong>{item.name}</strong>
                                                <div className="text-[0.65rem] text-muted-foreground">
                                                    Code : {item.code ?? '—'}
                                                    {item.originalPrice !== null
                                                        ? ` · Prix d’origine : ${formatMoney(item.originalPrice, invoice.currency)}`
                                                        : ''}
                                                    {item.priceModification
                                                        ? ` · ${item.priceModification}`
                                                        : ''}
                                                </div>
                                            </td>
                                            <td className="py-2.5">
                                                {item.taxGroup ?? '—'}
                                                {item.taxSpecific !== null
                                                    ? ` · ${item.taxSpecific}`
                                                    : ''}
                                            </td>
                                            <td className="py-2.5 text-right">
                                                {item.quantity}
                                            </td>
                                            <td className="py-2.5 text-right">
                                                {item.creditedQuantity}
                                            </td>
                                            <td className="py-2.5 text-right">
                                                {item.remainingQuantity}
                                            </td>
                                            <td className="py-2.5 text-right">
                                                {formatMoney(
                                                    item.price,
                                                    invoice.currency,
                                                )}
                                            </td>
                                            <td className="py-2.5 text-right font-bold">
                                                {formatMoney(
                                                    item.price * item.quantity,
                                                    invoice.currency,
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Section>
                    <Section title="Totaux et groupes de taxe">
                        <div className="grid gap-x-5 sm:grid-cols-2">
                            {Object.entries(taxes).map(([key, value]) => (
                                <Field
                                    key={key}
                                    label={key.toUpperCase()}
                                    value={formatMoney(
                                        Number(value),
                                        invoice.currency,
                                    )}
                                />
                            ))}
                        </div>
                    </Section>

                    <Section title="Paiements">
                        <div>
                            {invoice.payments.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Aucun paiement fiscal enregistré.
                                </p>
                            ) : (
                                invoice.payments.map((payment, index) => (
                                    <Field
                                        key={`${payment.name}-${index}`}
                                        label={payment.label || payment.name}
                                        value={formatMoney(
                                            payment.amount,
                                            invoice.currency,
                                        )}
                                    />
                                ))
                            )}
                        </div>
                    </Section>
                    <Section title="Certification MECeF">
                        <Field
                            label="Code MECeF/DGI"
                            value={invoice.fiscal.codeMecEfDgi}
                        />
                        <Field label="NIM" value={invoice.fiscal.nim} />
                        <Field
                            label="Compteurs"
                            value={invoice.fiscal.counters}
                        />
                        <Field
                            label="Date/heure"
                            value={invoice.fiscal.dateTime}
                        />
                        <Field
                            label="Finalisée le"
                            value={formatDate(invoice.fiscal.finalizedAt)}
                        />
                        <Field
                            label="QR"
                            value={
                                invoice.fiscal.qrCode
                                    ? 'Disponible'
                                    : 'Non disponible'
                            }
                        />
                    </Section>

                    {invoice.originalInvoice && (
                        <Section title="Facture d’origine">
                            <Field
                                label="Référence"
                                value={invoice.originalInvoice.reference}
                            />
                            <Field
                                label="Montant d’origine"
                                value={formatMoney(
                                    invoice.originalInvoice.amount,
                                    invoice.currency,
                                )}
                            />
                            <Field label="Motif" value={invoice.reason} />
                        </Section>
                    )}
                    <Section title="Avoirs liés">
                        <div className="space-y-2">
                            {invoice.creditNotes.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Aucun avoir lié.
                                </p>
                            ) : (
                                invoice.creditNotes.map((note) => (
                                    <div
                                        key={note.uuid}
                                        className="flex items-center justify-between rounded-lg bg-muted/40 px-3 py-2 text-xs"
                                    >
                                        <span>
                                            <strong>{note.reference}</strong>
                                            <span className="ml-2 text-muted-foreground">
                                                {note.statusLabel}
                                            </span>
                                        </span>
                                        <a
                                            href={pdf.url(note.uuid)}
                                            className="text-kora underline-offset-2 hover:underline"
                                        >
                                            PDF
                                        </a>
                                    </div>
                                ))
                            )}
                        </div>
                    </Section>
                </div>

                <div className="border-t border-border/70 px-6 py-4">
                    {invoice.lastError && (
                        <p className="mb-3 rounded-lg bg-destructive/10 px-3 py-2 text-xs font-semibold text-destructive">
                            {invoice.lastError}
                        </p>
                    )}
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div className="text-xs text-muted-foreground">
                            {invoice.attempts.length} tentative(s) · créée le{' '}
                            {formatDate(invoice.createdAt)}
                        </div>
                        <div className="flex gap-2">
                            {invoice.status === 'failed' &&
                                permissions.canRetry && (
                                    <button
                                        type="button"
                                        className="btn btn-ghost"
                                        onClick={() =>
                                            router.post(retry.url(invoice.uuid))
                                        }
                                    >
                                        <RefreshCw className="size-3.5" />{' '}
                                        Relancer
                                    </button>
                                )}
                            {permissions.canCredit &&
                                invoice.documentKind === 'sale' &&
                                invoice.status === 'certified' && (
                                    <button
                                        type="button"
                                        className="btn btn-primary"
                                        onClick={onCredit}
                                    >
                                        <FileText className="size-3.5" />{' '}
                                        Initier un avoir
                                    </button>
                                )}
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}

function CreditNoteDialog({
    invoice,
    open,
    onClose,
}: {
    invoice: InvoiceDetail | null;
    open: boolean;
    onClose: () => void;
}) {
    const [mode, setMode] = useState<'full' | 'partial'>('full');
    const [reason, setReason] = useState('Correction de la facture de vente');
    const [quantities, setQuantities] = useState<Record<string, number>>(() =>
        invoice
            ? Object.fromEntries(
                  invoice.items
                      .filter((item) => item.remainingQuantity > 0)
                      .map((item) => [item.lineKey, item.remainingQuantity]),
              )
            : {},
    );

    if (!invoice) {
        return null;
    }

    const lines = invoice.items
        .filter(
            (item) =>
                (quantities[item.lineKey] ?? 0) > 0 &&
                item.remainingQuantity > 0,
        )
        .map((item) => ({
            key: item.lineKey,
            quantity: quantities[item.lineKey],
        }));

    return (
        <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Initier une facture d’avoir</DialogTitle>
                    <DialogDescription>
                        La facture sera soumise puis confirmée automatiquement
                        par un job e‑MECeF.
                    </DialogDescription>
                </DialogHeader>
                <div className="space-y-5">
                    <div className="grid gap-2 sm:grid-cols-2">
                        <button
                            type="button"
                            className={cn(
                                'rounded-xl border p-3 text-left text-sm',
                                mode === 'full'
                                    ? 'border-gold bg-gold/10'
                                    : 'border-border',
                            )}
                            onClick={() => setMode('full')}
                        >
                            <strong>Avoir total</strong>
                            <span className="mt-1 block text-xs text-muted-foreground">
                                Toutes les lignes restantes
                            </span>
                        </button>
                        <button
                            type="button"
                            className={cn(
                                'rounded-xl border p-3 text-left text-sm',
                                mode === 'partial'
                                    ? 'border-gold bg-gold/10'
                                    : 'border-border',
                            )}
                            onClick={() => setMode('partial')}
                        >
                            <strong>Avoir partiel</strong>
                            <span className="mt-1 block text-xs text-muted-foreground">
                                Choisir les lignes et quantités
                            </span>
                        </button>
                    </div>
                    {mode === 'partial' && (
                        <div className="max-h-64 space-y-2 overflow-y-auto rounded-xl border border-border p-3">
                            {invoice.items.map((item) => (
                                <label
                                    key={item.lineKey}
                                    className="flex items-center gap-3 rounded-lg bg-muted/30 p-3 text-xs"
                                >
                                    <input
                                        type="checkbox"
                                        checked={
                                            (quantities[item.lineKey] ?? 0) > 0
                                        }
                                        disabled={item.remainingQuantity <= 0}
                                        onChange={(event) =>
                                            setQuantities((current) => ({
                                                ...current,
                                                [item.lineKey]: event.target
                                                    .checked
                                                    ? item.remainingQuantity
                                                    : 0,
                                            }))
                                        }
                                    />
                                    <span className="min-w-0 flex-1">
                                        <strong className="block truncate">
                                            {item.name}
                                        </strong>
                                        <span className="text-muted-foreground">
                                            Reste : {item.remainingQuantity}
                                        </span>
                                    </span>
                                    <input
                                        type="number"
                                        min="0"
                                        max={item.remainingQuantity}
                                        step="0.001"
                                        value={quantities[item.lineKey] ?? 0}
                                        disabled={
                                            (quantities[item.lineKey] ?? 0) <= 0
                                        }
                                        onChange={(event) =>
                                            setQuantities((current) => ({
                                                ...current,
                                                [item.lineKey]: Math.min(
                                                    item.remainingQuantity,
                                                    Math.max(
                                                        0,
                                                        Number(
                                                            event.target.value,
                                                        ),
                                                    ),
                                                ),
                                            }))
                                        }
                                        className="h-9 w-24 rounded-lg border border-border bg-background px-2 text-right"
                                    />
                                </label>
                            ))}
                        </div>
                    )}
                    <label className="block">
                        <span className="text-xs font-bold text-foreground">
                            Motif obligatoire
                        </span>
                        <textarea
                            value={reason}
                            onChange={(event) => setReason(event.target.value)}
                            rows={3}
                            maxLength={500}
                            className="mt-2 w-full rounded-xl border border-border bg-background p-3 text-sm outline-none focus:ring-2 focus:ring-gold/35"
                        />
                    </label>
                    <p className="rounded-xl bg-gold/10 px-3 py-2 text-xs text-foreground">
                        L’avoir sera créé avec les mêmes taxes et informations
                        client que la facture d’origine. Les quantités seront
                        transmises en négatif selon le contrat e‑MECeF.
                    </p>
                </div>
                <DialogFooter>
                    <button
                        type="button"
                        className="btn btn-ghost"
                        onClick={onClose}
                    >
                        Annuler
                    </button>
                    <button
                        type="button"
                        className="btn btn-primary"
                        disabled={
                            reason.trim() === '' ||
                            (mode === 'partial' && lines.length === 0)
                        }
                        onClick={() => {
                            router.post(
                                credit.url(invoice.uuid),
                                {
                                    mode,
                                    reason,
                                    lines: mode === 'partial' ? lines : [],
                                    idempotency_key: crypto.randomUUID(),
                                },
                                { onSuccess: onClose },
                            );
                        }}
                    >
                        Confirmer l’émission
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
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
    const [creditOpen, setCreditOpen] = useState(false);
    const statusOptions = useMemo<[string, string][]>(
        () => [['all', 'Tous les statuts'], ...Object.entries(statuses)],
        [statuses],
    );
    const params = () => ({
        ...(query.trim() ? { q: query.trim() } : {}),
        ...(status !== 'all' ? { status } : {}),
        ...(from ? { from } : {}),
        ...(to ? { to } : {}),
    });
    const applyFilters = () =>
        router.get(
            index.url({ query: params() }),
            {},
            { preserveScroll: true, preserveState: true },
        );
    const closeDetail = () =>
        router.get(
            index.url({ query: params() }),
            {},
            { preserveState: true, preserveScroll: true },
        );
    const openDetail = (uuid: string) =>
        router.get(
            index.url({ query: { ...params(), invoice: uuid } }),
            {},
            { preserveState: true, preserveScroll: true },
        );
    const exportQuery = (format: 'csv' | 'xlsx') =>
        exportMethod.url({ query: { ...params(), format } });
    const goToPage = (page: number) =>
        router.get(
            index.url({ query: { ...params(), page } }),
            {},
            { preserveScroll: true, preserveState: true },
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
                            Factures fiscales, certification e‑MECeF et avoirs
                            liés à chaque réservation.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <a className="btn btn-ghost" href={exportQuery('csv')}>
                            <Table className="size-3.5" /> CSV
                        </a>
                        <a
                            className="btn btn-primary"
                            href={exportQuery('xlsx')}
                        >
                            <FileSpreadsheet className="size-3.5" /> Excel
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
                        label="Avoirs / annulées"
                        value={counters.canceled}
                        tone="muted"
                    />
                </section>
                <div className="cash-reveal cash-reveal-delay-2 cash-section-intro">
                    <div>
                        <span className="section-kicker">Journal fiscal</span>
                        <h2>Factures de vente et d’avoir</h2>
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
                        placeholder="Référence fiscale, client, réservation…"
                        className="cash-filter-search"
                    />
                    <FilterSelect
                        label="Statut"
                        value={status}
                        onChange={setStatus}
                        options={statusOptions}
                    />
                    <label className="cash-filter-field">
                        <span className="sr-only">Du</span>
                        <input
                            type="date"
                            value={from}
                            onChange={(event) => setFrom(event.target.value)}
                            className="cash-filter-select h-11 w-full rounded-xl bg-muted/55 px-3 text-xs font-bold"
                        />
                    </label>
                    <label className="cash-filter-field">
                        <span className="sr-only">Au</span>
                        <input
                            type="date"
                            value={to}
                            onChange={(event) => setTo(event.target.value)}
                            className="cash-filter-select h-11 w-full rounded-xl bg-muted/55 px-3 text-xs font-bold"
                        />
                    </label>
                    <button
                        type="submit"
                        className="btn btn-navy cash-filter-submit"
                    >
                        <SlidersHorizontal className="size-4" /> Filtrer
                    </button>
                    <button
                        type="button"
                        className="btn btn-ghost cash-filter-reset"
                        onClick={() => {
                            setQuery('');
                            setStatus('all');
                            setFrom('');
                            setTo('');
                            router.get(
                                index.url(),
                                {},
                                { preserveState: true },
                            );
                        }}
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
                                Modifiez les filtres ou attendez une nouvelle
                                certification.
                            </p>
                        </div>
                    ) : (
                        <div className="cash-table-scroll overflow-x-auto">
                            <table className="w-full min-w-[1080px] text-left">
                                <thead className="bg-muted/45 text-[0.625rem] font-black tracking-[0.14em] text-muted-foreground uppercase">
                                    <tr>
                                        <th className="px-4 py-3">
                                            Référence fiscale
                                        </th>
                                        <th className="px-4 py-3">Type</th>
                                        <th className="px-4 py-3">
                                            Réservation / client
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Montant
                                        </th>
                                        <th className="px-4 py-3">Statut</th>
                                        <th className="px-4 py-3">Date</th>
                                        <th className="px-4 py-3 text-right">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {invoices.data.map((invoice) => (
                                        <tr
                                            key={invoice.uuid}
                                            className="group cursor-pointer border-b border-border/60 transition-colors hover:bg-muted/30"
                                            onClick={() =>
                                                openDetail(invoice.uuid)
                                            }
                                        >
                                            <td className="px-4 py-3.5">
                                                <div className="flex items-center gap-3">
                                                    <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-gold/12 text-kora">
                                                        <FileCheck2
                                                            className="size-4"
                                                            strokeWidth={1.6}
                                                        />
                                                    </span>
                                                    <div>
                                                        <p className="text-xs font-black">
                                                            {invoice.reference}
                                                        </p>
                                                        <p className="mt-0.5 text-[0.625rem] text-muted-foreground">
                                                            {invoice.mecefUid ??
                                                                'UID e‑MECeF à venir'}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <KoraStatusBadge
                                                    label={invoice.documentType}
                                                    tone={
                                                        invoice.documentKind ===
                                                        'credit_note'
                                                            ? 'gold'
                                                            : 'blue'
                                                    }
                                                />
                                                <p className="mt-1 text-[0.625rem] text-muted-foreground">
                                                    {invoice.documentTypeLabel}
                                                </p>
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <p className="text-xs font-bold">
                                                    {invoice.reservationReference ??
                                                        invoice.reservationUuid ??
                                                        '—'}
                                                </p>
                                                <p className="mt-1 text-[0.6875rem] text-muted-foreground">
                                                    {invoice.client.name ??
                                                        invoice.client
                                                            .contact ??
                                                        'Client non renseigné'}
                                                </p>
                                            </td>
                                            <td className="px-4 py-3.5 text-right text-xs font-black">
                                                {formatMoney(
                                                    invoice.amount,
                                                    invoice.currency,
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <KoraStatusBadge
                                                    label={invoice.statusLabel}
                                                    tone={
                                                        tones[invoice.status] ??
                                                        'blue'
                                                    }
                                                />
                                                {invoice.creditNotesCount >
                                                    0 && (
                                                    <p className="mt-1 text-[0.625rem] text-muted-foreground">
                                                        {
                                                            invoice.creditNotesCount
                                                        }{' '}
                                                        avoir(s)
                                                    </p>
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5 text-xs text-muted-foreground">
                                                {formatDate(
                                                    invoice.finalizedAt ??
                                                        invoice.createdAt,
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <div
                                                    className="flex justify-end gap-1.5"
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                >
                                                    <a
                                                        href={pdf.url(
                                                            invoice.uuid,
                                                        )}
                                                        className="grid size-8 place-items-center rounded-lg bg-muted text-foreground hover:bg-gold/15"
                                                        aria-label="Télécharger le PDF"
                                                    >
                                                        <Download className="size-3.5" />
                                                    </a>
                                                    <button
                                                        type="button"
                                                        className="grid size-8 place-items-center rounded-lg bg-muted text-foreground hover:bg-gold/15"
                                                        aria-label="Voir le détail"
                                                        onClick={() =>
                                                            openDetail(invoice.uuid)
                                                        }
                                                    >
                                                        <Eye className="size-3.5" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}{' '}
                    {invoices.total > 0 && (
                        <div className="flex items-center justify-between border-t border-border/60 px-4 py-3 text-xs text-muted-foreground">
                            <span>
                                {invoices.from}–{invoices.to} sur{' '}
                                {invoices.total}
                            </span>
                            <div className="flex items-center gap-1.5">
                                <button
                                    type="button"
                                    className="grid size-[31px] place-items-center rounded-lg bg-muted disabled:opacity-40"
                                    disabled={invoices.current_page <= 1}
                                    onClick={() =>
                                        goToPage(invoices.current_page - 1)
                                    }
                                    aria-label="Page précédente"
                                >
                                    <ChevronRight className="size-3.5 rotate-180" />
                                </button>
                                <span className="px-2 font-bold text-foreground">
                                    {invoices.current_page} /{' '}
                                    {invoices.last_page}
                                </span>
                                <button
                                    type="button"
                                    className="grid size-[31px] place-items-center rounded-lg bg-muted disabled:opacity-40"
                                    disabled={
                                        invoices.current_page >=
                                        invoices.last_page
                                    }
                                    onClick={() =>
                                        goToPage(invoices.current_page + 1)
                                    }
                                    aria-label="Page suivante"
                                >
                                    <ChevronRight className="size-3.5" />
                                </button>
                            </div>
                        </div>
                    )}
                </section>
            </div>
            <DetailDialog
                invoice={invoiceDetail}
                open={Boolean(invoiceDetail)}
                onClose={closeDetail}
                permissions={permissions}
                onCredit={() => setCreditOpen(true)}
            />
            <CreditNoteDialog
                key={invoiceDetail?.uuid ?? 'credit-note'}
                invoice={creditOpen ? (invoiceDetail ?? null) : null}
                open={creditOpen}
                onClose={() => setCreditOpen(false)}
            />
        </>
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
    const styles = {
        navy: 'bg-kora text-white',
        gold: 'bg-gold/15 text-gold',
        blue: 'bg-digital/10 text-digital',
        muted: 'bg-muted text-muted-foreground',
        green: 'bg-emerald-500/10 text-emerald-600',
        destructive: 'bg-destructive/10 text-destructive',
    };

    return (
        <article className="cash-ledger-card cash-ledger-card-cash">
            <div className="cash-ledger-head">
                <div className="cash-ledger-identity">
                    <span className={cn('cash-ledger-brand', styles[tone])}>
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
