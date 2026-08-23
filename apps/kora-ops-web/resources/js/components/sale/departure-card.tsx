import { ArrowRight, BusFront, Clock3 } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { SaleDeparture } from './sale-types';
import {
    formatDate,
    formatDuration,
    formatMoney,
    formatTime,
} from './sale-utils';

type Props = {
    departure: SaleDeparture;
    selected: boolean;
    onSelect: () => void;
};

export function DepartureCard({ departure, selected, onSelect }: Props) {
    const price = departure.farePreview?.final_amount;
    const currency = departure.farePreview?.currency;

    return (
        <article
            className={cn(
                'grid gap-4 rounded-[1.1rem] border bg-card p-4 shadow-[0_10px_30px_rgba(0,29,78,0.045)] transition sm:grid-cols-[minmax(0,1.4fr)_minmax(8rem,0.8fr)_auto] sm:items-center',
                selected
                    ? 'border-gold ring-2 ring-gold/20'
                    : 'border-border hover:border-gold/60',
            )}
        >
            <div className="flex items-center gap-3">
                <span className="grid size-11 shrink-0 place-items-center rounded-[0.8rem] bg-[var(--kora)] text-gold">
                    <BusFront className="size-5" />
                </span>
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2 text-[0.6875rem] font-black text-digital">
                        <span>{departure.line.code}</span>
                        <span className="text-muted-foreground">·</span>
                        <span>{departure.bus.registrationNumber}</span>
                    </div>
                    <div className="mt-1 flex flex-wrap items-center gap-2 text-[0.75rem] font-extrabold text-foreground">
                        <span>{departure.originStop.city}</span>
                        <ArrowRight className="size-3.5 text-gold" />
                        <span>{departure.destinationStop.city}</span>
                    </div>
                    <div className="mt-1 flex flex-wrap items-center gap-2 text-[0.625rem] text-muted-foreground">
                        <span>{formatDate(departure.departureDate)}</span>
                        <span>·</span>
                        <Clock3 className="size-3" />
                        <span>{formatTime(departure.departureTime)}</span>
                        <span>·</span>
                        <span>
                            {formatDuration(
                                departure.line.estimatedDurationMinutes,
                            )}
                        </span>
                    </div>
                </div>
            </div>
            <div className="flex items-center justify-between gap-4 sm:block sm:text-right">
                <div>
                    <strong className="block text-[0.8rem] font-black text-digital">
                        {departure.availableSeatsCount}
                    </strong>
                    <span className="text-[0.625rem] text-muted-foreground">
                        places disponibles
                    </span>
                </div>
                <div className="mt-2">
                    <span className="block text-[0.5625rem] text-muted-foreground">
                        par passager
                    </span>
                    <strong className="text-[0.85rem] font-black text-foreground">
                        {price
                            ? formatMoney(price, currency)
                            : 'Tarif indisponible'}
                    </strong>
                </div>
            </div>
            <button
                type="button"
                onClick={onSelect}
                className={cn(
                    'inline-flex h-10 items-center justify-center rounded-[0.65rem] px-4 text-[0.6875rem] font-black transition hover:-translate-y-0.5',
                    selected
                        ? 'bg-[var(--kora)] text-white'
                        : 'border border-border bg-background text-foreground hover:border-gold',
                )}
            >
                {selected ? 'Sélectionné' : 'Choisir'}
            </button>
        </article>
    );
}
