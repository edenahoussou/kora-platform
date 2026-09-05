import { BusFront } from 'lucide-react';
import { ItineraryCard } from './itinerary-card';
import type { SaleItinerary } from './sale-types';

type Props = {
    itineraries: SaleItinerary[];
    selectedLegsKey: string | null;
    onSelect: (itinerary: SaleItinerary) => void;
};

function itineraryKey(itinerary: SaleItinerary): string {
    return itinerary.legs
        .map((leg) => `${leg.departureUuid}:${leg.originStop.uuid}:${leg.destinationStop.uuid}`)
        .join('|');
}

export function ItineraryList({
    itineraries,
    selectedLegsKey,
    onSelect,
}: Props) {
    return (
        <section className="departure-results">
            <div className="results-head">
                <div>
                    <span className="section-kicker">
                        Itinéraires disponibles ({itineraries.length})
                    </span>
                    <h2>Choisissez le voyage</h2>
                </div>
            </div>
            {itineraries.length > 0 ? (
                <div className="departure-list">
                    {itineraries.map((itinerary) => (
                        <ItineraryCard
                            key={itineraryKey(itinerary)}
                            itinerary={itinerary}
                            selected={itineraryKey(itinerary) === selectedLegsKey}
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
                            Aucun itinéraire trouvé
                        </strong>
                        <span className="mt-1 block text-[0.6875rem] text-muted-foreground">
                            Modifiez vos critères puis relancez la recherche.
                        </span>
                    </div>
                </div>
            )}
        </section>
    );
}
