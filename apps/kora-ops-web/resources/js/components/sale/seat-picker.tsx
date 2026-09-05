import type { SaleDeparture, SaleSeat } from './sale-types';
import { SeatGrid } from './seat-grid';
import { SeatPickerDialog } from './seat-picker-dialog';
import { SeatPickerTripSummary } from './seat-picker-trip-summary';
import { SeatSelectionRail } from './seat-selection-rail';

type Props = {
    departure: SaleDeparture;
    seats: SaleSeat[];
    selectedSeatUuids: string[];
    passengerCount: number;
    maxPassengers?: number;
    loading: boolean;
    processing: boolean;
    onToggleSeat: (seat: SaleSeat) => void;
    onPassengerCountChange: (count: number) => void;
    onClose: () => void;
    onConfirm: () => void;
};

export function SeatPicker({
    departure,
    seats,
    selectedSeatUuids,
    passengerCount,
    maxPassengers = 10,
    loading,
    processing,
    onToggleSeat,
    onPassengerCountChange,
    onClose,
    onConfirm,
}: Props) {
    const selectedSeatCodes = seats
        .filter((seat) => selectedSeatUuids.includes(seat.uuid))
        .map((seat) => seat.code);
    const availableCount = seats.filter(
        (seat) => seat.availabilityStatus === 'available',
    ).length;

    return (
        <SeatPickerDialog departure={departure} onClose={onClose}>
            <div className="seat-dialog-body">
                <section
                    className="seat-dialog-main"
                    aria-label="Plan des sièges"
                >
                    <SeatPickerTripSummary
                        departure={departure}
                        availableCount={availableCount}
                    />
                    <div className="seat-map-scroll">
                        <SeatGrid
                            seats={seats}
                            selectedSeatUuids={selectedSeatUuids}
                            loading={loading}
                            onToggleSeat={onToggleSeat}
                        />
                    </div>
                </section>

                <SeatSelectionRail
                    seats={seats}
                    selectedSeatCodes={selectedSeatCodes}
                    passengerCount={passengerCount}
                    maxPassengers={maxPassengers}
                    loading={loading}
                    processing={processing}
                    onPassengerCountChange={onPassengerCountChange}
                    onClose={onClose}
                    onConfirm={onConfirm}
                />
            </div>
        </SeatPickerDialog>
    );
}
