import { BusFront } from 'lucide-react';
import { DepartureCard } from './departure-card';
import type { SaleDeparture } from './sale-types';

type Props = {
    departures: SaleDeparture[];
    selectedUuid: string | null;
    onSelect: (departure: SaleDeparture) => void;
};

export function DepartureList({ departures, selectedUuid, onSelect }: Props) {
    return (
        <section className="grid gap-3">
            <div className="flex items-end justify-between gap-4">
                <div>
                    <span className="block text-[0.625rem] font-black tracking-[0.14em] text-digital uppercase">
                        Départs disponibles
                    </span>
                    <h2 className="mt-1 text-[1.15rem] font-extrabold tracking-[-0.04em] text-foreground">
                        Choisissez le voyage
                    </h2>
                </div>
                <span className="text-[0.6875rem] font-bold text-muted-foreground">
                    {departures.length} résultat
                    {departures.length > 1 ? 's' : ''}
                </span>
            </div>
            {departures.length > 0 ? (
                departures.map((departure) => (
                    <DepartureCard
                        key={departure.uuid}
                        departure={departure}
                        selected={departure.uuid === selectedUuid}
                        onSelect={() => onSelect(departure)}
                    />
                ))
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
