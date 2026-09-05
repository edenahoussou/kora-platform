import { ArrowRight, BusFront, Clock, MapPin } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { SaleItinerary } from './sale-types';
import { formatMoney, formatTime } from './sale-utils';

type Props = {
    itinerary: SaleItinerary;
    selected: boolean;
    onSelect: () => void;
};

export function ItineraryCard({ itinerary, selected, onSelect }: Props) {
    const firstLeg = itinerary.legs[0];
    const lastLeg = itinerary.legs[itinerary.legs.length - 1];

    if (!firstLeg || !lastLeg) {
        return null;
    }

    const departureTime = formatTime(firstLeg.departureTime);
    const arrivalTime = formatTime(
        new Date(itinerary.arrivalAt).toTimeString().slice(0, 5),
    );

    return (
        <article
            className={cn(
                'departure premium-departure',
                selected && 'selected',
            )}
            onClick={onSelect}
            role="button"
            tabIndex={0}
            onKeyDown={(e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    onSelect();
                }
            }}
        >
            <div className="departure-time">
                <small>Départ</small>
                <time dateTime={firstLeg.departureAt}>{departureTime}</time>
                <em>{firstLeg.departureDate}</em>
                <span>Arrivée {arrivalTime}</span>
            </div>

            <div className="trip-visual">
                <div className="trip-title">
                    <strong>
                        {itinerary.isDirect
                            ? firstLeg.line.code
                            : `${itinerary.legs.length} jambes`}
                    </strong>
                    <div className="departure-badges">
                        {itinerary.isDirect ? (
                            <span className="badge badge-direct">Direct</span>
                        ) : (
                            <span className="badge badge-connection">
                                {itinerary.connectionsCount} correspondance
                                {itinerary.connectionsCount > 1 ? 's' : ''}
                            </span>
                        )}
                        {firstLeg.line.isInternational && (
                            <span className="badge badge-international">
                                International
                            </span>
                        )}
                    </div>
                </div>
                <div className="itinerary-timeline">
                    {itinerary.legs.map((leg, index) => (
                        <div key={leg.legOrder} className="timeline-leg">
                            <div className="timeline-leg-header">
                                <span className="timeline-line-code">
                                    {leg.line.code}
                                </span>
                                <span className="timeline-leg-time">
                                    {formatTime(leg.departureTime)}
                                </span>
                            </div>
                            <div className="timeline-leg-route">
                                <MapPin className="size-3" />
                                <span>{leg.originStop.city}</span>
                                <ArrowRight className="size-3" />
                                <span>{leg.destinationStop.city}</span>
                            </div>
                            {index < itinerary.legs.length - 1 && (
                                <div className="timeline-connection">
                                    <Clock className="size-3" />
                                    <span>
                                        Correspondance{' '}
                                        {itinerary.connectionMarginMinutes} min
                                    </span>
                                </div>
                            )}
                        </div>
                    ))}
                </div>
                <div className="route-cities">
                    <span>
                        <b>{firstLeg.originStop.city}</b>
                        <em>{firstLeg.originStop.name}</em>
                    </span>
                    <span>
                        <b>{lastLeg.destinationStop.city}</b>
                        <em>{lastLeg.destinationStop.name}</em>
                    </span>
                </div>
            </div>

            <div className="seat-count">
                <BusFront className="size-4" />
                <span>
                    {itinerary.legs.length}{' '}
                    {itinerary.legs.length > 1 ? 'bus' : 'bus'}
                </span>
            </div>

            <div className="departure-price">
                <small>par passager</small>
                <div className="price">
                    {formatMoney(itinerary.totalAmount, itinerary.currency)}
                </div>
                <button
                    type="button"
                    onClick={(e) => {
                        e.stopPropagation();
                        onSelect();
                    }}
                    className={cn(
                        'btn',
                        selected ? 'btn-primary' : 'btn-ghost',
                    )}
                >
                    {selected ? 'Sélectionné' : 'Choisir'}
                    <ArrowRight className="size-3.5" />
                </button>
            </div>
        </article>
    );
}
