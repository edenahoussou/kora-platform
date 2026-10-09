import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Armchair,
    Building2,
    CalendarDays,
    CreditCard,
    Download,
    FileText,
    MapPin,
    MoveRight,
    Ticket,
    User,
    Users,
    Wallet,
} from 'lucide-react';
import { formatFcfa } from '@/components/company-accounts/company-account-types';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import companyPortal from '@/routes/company-portal';

type SeatAssignment = {
    legOrder: number;
    direction: string;
    origin: string | null;
    destination: string | null;
    travelDate: string | null;
    seat: string | null;
};

type PassengerTicket = {
    uuid: string;
    shortCode: string;
    status: string;
    issuedAt: string | null;
};

type PassengerRow = {
    uuid: string;
    firstName: string;
    lastName: string;
    phone: string | null;
    documentType: string | null;
    documentNumber: string | null;
    seats: SeatAssignment[];
    tickets: PassengerTicket[];
};

type ReservationDetail = {
    uuid: string;
    reference: string;
    createdAt: string | null;
    status: string;
    statusLabel: string;
    purchaseType: string;
    passengerCount: number;
    totalAmount: number;
    currency: string;
    channel: string | null;
    itinerary: { label: string; travelDate: string | null; roundTrip: boolean };
    pricing: {
        totalAmount: number;
        currency: string;
        promotionDiscountAmount: number;
        groupDiscount: {
            applied: boolean;
            amount: number;
        } | null;
        companyDiscount: {
            applied: boolean;
            type: string;
            value: number;
            amount: number;
        } | null;
    };
    legs: {
        direction: string;
        origin: string | null;
        originCity: string | null;
        destination: string | null;
        destinationCity: string | null;
        travelDate: string | null;
        line: string | null;
    }[];
    passengers: PassengerRow[];
    credit: {
        holds: {
            amount: number;
            status: string;
            periodKey: string;
            convertedAt: string | null;
            releasedAt: string | null;
        }[];
        transactions: {
            type: string;
            amount: number;
            periodKey: string;
            reference: string | null;
            createdAt: string | null;
        }[];
    };
};

type Props = {
    account: {
        uuid: string;
        name: string;
        reference: string;
    };
    reservation: ReservationDetail;
};

const statusTones: Record<string, 'navy' | 'gold' | 'blue' | 'muted'> = {
    held: 'gold',
    assistance_pending: 'gold',
    confirmed: 'navy',
    paid: 'navy',
    ticketed: 'blue',
    boarded: 'blue',
    cancelled: 'muted',
    expired: 'muted',
    unavailable: 'muted',
};

const ticketStatusLabels: Record<string, string> = {
    issued: 'Émis',
    used: 'Utilisé',
    cancelled: 'Annulé',
    refunded: 'Remboursé',
};

const creditHoldLabels: Record<string, string> = {
    reserved: 'Bloqué',
    converted: 'Débité',
    released: 'Libéré',
};

const creditTypeLabels: Record<string, string> = {
    debit: 'Débit',
    refund: 'Remboursement',
    adjustment: 'Ajustement',
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

function formatDateTime(iso: string | null): string {
    if (iso === null) {
        return '—';
    }

    return new Date(iso).toLocaleString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function CompanyPortalReservationDetail({
    account,
    reservation,
}: Props) {
    return (
        <>
            <Head title={`Réservation ${reservation.reference}`} />
            <div className="cash-page">
                <div className="kora-page-head cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                            KORA OPS / Espace entreprise / Historique des achats
                            / {reservation.reference}
                        </div>
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                                {reservation.reference}
                            </h1>
                            <KoraStatusBadge
                                label={reservation.statusLabel}
                                tone={
                                    statusTones[reservation.status] ?? 'muted'
                                }
                            />
                        </div>
                        <p className="mt-1.5 text-[0.8125rem] leading-6 text-muted-foreground">
                            {account.name} — achetée le{' '}
                            {formatDate(reservation.createdAt)}
                            {reservation.channel === 'company_portal' &&
                                ' via votre espace entreprise'}
                            .
                        </p>
                    </div>
                    <Link
                        href={companyPortal.reservations.index.url()}
                        className="btn btn-ghost"
                    >
                        <ArrowLeft className="size-4" /> Historique
                    </Link>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="space-y-4 lg:col-span-2">
                        <section className="cash-ledger-card cash-reveal cash-reveal-delay-1">
                            <div className="mb-4 flex items-center gap-2.5">
                                <span className="grid size-9 place-items-center rounded-xl bg-gold/10 text-gold">
                                    <MapPin className="size-4" />
                                </span>
                                <div>
                                    <span className="block text-[0.5625rem] font-extrabold tracking-[0.08em] text-muted-foreground uppercase">
                                        Itinéraire
                                    </span>
                                    <strong className="text-sm font-extrabold text-foreground">
                                        {reservation.itinerary.label}
                                    </strong>
                                </div>
                            </div>
                            <div className="space-y-3">
                                {reservation.legs.map((leg, index) => (
                                    <div
                                        key={index}
                                        className="flex items-center gap-3 rounded-xl bg-muted/45 px-4 py-3 ring-1 ring-black/[0.045]"
                                    >
                                        <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-gold/12 text-[0.625rem] font-black text-[var(--kora)] uppercase">
                                            {leg.direction === 'inbound'
                                                ? 'RET'
                                                : 'ALL'}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-xs font-black text-foreground">
                                                {leg.origin} (
                                                {leg.originCity ?? '—'})
                                                <MoveRight className="mx-1.5 inline size-3 text-gold" />
                                                {leg.destination} (
                                                {leg.destinationCity ?? '—'})
                                            </p>
                                            <p className="mt-0.5 text-[0.625rem] text-muted-foreground">
                                                {leg.line ?? 'Ligne'} — départ{' '}
                                                {leg.travelDate ?? '—'}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                                {reservation.legs.length === 0 && (
                                    <p className="text-xs text-muted-foreground">
                                        Détail de l’itinéraire indisponible.
                                    </p>
                                )}
                            </div>
                        </section>

                        <section className="cash-ledger-card cash-reveal cash-reveal-delay-2">
                            <div className="mb-4 flex items-center gap-2.5">
                                <span className="grid size-9 place-items-center rounded-xl bg-gold/10 text-gold">
                                    <Users className="size-4" />
                                </span>
                                <div>
                                    <span className="block text-[0.5625rem] font-extrabold tracking-[0.08em] text-muted-foreground uppercase">
                                        Voyageurs
                                    </span>
                                    <strong className="text-sm font-extrabold text-foreground">
                                        {reservation.passengerCount} passager
                                        {reservation.passengerCount > 1
                                            ? 's'
                                            : ''}
                                    </strong>
                                </div>
                            </div>
                            <div className="space-y-3">
                                {reservation.passengers.map((passenger) => (
                                    <div
                                        key={passenger.uuid}
                                        className="rounded-xl bg-muted/45 px-4 py-3 ring-1 ring-black/[0.045]"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <div className="flex items-center gap-2.5">
                                                <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-gold/12 text-[0.625rem] font-black text-[var(--kora)] uppercase">
                                                    <User className="size-3.5" />
                                                </span>
                                                <div>
                                                    <p className="text-xs font-black text-foreground">
                                                        {passenger.firstName}{' '}
                                                        {passenger.lastName}
                                                    </p>
                                                    <p className="text-[0.625rem] text-muted-foreground">
                                                        {passenger.phone ??
                                                            'Téléphone non renseigné'}
                                                        {passenger.documentNumber &&
                                                            ` · ${passenger.documentType ?? 'Pièce'} ${passenger.documentNumber}`}
                                                    </p>
                                                </div>
                                            </div>
                                            {passenger.tickets.map((ticket) =>
                                                ['issued', 'used'].includes(
                                                    ticket.status,
                                                ) ? (
                                                    <a
                                                        key={ticket.uuid}
                                                        href={companyPortal.reservations.tickets.pdf.url(
                                                            {
                                                                reservation:
                                                                    reservation.uuid,
                                                                ticket: ticket.uuid,
                                                            },
                                                        )}
                                                        className="inline-flex items-center gap-1 rounded-lg bg-gold/12 px-2 py-1 text-[0.625rem] font-black text-[var(--kora)] transition hover:bg-gold/25"
                                                        title="Télécharger le billet PDF"
                                                    >
                                                        <Download className="size-3" />
                                                        {ticket.shortCode} ·{' '}
                                                        {ticketStatusLabels[
                                                            ticket.status
                                                        ] ?? ticket.status}
                                                    </a>
                                                ) : (
                                                    <span
                                                        key={ticket.uuid}
                                                        className="inline-flex items-center gap-1 rounded-lg bg-gold/12 px-2 py-1 text-[0.625rem] font-black text-[var(--kora)]"
                                                    >
                                                        <Ticket className="size-3" />
                                                        {ticket.shortCode} ·{' '}
                                                        {ticketStatusLabels[
                                                            ticket.status
                                                        ] ?? ticket.status}
                                                    </span>
                                                ),
                                            )}
                                        </div>
                                        {passenger.seats.length > 0 && (
                                            <div className="mt-2.5 flex flex-wrap gap-1.5">
                                                {passenger.seats.map(
                                                    (seat, index) => (
                                                        <span
                                                            key={index}
                                                            className="inline-flex items-center gap-1 rounded-md bg-muted px-2 py-1 text-[0.625rem] font-bold text-muted-foreground"
                                                        >
                                                            <Armchair className="size-3" />
                                                            {seat.direction ===
                                                            'inbound'
                                                                ? 'Retour'
                                                                : 'Aller'}{' '}
                                                            · siège{' '}
                                                            {seat.seat ?? '—'} ·{' '}
                                                            {seat.origin} →{' '}
                                                            {seat.destination}
                                                        </span>
                                                    ),
                                                )}
                                            </div>
                                        )}
                                    </div>
                                ))}
                                {reservation.passengers.length === 0 && (
                                    <p className="text-xs text-muted-foreground">
                                        Les voyageurs seront visibles une fois
                                        les fiches complétées.
                                    </p>
                                )}
                            </div>
                        </section>
                    </div>

                    <div className="space-y-4">
                        <section className="cash-ledger-card cash-reveal cash-reveal-delay-1">
                            <div className="mb-4 flex items-center gap-2.5">
                                <span className="grid size-9 place-items-center rounded-xl bg-gold/10 text-gold">
                                    <Wallet className="size-4" />
                                </span>
                                <span className="block text-[0.5625rem] font-extrabold tracking-[0.08em] text-muted-foreground uppercase">
                                    Tarification
                                </span>
                            </div>
                            <dl className="space-y-2.5 text-xs">
                                {reservation.pricing.companyDiscount
                                    ?.applied && (
                                    <div className="flex items-center justify-between">
                                        <dt className="font-bold text-muted-foreground">
                                            Remise négociée (
                                            {reservation.pricing.companyDiscount
                                                .type === 'percentage'
                                                ? `${reservation.pricing.companyDiscount.value} %`
                                                : 'montant fixe'}
                                            )
                                        </dt>
                                        <dd className="font-black text-emerald-600">
                                            -
                                            {formatFcfa(
                                                reservation.pricing
                                                    .companyDiscount.amount,
                                            )}
                                        </dd>
                                    </div>
                                )}
                                {reservation.pricing.groupDiscount?.applied ===
                                    false &&
                                    reservation.pricing.companyDiscount
                                        ?.applied && (
                                        <div className="flex items-center justify-between">
                                            <dt className="font-bold text-muted-foreground">
                                                Remise groupe
                                            </dt>
                                            <dd className="font-bold text-muted-foreground">
                                                Non applicable
                                            </dd>
                                        </div>
                                    )}
                                {reservation.pricing.promotionDiscountAmount >
                                    0 && (
                                    <div className="flex items-center justify-between">
                                        <dt className="font-bold text-muted-foreground">
                                            Promotion
                                        </dt>
                                        <dd className="font-black text-emerald-600">
                                            -
                                            {formatFcfa(
                                                reservation.pricing
                                                    .promotionDiscountAmount,
                                            )}
                                        </dd>
                                    </div>
                                )}
                                <div className="flex items-center justify-between border-t border-black/[0.045] pt-2.5">
                                    <dt className="font-extrabold text-foreground">
                                        Total débité
                                    </dt>
                                    <dd className="text-sm font-black text-foreground">
                                        {formatFcfa(
                                            reservation.pricing.totalAmount,
                                        )}
                                    </dd>
                                </div>
                            </dl>
                        </section>

                        <section className="cash-ledger-card cash-reveal cash-reveal-delay-2">
                            <div className="mb-4 flex items-center gap-2.5">
                                <span className="grid size-9 place-items-center rounded-xl bg-gold/10 text-gold">
                                    <CreditCard className="size-4" />
                                </span>
                                <span className="block text-[0.5625rem] font-extrabold tracking-[0.08em] text-muted-foreground uppercase">
                                    Imputation crédit
                                </span>
                            </div>
                            <div className="space-y-2.5">
                                {reservation.credit.holds.map((hold, index) => (
                                    <div
                                        key={index}
                                        className="flex items-center justify-between rounded-xl bg-muted/45 px-3.5 py-2.5 ring-1 ring-black/[0.045]"
                                    >
                                        <div>
                                            <p className="text-xs font-black text-foreground">
                                                {formatFcfa(hold.amount)}
                                            </p>
                                            <p className="text-[0.625rem] text-muted-foreground">
                                                période {hold.periodKey}
                                            </p>
                                        </div>
                                        <KoraStatusBadge
                                            label={
                                                creditHoldLabels[hold.status] ??
                                                hold.status
                                            }
                                            tone={
                                                hold.status === 'converted'
                                                    ? 'navy'
                                                    : hold.status === 'released'
                                                      ? 'muted'
                                                      : 'gold'
                                            }
                                        />
                                    </div>
                                ))}
                                {reservation.credit.transactions.map(
                                    (transaction, index) => (
                                        <div
                                            key={index}
                                            className="flex items-center justify-between rounded-xl bg-muted/45 px-3.5 py-2.5 ring-1 ring-black/[0.045]"
                                        >
                                            <div>
                                                <p className="text-xs font-black text-foreground">
                                                    {formatFcfa(
                                                        transaction.amount,
                                                    )}
                                                </p>
                                                <p className="text-[0.625rem] text-muted-foreground">
                                                    {formatDateTime(
                                                        transaction.createdAt,
                                                    )}{' '}
                                                    · {transaction.periodKey}
                                                </p>
                                            </div>
                                            <KoraStatusBadge
                                                label={
                                                    creditTypeLabels[
                                                        transaction.type
                                                    ] ?? transaction.type
                                                }
                                                tone={
                                                    transaction.type ===
                                                    'refund'
                                                        ? 'blue'
                                                        : 'navy'
                                                }
                                            />
                                        </div>
                                    ),
                                )}
                                {reservation.credit.holds.length === 0 &&
                                    reservation.credit.transactions.length ===
                                        0 && (
                                        <p className="text-xs text-muted-foreground">
                                            Aucune imputation crédit enregistrée
                                            pour cette réservation.
                                        </p>
                                    )}
                            </div>
                        </section>

                        <section className="cash-ledger-card cash-reveal cash-reveal-delay-2">
                            <div className="mb-3 flex items-center gap-2.5">
                                <span className="grid size-9 place-items-center rounded-xl bg-gold/10 text-gold">
                                    <FileText className="size-4" />
                                </span>
                                <span className="block text-[0.5625rem] font-extrabold tracking-[0.08em] text-muted-foreground uppercase">
                                    Résumé
                                </span>
                            </div>
                            <dl className="space-y-2 text-xs">
                                <div className="flex items-center justify-between">
                                    <dt className="flex items-center gap-1.5 font-bold text-muted-foreground">
                                        <Building2 className="size-3" /> Compte
                                    </dt>
                                    <dd className="font-black text-foreground">
                                        {account.reference}
                                    </dd>
                                </div>
                                <div className="flex items-center justify-between">
                                    <dt className="flex items-center gap-1.5 font-bold text-muted-foreground">
                                        <CalendarDays className="size-3" />{' '}
                                        Voyage
                                    </dt>
                                    <dd className="font-black text-foreground">
                                        {formatDate(
                                            reservation.itinerary.travelDate,
                                        )}
                                    </dd>
                                </div>
                                <div className="flex items-center justify-between">
                                    <dt className="font-bold text-muted-foreground">
                                        Type d’achat
                                    </dt>
                                    <dd className="font-black text-foreground">
                                        {reservation.purchaseType === 'group'
                                            ? 'Groupé'
                                            : 'Individuel'}
                                    </dd>
                                </div>
                            </dl>
                        </section>
                    </div>
                </div>
            </div>
        </>
    );
}
