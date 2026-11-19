import type { SaleItineraryLeg } from './sale-types';
import { formatStopSchedule } from './sale-utils';

type Props = {
    leg: SaleItineraryLeg;
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

export function SeatPickerTripSummary({ leg, availableCount }: Props) {
    return (
        <div className="seat-trip-summary">
            <div className="seat-trip-route">
                <div>
                    <strong>{stopCode(leg.originStop.city)}</strong>
                    <span>{leg.originStop.city}</span>
                    <small>{leg.originStop.name}</small>
                </div>
                <div className="seat-trip-route-line" aria-hidden="true">
                    <i />
                </div>
                <div className="seat-trip-destination">
                    <strong>{stopCode(leg.destinationStop.city)}</strong>
                    <span>{leg.destinationStop.city}</span>
                    <small>{leg.destinationStop.name}</small>
                </div>
            </div>
            <div className="seat-trip-details">
                <span>
                    Montée :{' '}
                    {formatStopSchedule(leg.departureAt, leg.departureTimezone)}
                    <br />
                    Descente :{' '}
                    {formatStopSchedule(leg.arrivalAt, leg.arrivalTimezone)}
                </span>
                <strong>{availableCount} places disponibles</strong>
            </div>
        </div>
    );
}
