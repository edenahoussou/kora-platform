import { Clock3, Minus, Plus } from 'lucide-react';
import type { SaleSeat } from './sale-types';
import { SeatPickerLegend } from './seat-picker-legend';

type Props = {
    seats: SaleSeat[];
    selectedSeatCodes: string[];
    passengerCount: number;
    loading: boolean;
    processing: boolean;
    onPassengerCountChange: (count: number) => void;
    onClose: () => void;
    onConfirm: () => void;
};

export function SeatSelectionRail({
    seats,
    selectedSeatCodes,
    passengerCount,
    loading,
    processing,
    onPassengerCountChange,
    onClose,
    onConfirm,
}: Props) {
    const availableCount = seats.filter(
        (seat) => seat.availabilityStatus === 'available',
    ).length;
    const selectionMatchesPassengers =
        selectedSeatCodes.length === passengerCount;

    return (
        <aside className="seat-selection-rail" aria-label="Votre sélection">
            <div className="seat-selection-rail-content">
                <div className="seat-selection-heading">
                    <div>
                        <span className="section-kicker">Votre sélection</span>
                        <h3>Places du voyage</h3>
                    </div>
                    <strong>
                        {selectedSeatCodes.length}/{passengerCount}
                    </strong>
                </div>

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

                <div className="seat-selection-counter">
                    <div>
                        <span>Voyageurs</span>
                        <small>
                            {selectedSeatCodes.length}/{passengerCount} places
                            choisies
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
                            disabled={passengerCount >= 10 || processing}
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
            </div>
        </aside>
    );
}
