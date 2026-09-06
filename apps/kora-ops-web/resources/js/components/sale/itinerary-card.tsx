import {
    Accessibility,
    ArrowRight,
    BusFront,
    Clock,
    MapPin,
    Plug,
    Repeat2,
    Snowflake,
    Sofa,
    Timer,
    Tv,
    Utensils,
    Wifi,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { cn } from '@/lib/utils';
import type {
    SaleBusAmenity,
    SaleItinerary,
    SaleItineraryLeg,
} from './sale-types';
import {
    formatDayMonth,
    formatDuration,
    formatIsoDate,
    formatIsoDepartureCountdown,
    formatIsoTime,
    formatMoney,
    formatShortDate,
} from './sale-utils';

type Props = {
    itinerary: SaleItinerary;
    selected: boolean;
    isNextDeparture: boolean;
    now: number;
    direction?: 'outbound' | 'inbound';
    onSelect: () => void;
};

function legDurationMinutes(leg: SaleItineraryLeg): number {
    const departure = new Date(leg.departureAt).getTime();
    const arrival = new Date(leg.arrivalAt).getTime();

    if (Number.isNaN(departure) || Number.isNaN(arrival)) {
        return 0;
    }

    return Math.max(0, Math.round((arrival - departure) / 60_000));
}

function connectionDurationMinutes(
    current: SaleItineraryLeg,
    next: SaleItineraryLeg,
): number {
    const arrival = new Date(current.arrivalAt).getTime();
    const nextDeparture = new Date(next.departureAt).getTime();

    if (Number.isNaN(arrival) || Number.isNaN(nextDeparture)) {
        return 0;
    }

    return Math.max(0, Math.round((nextDeparture - arrival) / 60_000));
}

function seatsTone(count: number): 'gold' | 'navy' {
    if (count <= 3) {
        return 'navy';
    }

    return 'gold';
}

function busLabel(leg: SaleItineraryLeg): string {
    const reg = leg.bus?.registrationNumber ?? '';
    const brand = leg.bus?.brand ?? '';
    const model = leg.bus?.model ?? '';

    if (brand || model) {
        return `${brand} ${model}`.trim();
    }

    return reg || 'Bus';
}

const amenityIconMap: Record<string, LucideIcon> = {
    wifi: Wifi,
    wifi_internet: Wifi,
    power: Plug,
    power_outlet: Plug,
    power_outlets: Plug,
    usb: Plug,
    usb_charging: Plug,
    ac: Snowflake,
    air_conditioning: Snowflake,
    tv: Tv,
    tv_screen: Tv,
    entertainment: Tv,
    food: Utensils,
    meal_service: Utensils,
    snacks: Utensils,
    onboard_snack: Utensils,
    bottled_water: Utensils,
    accessibility: Accessibility,
    wheelchair: Accessibility,
    onboard_toilet: Sofa,
    luggage_storage: Sofa,
    reclining_seats: Sofa,
    extra_legroom: Sofa,
    premium_seat: Sofa,
    individual_seat: Sofa,
};

function amenityIcon(code: string): LucideIcon {
    return amenityIconMap[code.toLowerCase()] ?? Sofa;
}

const PRIORITY_AMENITIES = ['wifi', 'air_conditioning', 'power_outlet', 'usb_charging', 'onboard_toilet'];

function BusAmenities({ amenities }: { amenities: SaleBusAmenity[] }) {
    const priority = amenities.filter((a) =>
        PRIORITY_AMENITIES.includes(a.code),
    );
    const display = priority.length > 0 ? priority : amenities.slice(0, 4);

    if (display.length === 0) {
        return null;
    }

    return (
        <div className="bus-amenities">
            {display.map((amenity) => {
                const Icon = amenityIcon(amenity.code);

                return (
                    <span
                        key={amenity.code}
                        className="bus-amenity-chip"
                        title={amenity.label}
                    >
                        <Icon className="size-3" />
                        {amenity.label}
                    </span>
                );
            })}
        </div>
    );
}

export function ItineraryCard({
    itinerary,
    selected,
    isNextDeparture,
    now,
    direction = 'outbound',
    onSelect,
}: Props) {
    const firstLeg = itinerary.legs[0];
    const lastLeg = itinerary.legs[itinerary.legs.length - 1];

    if (!firstLeg || !lastLeg) {
        return null;
    }

    const departureCountdown = isNextDeparture
        ? formatIsoDepartureCountdown(firstLeg.departureAt, now)
        : null;
    const minAvailableSeats = Math.min(
        ...itinerary.legs.map((leg) => leg.availableSeatsCount),
    );
    const hasLastSeats = minAvailableSeats > 0 && minAvailableSeats <= 3;
    const serviceClass =
        firstLeg.serviceClassName ??
        firstLeg.serviceClassCode ??
        'Service non renseigné';
    const arrivalTime = formatIsoTime(itinerary.arrivalAt);
    const totalTripMinutes = (() => {
        const start = new Date(itinerary.departureAt).getTime();
        const end = new Date(itinerary.arrivalAt).getTime();

        if (Number.isNaN(start) || Number.isNaN(end)) {
            return null;
        }

        return Math.max(0, Math.round((end - start) / 60_000));
    })();

    return (
        <article
            className={cn(
                'departure premium-departure',
                selected && 'selected',
                isNextDeparture && 'next-departure',
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
                <time dateTime={firstLeg.departureAt}>
                    {formatIsoTime(firstLeg.departureAt)}
                </time>
                <em>{formatShortDate(formatIsoDate(firstLeg.departureAt))}</em>
                <span>
                    {formatDuration(totalTripMinutes ?? null)}
                </span>
                {departureCountdown && (
                    <small className="departure-countdown" aria-live="polite">
                        {departureCountdown}
                    </small>
                )}
                <small>Arrivée {arrivalTime}</small>
            </div>

            <div className="trip-visual">
                <div className="trip-title">
                    <strong>
                        {itinerary.isDirect
                            ? firstLeg.line.code
                            : `${itinerary.legs.length} segments`}
                    </strong>
                    <div className="departure-badges">
                        {isNextDeparture && departureCountdown && (
                            <KoraStatusBadge
                                label="Prochain départ"
                                tone="gold"
                            />
                        )}
                        {hasLastSeats && (
                            <KoraStatusBadge
                                label="Dernières places"
                                tone="navy"
                            />
                        )}
                        {itinerary.isDirect ? (
                            <KoraStatusBadge label="Direct" tone="gold" />
                        ) : (
                            <KoraStatusBadge
                                label={`${itinerary.connectionsCount} correspondance${itinerary.connectionsCount > 1 ? 's' : ''}`}
                                tone="navy"
                            />
                        )}
                        {firstLeg.line.isInternational && (
                            <KoraStatusBadge
                                label="International"
                                tone="navy"
                            />
                        )}
                        <KoraStatusBadge label={serviceClass} tone="gold" />
                    </div>
                </div>

                {itinerary.isDirect ? (
                    <>
                        <div className="route-line" aria-hidden="true">
                            <i className="moving-bus">
                                <BusFront className="size-3.5" />
                            </i>
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
                        <div className="direct-bus-info">
                            <span className="direct-bus-label">
                                <BusFront className="size-3.5" />
                                <strong>{busLabel(firstLeg)}</strong>
                                {firstLeg.bus?.registrationNumber && (
                                    <em>
                                        {firstLeg.bus.registrationNumber}
                                    </em>
                                )}
                            </span>
                            {firstLeg.bus?.amenities &&
                                firstLeg.bus.amenities.length > 0 && (
                                    <BusAmenities
                                        amenities={firstLeg.bus.amenities}
                                    />
                                )}
                        </div>
                    </>
                ) : (
                    <div className="itinerary-timeline itinerary-timeline-segments">
                        {itinerary.legs.map((leg, index) => {
                            const isLast = index === itinerary.legs.length - 1;
                            const nextLeg = isLast
                                ? null
                                : itinerary.legs[index + 1];
                            const waitMinutes = nextLeg
                                ? connectionDurationMinutes(leg, nextLeg)
                                : 0;
                            const sameBusAsPrevious =
                                index > 0 &&
                                itinerary.legs[index - 1].bus?.uuid ===
                                leg.bus?.uuid;

                            return (
                                <div
                                    key={leg.legOrder}
                                    className="timeline-segment"
                                >
                                    <div className="timeline-segment-header">
                                        <span className="timeline-segment-line">
                                            <BusFront className="size-3.5" />
                                            <strong>{leg.line.code}</strong>
                                            <em>{leg.line.name}</em>
                                        </span>
                                        <KoraStatusBadge
                                            label={`${leg.availableSeatsCount} places`}
                                            tone={seatsTone(
                                                leg.availableSeatsCount,
                                            )}
                                        />
                                    </div>

                                    <div className="timeline-segment-body">
                                        <div className="timeline-segment-endpoint">
                                            <div className="timeline-segment-time">
                                                <strong>
                                                    {formatIsoTime(
                                                        leg.departureAt,
                                                    )}
                                                </strong>
                                                <small>
                                                    {formatDayMonth(formatIsoDate(leg.departureAt))}
                                                </small>
                                                <em>départ</em>
                                            </div>
                                            <div className="timeline-segment-place">
                                                <MapPin className="size-3" />
                                                <span>
                                                    <b>
                                                        {leg.originStop.city}
                                                    </b>
                                                    <em>
                                                        {leg.originStop.name}
                                                    </em>
                                                </span>
                                            </div>
                                        </div>

                                        <div className="timeline-segment-meta">
                                            <span className="timeline-segment-duration">
                                                <Timer className="size-3" />
                                                {formatDuration(
                                                    legDurationMinutes(leg),
                                                )}
                                            </span>
                                            {!sameBusAsPrevious && (
                                                <span className="timeline-segment-bus">
                                                    <BusFront className="size-3" />
                                                    {busLabel(leg)}
                                                    {leg.bus?.registrationNumber && (
                                                        <em>
                                                            {leg.bus.registrationNumber}
                                                        </em>
                                                    )}
                                                </span>
                                            )}
                                            {sameBusAsPrevious && (
                                                <span className="timeline-segment-bus-same">
                                                    <Repeat2 className="size-3" />
                                                    Même bus
                                                </span>
                                            )}
                                        </div>

                                        {leg.bus?.amenities &&
                                            leg.bus.amenities.length > 0 && (
                                                <BusAmenities
                                                    amenities={leg.bus.amenities}
                                                />
                                            )}

                                        <div className="timeline-segment-endpoint">
                                            <div className="timeline-segment-time">
                                                <strong>
                                                    {formatIsoTime(
                                                        leg.arrivalAt,
                                                    )}
                                                </strong>
                                                <small>
                                                    {formatDayMonth(
                                                        formatIsoDate(leg.arrivalAt),
                                                    )}
                                                </small>
                                                <em>arrivée</em>
                                            </div>
                                            <div className="timeline-segment-place">
                                                <MapPin className="size-3" />
                                                <span>
                                                    <b>
                                                        {
                                                            leg.destinationStop
                                                                .city
                                                        }
                                                    </b>
                                                    <em>
                                                        {
                                                            leg.destinationStop
                                                                .name
                                                        }
                                                    </em>
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    {!isLast && nextLeg && (
                                        <div className="timeline-segment-connection">
                                            <Clock className="size-3.5" />
                                            <span>
                                                Correspondance{' '}
                                                <strong>
                                                    {formatDuration(waitMinutes)}
                                                </strong>{' '}
                                                à {leg.destinationStop.city}
                                            </span>
                                            <ArrowRight className="size-3" />
                                            <span>
                                                {formatDayMonth(formatIsoDate(nextLeg.departureAt))}{' '}
                                                {formatIsoTime(
                                                    nextLeg.departureAt,
                                                )}
                                            </span>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>

            <div className="seat-count">
                {itinerary.isDirect ? (
                    <>
                        <strong>{minAvailableSeats}</strong>
                        <span>places disponibles</span>
                    </>
                ) : (
                    <>
                        <strong>{minAvailableSeats}</strong>
                        <span>places min.</span>
                        <small className="seat-count-detail">
                            <Sofa className="size-3" />
                            par segment
                        </small>
                    </>
                )}
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
                    {selected
                        ? direction === 'inbound'
                            ? 'Retour sélectionné'
                            : 'Aller sélectionné'
                        : direction === 'inbound'
                            ? 'Sélectionner le retour'
                            : "Sélectionner l'aller"}
                    <ArrowRight className="size-3.5" />
                </button>
            </div>
        </article>
    );
}
