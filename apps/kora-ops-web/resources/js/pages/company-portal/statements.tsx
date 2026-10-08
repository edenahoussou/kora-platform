import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarClock,
    ChevronRight,
    FileText,
    MailCheck,
    Ticket,
} from 'lucide-react';
import { formatFcfa } from '@/components/company-accounts/company-account-types';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import companyPortal from '@/routes/company-portal';

type StatementRow = {
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
    generatedAt: string | null;
    emailedAt: string | null;
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
    account: {
        uuid: string;
        name: string;
        reference: string;
    };
    statements: Paginated<StatementRow>;
};

const statusTones: Record<string, 'navy' | 'gold' | 'blue' | 'muted'> = {
    generated: 'gold',
    available: 'blue',
};

function formatDate(iso: string | null): string {
    if (iso === null) {
        return '—';
    }

    return new Date(iso).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

function Pagination({
    currentPage,
    totalPages,
    total,
    from,
    to,
}: {
    currentPage: number;
    totalPages: number;
    total: number;
    from: number | null;
    to: number | null;
}) {
    if (total === 0) {
        return null;
    }

    const goTo = (page: number) => {
        router.get(
            companyPortal.statements.index.url(),
            page > 1 ? { page } : {},
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    return (
        <div className="flex items-center justify-between border-t border-black/[0.045] px-4 py-3 text-xs text-muted-foreground">
            <span>
                {from}–{to} sur {total}
            </span>
            <div className="flex items-center gap-1.5">
                <button
                    type="button"
                    className="grid size-[31px] place-items-center rounded-[8px] bg-muted text-foreground transition-[transform,opacity,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:-translate-x-0.5 hover:bg-gold/15 disabled:pointer-events-none disabled:opacity-35"
                    onClick={() => goTo(currentPage - 1)}
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
                    onClick={() => goTo(currentPage + 1)}
                    disabled={currentPage >= totalPages}
                    aria-label="Page suivante"
                >
                    <ChevronRight className="size-3.5" strokeWidth={1.7} />
                </button>
            </div>
        </div>
    );
}

export default function CompanyPortalStatements({
    account,
    statements,
}: Props) {
    return (
        <>
            <Head title="Relevés de facturation" />
            <div className="cash-page">
                <div className="kora-page-head cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                            KORA OPS / Espace entreprise / Relevés de
                            facturation
                        </div>
                        <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                            Relevés de facturation
                        </h1>
                        <p className="mt-1.5 text-[0.8125rem] leading-6 text-muted-foreground">
                            {account.name} — relevés mensuels consolidés de vos
                            achats réglés par crédit entreprise.
                        </p>
                    </div>
                    <Link
                        href={companyPortal.index.url()}
                        className="btn btn-ghost"
                    >
                        <ArrowLeft className="size-4" /> Retour
                    </Link>
                </div>

                <section className="cash-table-card cash-reveal cash-reveal-delay-1">
                    {statements.data.length === 0 ? (
                        <div className="px-6 py-16 text-center">
                            <span className="mx-auto grid size-12 place-items-center rounded-2xl bg-gold/10 text-gold">
                                <CalendarClock
                                    className="size-5"
                                    strokeWidth={1.6}
                                />
                            </span>
                            <p className="mt-4 text-sm font-bold text-foreground">
                                Aucun relevé disponible
                            </p>
                            <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                Le relevé consolidé de chaque mois civil est
                                généré automatiquement le 1er du mois suivant.
                            </p>
                        </div>
                    ) : (
                        <>
                            <div className="cash-table-scroll overflow-x-auto">
                                <table className="w-full min-w-[860px] text-left">
                                    <thead className="bg-muted/45 text-[0.625rem] font-black tracking-[0.14em] text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-4 py-3">
                                                Période
                                            </th>
                                            <th className="px-4 py-3">
                                                Achats
                                            </th>
                                            <th className="px-4 py-3">
                                                Débits
                                            </th>
                                            <th className="px-4 py-3">
                                                Remboursements
                                            </th>
                                            <th className="px-4 py-3">
                                                Net à payer
                                            </th>
                                            <th className="px-4 py-3">
                                                Statut
                                            </th>
                                            <th className="px-4 py-3 text-right">
                                                Détail
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {statements.data.map((statement) => (
                                            <tr
                                                key={statement.uuid}
                                                className="group border-b border-black/[0.045] transition-[background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:bg-muted/30"
                                            >
                                                <td className="px-4 py-3.5">
                                                    <p className="text-xs font-black text-foreground">
                                                        {statement.periodLabel}
                                                    </p>
                                                    <p className="mt-0.5 flex items-center gap-1 text-[0.625rem] text-muted-foreground">
                                                        <FileText className="size-3" />
                                                        {statement.reference}
                                                    </p>
                                                </td>
                                                <td className="px-4 py-3.5">
                                                    <p className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                                                        <Ticket className="size-3" />
                                                        {
                                                            statement.reservationsCount
                                                        }{' '}
                                                        réservation
                                                        {statement.reservationsCount >
                                                        1
                                                            ? 's'
                                                            : ''}
                                                    </p>
                                                    <p className="mt-0.5 text-[0.625rem] text-muted-foreground">
                                                        {
                                                            statement.invoicesCount
                                                        }{' '}
                                                        facture
                                                        {statement.invoicesCount >
                                                        1
                                                            ? 's'
                                                            : ''}{' '}
                                                        normalisée
                                                        {statement.invoicesCount >
                                                        1
                                                            ? 's'
                                                            : ''}
                                                    </p>
                                                </td>
                                                <td className="px-4 py-3.5 text-xs font-bold text-foreground">
                                                    {formatFcfa(
                                                        statement.debitTotal,
                                                    )}
                                                </td>
                                                <td className="px-4 py-3.5 text-xs font-bold text-foreground">
                                                    {formatFcfa(
                                                        statement.refundTotal,
                                                    )}
                                                </td>
                                                <td className="px-4 py-3.5 text-xs font-black text-foreground">
                                                    {formatFcfa(
                                                        statement.netTotal,
                                                    )}
                                                </td>
                                                <td className="px-4 py-3.5">
                                                    <div className="flex flex-col items-start gap-1">
                                                        <KoraStatusBadge
                                                            label={
                                                                statement.statusLabel
                                                            }
                                                            tone={
                                                                statusTones[
                                                                    statement
                                                                        .status
                                                                ] ?? 'muted'
                                                            }
                                                        />
                                                        {statement.emailedAt !==
                                                            null && (
                                                            <span className="flex items-center gap-1 text-[0.625rem] text-muted-foreground">
                                                                <MailCheck className="size-3" />
                                                                envoyé le{' '}
                                                                {formatDate(
                                                                    statement.emailedAt,
                                                                )}
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3.5 text-right">
                                                    <Link
                                                        href={companyPortal.statements.show.url(
                                                            statement.uuid,
                                                        )}
                                                        className="inline-flex items-center gap-1 text-xs font-bold text-gold transition-colors hover:text-foreground"
                                                    >
                                                        Voir
                                                        <ChevronRight className="size-3.5" />
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <Pagination
                                currentPage={statements.current_page}
                                totalPages={statements.last_page}
                                total={statements.total}
                                from={statements.from}
                                to={statements.to}
                            />
                        </>
                    )}
                </section>
            </div>
        </>
    );
}
