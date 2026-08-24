import { BusFront } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { DepartureCard } from './departure-card';
import type { SaleDeparture } from './sale-types';
import { getDepartureTimestamp, isToday } from './sale-utils';

type Props = {
    departures: SaleDeparture[];
    selectedUuid: string | null;
    onSelect: (departure: SaleDeparture) => void;
};

export function DepartureList({ departures, selectedUuid, onSelect }: Props) {
    const [now, setNow] = useState(() => Date.now());
    const nextDepartureUuid = useMemo(() => {
        const nextDeparture = departures
            .filter((departure) => isToday(departure.departureDate))
            .map((departure) => ({
                departure,
                timestamp: getDepartureTimestamp(
                    departure.departureDate,
                    departure.departureTime,
                ),
            }))
            .filter(
                (
                    item,
                ): item is {
                    departure: SaleDeparture;
                    timestamp: number;
                } => item.timestamp !== null && item.timestamp > now,
            )
            .sort((first, second) => first.timestamp - second.timestamp)[0];

        return nextDeparture?.departure.uuid ?? null;
    }, [departures, now]);

    useEffect(() => {
        const interval = window.setInterval(() => {
            setNow(Date.now());
        }, 60_000);

        return () => window.clearInterval(interval);
    }, []);

    return (
        <section className="departure-results">
            <div className="results-head">
                <div>
                    <span className="section-kicker">
                        Départs disponibles ({departures.length})
                    </span>
                    <h2>Choisissez le voyage</h2>
                </div>
            </div>
            {departures.length > 0 ? (
                <div className="departure-list">
                    {departures.map((departure) => (
                        <DepartureCard
                            key={departure.uuid}
                            departure={departure}
                            selected={departure.uuid === selectedUuid}
                            isNextDeparture={
                                departure.uuid === nextDepartureUuid
                            }
                            now={now}
                            onSelect={() => onSelect(departure)}
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
                            Aucun départ trouvé
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
