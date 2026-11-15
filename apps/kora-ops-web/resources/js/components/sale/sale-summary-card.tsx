import {
    Building2,
    CheckCircle2,
    Clock3,
    LayoutGrid,
    Tag,
    Users,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import type {
    SaleCompanyAccount,
    SaleDeparture,
    SaleItinerary,
    SaleItineraryQuote,
    SalePayerType,
    SaleQuote,
    SaleReservation,
} from './sale-types';
import { formatStopSchedule, formatMoney } from './sale-utils';
import { useHoldCountdown } from './use-hold-countdown';

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
    onOpenSeatPicker?: () => void;
    companyAccounts?: SaleCompanyAccount[];
    payerType?: SalePayerType;
    selectedCompanyAccountUuid?: string | null;
    onPayerTypeChange?: (type: SalePayerType) => void;
    onCompanyAccountChange?: (uuid: string | null) => void;
    promoCode?: string;
    onPromoCodeChange?: (code: string) => void;
    onApplyPromoCode?: (code: string | null) => void;
    companyPortal?: boolean;
    companyName?: string;
    creditAvailable?: number;
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
    onOpenSeatPicker,
    companyAccounts = [],
    payerType = 'personal',
    selectedCompanyAccountUuid = null,
    promoCode = '',
    onPromoCodeChange,
    onApplyPromoCode,
    companyPortal = false,
    companyName,
    creditAvailable,
}: Props) {
    const holdCountdown = useHoldCountdown(
        reservation?.expiresAt ?? null,
        reservation?.heldAt ?? null,
    );
    const quoteCountdown = useHoldCountdown(itineraryQuote?.expiresAt ?? null);

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
        const lastLeg =
            selectedItinerary.legs[selectedItinerary.legs.length - 1];
        // Before the quote is calculated, sum the per-passenger totals of
        // the outbound and inbound itineraries. The itineraryQuote, when
        // available, is the authoritative amount (it includes group
        // discounts and server-side pricing).
        const fallbackAmount =
            (selectedItinerary.totalAmount +
                (selectedInbound?.totalAmount ?? 0)) *
            passengerCount;
        const amount =
            reservation?.totalAmount ??
            itineraryQuote?.totalAmount ??
            fallbackAmount;
        const currency =
            reservation?.currency ??
            itineraryQuote?.currency ??
            selectedItinerary.currency;
        const canQuote = selectedItinerary !== null;
        const isGroup = itineraryQuote?.isGroup ?? passengerCount >= 5;
        const reservationPromotion = reservation?.promotionDiscount;
        const promotionAmount = reservation
            ? (reservationPromotion?.amount ?? 0)
            : (itineraryQuote?.promotionDiscountAmount ?? 0);
        const promotionCode = reservation
            ? (reservationPromotion?.code ?? '')
            : (itineraryQuote?.promotion?.code ?? '');
        const promoEditable =
            onApplyPromoCode !== undefined &&
            (!reservation ||
                ['held', 'confirmed'].includes(reservation.status));
        const companyDiscountAmount =
            itineraryQuote?.companyDiscount?.amount ?? 0;
        const discountAmount = companyPortal
            ? companyDiscountAmount
            : (itineraryQuote?.groupDiscountAmount ?? 0);
        const hasDiscount = discountAmount > 0;
        const heldDiscounts = reservation?.discounts ?? null;
        const hasHeldPromo = (heldDiscounts?.promotionDiscountAmount ?? 0) > 0;
        const hasHeldOtherDiscount =
            heldDiscounts !== null &&
            heldDiscounts.totalDiscount > heldDiscounts.promotionDiscountAmount;

        const renderLegGroup = (
            legs: SaleItinerary['legs'],
            directionLabel: string,
        ) => (
            <div className="summary-leg-group">
                <span className="summary-leg-group-title">
                    {directionLabel}
                </span>
                {legs.map((leg) => (
                    <div key={leg.legOrder} className="summary-leg">
                        <div className="summary-leg-header">
                            <strong>{leg.line.code}</strong>
                            <span>Jambe {leg.legOrder}</span>
                        </div>
                        <div className="summary-leg-route">
                            {leg.originStop.city} → {leg.destinationStop.city}
                        </div>
                        <div className="summary-leg-schedule">
                            <div>
                                Montée :{' '}
                                {formatStopSchedule(
                                    leg.departureAt,
                                    leg.departureTimezone,
                                )}
                            </div>
                            <div>
                                Descente :{' '}
                                {formatStopSchedule(
                                    leg.arrivalAt,
                                    leg.arrivalTimezone,
                                )}
                            </div>
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
                    {selectedInbound && <small>Aller</small>}
                    <p>
                        Montée à {firstLeg.originStop.city} :{' '}
                        {formatStopSchedule(
                            firstLeg.departureAt,
                            firstLeg.departureTimezone,
                        )}
                    </p>
                    <p>
                        Arrivée à {lastLeg.destinationStop.city} :{' '}
                        {formatStopSchedule(
                            lastLeg.arrivalAt,
                            lastLeg.arrivalTimezone,
                        )}
                    </p>
                </div>

                {renderLegGroup(selectedItinerary.legs, 'Aller')}
                {selectedInbound &&
                    renderLegGroup(selectedInbound.legs, 'Retour')}

                <div className="summary-row">
                    <span>Passagers</span>
                    <strong>
                        {passengerCount}
                        {isGroup && !companyPortal && (
                            <span className="badge badge-group">
                                <Users className="size-3" />
                                Groupe
                            </span>
                        )}
                    </strong>
                </div>
                <div className="summary-row">
                    <span>Sièges</span>
                    <strong>
                        {selectedSeatCodes.join(', ') || 'À choisir'}
                    </strong>
                </div>

                {!reservation && onPromoCodeChange && (
                    <div className="summary-row summary-promo-row">
                        <span className="field-label">
                            <Tag className="size-3.5" />
                            Code promo
                        </span>
                        <input
                            type="text"
                            className="summary-promo-input"
                            value={promoCode}
                            onChange={(event) =>
                                onPromoCodeChange(
                                    event.target.value.toUpperCase(),
                                )
                            }
                            placeholder="Optionnel"
                            maxLength={32}
                        />
                    </div>
                )}

                {reservation && promoEditable && onPromoCodeChange && (
                    <div className="summary-promo-row summary-promo-edit">
                        <span className="field-label">
                            <Tag className="size-3.5" />
                            Code promo
                        </span>
                        <input
                            type="text"
                            className="summary-promo-input"
                            value={promoCode}
                            onChange={(event) =>
                                onPromoCodeChange(
                                    event.target.value.toUpperCase(),
                                )
                            }
                            placeholder="Optionnel"
                            maxLength={32}
                        />
                        <div className="summary-promo-actions">
                            <button
                                type="button"
                                className="btn btn-ghost btn-sm"
                                disabled={processing}
                                onClick={() =>
                                    onApplyPromoCode?.(
                                        promoCode.trim() === ''
                                            ? null
                                            : promoCode,
                                    )
                                }
                            >
                                Appliquer
                            </button>
                            {promotionAmount > 0 && (
                                <button
                                    type="button"
                                    className="btn btn-ghost btn-sm"
                                    disabled={processing}
                                    onClick={() => onApplyPromoCode?.(null)}
                                >
                                    Retirer
                                </button>
                            )}
                        </div>
                    </div>
                )}

                {promotionAmount > 0 && (
                    <div className="summary-row summary-discount-row">
                        <span>Promotion {promotionCode}</span>
                        <strong className="is-discount">
                            −{formatMoney(promotionAmount, currency)}
                        </strong>
                    </div>
                )}

                {itineraryQuote && hasDiscount && (
                    <div className="summary-row summary-discount-row">
                        <span>
                            {companyPortal
                                ? 'Remise entreprise'
                                : 'Remise groupe'}
                        </span>
                        <strong className="is-discount">
                            −{formatMoney(discountAmount, currency)}
                        </strong>
                    </div>
                )}

                {heldDiscounts && hasHeldPromo && (
                    <div className="summary-row summary-discount-row">
                        <span>
                            Code promo{' '}
                            {heldDiscounts.promotionCode ??
                                heldDiscounts.label ??
                                ''}
                        </span>
                        <strong className="is-discount">
                            −
                            {formatMoney(
                                heldDiscounts.promotionDiscountAmount,
                                reservation?.currency ?? currency,
                            )}
                        </strong>
                    </div>
                )}

                {heldDiscounts && hasHeldOtherDiscount && (
                    <div className="summary-row summary-discount-row">
                        <span>
                            {(heldDiscounts.companyDiscountAmount ?? 0) > 0
                                ? 'Remise entreprise'
                                : 'Remise groupe'}
                        </span>
                        <strong className="is-discount">
                            −
                            {formatMoney(
                                heldDiscounts.totalDiscount -
                                    heldDiscounts.promotionDiscountAmount,
                                reservation?.currency ?? currency,
                            )}
                        </strong>
                    </div>
                )}

                {companyPortal && creditAvailable !== undefined && (
                    <div className="summary-payer-section">
                        <span className="field-label">
                            <Building2 className="size-3.5" />
                            Payeur entreprise
                        </span>
                        <small className="payment-field-help">
                            {companyName} ·{' '}
                            {formatMoney(creditAvailable, currency)} dispo
                        </small>
                    </div>
                )}

                {!reservation &&
                    companyAccounts.length > 0 &&
                    payerType === 'company' && (
                        <div className="summary-payer-section">
                            <span className="field-label">
                                <Building2 className="size-3.5" />
                                Payeur entreprise
                            </span>
                            {(() => {
                                const account = companyAccounts.find(
                                    (a) =>
                                        a.uuid === selectedCompanyAccountUuid,
                                );

                                return account ? (
                                    <small className="payment-field-help">
                                        {account.name} ·{' '}
                                        {formatMoney(
                                            account.availableCredit,
                                            currency,
                                        )}{' '}
                                        dispo
                                    </small>
                                ) : (
                                    <small className="payment-field-help">
                                        Compte non sélectionné
                                    </small>
                                );
                            })()}
                        </div>
                    )}

                <div className="summary-total">
                    <span>Total</span>
                    <strong>
                        {amount ? formatMoney(amount, currency) : '—'}
                    </strong>
                </div>

                {reservation ? (
                    <div
                        className={cn(
                            'summary-callout',
                            holdCountdown.isExpired || holdCountdown.isUrgent
                                ? 'danger'
                                : 'success',
                        )}
                    >
                        <div>
                            <CheckCircle2 className="size-4" />
                            Places bloquées
                        </div>
                        <p>
                            {holdCountdown.isExpired
                                ? 'Blocage expiré.'
                                : `Expiration dans ${holdCountdown.label}.`}
                        </p>
                    </div>
                ) : (
                    <>
                        {itineraryQuote && (
                            <div
                                className={cn(
                                    'summary-callout warning',
                                    (quoteCountdown.isExpired ||
                                        quoteCountdown.isUrgent) &&
                                        'danger',
                                )}
                            >
                                <Clock3 className="size-3.5" />
                                {quoteCountdown.isExpired
                                    ? 'Devis expiré'
                                    : `Devis valable encore ${quoteCountdown.label}`}
                            </div>
                        )}
                        {onOpenSeatPicker && (
                            // Opens the seat picker directly — the quote is
                            // created only after seat/passenger/payer
                            // selection is complete (inside the picker's
                            // confirm action), so the price is frozen at
                            // the right moment with dynamic pricing and
                            // group discounts applied.
                            <button
                                type="button"
                                onClick={onOpenSeatPicker}
                                disabled={processing}
                                className="btn btn-primary summary-action"
                            >
                                <LayoutGrid className="size-4" />
                                Choisir les places
                            </button>
                        )}
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
