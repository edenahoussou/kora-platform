import type { SaleDeparture } from './sale-types';
import { formatShortDate, formatTime } from './sale-utils';

type Props = {
    departure: SaleDeparture;
    availableCount: number;
};

function stopCode(city: string): string {
    return city
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^A-Za-z]/g, '')
        .slice(0, 3)
        .toUpperCase();
}

export function SeatPickerTripSummary({ departure, availableCount }: Props) {
    return (
        <div className="seat-trip-summary">
            <div className="seat-trip-route">
                <div>
                    <strong>{stopCode(departure.originStop.city)}</strong>
                    <span>{departure.originStop.city}</span>
                    <small>{departure.originStop.name}</small>
                </div>
                <div className="seat-trip-route-line" aria-hidden="true">
                    <i />
                </div>
                <div className="seat-trip-destination">
                    <strong>{stopCode(departure.destinationStop.city)}</strong>
                    <span>{departure.destinationStop.city}</span>
                    <small>{departure.destinationStop.name}</small>
                </div>
            </div>
            <div className="seat-trip-details">
                <span>
                    {formatShortDate(departure.departureDate)} ·{' '}
                    {formatTime(departure.departureTime)}
                </span>
                <strong>{availableCount} places disponibles</strong>
            </div>
        </div>
    );
}
