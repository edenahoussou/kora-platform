import { ArrowRight, Building2, CheckCircle2, Clock3, ShieldCheck, UserRound, Users } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { SaleCompanyAccount, SaleDeparture, SaleItinerary, SaleItineraryQuote, SalePayerType, SaleQuote, SaleReservation } from './sale-types';
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
    itineraryQuote?: SaleItineraryQuote | null;
    selectedItinerary?: SaleItinerary | null;
    reservation: SaleReservation | null;
    processing: boolean;
    onQuote: () => void;
    onHold: () => void;
    companyAccounts?: SaleCompanyAccount[];
    payerType?: SalePayerType;
    selectedCompanyAccountUuid?: string | null;
    onPayerTypeChange?: (type: SalePayerType) => void;
    onCompanyAccountChange?: (uuid: string | null) => void;
};

export function SaleSummaryCard({
    departure,
    passengerCount,
    selectedSeatCodes,
    quote,
    itineraryQuote,
    selectedItinerary,
    reservation,
    processing,
    onQuote,
    onHold,
    companyAccounts = [],
    payerType = 'personal',
    selectedCompanyAccountUuid = null,
    onPayerTypeChange,
    onCompanyAccountChange,
}: Props) {
    const isMultiLeg = selectedItinerary != null && !selectedItinerary.isDirect;

    if (!departure && !isMultiLeg) {
        return (
            <aside className="summary empty-summary">
                Sélectionnez un itinéraire pour préparer la vente.
            </aside>
        );
    }

    // Multi-leg: use itinerary quote data
    if (isMultiLeg && selectedItinerary) {
        const firstLeg = selectedItinerary.legs[0];
        const lastLeg = selectedItinerary.legs[selectedItinerary.legs.length - 1];
        const amount = itineraryQuote?.totalAmount ?? selectedItinerary.totalAmount * passengerCount;
        const currency = itineraryQuote?.currency ?? selectedItinerary.currency;
        const canQuote = selectedSeatCodes.length === passengerCount;
        const canHold =
            itineraryQuote !== null &&
            itineraryQuote !== undefined &&
            selectedSeatCodes.length === itineraryQuote.passengerCount &&
            (payerType === 'company'
                ? selectedCompanyAccountUuid !== null &&
                (companyAccounts.find((account) => account.uuid === selectedCompanyAccountUuid)?.availableCredit ?? 0) >= amount
                : true);
        const isGroup = itineraryQuote?.isGroup ?? passengerCount >= 5;
        const hasDiscount = (itineraryQuote?.groupDiscountAmount ?? 0) > 0;

        return (
            <aside className="summary">
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

                {selectedItinerary.legs.map((leg) => (
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

                {!reservation && companyAccounts.length > 0 && (
                    <div className="summary-payer-section">
                        <span className="field-label">
                            <UserRound className="size-3.5" />
                            Payeur
                        </span>
                        <div className="payer-toggle">
                            <button
                                type="button"
                                className={cn('payer-choice', payerType === 'personal' && 'is-active')}
                                onClick={() => onPayerTypeChange?.('personal')}
                            >
                                <UserRound className="size-3.5" />
                                Personnel
                            </button>
                            <button
                                type="button"
                                className={cn('payer-choice', payerType === 'company' && 'is-active')}
                                onClick={() => onPayerTypeChange?.('company')}
                            >
                                <Building2 className="size-3.5" />
                                Entreprise
                            </button>
                        </div>
                        {payerType === 'company' && (
                            <label className="field">
                                <span className="field-label">
                                    <Building2 className="size-3.5" />
                                    Compte entreprise
                                </span>
                                <select
                                    className="control"
                                    value={selectedCompanyAccountUuid ?? ''}
                                    onChange={(e) =>
                                        onCompanyAccountChange?.(e.target.value || null)
                                    }
                                >
                                    <option value="">Sélectionner le compte</option>
                                    {companyAccounts.map((account) => (
                                        <option key={account.uuid} value={account.uuid}>
                                            {account.name} · {formatMoney(account.availableCredit, 'XOF')} dispo
                                        </option>
                                    ))}
                                </select>
                                {selectedCompanyAccountUuid && (() => {
                                    const account = companyAccounts.find(
                                        (a) => a.uuid === selectedCompanyAccountUuid,
                                    );

                                    if (!account) {
                                        return null;
                                    }

                                    const insufficient = account.availableCredit < amount;

                                    return (
                                        <small className={cn('payment-field-help', insufficient && 'field-error-text')}>
                                            {insufficient
                                                ? `Crédit insuffisant (${formatMoney(account.availableCredit, currency)} / ${formatMoney(amount, currency)})`
                                                : `Crédit disponible : ${formatMoney(account.availableCredit, currency)}`}
                                        </small>
                                    );
                                })()}
                            </label>
                        )}
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
                                Sélectionnez exactement {passengerCount} place
                                {passengerCount > 1 ? 's' : ''}.
                            </p>
                        )}
                    </>
                )}
            </aside>
        );
    }

    // Single-leg (direct) flow — original behavior
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
        selectedSeatCodes.length === quote.passengerCount &&
        (payerType === 'company'
            ? selectedCompanyAccountUuid !== null &&
            (companyAccounts.find((account) => account.uuid === selectedCompanyAccountUuid)?.availableCredit ?? 0) >= amount
            : true);
    const saleClosed = !departure.sellability.isSellable;
    const isGroup = quote?.isGroup ?? passengerCount >= 5;
    const hasDiscount = quote?.groupDiscount?.applied ?? false;

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
                <span>Siège</span>
                <strong>{selectedSeatCodes.join(', ') || 'À choisir'}</strong>
            </div>
            <div className="summary-row">
                <span>Tarif unitaire</span>
                <strong>
                    {unitAmount ? formatMoney(unitAmount, currency) : '—'}
                </strong>
            </div>

            {quote && (
                <>
                    <div className="summary-row">
                        <span>Sous-total ajusté</span>
                        <strong>{formatMoney(quote.adjustedSubtotalAmount, currency)}</strong>
                    </div>
                    {quote.dynamicAdjustmentAmount !== 0 && (
                        <div className="summary-row">
                            <span>Ajustement dynamique</span>
                            <strong className={quote.dynamicAdjustmentAmount > 0 ? undefined : 'is-discount'}>
                                {quote.dynamicAdjustmentAmount > 0 ? '+' : '−'}{formatMoney(Math.abs(quote.dynamicAdjustmentAmount), currency)}
                            </strong>
                        </div>
                    )}
                </>
            )}

            {quote && hasDiscount && (
                <div className="summary-row summary-discount-row">
                    <span>
                        Remise groupe ({quote.groupDiscount.percentage}%)
                    </span>
                    <strong className="is-discount">
                        −{formatMoney(quote.groupDiscount.amount, currency)}
                    </strong>
                </div>
            )}

            {!reservation && companyAccounts.length > 0 && (
                <div className="summary-payer-section">
                    <span className="field-label">
                        <UserRound className="size-3.5" />
                        Payeur
                    </span>
                    <div className="payer-toggle">
                        <button
                            type="button"
                            className={cn('payer-choice', payerType === 'personal' && 'is-active')}
                            onClick={() => onPayerTypeChange?.('personal')}
                        >
                            <UserRound className="size-3.5" />
                            Personnel
                        </button>
                        <button
                            type="button"
                            className={cn('payer-choice', payerType === 'company' && 'is-active')}
                            onClick={() => onPayerTypeChange?.('company')}
                        >
                            <Building2 className="size-3.5" />
                            Entreprise
                        </button>
                    </div>
                    {payerType === 'company' && (
                        <label className="field">
                            <span className="field-label">
                                <Building2 className="size-3.5" />
                                Compte entreprise
                            </span>
                            <select
                                className="control"
                                value={selectedCompanyAccountUuid ?? ''}
                                onChange={(e) =>
                                    onCompanyAccountChange?.(e.target.value || null)
                                }
                            >
                                <option value="">Sélectionner le compte</option>
                                {companyAccounts.map((account) => (
                                    <option key={account.uuid} value={account.uuid}>
                                        {account.name} · {formatMoney(account.availableCredit, 'XOF')} dispo
                                    </option>
                                ))}
                            </select>
                            {selectedCompanyAccountUuid && (() => {
                                const account = companyAccounts.find(
                                    (a) => a.uuid === selectedCompanyAccountUuid,
                                );

                                if (!account) {
                                    return null;
                                }

                                const insufficient = account.availableCredit < amount;

                                return (
                                    <small className={cn('payment-field-help', insufficient && 'field-error-text')}>
                                        {insufficient
                                            ? `Crédit insuffisant (${formatMoney(account.availableCredit, currency)} / ${formatMoney(amount, currency)})`
                                            : `Crédit disponible : ${formatMoney(account.availableCredit, currency)}`}
                                    </small>
                                );
                            })()}
                        </label>
                    )}
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
