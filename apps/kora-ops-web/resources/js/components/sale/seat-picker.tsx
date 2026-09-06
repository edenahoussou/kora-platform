import { Check, CircleDot, Octagon } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { cn } from '@/lib/utils';
import type {
    SaleCompanyAccount,
    SaleItineraryLeg,
    SaleItineraryLegSeat,
    SalePayerType,
    SaleSeat,
} from './sale-types';
import { formatShortDate } from './sale-utils';
import { SeatGrid } from './seat-grid';
import { SeatPickerDialog } from './seat-picker-dialog';
import { SeatPickerHeader } from './seat-picker-header';
import { SeatPickerTripSummary } from './seat-picker-trip-summary';
import { SeatSelectionRail } from './seat-selection-rail';

type LegAssignment = Record<number, string>;
type PassengerAssignments = Record<number, LegAssignment>;

type Props = {
    legs: SaleItineraryLeg[];
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

function emptyAssignmentsFor(
    passengerCount: number,
    legs: SaleItineraryLeg[],
): PassengerAssignments {
    return Object.fromEntries(
        Array.from({ length: passengerCount }, (_, i) => [
            i,
            Object.fromEntries(legs.map((leg) => [leg.legOrder, ''])),
        ]),
    );
}

/**
 * Seat picker for a counter sale itinerary — supports a single direct leg
 * (the original, validated flat "click N seats" flow: order of clicks maps
 * to passenger order, all on one grid) as well as multi-leg itineraries
 * (connections and/or a return trip), where each passenger's seat is
 * targeted explicitly per leg via a passenger tab + leg stepper.
 */
export function SeatPicker({
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
    const isMultiLeg = legs.length > 1;
    const [activePassenger, setActivePassenger] = useState(0);
    const [activeLegIndex, setActiveLegIndex] = useState(0);
    const [assignments, setAssignments] = useState<PassengerAssignments>(() =>
        emptyAssignmentsFor(passengerCount, legs),
    );
    const autoAdvanceTimerRef = useRef<ReturnType<typeof setTimeout> | null>(
        null,
    );

    useEffect(() => {
        return () => {
            if (autoAdvanceTimerRef.current) {
                clearTimeout(autoAdvanceTimerRef.current);
            }
        };
    }, []);

    const activeLeg = legs[activeLegIndex];
    const activeLegSeat = legSeats.find(
        (entry) => entry.legOrder === activeLeg.legOrder,
    );
    const seatsForActiveLeg: SaleSeat[] = activeLegSeat?.seats ?? [];

    const selectedUuidsForActiveLeg = useMemo(() => {
        if (isMultiLeg) {
            const uuid = assignments[activePassenger]?.[activeLeg.legOrder];

            return uuid ? [uuid] : [];
        }

        // Single-leg flat mode: every seat assigned to any passenger on
        // this (only) leg is shown as selected on the grid.
        return Object.values(assignments)
            .map((passenger) => passenger[activeLeg.legOrder])
            .filter((uuid): uuid is string => Boolean(uuid));
    }, [assignments, activePassenger, activeLeg, isMultiLeg]);

    // Multi-leg mode: seats already chosen by OTHER passengers on the
    // active leg must be visible and non-clickable so the active passenger
    // cannot pick the same seat twice on the same segment.
    const takenByOthersForActiveLeg = useMemo(() => {
        if (!isMultiLeg) {
            return [];
        }

        return Object.entries(assignments)
            .filter(([idx]) => Number(idx) !== activePassenger)
            .map(([, passenger]) => passenger[activeLeg.legOrder])
            .filter((uuid): uuid is string => Boolean(uuid));
    }, [assignments, activePassenger, activeLeg, isMultiLeg]);

    const selectedSeatCodes = seatsForActiveLeg
        .filter((seat) => selectedUuidsForActiveLeg.includes(seat.uuid))
        .map((seat) => seat.code);

    const isPassengerComplete = (passengerIndex: number) => {
        const passenger = assignments[passengerIndex];

        if (!passenger) {
            return false;
        }

        return legs.every((leg) => Boolean(passenger[leg.legOrder]));
    };

    const isLegComplete = (passengerIndex: number, legOrder: number) =>
        Boolean(assignments[passengerIndex]?.[legOrder]);

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
        (acc, p) =>
            acc + legs.filter((leg) => isLegComplete(p, leg.legOrder)).length,
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

    const toggleSeat = (seat: SaleSeat) => {
        const legOrder = activeLeg.legOrder;

        if (isMultiLeg) {
            // Defense-in-depth: a seat already taken by another passenger
            // on this leg must never be reassigned, even if the grid failed
            // to disable it.
            if (takenByOthersForActiveLeg.includes(seat.uuid)) {
                return;
            }

            const currentUuid = assignments[activePassenger]?.[legOrder] ?? '';
            const isAssigning = currentUuid !== seat.uuid;

            setAssignments((current) => {
                const passenger = { ...(current[activePassenger] ?? {}) };
                passenger[legOrder] = isAssigning ? seat.uuid : '';

                return { ...current, [activePassenger]: passenger };
            });

            // Auto-advance to the next leg/passenger after a brief delay
            // so the user sees the green check transition before moving on.
            if (isAssigning) {
                if (autoAdvanceTimerRef.current) {
                    clearTimeout(autoAdvanceTimerRef.current);
                }

                const isLastStep =
                    activeLegIndex === legs.length - 1 &&
                    activePassenger === passengerCount - 1;

                if (!isLastStep) {
                    autoAdvanceTimerRef.current = setTimeout(() => {
                        goToNextStep();
                        autoAdvanceTimerRef.current = null;
                    }, 480);
                }
            }

            return;
        }

        // Single-leg flat mode: clicking an already-assigned seat frees
        // it; otherwise it's assigned to the next passenger without a
        // seat yet, so click order maps to passenger order.
        setAssignments((current) => {
            const assignedTo = Object.entries(current).find(
                ([, passenger]) => passenger[legOrder] === seat.uuid,
            );

            if (assignedTo) {
                const [passengerIndex] = assignedTo;

                return {
                    ...current,
                    [Number(passengerIndex)]: {
                        ...current[Number(passengerIndex)],
                        [legOrder]: '',
                    },
                };
            }

            const nextPassengerIndex = Array.from(
                { length: passengerCount },
                (_, i) => i,
            ).find((i) => !current[i]?.[legOrder]);

            if (nextPassengerIndex === undefined) {
                return current;
            }

            return {
                ...current,
                [nextPassengerIndex]: {
                    ...current[nextPassengerIndex],
                    [legOrder]: seat.uuid,
                },
            };
        });
    };

    const handlePassengerCountChange = (nextCount: number) => {
        onPassengerCountChange(nextCount);

        if (nextCount < passengerCount) {
            setAssignments((current) => {
                const copy = { ...current };
                delete copy[nextCount];

                return copy;
            });

            if (activePassenger >= nextCount) {
                setActivePassenger(Math.max(0, nextCount - 1));
            }

            return;
        }

        setAssignments((current) => ({
            ...current,
            [nextCount - 1]: Object.fromEntries(
                legs.map((leg) => [leg.legOrder, '']),
            ),
        }));
    };

    const getSeatCode = (legOrder: number, seatUuid: string) =>
        legSeats
            .find((entry) => entry.legOrder === legOrder)
            ?.seats.find((seat) => seat.uuid === seatUuid)?.code;

    const directionLabel = isMultiLeg
        ? (DIRECTION_LABEL[activeLeg.direction ?? 'outbound'] ?? 'Aller')
        : undefined;

    return (
        <SeatPickerDialog
            onClose={onClose}
            closeLabel={
                isMultiLeg
                    ? 'Fermer la sélection des places'
                    : `Fermer la sélection du ${formatShortDate(activeLeg.departureDate)}`
            }
            header={
                <SeatPickerHeader
                    leg={activeLeg}
                    directionLabel={isMultiLeg ? undefined : directionLabel}
                    progressBadge={
                        isMultiLeg
                            ? `${completedSteps}/${totalSteps} places`
                            : undefined
                    }
                />
            }
        >
            <div className="seat-dialog-body">
                <section
                    className="seat-dialog-main"
                    aria-label="Plan des sièges"
                >
                    {isMultiLeg ? (
                        <div className="multi-leg-stepper">
                            {legs.map((leg, index) => {
                                const done = isLegComplete(
                                    activePassenger,
                                    leg.legOrder,
                                );
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
                                            !isActive && !done && 'is-pending',
                                        )}
                                    >
                                        <span className="multi-leg-step-indicator">
                                            {done ? (
                                                <Check className="size-3.5" />
                                            ) : isActive ? (
                                                <CircleDot className="size-3.5" />
                                            ) : (
                                                <Octagon className="size-3.5" />
                                            )}
                                        </span>
                                        <span className="multi-leg-step-number">
                                            {index + 1}
                                        </span>
                                        <span className="multi-leg-step-info">
                                            <span className="multi-leg-step-direction">
                                                {DIRECTION_LABEL[
                                                    leg.direction ?? 'outbound'
                                                ] ?? 'Aller'}
                                            </span>
                                            <span className="multi-leg-step-route">
                                                {leg.originStop.city} →{' '}
                                                {leg.destinationStop.city}
                                            </span>
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    ) : (
                        <SeatPickerTripSummary
                            leg={activeLeg}
                            availableCount={
                                seatsForActiveLeg.filter(
                                    (seat) =>
                                        seat.availabilityStatus === 'available',
                                ).length
                            }
                        />
                    )}
                    <div className="seat-map-scroll">
                        <SeatGrid
                            seats={seatsForActiveLeg}
                            selectedSeatUuids={selectedUuidsForActiveLeg}
                            takenSeatUuids={takenByOthersForActiveLeg}
                            loading={seatsForActiveLeg.length === 0}
                            onToggleSeat={toggleSeat}
                        />
                    </div>
                </section>

                <SeatSelectionRail
                    seats={seatsForActiveLeg}
                    selectedSeatCodes={selectedSeatCodes}
                    passengerCount={passengerCount}
                    maxPassengers={maxPassengers}
                    loading={seatsForActiveLeg.length === 0}
                    processing={processing}
                    onPassengerCountChange={handlePassengerCountChange}
                    onClose={onClose}
                    onConfirm={() => onConfirm(assignments)}
                    payerType={payerType}
                    selectedCompanyAccountUuid={selectedCompanyAccountUuid}
                    companyAccounts={companyAccounts}
                    amount={amount}
                    currency={currency}
                    onPayerTypeChange={onPayerTypeChange}
                    onCompanyAccountChange={onCompanyAccountChange}
                    multiLeg={
                        isMultiLeg
                            ? {
                                  legs,
                                  activePassenger,
                                  assignments,
                                  onSelectPassenger: (i) => {
                                      setActivePassenger(i);
                                      setActiveLegIndex(0);
                                  },
                                  onNextStep: goToNextStep,
                                  onPreviousStep: goToPreviousStep,
                                  canGoPrevious: !(
                                      activePassenger === 0 &&
                                      activeLegIndex === 0
                                  ),
                                  canGoNext: isLegComplete(
                                      activePassenger,
                                      activeLeg.legOrder,
                                  ),
                                  allComplete,
                                  completedSteps,
                                  totalSteps,
                                  getSeatCode,
                              }
                            : undefined
                    }
                />
            </div>
        </SeatPickerDialog>
    );
}
