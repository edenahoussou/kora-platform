import { ArrowUpDown, Search } from 'lucide-react';
import type { FormEvent } from 'react';
import type { SaleCity, SaleSearch } from './sale-types';

type Props = {
    cities: SaleCity[];
    value: SaleSearch;
    processing: boolean;
    onChange: (value: SaleSearch) => void;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
};

export function RouteSearch({
    cities,
    value,
    processing,
    onChange,
    onSubmit,
}: Props) {
    const update = (key: keyof SaleSearch, nextValue: string) =>
        onChange({ ...value, [key]: nextValue || null });

    return (
        <form
            onSubmit={onSubmit}
            className="relative overflow-hidden rounded-[1.45rem] bg-[var(--kora)] p-5 text-white shadow-[0_22px_52px_rgba(0,29,78,0.18)] sm:p-6"
        >
            <div className="pointer-events-none absolute top-0 right-7 h-1 w-24 rounded-b-md bg-gold" />
            <div className="mb-5">
                <span className="block text-[0.625rem] font-black tracking-[0.16em] text-gold uppercase">
                    Itinéraire par segment
                </span>
                <strong className="mt-1.5 block text-[1.2rem] font-extrabold tracking-[-0.035em]">
                    Où voyage le passager ?
                </strong>
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
        </form>
    );
}
