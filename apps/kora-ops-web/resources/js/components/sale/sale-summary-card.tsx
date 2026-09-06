import { ArrowRight, Building2, CheckCircle2, Clock3, ShieldCheck, Users } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { SaleCompanyAccount, SaleDeparture, SaleItinerary, SaleItineraryQuote, SalePayerType, SaleQuote, SaleReservation } from './sale-types';
import {
    formatDate,
    formatMoney,
    formatRemainingTime,
    formatTime,
} from './sale-utils';

type Props = {
    className?: string;
    departure?: SaleDeparture | null;
    passengerCount: number;
    selectedSeatCodes: string[];
    quote: SaleQuote | null;
    itineraryQuote?: SaleItineraryQuote | null;
    selectedItinerary?: SaleItinerary | null;
    selectedInbound?: SaleItinerary | null;
    reservation: SaleReservation | null;
    processing: boolean;
    onQuote: () => void;
    onHold: () => void;
    onOpenSeatPicker?: () => void;
    companyAccounts?: SaleCompanyAccount[];
    payerType?: SalePayerType;
    selectedCompanyAccountUuid?: string | null;
    onPayerTypeChange?: (type: SalePayerType) => void;
    onCompanyAccountChange?: (uuid: string | null) => void;
};

export function SaleSummaryCard({
    className,
    passengerCount,
    selectedSeatCodes,
    itineraryQuote,
    selectedItinerary,
    selectedInbound,
    reservation,
    processing,
    onQuote,
    onHold,
    onOpenSeatPicker,
    companyAccounts = [],
    payerType = 'personal',
    selectedCompanyAccountUuid = null,
}: Props) {

    if (!selectedItinerary) {
        return (
            <aside className={cn('summary empty-summary', className)}>
                Sélectionnez un itinéraire pour préparer la vente.
            </aside>
        );
    }

    // Unified itinerary path — handles direct (1 leg) and multi-leg
    if (selectedItinerary) {
        const firstLeg = selectedItinerary.legs[0];
        const lastLeg = selectedItinerary.legs[selectedItinerary.legs.length - 1];
        const amount = itineraryQuote?.totalAmount ?? selectedItinerary.totalAmount * passengerCount;
        const currency = itineraryQuote?.currency ?? selectedItinerary.currency;
        const canQuote = selectedItinerary !== null;
        const canHold =
            itineraryQuote !== null &&
            itineraryQuote !== undefined &&
            (payerType === 'company'
                ? selectedCompanyAccountUuid !== null &&
                (companyAccounts.find((account) => account.uuid === selectedCompanyAccountUuid)?.availableCredit ?? 0) >= amount
                : true);
        const isGroup = itineraryQuote?.isGroup ?? passengerCount >= 5;
        const hasDiscount = (itineraryQuote?.groupDiscountAmount ?? 0) > 0;

        const renderLegGroup = (
            legs: SaleItinerary['legs'],
            directionLabel: string,
        ) => (
            <div className="summary-leg-group">
                <span className="summary-leg-group-title">{directionLabel}</span>
                {legs.map((leg) => (
                    <div key={leg.legOrder} className="summary-leg">
                        <div className="summary-leg-header">
                            <strong>{leg.line.code}</strong>
                            <span>Jambe {leg.legOrder}</span>
                        </div>
                        <div className="summary-leg-route">
                            {leg.originStop.city} → {leg.destinationStop.city}
                        </div>
                        <div className="summary-leg-price">
                            {formatMoney(leg.amount, currency)} / passager
                        </div>
                    </div>
                ))}
            </div>
        );

        return (
            <aside className={cn('summary', className)}>
                <div className="summary-hero">
                    <small>Itinéraire sélectionné</small>
                    <h3>
                        {firstLeg.originStop.city} →{' '}
                        {lastLeg.destinationStop.city}
                    </h3>
                    <p>
                        {formatDate(firstLeg.departureDate)} ·{' '}
                        {formatTime(firstLeg.departureTime)}
                    </p>
                </div>

                {renderLegGroup(selectedItinerary.legs, 'Aller')}
                {selectedInbound && renderLegGroup(selectedInbound.legs, 'Retour')}

                <div className="summary-row">
                    <span>Passagers</span>
                    <strong>
                        {passengerCount}
                        {isGroup && (
                            <span className="badge badge-group">
                                <Users className="size-3" />
                                Groupe
                            </span>
                        )}
                    </strong>
                </div>
                <div className="summary-row">
                    <span>Sièges</span>
                    <strong>{selectedSeatCodes.join(', ') || 'À choisir'}</strong>
                </div>

                {itineraryQuote && hasDiscount && (
                    <div className="summary-row summary-discount-row">
                        <span>Remise groupe</span>
                        <strong className="is-discount">
                            −{formatMoney(itineraryQuote.groupDiscountAmount, currency)}
                        </strong>
                    </div>
                )}

                {!reservation && companyAccounts.length > 0 && payerType === 'company' && (
                    <div className="summary-payer-section">
                        <span className="field-label">
                            <Building2 className="size-3.5" />
                            Payeur entreprise
                        </span>
                        {(() => {
                            const account = companyAccounts.find(
                                (a) => a.uuid === selectedCompanyAccountUuid,
                            );

                            return account ? (
                                <small className="payment-field-help">
                                    {account.name} · {formatMoney(account.availableCredit, currency)} dispo
                                </small>
                            ) : (
                                <small className="payment-field-help">Compte non sélectionné</small>
                            );
                        })()}
                    </div>
                )}

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
                        {itineraryQuote && (
                            <div className="summary-callout warning">
                                <Clock3 className="size-3.5" />
                                Devis valable encore{' '}
                                {formatRemainingTime(itineraryQuote.expiresAt)}
                            </div>
                        )}
                        {itineraryQuote && onOpenSeatPicker && (
                            <button
                                type="button"
                                onClick={onOpenSeatPicker}
                                className="btn btn-ghost summary-action"
                            >
                                Choisir les places
                            </button>
                        )}
                        <button
                            type="button"
                            disabled={!canQuote || processing}
                            onClick={canHold ? onHold : onQuote}
                            className="btn btn-primary summary-action"
                        >
                            {processing ? (
                                'Traitement…'
                            ) : canHold ? (
                                <>
                                    <ShieldCheck className="size-4" />
                                    Bloquer les places
                                </>
                            ) : (
                                <>
                                    {itineraryQuote ? 'Actualiser le devis' : 'Continuer'}
                                    <ArrowRight className="size-4" />
                                </>
                            )}
                        </button>
                        {!canQuote && (
                            <p className="summary-hint">
                                Sélectionnez un itinéraire aller
                                {selectedInbound ? ' et retour' : ''}.
                            </p>
                        )}
                    </>
                )}
            </aside>
        );
    }
}
