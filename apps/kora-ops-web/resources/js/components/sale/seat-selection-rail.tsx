import {
    ArrowLeft,
    ArrowRight,
    Check,
    Clock3,
    Minus,
    Plus,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { PayerSelector } from './payer-selector';
import type {
    SaleCompanyAccount,
    SaleItineraryLeg,
    SalePayerType,
    SaleSeat,
} from './sale-types';
import { SeatPickerLegend } from './seat-picker-legend';

const DIRECTION_LABEL: Record<string, string> = {
    outbound: 'Aller',
    inbound: 'Retour',
};

/**
 * Multi-leg/multi-passenger extension. When provided (with more than one
 * leg), the rail switches from the flat "select N seats" single-leg mode to
 * a per-passenger, per-leg assignment view. Omitting it (or a single leg)
 * keeps the original, already-validated single-leg behavior unchanged.
 */
type MultiLegRailProps = {
    legs: SaleItineraryLeg[];
    activePassenger: number;
    assignments: Record<number, Record<number, string>>;
    onSelectPassenger: (index: number) => void;
    onNextStep: () => void;
    onPreviousStep: () => void;
    canGoPrevious: boolean;
    canGoNext: boolean;
    allComplete: boolean;
    completedSteps: number;
    totalSteps: number;
    getSeatCode: (legOrder: number, seatUuid: string) => string | undefined;
};

type Props = {
    seats: SaleSeat[];
    selectedSeatCodes: string[];
    passengerCount: number;
    maxPassengers?: number;
    loading: boolean;
    processing: boolean;
    onPassengerCountChange: (count: number) => void;
    onClose: () => void;
    onConfirm: () => void;
    payerType?: SalePayerType;
    selectedCompanyAccountUuid?: string | null;
    companyAccounts?: SaleCompanyAccount[];
    amount?: number;
    currency?: string;
    onPayerTypeChange?: (type: SalePayerType) => void;
    onCompanyAccountChange?: (uuid: string | null) => void;
    multiLeg?: MultiLegRailProps;
};

export function SeatSelectionRail({
    seats,
    selectedSeatCodes,
    passengerCount,
    maxPassengers = 10,
    loading,
    processing,
    onPassengerCountChange,
    onClose,
    onConfirm,
    payerType = 'personal',
    selectedCompanyAccountUuid = null,
    companyAccounts = [],
    amount = 0,
    currency = 'XOF',
    onPayerTypeChange,
    onCompanyAccountChange,
    multiLeg,
}: Props) {
    const availableCount = seats.filter(
        (seat) => seat.availabilityStatus === 'available',
    ).length;
    const selectionMatchesPassengers =
        selectedSeatCodes.length === passengerCount;
    const isMultiLeg = Boolean(multiLeg && multiLeg.legs.length > 1);

    return (
        <aside className="seat-selection-rail" aria-label="Votre sélection">
            <div className="seat-selection-rail-content">
                <div className="seat-selection-heading">
                    <div>
                        <span className="section-kicker">
                            {isMultiLeg
                                ? `Voyageur ${multiLeg!.activePassenger + 1}/${passengerCount}`
                                : 'Votre sélection'}
                        </span>
                        <h3>Places du voyage</h3>
                    </div>
                    <strong>
                        {isMultiLeg
                            ? `${multiLeg!.completedSteps}/${multiLeg!.totalSteps}`
                            : `${selectedSeatCodes.length}/${passengerCount}`}
                    </strong>
                </div>

                {isMultiLeg && passengerCount > 1 && (
                    <div className="multi-leg-passenger-tabs">
                        {Array.from({ length: passengerCount }, (_, i) => {
                            const isDone = multiLeg!.legs.every((leg) =>
                                Boolean(
                                    multiLeg!.assignments[i]?.[leg.legOrder],
                                ),
                            );

                            return (
                                <button
                                    key={i}
                                    type="button"
                                    onClick={() =>
                                        multiLeg!.onSelectPassenger(i)
                                    }
                                    className={cn(
                                        'multi-leg-passenger-tab',
                                        multiLeg!.activePassenger === i &&
                                            'is-active',
                                        isDone && 'is-done',
                                    )}
                                >
                                    {isDone && <Check className="size-3" />}P
                                    {i + 1}
                                </button>
                            );
                        })}
                    </div>
                )}

                {isMultiLeg ? (
                    <div className="multi-leg-assignment-list">
                        {multiLeg!.legs.map((leg) => {
                            const uuid =
                                multiLeg!.assignments[
                                    multiLeg!.activePassenger
                                ]?.[leg.legOrder];
                            const code = uuid
                                ? multiLeg!.getSeatCode(leg.legOrder, uuid)
                                : undefined;

                            return (
                                <div
                                    key={leg.legOrder}
                                    className={cn(
                                        'multi-leg-assignment-row',
                                        uuid && 'is-assigned',
                                    )}
                                >
                                    <span>
                                        {DIRECTION_LABEL[
                                            leg.direction ?? 'outbound'
                                        ] ?? 'Aller'}{' '}
                                        · {leg.originStop.city} →{' '}
                                        {leg.destinationStop.city}
                                    </span>
                                    <strong>{code ?? 'À choisir'}</strong>
                                </div>
                            );
                        })}
                    </div>
                ) : (
                    <div
                        className={
                            selectedSeatCodes.length > 0
                                ? 'selected-seat-list has-seats'
                                : 'selected-seat-list'
                        }
                        aria-live="polite"
                    >
                        {selectedSeatCodes.length > 0 ? (
                            selectedSeatCodes.map((code) => (
                                <span key={code}>{code}</span>
                            ))
                        ) : (
                            <p>Aucune place sélectionnée</p>
                        )}
                    </div>
                )}

                <div className="seat-selection-counter">
                    <div>
                        <span>Voyageurs</span>
                        <small>
                            {isMultiLeg
                                ? `${multiLeg!.completedSteps}/${multiLeg!.totalSteps} places choisies`
                                : `${selectedSeatCodes.length}/${passengerCount} places choisies`}
                        </small>
                    </div>
                    <div className="seat-counter-controls">
                        <button
                            type="button"
                            aria-label="Retirer un voyageur"
                            disabled={passengerCount <= 1 || processing}
                            onClick={() =>
                                onPassengerCountChange(passengerCount - 1)
                            }
                        >
                            <Minus className="size-4" />
                        </button>
                        <strong>{passengerCount}</strong>
                        <button
                            type="button"
                            aria-label="Ajouter un voyageur"
                            disabled={
                                passengerCount >= maxPassengers || processing
                            }
                            onClick={() =>
                                onPassengerCountChange(passengerCount + 1)
                            }
                        >
                            <Plus className="size-4" />
                        </button>
                    </div>
                </div>

                <div className="seat-selection-availability">
                    <span>Disponibilité actuelle</span>
                    <strong>{availableCount} places</strong>
                </div>

                <SeatPickerLegend />

                {onPayerTypeChange && (
                    <PayerSelector
                        payerType={payerType}
                        selectedCompanyAccountUuid={selectedCompanyAccountUuid}
                        companyAccounts={companyAccounts}
                        amount={amount}
                        currency={currency}
                        onPayerTypeChange={onPayerTypeChange}
                        onCompanyAccountChange={
                            onCompanyAccountChange ?? (() => {})
                        }
                        disabled={processing}
                    />
                )}

                <div className="seat-selection-notice">
                    <Clock3 className="size-4" />
                    <p>
                        Les places seront bloquées pendant 15 minutes après le
                        calcul du devis et la validation de la vente.
                    </p>
                </div>
            </div>

            <div className="seat-selection-actions">
                <button
                    type="button"
                    className="btn btn-ghost"
                    onClick={onClose}
                >
                    Annuler
                </button>
                {isMultiLeg ? (
                    <div className="multi-leg-nav-actions">
                        <button
                            type="button"
                            className="btn btn-ghost"
                            disabled={!multiLeg!.canGoPrevious}
                            onClick={multiLeg!.onPreviousStep}
                        >
                            <ArrowLeft className="size-4" />
                            Précédent
                        </button>
                        {!multiLeg!.allComplete ? (
                            <button
                                type="button"
                                className="btn btn-primary"
                                disabled={!multiLeg!.canGoNext || processing}
                                onClick={multiLeg!.onNextStep}
                            >
                                Suivant
                                <ArrowRight className="size-4" />
                            </button>
                        ) : (
                            <button
                                type="button"
                                className="btn btn-primary"
                                disabled={processing}
                                onClick={onConfirm}
                            >
                                <Check className="size-4" />
                                Confirmer ({multiLeg!.totalSteps} places)
                            </button>
                        )}
                    </div>
                ) : (
                    <button
                        type="button"
                        className="btn btn-primary"
                        disabled={
                            !selectionMatchesPassengers || loading || processing
                        }
                        onClick={onConfirm}
                    >
                        Confirmer la sélection
                    </button>
                )}
            </div>
        </aside>
    );
}
