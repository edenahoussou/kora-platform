import { ArrowLeft, ArrowRight, BusFront, Check, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Dialog, DialogClose, DialogContent } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { PayerSelector } from './payer-selector';
import type {
    SaleCompanyAccount,
    SaleItineraryLegSeat,
    SaleItineraryQuoteLeg,
    SalePayerType,
    SaleSeat,
} from './sale-types';
import { formatShortDate, formatTime } from './sale-utils';
import { SeatGrid } from './seat-grid';
import { SeatPickerLegend } from './seat-picker-legend';

type LegAssignment = Record<number, string>;
type PassengerAssignments = Record<number, LegAssignment>;

type Props = {
    legs: SaleItineraryQuoteLeg[];
    legSeats: SaleItineraryLegSeat[];
    passengerCount: number;
    maxPassengers?: number;
    processing: boolean;
    onPassengerCountChange: (count: number) => void;
    onClose: () => void;
    onConfirm: (assignments: PassengerAssignments) => void;
    payerType?: SalePayerType;
    selectedCompanyAccountUuid?: string | null;
    companyAccounts?: SaleCompanyAccount[];
    amount?: number;
    currency?: string;
    onPayerTypeChange?: (type: SalePayerType) => void;
    onCompanyAccountChange?: (uuid: string | null) => void;
};

const DIRECTION_LABEL: Record<string, string> = {
    outbound: 'Aller',
    inbound: 'Retour',
};

export function MultiLegSeatPicker({
    legs,
    legSeats,
    passengerCount,
    maxPassengers = 10,
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
}: Props) {
    const [activePassenger, setActivePassenger] = useState(0);
    const [activeLegIndex, setActiveLegIndex] = useState(0);
    const [assignments, setAssignments] = useState<PassengerAssignments>(
        () => Object.fromEntries(
            Array.from({ length: passengerCount }, (_, i) => [
                i,
                Object.fromEntries(legs.map((leg) => [leg.legOrder, ''])),
            ]),
        ),
    );

    const activeLeg = legs[activeLegIndex];
    const activeLegSeat = legSeats.find(
        (entry) => entry.legOrder === activeLeg.legOrder,
    );
    const seatsForActiveLeg: SaleSeat[] = activeLegSeat?.seats ?? [];
    const selectedUuidsForActiveLeg = useMemo(() => {
        const passenger = assignments[activePassenger];

        if (!passenger) {
            return [] as string[];
        }

        const uuid = passenger[activeLeg.legOrder];

        return uuid ? [uuid] : [];
    }, [assignments, activePassenger, activeLeg]);

    const toggleSeat = (seat: SaleSeat) => {
        setAssignments((current) => {
            const passenger = { ...(current[activePassenger] ?? {}) };
            const currentUuid = passenger[activeLeg.legOrder];

            if (currentUuid === seat.uuid) {
                passenger[activeLeg.legOrder] = '';
            } else {
                passenger[activeLeg.legOrder] = seat.uuid;
            }

            return { ...current, [activePassenger]: passenger };
        });
    };

    const isPassengerComplete = (passengerIndex: number) => {
        const passenger = assignments[passengerIndex];

        if (!passenger) {
            return false;
        }

        return legs.every((leg) => Boolean(passenger[leg.legOrder]));
    };

    const isLegComplete = (passengerIndex: number, legOrder: number) => {
        const passenger = assignments[passengerIndex];

        return Boolean(passenger?.[legOrder]);
    };

    const allComplete =
        Object.keys(assignments).length === passengerCount &&
        Array.from({ length: passengerCount }, (_, i) => i).every((p) =>
            isPassengerComplete(p),
        );

    const totalSteps = passengerCount * legs.length;
    const completedSteps = Array.from(
        { length: passengerCount },
        (_, p) => p,
    ).reduce(
        (acc, p) => acc + legs.filter((leg) => isLegComplete(p, leg.legOrder)).length,
        0,
    );

    const goToNextStep = () => {
        const isLastLeg = activeLegIndex === legs.length - 1;

        if (isLastLeg) {
            if (activePassenger < passengerCount - 1) {
                setActivePassenger(activePassenger + 1);
                setActiveLegIndex(0);
            }
        } else {
            setActiveLegIndex(activeLegIndex + 1);
        }
    };

    const goToPreviousStep = () => {
        if (activeLegIndex > 0) {
            setActiveLegIndex(activeLegIndex - 1);
        } else if (activePassenger > 0) {
            setActivePassenger(activePassenger - 1);
            setActiveLegIndex(legs.length - 1);
        }
    };

    const directionLabel = DIRECTION_LABEL[activeLeg.direction ?? 'outbound'] ?? 'Aller';
    const isSingleLeg = legs.length === 1;
    const isSoloDirect = isSingleLeg && passengerCount === 1;

    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogContent
                className="seat-dialog-content p-0"
                showClose={false}
                aria-describedby="multi-leg-seat-picker-description"
            >
                <div className="seat-dialog-header">
                    <div className="seat-dialog-header-copy">
                        <div className="seat-dialog-heading-line">
                            <h2 id="multi-leg-seat-picker-title" className="seat-dialog-title">
                                {isSingleLeg ? 'Choisir les places' : `Choisir les places · ${directionLabel}`}
                            </h2>
                            <span className="seat-dialog-service-badge kora-status-badge gold">
                                {completedSteps}/{totalSteps} places
                            </span>
                        </div>
                        <p id="multi-leg-seat-picker-description" className="seat-dialog-description">
                            {isSingleLeg
                                ? 'Attribuez une place à chaque voyageur.'
                                : 'Attribuez une place à chaque voyageur sur chaque jambe du trajet.'}
                        </p>
                        <div className="seat-dialog-meta">
                            <span>
                                <BusFront className="size-3.5" />
                                Ligne <strong>{activeLeg.line.code}</strong>
                            </span>
                            <span>
                                {activeLeg.originStop.city} →{' '}
                                <strong>{activeLeg.destinationStop.city}</strong>
                            </span>
                            <span>
                                {formatShortDate(activeLeg.departureDate)} ·{' '}
                                {formatTime(activeLeg.departureTime)}
                            </span>
                        </div>
                    </div>
                </div>
                <DialogClose asChild>
                    <button
                        type="button"
                        className="seat-dialog-close"
                        aria-label="Fermer la sélection des places"
                    >
                        <X className="size-4" />
                    </button>
                </DialogClose>

                <div className="seat-dialog-body multi-leg-seat-dialog-body">
                    <section
                        className="seat-dialog-main"
                        aria-label="Plan des sièges"
                    >
                        {!isSingleLeg && (
                            <div className="multi-leg-stepper">
                                {legs.map((leg, index) => {
                                    const done = isLegComplete(activePassenger, leg.legOrder);
                                    const isActive = index === activeLegIndex;

                                    return (
                                        <button
                                            key={leg.legOrder}
                                            type="button"
                                            onClick={() => setActiveLegIndex(index)}
                                            className={cn(
                                                'multi-leg-step',
                                                isActive && 'is-active',
                                                done && 'is-done',
                                            )}
                                        >
                                            <span className="multi-leg-step-direction">
                                                {DIRECTION_LABEL[leg.direction ?? 'outbound'] ?? 'Aller'}
                                            </span>
                                            <span className="multi-leg-step-route">
                                                {leg.originStop.city} → {leg.destinationStop.city}
                                            </span>
                                            {done && <Check className="size-3" />}
                                        </button>
                                    );
                                })}
                            </div>
                        )}

                        <div className="seat-map-scroll">
                            <SeatGrid
                                seats={seatsForActiveLeg}
                                selectedSeatUuids={selectedUuidsForActiveLeg}
                                loading={seatsForActiveLeg.length === 0}
                                onToggleSeat={toggleSeat}
                            />
                        </div>
                    </section>

                    <aside className="seat-selection-rail" aria-label="Votre sélection">
                        <div className="seat-selection-rail-content">
                            <div className="seat-selection-heading">
                                <div>
                                    <span className="section-kicker">
                                        Voyageur {activePassenger + 1}/{passengerCount}
                                    </span>
                                    <h3>Places du voyage</h3>
                                </div>
                                <strong>
                                    {completedSteps}/{totalSteps}
                                </strong>
                            </div>

                            {!isSoloDirect && (
                                <div className="multi-leg-passenger-tabs">
                                    {Array.from({ length: passengerCount }, (_, i) => (
                                        <button
                                            key={i}
                                            type="button"
                                            onClick={() => {
                                                setActivePassenger(i);
                                                setActiveLegIndex(0);
                                            }}
                                            className={cn(
                                                'multi-leg-passenger-tab',
                                                activePassenger === i && 'is-active',
                                                isPassengerComplete(i) && 'is-done',
                                            )}
                                        >
                                            {isPassengerComplete(i) && <Check className="size-3" />}
                                            P{i + 1}
                                        </button>
                                    ))}
                                </div>
                            )}

                            {!isSingleLeg && (
                                <div className="multi-leg-assignment-list">
                                    {legs.map((leg) => {
                                        const uuid = assignments[activePassenger]?.[leg.legOrder];
                                        const seat = seatsForActiveLeg.find((s) => s.uuid === uuid)
                                            ?? legSeats
                                                .find((entry) => entry.legOrder === leg.legOrder)
                                                ?.seats.find((s) => s.uuid === uuid);

                                        return (
                                            <div
                                                key={leg.legOrder}
                                                className={cn(
                                                    'multi-leg-assignment-row',
                                                    uuid && 'is-assigned',
                                                )}
                                            >
                                                <span>
                                                    {DIRECTION_LABEL[leg.direction ?? 'outbound'] ?? 'Aller'} ·{' '}
                                                    {leg.originStop.city} → {leg.destinationStop.city}
                                                </span>
                                                <strong>{seat?.code ?? 'À choisir'}</strong>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}

                            <div className="seat-selection-counter">
                                <div>
                                    <span>Voyageurs</span>
                                    <small>
                                        {passengerCount} voyageur{passengerCount > 1 ? 's' : ''}
                                    </small>
                                </div>
                                <div className="seat-counter-controls">
                                    <button
                                        type="button"
                                        aria-label="Retirer un voyageur"
                                        disabled={passengerCount <= 1 || processing}
                                        onClick={() => {
                                            const next = passengerCount - 1;
                                            onPassengerCountChange(next);
                                            setAssignments((current) => {
                                                const copy = { ...current };
                                                delete copy[next];

                                                return copy;
                                            });

                                            if (activePassenger >= next) {
                                                setActivePassenger(Math.max(0, next - 1));
                                            }
                                        }}
                                    >
                                        −
                                    </button>
                                    <strong>{passengerCount}</strong>
                                    <button
                                        type="button"
                                        aria-label="Ajouter un voyageur"
                                        disabled={passengerCount >= maxPassengers || processing}
                                        onClick={() => {
                                            const next = passengerCount + 1;
                                            onPassengerCountChange(next);
                                            setAssignments((current) => ({
                                                ...current,
                                                [next - 1]: Object.fromEntries(
                                                    legs.map((leg) => [leg.legOrder, '']),
                                                ),
                                            }));
                                        }}
                                    >
                                        +
                                    </button>
                                </div>
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
                                    onCompanyAccountChange={onCompanyAccountChange ?? (() => { })}
                                    disabled={processing}
                                />
                            )}
                        </div>

                        <div className="seat-selection-actions">
                            <button
                                type="button"
                                className="btn btn-ghost"
                                onClick={onClose}
                            >
                                Annuler
                            </button>
                            <div className="multi-leg-nav-actions">
                                {!isSingleLeg && (
                                    <button
                                        type="button"
                                        className="btn btn-ghost"
                                        disabled={
                                            activePassenger === 0 && activeLegIndex === 0
                                        }
                                        onClick={goToPreviousStep}
                                    >
                                        <ArrowLeft className="size-4" />
                                        Précédent
                                    </button>
                                )}
                                {!allComplete && !isSingleLeg ? (
                                    <button
                                        type="button"
                                        className="btn btn-primary"
                                        disabled={
                                            !isLegComplete(
                                                activePassenger,
                                                activeLeg.legOrder,
                                            ) || processing
                                        }
                                        onClick={goToNextStep}
                                    >
                                        Suivant
                                        <ArrowRight className="size-4" />
                                    </button>
                                ) : (
                                    <button
                                        type="button"
                                        className="btn btn-primary"
                                        disabled={processing}
                                        onClick={() => onConfirm(assignments)}
                                    >
                                        <Check className="size-4" />
                                        Confirmer ({passengerCount * legs.length} places)
                                    </button>
                                )}
                            </div>
                        </div>
                    </aside>
                </div>
            </DialogContent>
        </Dialog>
    );
}
