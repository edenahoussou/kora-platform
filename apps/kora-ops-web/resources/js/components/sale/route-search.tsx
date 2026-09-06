import {
    ArrowRightLeft,
    CalendarDays,
    MapPin,
    Search,
    Sofa,
} from 'lucide-react';
import type { ReactNode, SyntheticEvent } from 'react';
import { cn } from '@/lib/utils';
import type {
    SaleCity,
    SaleSearch,
    SaleServiceClass,
    SaleTripType,
} from './sale-types';

type Props = {
    cities: SaleCity[];
    serviceClasses: SaleServiceClass[];
    value: SaleSearch;
    processing: boolean;
    onChange: (value: SaleSearch) => void;
    onSubmit: (event: SyntheticEvent<HTMLFormElement>) => void;
};

const TRIP_OPTIONS: Array<{ value: SaleTripType; label: string }> = [
    { value: 'one_way', label: 'Aller simple' },
    { value: 'round_trip', label: 'Aller-retour' },
];

function FieldLabel({
    icon,
    children,
    required = false,
}: {
    icon: ReactNode;
    children: ReactNode;
    required?: boolean;
}) {
    return (
        <span className="flex items-center gap-1.5">
            <span className="text-gold/80">{icon}</span>
            {children}
            {required && (
                <span className="text-[0.625rem] text-red-400" aria-hidden>
                    *
                </span>
            )}
        </span>
    );
}

export function RouteSearch({
    cities,
    serviceClasses,
    value,
    processing,
    onChange,
    onSubmit,
}: Props) {
    const update = (key: keyof SaleSearch, nextValue: string) =>
        onChange({ ...value, [key]: nextValue || null });

    const setTripType = (tripType: SaleTripType) =>
        onChange({
            ...value,
            tripType,
            returnDate: tripType === 'round_trip' ? value.returnDate : null,
        });

    const isRoundTrip = value.tripType === 'round_trip';

    return (
        <form onSubmit={onSubmit} className="search-route premium-route">
            <div className="route-caption">
                <span className="section-kicker">Itinéraire par segment</span>
                <strong>Où voyage le passager ?</strong>
            </div>

            <div
                role="radiogroup"
                aria-label="Type de trajet"
                className="trip-type-segmented"
            >
                {TRIP_OPTIONS.map((option) => (
                    <button
                        key={option.value}
                        type="button"
                        role="radio"
                        aria-checked={value.tripType === option.value}
                        onClick={() => setTripType(option.value)}
                        className={cn(
                            'trip-type-pill',
                            value.tripType === option.value && 'is-active',
                        )}
                    >
                        {option.label}
                    </button>
                ))}
            </div>

            <div
                className={cn(
                    'grid gap-3 xl:items-end',
                    isRoundTrip
                        ? 'xl:grid-cols-[minmax(0,1fr)_2.2rem_minmax(0,1fr)_minmax(8rem,0.7fr)_minmax(8rem,0.7fr)_minmax(7rem,0.6fr)_auto]'
                        : 'xl:grid-cols-[minmax(0,1fr)_2.2rem_minmax(0,1fr)_minmax(8.5rem,0.8fr)_minmax(8rem,0.7fr)_auto]',
                )}
            >
                <label className="grid gap-1.5 text-[0.625rem] font-extrabold text-white/60">
                    <FieldLabel icon={<MapPin className="size-3.5" />}>
                        Ligne et montée
                    </FieldLabel>
                    <select
                        value={value.from ?? ''}
                        onChange={(event) => update('from', event.target.value)}
                        className="h-12 w-full rounded-[0.8rem] border border-white/15 bg-white/10 px-3 text-[0.75rem] font-bold text-white outline-none focus:border-gold focus:ring-2 focus:ring-gold/30"
                    >
                        <option value="" className="text-foreground">
                            Toutes les villes
                        </option>
                        {cities.map((city) => (
                            <option
                                key={city.city}
                                value={city.city}
                                className="text-foreground"
                            >
                                {city.city}
                            </option>
                        ))}
                    </select>
                </label>
                <button
                    type="button"
                    onClick={() =>
                        onChange({ ...value, from: value.to, to: value.from })
                    }
                    className="hidden h-10 place-items-center self-end rounded-[0.7rem] border border-white/15 bg-white/10 text-gold transition hover:bg-white/15 xl:grid"
                    aria-label="Inverser le départ et la destination"
                >
                    <ArrowRightLeft className="size-4" />
                </button>
                <label className="grid gap-1.5 text-[0.625rem] font-extrabold text-white/60">
                    <FieldLabel icon={<MapPin className="size-3.5" />}>
                        Descente
                    </FieldLabel>
                    <select
                        value={value.to ?? ''}
                        onChange={(event) => update('to', event.target.value)}
                        className="h-12 w-full rounded-[0.8rem] border border-white/15 bg-white/10 px-3 text-[0.75rem] font-bold text-white outline-none focus:border-gold focus:ring-2 focus:ring-gold/30"
                    >
                        <option value="" className="text-foreground">
                            Toutes les villes
                        </option>
                        {cities.map((city) => (
                            <option
                                key={city.city}
                                value={city.city}
                                className="text-foreground"
                            >
                                {city.city}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="grid gap-1.5 text-[0.625rem] font-extrabold text-white/60">
                    <FieldLabel icon={<Sofa className="size-3.5" />}>
                        Type de service
                    </FieldLabel>
                    <select
                        value={value.serviceClass ?? ''}
                        onChange={(event) =>
                            update('serviceClass', event.target.value)
                        }
                        className="h-12 w-full rounded-[0.8rem] border border-white/15 bg-white/10 px-3 text-[0.75rem] font-bold text-white outline-none focus:border-gold focus:ring-2 focus:ring-gold/30"
                    >
                        <option value="" className="text-foreground">
                            Tous les services
                        </option>
                        {serviceClasses.map((serviceClass) => (
                            <option
                                key={serviceClass.uuid}
                                value={serviceClass.code}
                                className="text-foreground"
                            >
                                {serviceClass.name}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="grid gap-1.5 text-[0.625rem] font-extrabold text-white/60">
                    <FieldLabel
                        icon={<CalendarDays className="size-3.5" />}
                        required={isRoundTrip}
                    >
                        {isRoundTrip ? 'Départ' : 'Date du voyage'}
                    </FieldLabel>
                    <input
                        type="date"
                        value={value.date ?? ''}
                        onChange={(event) => update('date', event.target.value)}
                        className="h-12 w-full rounded-[0.8rem] border border-white/15 bg-white/10 px-3 text-[0.75rem] font-bold text-white outline-none focus:border-gold focus:ring-2 focus:ring-gold/30"
                    />
                </label>
                {isRoundTrip && (
                    <label className="grid gap-1.5 text-[0.625rem] font-extrabold text-white/60">
                        <FieldLabel
                            icon={<CalendarDays className="size-3.5" />}
                        >
                            Retour
                        </FieldLabel>
                        <input
                            type="date"
                            value={value.returnDate ?? ''}
                            onChange={(event) =>
                                update('returnDate', event.target.value)
                            }
                            className="h-12 w-full rounded-[0.8rem] border border-white/15 bg-white/10 px-3 text-[0.75rem] font-bold text-white outline-none focus:border-gold focus:ring-2 focus:ring-gold/30"
                        />
                    </label>
                )}
                <button
                    type="submit"
                    disabled={processing}
                    className="route-search-submit inline-flex h-12 cursor-pointer items-center justify-center gap-2 rounded-[0.8rem] bg-gold px-5 text-[0.75rem] font-black text-[var(--kora)] shadow-[0_10px_22px_rgba(246,176,0,0.18)] transition hover:-translate-y-0.5 hover:bg-[#ffc329] disabled:cursor-wait disabled:opacity-70"
                >
                    <Search className="size-4" />
                    {processing ? 'Recherche…' : 'Rechercher'}
                </button>
            </div>
            <div className="segment-note">
                <span>Vente au guichet</span>
                <strong>Disponibilité en temps réel</strong>
                <small>
                    Les places sont vérifiées sur chaque segment du trajet.
                </small>
            </div>
        </form>
    );
}
