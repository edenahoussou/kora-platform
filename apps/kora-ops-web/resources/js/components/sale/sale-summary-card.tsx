import { ArrowRight, CheckCircle2, Clock3, ShieldCheck } from 'lucide-react';
import type { SaleDeparture, SaleQuote, SaleReservation } from './sale-types';
import {
    formatDate,
    formatMoney,
    formatRemainingTime,
    formatTime,
} from './sale-utils';

type Props = {
    departure: SaleDeparture | null;
    passengerCount: number;
    selectedSeatCodes: string[];
    quote: SaleQuote | null;
    reservation: SaleReservation | null;
    processing: boolean;
    onQuote: () => void;
    onHold: () => void;
};

export function SaleSummaryCard({
    departure,
    passengerCount,
    selectedSeatCodes,
    quote,
    reservation,
    processing,
    onQuote,
    onHold,
}: Props) {
    if (!departure) {
        return (
            <aside className="summary empty-summary">
                Sélectionnez un départ pour préparer la vente.
            </aside>
        );
    }

    const unitAmount =
        quote?.finalAmount ?? departure.farePreview?.final_amount;
    const amount = quote?.totalAmount ?? (unitAmount ?? 0) * passengerCount;
    const currency =
        quote?.currency ?? departure.farePreview?.currency ?? 'XOF';
    const canQuote =
        departure.sellability.isSellable &&
        selectedSeatCodes.length === passengerCount;
    const canHold =
        departure.sellability.isSellable &&
        quote !== null &&
        selectedSeatCodes.length === quote.passengerCount;
    const saleClosed = !departure.sellability.isSellable;

    return (
        <aside className="summary">
            <div className="summary-hero">
                <small>Départ sélectionné</small>
                <h3>
                    {departure.originStop.city} →{' '}
                    {departure.destinationStop.city}
                </h3>
                <p>
                    {formatDate(departure.departureDate)} ·{' '}
                    {formatTime(departure.departureTime)}
                </p>
            </div>

            <div className="summary-row">
                <span>Référence</span>
                <strong>{departure.line.code}</strong>
            </div>
            <div className="summary-row">
                <span>Service</span>
                <strong>
                    {departure.farePreview?.service_class_name ??
                        'Service non renseigné'}
                </strong>
            </div>
            <div className="summary-row">
                <span>Passagers</span>
                <strong>{passengerCount}</strong>
            </div>
            <div className="summary-row">
                <span>Siège</span>
                <strong>{selectedSeatCodes.join(', ') || 'À choisir'}</strong>
            </div>
            <div className="summary-row">
                <span>Tarif unitaire</span>
                <strong>
                    {unitAmount ? formatMoney(unitAmount, currency) : '—'}
                </strong>
            </div>

            <div className="summary-total">
                <span>Total</span>
                <strong>{amount ? formatMoney(amount, currency) : '—'}</strong>
            </div>

            {reservation ? (
                <div className="summary-callout success">
                    <div>
                        <CheckCircle2 className="size-4" />
                        Places bloquées
                    </div>
                    <p>
                        Expiration dans{' '}
                        {formatRemainingTime(reservation.expiresAt)}.
                    </p>
                </div>
            ) : (
                <>
                    {(saleClosed ||
                        departure.sellability.reason === 'within_grace') && (
                        <div className="summary-callout warning">
                            <Clock3 className="size-3.5" />
                            {departure.sellability.reason === 'within_grace'
                                ? `Vente prolongée · fermeture dans ${formatRemainingTime(departure.sellability.saleClosesAt)}`
                                : 'Vente fermée pour ce départ.'}
                        </div>
                    )}
                    {quote && (
                        <div className="summary-callout warning">
                            <Clock3 className="size-3.5" />
                            Devis valable encore{' '}
                            {formatRemainingTime(quote.expiresAt)}
                        </div>
                    )}
                    <button
                        type="button"
                        disabled={!canQuote || processing}
                        onClick={canHold ? onHold : onQuote}
                        className="btn btn-primary summary-action"
                    >
                        {processing ? (
                            'Traitement…'
                        ) : saleClosed ? (
                            'Vente fermée'
                        ) : canHold ? (
                            <>
                                <ShieldCheck className="size-4" />
                                Bloquer les places
                            </>
                        ) : (
                            <>
                                {quote ? 'Actualiser le devis' : 'Continuer'}
                                <ArrowRight className="size-4" />
                            </>
                        )}
                    </button>
                    {!saleClosed && !canQuote && (
                        <p className="summary-hint">
                            Sélectionnez exactement {passengerCount} place
                            {passengerCount > 1 ? 's' : ''}.
                        </p>
                    )}
                </>
            )}
        </aside>
    );
}
