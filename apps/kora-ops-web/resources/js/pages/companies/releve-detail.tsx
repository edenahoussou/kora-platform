import { Head, Link } from '@inertiajs/react';
import {
    ArrowDownLeft,
    ArrowLeft,
    ArrowUpRight,
    CalendarClock,
    Download,
    FileSpreadsheet,
    FileText,
    MailCheck,
    Receipt,
    Ticket,
    Wallet,
} from 'lucide-react';
import { formatFcfa } from '@/components/company-accounts/company-account-types';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import companies from '@/routes/companies';

type StatementLine = {
    type: string;
    reference: string;
    amount: number;
    occurredAt: string | null;
    reservation: {
        uuid: string;
        reference: string;
        itinerary: string;
        passengers: number;
    } | null;
    invoice: {
        uuid: string;
        koraReference: string | null;
        status: string;
        totalWithTax: number;
    } | null;
};

type Statement = {
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
    lines: StatementLine[];
    generatedAt: string | null;
    emailedAt: string | null;
    exportable: boolean;
};

type Props = {
    account: {
        uuid: string;
        name: string;
        reference: string;
    };
    statement: Statement;
};

const statusTones: Record<string, 'navy' | 'gold' | 'blue' | 'muted'> = {
    generated: 'gold',
    available: 'blue',
};

const invoiceStatusLabels: Record<string, string> = {
    pending: 'En attente',
    certified: 'Certifiée',
    failed: 'Échouée',
    canceled: 'Annulée',
};

const invoiceStatusTones: Record<
    string,
    'navy' | 'gold' | 'blue' | 'muted' | 'destructive'
> = {
    pending: 'gold',
    certified: 'blue',
    failed: 'destructive',
    canceled: 'muted',
};

function formatDateTime(iso: string | null): string {
    if (iso === null) {
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

function KpiCard({
    icon: Icon,
    label,
    value,
    hint,
}: {
    icon: typeof Wallet;
    label: string;
    value: string;
    hint?: string;
}) {
    return (
        <div className="cash-ledger-card cash-ledger-card-cash">
            <span className="grid size-9 place-items-center rounded-xl bg-gold/10 text-gold">
                <Icon className="size-4" />
            </span>
            <span className="mt-4 block text-[0.5625rem] font-extrabold tracking-[0.08em] text-muted-foreground uppercase">
                {label}
            </span>
            <strong className="mt-1 block text-lg font-black text-foreground">
                {value}
            </strong>
            {hint !== undefined && (
                <span className="mt-1 block text-[0.625rem] text-muted-foreground">
                    {hint}
                </span>
            )}
        </div>
    );
}

export default function CompanyAccountStatementDetail({
    account,
    statement,
}: Props) {
    return (
        <>
            <Head title={`Relevé ${statement.periodLabel} — ${account.name}`} />
            <div className="cash-page">
                <div className="kora-page-head cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                            <Link
                                href={`/companies/${account.uuid}?tab=factures`}
                                className="transition-colors hover:text-foreground"
                            >
                                KORA OPS / Organisations / {account.name}
                            </Link>{' '}
                            / Relevés / {statement.reference}
                        </div>
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                                {statement.periodLabel}
                            </h1>
                            <KoraStatusBadge
                                label={statement.statusLabel}
                                tone={statusTones[statement.status] ?? 'muted'}
                            />
                        </div>
                        <p className="mt-1.5 text-[0.8125rem] leading-6 text-muted-foreground">
                            Organisation :{' '}
                            <strong className="text-foreground">
                                {account.name}
                            </strong>{' '}
                            ({account.reference}) — relevé {statement.reference}{' '}
                            généré le {formatDateTime(statement.generatedAt)}
                            {statement.emailedAt !== null && (
                                <span className="ml-1 inline-flex items-center gap-1">
                                    <MailCheck className="size-3.5 text-gold" />
                                    envoyé au référent le{' '}
                                    {formatDateTime(statement.emailedAt)}
                                </span>
                            )}
                            .
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2.5">
                        {statement.exportable && (
                            <>
                                <a
                                    href={companies.statements.export.url(
                                        {
                                            companyAccount: account.uuid,
                                            statement: statement.uuid,
                                        },
                                        { query: { format: 'pdf' } },
                                    )}
                                    className="btn btn-primary"
                                >
                                    <Download className="size-4" />
                                    Relevé PDF
                                </a>
                                <a
                                    href={companies.statements.export.url(
                                        {
                                            companyAccount: account.uuid,
                                            statement: statement.uuid,
                                        },
                                        { query: { format: 'xlsx' } },
                                    )}
                                    className="btn btn-navy"
                                >
                                    <FileSpreadsheet className="size-4" />
                                    Excel
                                </a>
                            </>
                        )}
                        <Link
                            href={`/companies/${account.uuid}?tab=factures`}
                            className="btn btn-ghost"
                        >
                            <ArrowLeft className="size-4" /> Retour aux factures
                        </Link>
                    </div>
                </div>

                <section className="cash-ledger-grid cash-reveal cash-reveal-delay-1">
                    <KpiCard
                        icon={Ticket}
                        label="Réservations"
                        value={String(statement.reservationsCount)}
                        hint={`${statement.invoicesCount} facture${
                            statement.invoicesCount > 1 ? 's' : ''
                        } normalisée${statement.invoicesCount > 1 ? 's' : ''}`}
                    />
                    <KpiCard
                        icon={ArrowUpRight}
                        label="Débits confirmés"
                        value={formatFcfa(statement.debitTotal)}
                    />
                    <KpiCard
                        icon={ArrowDownLeft}
                        label="Remboursements"
                        value={formatFcfa(statement.refundTotal)}
                    />
                    <KpiCard
                        icon={Wallet}
                        label="Net à payer"
                        value={formatFcfa(statement.netTotal)}
                        hint={statement.currency}
                    />
                </section>

                <section className="cash-table-card cash-reveal cash-reveal-delay-2 mt-6">
                    <div className="flex items-center justify-between border-b border-black/[0.045] px-4 py-3">
                        <h2 className="flex items-center gap-2 text-sm font-extrabold text-foreground">
                            <Receipt className="size-4 text-gold" />
                            Opérations de la période ({statement.reference})
                        </h2>
                        <span className="text-[0.625rem] font-bold text-muted-foreground">
                            {statement.lines.length} ligne
                            {statement.lines.length > 1 ? 's' : ''}
                        </span>
                    </div>
                    {statement.lines.length === 0 ? (
                        <div className="px-6 py-16 text-center">
                            <span className="mx-auto grid size-12 place-items-center rounded-2xl bg-gold/10 text-gold">
                                <CalendarClock
                                    className="size-5"
                                    strokeWidth={1.6}
                                />
                            </span>
                            <p className="mt-4 text-sm font-bold text-foreground">
                                Aucune opération sur la période
                            </p>
                            <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                Aucun débit ni remboursement de crédit
                                entreprise n'a été enregistré ce mois-ci.
                            </p>
                        </div>
                    ) : (
                        <div className="cash-table-scroll overflow-x-auto">
                            <table className="w-full min-w-[900px] text-left">
                                <thead className="bg-muted/45 text-[0.625rem] font-black tracking-[0.14em] text-muted-foreground uppercase">
                                    <tr>
                                        <th className="px-4 py-3">Date</th>
                                        <th className="px-4 py-3">Type</th>
                                        <th className="px-4 py-3">
                                            Réservation
                                        </th>
                                        <th className="px-4 py-3">
                                            Facture normalisée
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Montant
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {statement.lines.map((line) => (
                                        <tr
                                            key={line.reference}
                                            className="border-b border-black/[0.045] transition-[background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3.5 text-xs font-bold whitespace-nowrap text-foreground">
                                                {formatDateTime(
                                                    line.occurredAt,
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <KoraStatusBadge
                                                    label={
                                                        line.type === 'debit'
                                                            ? 'Débit'
                                                            : 'Remboursement'
                                                    }
                                                    tone={
                                                        line.type === 'debit'
                                                            ? 'navy'
                                                            : 'gold'
                                                    }
                                                />
                                            </td>
                                            <td className="px-4 py-3.5">
                                                {line.reservation !== null ? (
                                                    <>
                                                        <Link
                                                            href={companies.reservations.show.url(
                                                                {
                                                                    companyAccount:
                                                                        account.uuid,
                                                                    reservation:
                                                                        line
                                                                            .reservation
                                                                            .uuid,
                                                                },
                                                            )}
                                                            className="text-xs font-black text-foreground transition-colors hover:text-gold"
                                                        >
                                                            {
                                                                line.reservation
                                                                    .reference
                                                            }
                                                        </Link>
                                                        <p className="mt-0.5 text-[0.625rem] text-muted-foreground">
                                                            {
                                                                line.reservation
                                                                    .itinerary
                                                            }{' '}
                                                            ·{' '}
                                                            {
                                                                line.reservation
                                                                    .passengers
                                                            }{' '}
                                                            voyageur
                                                            {line.reservation
                                                                .passengers > 1
                                                                ? 's'
                                                                : ''}
                                                        </p>
                                                    </>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5">
                                                {line.invoice !== null ? (
                                                    <div className="flex flex-col items-start gap-1">
                                                        <Link
                                                            href={`/fiscal-invoices/${line.invoice.uuid}`}
                                                            className="flex items-center gap-1 text-xs font-bold text-foreground hover:text-gold"
                                                        >
                                                            <FileText className="size-3 text-gold" />
                                                            {line.invoice
                                                                .koraReference ??
                                                                '—'}
                                                        </Link>
                                                        <KoraStatusBadge
                                                            label={
                                                                invoiceStatusLabels[
                                                                    line.invoice
                                                                        .status
                                                                ] ??
                                                                line.invoice
                                                                    .status
                                                            }
                                                            tone={
                                                                invoiceStatusTones[
                                                                    line.invoice
                                                                        .status
                                                                ] ?? 'muted'
                                                            }
                                                        />
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">
                                                        Non émise
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5 text-right text-xs font-black whitespace-nowrap text-foreground">
                                                {line.type === 'refund'
                                                    ? '−'
                                                    : ''}
                                                {formatFcfa(line.amount)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}
