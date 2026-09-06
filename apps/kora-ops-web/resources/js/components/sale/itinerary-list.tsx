import { ArrowLeft, ArrowRight, BusFront } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { cn } from '@/lib/utils';
import { ItineraryCard } from './itinerary-card';
import type { SaleItinerary } from './sale-types';
import { formatIsoDate, getIsoTimestamp, isToday } from './sale-utils';

type Props = {
    itineraries: SaleItinerary[];
    selectedLegsKey: string | null;
    onSelect: (itinerary: SaleItinerary) => void;
    hasDate?: boolean;
    direction?: 'outbound' | 'inbound';
    emptyReason?: 'incompatible-with-outbound';
};

function itineraryKey(itinerary: SaleItinerary): string {
    return itinerary.legs
        .map(
            (leg) =>
                `${leg.departureUuid}:${leg.originStop.uuid}:${leg.destinationStop.uuid}`,
        )
        .join('|');
}

export function ItineraryList({
    itineraries,
    selectedLegsKey,
    onSelect,
    hasDate = true,
    direction = 'outbound',
    emptyReason,
}: Props) {
    const [now, setNow] = useState(() => Date.now());
    const nextItineraryKey = useMemo(() => {
        const next = itineraries
            .map((itinerary) => {
                const firstLeg = itinerary.legs[0];

                if (!firstLeg) {
                    return null;
                }

                return {
                    key: itineraryKey(itinerary),
                    timestamp: getIsoTimestamp(firstLeg.departureAt),
                };
            })
            .filter(
                (
                    item,
                ): item is {
                    key: string;
                    timestamp: number;
                } => {
                    if (item === null || item.timestamp === null || item.timestamp <= now) {
                        return false;
                    }

                    const itinerary = itineraries.find((i) => itineraryKey(i) === item.key);

                    return isToday(
                        formatIsoDate(itinerary?.legs[0]?.departureAt ?? null),
                    );
                },
            )
            .sort((first, second) => first.timestamp - second.timestamp)[0];

        return next?.key ?? null;
    }, [itineraries, now]);

    useEffect(() => {
        const interval = window.setInterval(() => {
            setNow(Date.now());
        }, 60_000);

        return () => window.clearInterval(interval);
    }, []);

    return (
        <section
            className={cn(
                'departure-results',
                direction === 'inbound' && 'departure-results-inbound',
            )}
        >
            <div className="results-head">
                <div>
                    <span className="section-kicker">
                        {direction === 'inbound'
                            ? hasDate
                                ? `Retour disponibles (${itineraries.length})`
                                : `Prochains retours (${itineraries.length})`
                            : hasDate
                                ? `Aller disponibles (${itineraries.length})`
                                : `Prochains allers (${itineraries.length})`}
                    </span>
                    <h2 className="results-title">
                        {direction === 'inbound' ? (
                            <>
                                <ArrowLeft className="size-4 text-gold" />
                                {hasDate
                                    ? 'Choisissez le retour'
                                    : 'Retours à venir'}
                                <BusFront className="size-4 text-gold" />
                            </>
                        ) : (
                            <>
                                <BusFront className="size-4 text-gold" />
                                {hasDate
                                    ? "Choisissez l'aller"
                                    : 'Départs à venir'}
                                <ArrowRight className="size-4 text-gold" />
                            </>
                        )}
                    </h2>
                </div>
            </div>
            {itineraries.length > 0 ? (
                <div className="departure-list">
                    {itineraries.map((itinerary) => (
                        <ItineraryCard
                            key={itineraryKey(itinerary)}
                            itinerary={itinerary}
                            selected={
                                itineraryKey(itinerary) === selectedLegsKey
                            }
                            isNextDeparture={
                                itineraryKey(itinerary) === nextItineraryKey
                            }
                            now={now}
                            direction={direction}
                            onSelect={() => onSelect(itinerary)}
                        />
                    ))}
                </div>
            ) : (
                <div className="grid min-h-48 place-items-center rounded-[1.1rem] border border-dashed border-border bg-card p-8 text-center">
                    <span className="grid size-11 place-items-center rounded-full bg-muted text-muted-foreground">
                        <BusFront className="size-5" />
                    </span>
                    <div className="mt-3">
                        <strong className="block text-[0.8rem] font-extrabold text-foreground">
                            {emptyReason === 'incompatible-with-outbound'
                                ? 'Aucun retour compatible'
                                : direction === 'inbound'
                                    ? 'Aucun retour trouvé'
                                    : 'Aucun itinéraire trouvé'}
                        </strong>
                        <span className="mt-1 block text-[0.6875rem] text-muted-foreground">
                            {emptyReason === 'incompatible-with-outbound'
                                ? "Les retours disponibles partent avant ou en même temps que l'aller sélectionné. Choisissez un aller plus tôt ou une autre date de retour."
                                : hasDate
                                    ? 'Modifiez vos critères puis relancez la recherche.'
                                    : 'Sélectionnez une date précise ou élargissez la ville de départ/descente.'}
                        </span>
                    </div>
                </div>
            )}
        </section>
    );
}
