import { ArrowRight, BusFront } from 'lucide-react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { cn } from '@/lib/utils';
import type { SaleDeparture } from './sale-types';
import {
    formatDepartureCountdown,
    formatDuration,
    formatMoney,
    formatShortDate,
    formatTime,
} from './sale-utils';

type Props = {
    departure: SaleDeparture;
    selected: boolean;
    isNextDeparture: boolean;
    now: number;
    onSelect: () => void;
};

export function DepartureCard({
    departure,
    selected,
    isNextDeparture,
    now,
    onSelect,
}: Props) {
    const price = departure.farePreview?.final_amount;
    const basePrice = departure.farePreview?.base_amount;
    const dynamicAdjustment = departure.farePreview?.dynamic_adjustment_amount ?? 0;
    const currency = departure.farePreview?.currency;
    const serviceClass =
        departure.farePreview?.service_class_name ??
        departure.farePreview?.service_class_code ??
        'Service non renseigné';
    const departureCountdown = isNextDeparture
        ? formatDepartureCountdown(
              departure.departureDate,
              departure.departureTime,
              now,
          )
        : null;
    const hasLastSeats =
        departure.availableSeatsCount > 0 && departure.availableSeatsCount <= 3;

    return (
        <article
            className={cn(
                'departure premium-departure',
                selected && 'selected',
                isNextDeparture && 'next-departure',
            )}
        >
            <div className="departure-time">
                <small>Départ</small>
                <time
                    dateTime={`${departure.departureDate}T${departure.departureTime}`}
                >
                    {formatTime(departure.departureTime)}
                </time>
                <em>{formatShortDate(departure.departureDate)}</em>
                <span>
                    {formatDuration(departure.line.estimatedDurationMinutes)}
                </span>
                {departureCountdown && (
                    <small className="departure-countdown" aria-live="polite">
                        {departureCountdown}
                    </small>
                )}
            </div>

            <div className="trip-visual">
                <div className="trip-title">
                    <strong>{departure.line.code}</strong>
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
                                tone="muted"
                            />
                        )}
                        <KoraStatusBadge label={serviceClass} tone="gold" />
                    </div>
                </div>
                <div className="route-line" aria-hidden="true">
                    <i className="moving-bus">
                        <BusFront className="size-3.5" />
                    </i>
                </div>
                <div className="route-cities">
                    <span>
                        <b>{departure.originStop.city}</b>
                        <em>{departure.originStop.name}</em>
                    </span>
                    <span>
                        <b>{departure.destinationStop.city}</b>
                        <em>{departure.destinationStop.name}</em>
                    </span>
                </div>
            </div>

            <div className="seat-count">
                <strong>{departure.availableSeatsCount}</strong>
                <span>places disponibles</span>
            </div>

            <div className="departure-price">
                <small>{dynamicAdjustment !== 0 ? 'prix dynamique' : 'par passager'}</small>
                <div className="price">
                    {price !== undefined
                        ? formatMoney(price, currency)
                        : 'Tarif indisponible'}
                </div>
                {dynamicAdjustment !== 0 && basePrice !== undefined && (
                    <span className="mt-1 block text-[0.65rem] text-muted-foreground">
                        Base {formatMoney(basePrice, currency)} · {dynamicAdjustment > 0 ? '+' : ''}{formatMoney(dynamicAdjustment, currency)}
                    </span>
                )}
                <button
                    type="button"
                    onClick={onSelect}
                    className={cn(
                        'btn',
                        selected ? 'btn-primary' : 'btn-ghost',
                    )}
                >
                    {selected ? 'Sélectionnée' : 'Choisir'}
                    <ArrowRight className="size-3.5" />
                </button>
            </div>
        </article>
    );
}
