import { router, usePoll } from '@inertiajs/react';
import { AlertCircle, Check, LoaderCircle, Plus, Printer, RefreshCw } from 'lucide-react';
import { useEffect } from 'react';
import { retry } from '@/routes/sale/tickets';
import { ReceiptQr } from './receipt-qr';
import type { SaleDeparture, SaleReservation, SaleTicket } from './sale-types';
import { formatMoney, formatTime } from './sale-utils';

export function CounterSaleSuccess({
    departure,
    reservation,
    onNewSale,
    releasing,
}: {
    departure: SaleDeparture | null;
    reservation: SaleReservation;
    onNewSale: () => void;
    releasing: boolean;
}) {
    useEffect(() => {
        const clearPrintMode = () => {
            document.body.classList.remove('receipt-print-mode', 'single-ticket-print-mode');
            document.querySelectorAll('.is-print-target').forEach((element) => element.classList.remove('is-print-target'));
        };

        window.addEventListener('afterprint', clearPrintMode);

        return () => {
            window.removeEventListener('afterprint', clearPrintMode);
            clearPrintMode();
        };
    }, []);

    const issuancePending = ['pending', 'processing'].includes(
        reservation.ticketIssuance.status,
    );
    const { stop, start } = usePoll(
        2000,
        { only: ['reservation'] },
        { autoStart: issuancePending, keepAlive: true, mode: 'rest' },
    );
    useEffect(() => {
        if (issuancePending) {
            start();
        } else {
            stop();
        }
    }, [issuancePending, start, stop]);

    const payment = reservation.payment;
    const ticketCount = reservation.tickets.length;
    const paymentName = paymentLabel(payment);

    return (
        <section className="sale-success">
            <header className="success-header">
                <div className="success-mark" aria-hidden="true">
                    <Check size={22} strokeWidth={1.8} />
                </div>
                <div className="success-copy">
                    <span className="hero-kicker">Paiement confirmé</span>
                    <h1>
                        {issuancePending
                            ? 'Émission des billets en cours…'
                            : ticketCount > 1
                            ? `${ticketCount} billets sont prêts.`
                            : 'Le billet est prêt.'}
                    </h1>
                    <p>
                        {ticketCount > 1
                            ? `La transaction ${departure?.line.code ?? 'KORA'} a été encaissée par ${paymentName.toLowerCase()} et intégrée à la caisse journalière.`
                            : `La place ${reservation.tickets[0]?.seatCode ?? '—'} est réservée pour ${passengerName(reservation, reservation.tickets[0])}. Le paiement ${paymentName.toLowerCase()} est enregistré dans la caisse journalière.`}
                    </p>
                </div>
            </header>

            <div className="success-workspace">
                <div className="issued-ticket-list">
                    {issuancePending && (
                        <div className="summary-callout warning" aria-live="polite">
                            <LoaderCircle className="size-4 animate-spin" />
                            Paiement conservé. Les billets disponibles apparaissent automatiquement.
                        </div>
                    )}
                    {reservation.ticketIssuance.status === 'failed' && (
                        <div className="summary-callout warning" role="alert">
                            <AlertCircle className="size-4" />
                            <div>
                                <strong>Émission incomplète</strong>
                                <p>{reservation.ticketIssuance.error ?? 'Une erreur a empêché certains billets.'}</p>
                                <button
                                    type="button"
                                    className="btn btn-primary mt-3"
                                    onClick={() => router.post(retry.url(reservation.uuid), {}, { preserveScroll: true })}
                                >
                                    <RefreshCw className="size-4" /> Relancer l’émission
                                </button>
                            </div>
                        </div>
                    )}
                    {reservation.tickets.map((ticket) => (
                        <TicketCard
                            key={ticket.uuid}
                            departure={departure}
                            fiscalization={reservation.fiscalization}
                            payment={payment}
                            reservation={reservation}
                            ticket={ticket}
                        />
                    ))}
                </div>

                <aside
                    className="success-action-rail ticket-screen-only"
                    aria-label="Actions du billet"
                >
                    <div className="action-rail-heading">
                        <span>Actions</span>
                        <small>Après encaissement</small>
                    </div>
                    {ticketCount > 0 && <button
                        type="button"
                        className="action-rail-button action-rail-button-primary"
                        onClick={() => {
                            document.body.classList.add('receipt-print-mode');
                            window.print();
                        }}
                    >
                        <span className="action-rail-icon" aria-hidden="true">
                            <Printer size={18} strokeWidth={1.7} />
                        </span>
                        <span className="action-rail-label">
                            <strong>Imprimer tout le groupe</strong>
                            <small>{ticketCount} billet{ticketCount > 1 ? 's' : ''}</small>
                        </span>
                    </button>}
                    <button
                        type="button"
                        className="action-rail-button action-rail-button-new"
                        onClick={onNewSale}
                        disabled={releasing}
                    >
                        <span className="action-rail-icon" aria-hidden="true">
                            <Plus size={18} strokeWidth={1.7} />
                        </span>
                        <span className="action-rail-label">
                            <strong>
                                {releasing ? 'Libération…' : 'Nouvelle vente'}
                            </strong>
                            <small>Revenir au guichet</small>
                        </span>
                    </button>
                </aside>
            </div>
        </section>
    );
}

function TicketCard({
    departure,
    fiscalization,
    payment,
    reservation,
    ticket,
}: {
    departure: SaleDeparture | null;
    fiscalization: SaleReservation['fiscalization'];
    payment: SaleReservation['payment'];
    reservation: SaleReservation;
    ticket: SaleTicket;
}) {
    const passenger = reservation.passengers.find(
        (item) => item.uuid === ticket.passengerUuid,
    );
    const ticketAmount = ticket.allocationAmount;

    return (
        <article className="ticket-card compact-ticket" data-ticket-uuid={ticket.uuid}>
            <div className="ticket-brand">
                <div className="ticket-brand-lockup">
                    <img src="/kora-logo-sidebar.png" alt="KORA EXPRESS" />
                </div>
                <span className="status navy">Payé</span>
            </div>

            <div className="ticket-route-large">
                <span>
                    <b>{stopCode(departure?.originStop.city)}</b>
                    <small>{departure?.originStop.city ?? 'Départ'}</small>
                </span>
                <i aria-hidden="true">
                    <em>K</em>
                </i>
                <span>
                    <b>{stopCode(departure?.destinationStop.city)}</b>
                    <small>
                        {departure?.destinationStop.city ?? 'Arrivée'}
                    </small>
                </span>
            </div>

            <div className="ticket-content">
                <div className="ticket-grid">
                    <TicketField
                        label="Passager"
                        value={
                            passenger
                                ? `${passenger.firstName} ${passenger.lastName}`
                                : '—'
                        }
                    />
                    <TicketField
                        label="Date"
                        value={formatTicketDate(
                            departure?.departureDate ?? null,
                        )}
                    />
                    <TicketField
                        label="Départ estimé"
                        value={
                            departure
                                ? `${departure.line.code} · ${formatTime(departure.departureTime)}`
                                : '—'
                        }
                    />
                    <TicketField label="Sièges" value={ticket.legs.map((leg) => leg.seatCode).filter(Boolean).join(' · ') || ticket.seatCode || '—'} />
                    <TicketField
                        label="Numéro billet"
                        value={shortUuid(ticket.ticketNumber)}
                    />
                    <TicketField
                        label="Montant"
                        value={formatMoney(ticketAmount, reservation.currency)}
                    />
                    <TicketField label="Code court" value={ticket.shortCode} />
                    <TicketField
                        label="Numéro MECeF"
                        value={
                            fiscalization?.mecefUid ??
                            fiscalization?.koraReference ??
                            'En attente'
                        }
                    />
                </div>
                <div className="ticket-qr" aria-label="QR Code billet">
                    {ticket.qrCode ? (
                        <ReceiptQr value={ticket.qrCode} />
                    ) : (
                        <span>QR indisponible</span>
                    )}
                    <small>{ticket.shortCode}</small>
                </div>
            </div>

            <div className="ticket-code">
                <span aria-hidden="true" />
                <b>
                    Achat guichet définitif · Agence{' '}
                    {payment?.agencyCode ?? '—'}
                </b>
                <span aria-hidden="true" />
            </div>
            <button
                type="button"
                className="btn btn-ghost ticket-screen-only mt-3 w-full"
                onClick={() => {
                    const target = document.querySelector<HTMLElement>(`[data-ticket-uuid="${ticket.uuid}"]`);
                    target?.classList.add('is-print-target');
                    document.body.classList.add('receipt-print-mode', 'single-ticket-print-mode');
                    window.print();
                    target?.classList.remove('is-print-target');
                    document.body.classList.remove('single-ticket-print-mode');
                }}
            >
                <Printer className="size-4" /> Imprimer ce billet
            </button>
        </article>
    );
}

function TicketField({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <small>{label}</small>
            <strong>{value}</strong>
        </div>
    );
}

function passengerName(
    reservation: SaleReservation,
    ticket: SaleTicket | undefined,
): string {
    const passenger = reservation.passengers.find(
        (item) => item.uuid === ticket?.passengerUuid,
    );

    return passenger ? `${passenger.firstName} ${passenger.lastName}` : '—';
}

function paymentLabel(payment: SaleReservation['payment']): string {
    if (!payment) {
        return 'paiement';
    }

    if (payment.method === 'company_credit') {
        return 'crédit entreprise';
    }

    if (payment.method !== 'mobile_money') {
        return 'espèces';
    }

    return payment.network === 'mtn_momo'
        ? 'MTN MoMo'
        : payment.network === 'moov_flooz'
            ? 'Moov Money / Flooz'
            : 'Celtis Cash';
}

function stopCode(city: string | undefined): string {
    return city
        ? city
            .replace(/[^a-zA-Z]/g, '')
            .slice(0, 3)
            .toUpperCase()
        : '—';
}

function formatTicketDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('fr-FR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    })
        .format(new Date(`${value}T12:00:00`))
        .replace('.', '')
        .toUpperCase();
}

function shortUuid(uuid: string): string {
    return `${uuid.slice(0, 8)}…${uuid.slice(-4)}`;
}
