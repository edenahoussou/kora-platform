import { router } from '@inertiajs/react';
import { Filter, X } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';

export type FilterOption = {
    value: string;
    label: string;
};

export type FilterConfig = {
    name: string;
    label: string;
    options: FilterOption[];
    placeholder?: string;
};

type KoraFilterBarProps = {
    searchName?: string;
    searchPlaceholder?: string;
    filters: FilterConfig[];
    /** Base route URL for navigation (e.g. '/agencies'). */
    route: string;
};

/**
 * Reusable filter bar with URL-synced filters.
 *
 * Matches the maquette `.filterbar` style:
 * - Bordered card, white background, rounded 15px
 * - All controls on a single flex row
 * - Search takes flex:1, selects have min-width
 * - Filters are NOT applied immediately — user clicks "Filtrer"
 * - Filter values sync to URL query params for shareable URLs
 */
export function KoraFilterBar({
    searchName = 'q',
    searchPlaceholder = 'Rechercher…',
    filters,
    route: routeUrl,
}: KoraFilterBarProps) {
    const urlParams = useMemo(
        () => new URLSearchParams(window.location.search),
        [],
    );

    const [searchValue, setSearchValue] = useState(
        urlParams.get(searchName) ?? '',
    );
    const [filterValues, setFilterValues] = useState<Record<string, string>>(
        () => {
            const initial: Record<string, string> = {};

            for (const filter of filters) {
                initial[filter.name] = urlParams.get(filter.name) ?? '';
            }

            return initial;
        },
    );

    const hasActiveFilters = useMemo(() => {
        return searchValue.trim() !== ''
            || Object.values(filterValues).some((v) => v !== '');
    }, [searchValue, filterValues]);

    const buildUrl = useCallback(
        (search: string, values: Record<string, string>): string => {
            const params = new URLSearchParams();

            if (search.trim()) {
                params.set(searchName, search.trim());
            }

            for (const filter of filters) {
                const val = values[filter.name];

                if (val && val !== '') {
                    params.set(filter.name, val);
                }
            }

            const query = params.toString();

            return query ? `${routeUrl}?${query}` : routeUrl;
        },
        [filters, routeUrl, searchName],
    );

    const applyFilters = () => {
        const url = buildUrl(searchValue, filterValues);
        router.get(url, {}, { preserveScroll: true, preserveState: true });
    };

    const resetFilters = () => {
        setSearchValue('');
        const cleared: Record<string, string> = {};

        for (const filter of filters) {
            cleared[filter.name] = '';
        }

        setFilterValues(cleared);
        router.get(routeUrl, {}, { preserveScroll: true, preserveState: true });
    };

    const handleKeyDown = (event: React.KeyboardEvent) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            applyFilters();
        }
    };

    return (
        <div
            className="kora-filterbar mb-[14px] flex flex-col items-stretch gap-[9px] rounded-[0.9375rem] border border-border bg-card p-[13px] sm:flex-row sm:items-center"
            onKeyDown={handleKeyDown}
        >
            <input
                type="search"
                value={searchValue}
                onChange={(event) => setSearchValue(event.target.value)}
                placeholder={searchPlaceholder}
                className="h-10 min-w-0 flex-1 rounded-[0.625rem] border border-border bg-background px-3 text-xs text-foreground outline-none transition-[border-color,box-shadow] placeholder:text-muted-foreground focus:border-gold focus:ring-[3px] focus:ring-[rgba(246,176,0,0.16)]"
            />
            {filters.map((filter) => (
                <select
                    key={filter.name}
                    value={filterValues[filter.name] ?? ''}
                    onChange={(event) =>
                        setFilterValues((prev) => ({
                            ...prev,
                            [filter.name]: event.target.value,
                        }))
                    }
                    className="h-10 min-w-[10rem] rounded-[0.625rem] border border-border bg-background px-3 text-xs text-foreground outline-none transition-[border-color,box-shadow] focus:border-gold focus:ring-[3px] focus:ring-[rgba(246,176,0,0.16)]"
                >
                    <option value="">
                        {filter.placeholder ?? `Tous les ${filter.label.toLowerCase()}`}
                    </option>
                    {filter.options.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
            ))}
            <Button
                type="button"
                onClick={applyFilters}
                className="h-10 shrink-0 rounded-[0.625rem] bg-gold px-4 font-medium text-[var(--kora)] shadow-[0_6px_18px_rgba(246,176,0,0.18)] hover:bg-[var(--kora)] hover:text-white"
            >
                <Filter className="size-4" />
                Filtrer
            </Button>
            {hasActiveFilters && (
                <Button
                    type="button"
                    variant="ghost"
                    onClick={resetFilters}
                    className="h-10 shrink-0 rounded-[0.625rem] px-3 font-medium text-muted-foreground hover:text-foreground"
                >
                    <X className="size-4" />
                    Réinitialiser
                </Button>
            )}
        </div>
    );
}
