import { ArrowUpDown, Search } from 'lucide-react';
import type { SyntheticEvent } from 'react';
import type { SaleCity, SaleSearch } from './sale-types';

type Props = {
    cities: SaleCity[];
    value: SaleSearch;
    processing: boolean;
    isRoundTrip?: boolean;
    returnDate?: string | null;
    onChange: (value: SaleSearch) => void;
    onRoundTripChange?: (enabled: boolean) => void;
    onReturnDateChange?: (date: string | null) => void;
    onSubmit: (event: SyntheticEvent<HTMLFormElement>) => void;
};

export function RouteSearch({
    cities,
    value,
    processing,
    isRoundTrip = false,
    returnDate = null,
    onChange,
    onRoundTripChange,
    onReturnDateChange,
    onSubmit,
}: Props) {
    const update = (key: keyof SaleSearch, nextValue: string) =>
        onChange({ ...value, [key]: nextValue || null });

    return (
        <form onSubmit={onSubmit} className="search-route premium-route">
            <div className="route-caption">
                <span className="section-kicker">Itinéraire par segment</span>
                <strong>Où voyage le passager ?</strong>
            </div>
            <div className="grid gap-3 xl:grid-cols-[minmax(0,1fr)_2.2rem_minmax(0,1fr)_minmax(8.5rem,0.8fr)_auto] xl:items-end">
                <label className="grid gap-1.5 text-[0.625rem] font-extrabold text-white/60">
                    Ligne et montée
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
                    <ArrowUpDown className="size-4" />
                </button>
                <label className="grid gap-1.5 text-[0.625rem] font-extrabold text-white/60">
                    Descente
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
                    Date du voyage (optionnel)
                    <input
                        type="date"
                        value={value.date ?? ''}
                        onChange={(event) => update('date', event.target.value)}
                        className="h-12 w-full rounded-[0.8rem] border border-white/15 bg-white/10 px-3 text-[0.75rem] font-bold text-white outline-none focus:border-gold focus:ring-2 focus:ring-gold/30"
                    />
                </label>
                <button
                    type="submit"
                    disabled={processing}
                    className="inline-flex h-12 items-center justify-center gap-2 rounded-[0.8rem] bg-gold px-5 text-[0.75rem] font-black text-[var(--kora)] shadow-[0_10px_22px_rgba(246,176,0,0.18)] transition hover:-translate-y-0.5 hover:bg-[#ffc329] disabled:cursor-wait disabled:opacity-70"
                >
                    <Search className="size-4" />
                    {processing ? 'Recherche…' : 'Rechercher'}
                </button>
            </div>
            {onRoundTripChange && (
                <div className="round-trip-toggle">
                    <label className="round-trip-label">
                        <input
                            type="checkbox"
                            checked={isRoundTrip}
                            onChange={(e) => onRoundTripChange(e.target.checked)}
                            className="round-trip-checkbox"
                        />
                        Aller-retour
                    </label>
                    {isRoundTrip && onReturnDateChange && (
                        <label className="grid gap-1.5 text-[0.625rem] font-extrabold text-white/60">
                            Date de retour (optionnel)
                            <input
                                type="date"
                                value={returnDate ?? ''}
                                onChange={(event) =>
                                    onReturnDateChange(
                                        event.target.value || null,
                                    )
                                }
                                className="h-12 w-full rounded-[0.8rem] border border-white/15 bg-white/10 px-3 text-[0.75rem] font-bold text-white outline-none focus:border-gold focus:ring-2 focus:ring-gold/30"
                            />
                        </label>
                    )}
                </div>
            )}
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
