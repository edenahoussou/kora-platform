import { router, usePage } from '@inertiajs/react';
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
    /** 'select' (default) renders a dropdown; 'date' renders a native date input. */
    type?: 'select' | 'date';
    options: FilterOption[];
    placeholder?: string;
};

type KoraFilterBarProps = {
    appearance?: 'default' | 'compact';
    searchName?: string;
    searchPlaceholder?: string;
    filters: FilterConfig[];
    /** Base route URL for navigation (e.g. '/agencies'). */
    route: string;
    /** Fallback values used when the URL carries no param (e.g. today's date). */
    initialValues?: Record<string, string>;
    /** Optional label block rendered before the search input (maquette .filter-label). */
    label?: { caption: string; value: string };
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
    appearance = 'default',
    searchName = 'q',
    searchPlaceholder = 'Rechercher…',
    filters,
    route: routeUrl,
    initialValues,
    label,
}: KoraFilterBarProps) {
    // Inertia's own page.url (path + query string) is available identically
    // on the server and the client, unlike window.location which only
    // exists in the browser — this is what makes the filter bar SSR-safe
    // and avoids a hydration mismatch between the two renders.
    const { url: currentUrl } = usePage();
    const urlParams = useMemo(() => {
        const queryString = currentUrl.includes('?')
            ? currentUrl.slice(currentUrl.indexOf('?') + 1)
            : '';

        return new URLSearchParams(queryString);
    }, [currentUrl]);

    const [searchValue, setSearchValue] = useState(
        urlParams.get(searchName) ?? '',
    );
    const [filterValues, setFilterValues] = useState<Record<string, string>>(
        () => {
            const initial: Record<string, string> = {};

            for (const filter of filters) {
                initial[filter.name] =
                    urlParams.get(filter.name) ??
                    initialValues?.[filter.name] ??
                    '';
            }

            return initial;
        },
    );

    const hasActiveFilters = useMemo(() => {
        return (
            searchValue.trim() !== '' ||
            Object.entries(filterValues).some(
                ([name, v]) => v !== (initialValues?.[name] ?? ''),
            )
        );
    }, [searchValue, filterValues, initialValues]);

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
            cleared[filter.name] = initialValues?.[filter.name] ?? '';
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
            {label && (
                <div className="flex shrink-0 flex-col justify-center px-1">
                    <span className="text-[0.5625rem] font-semibold tracking-[0.08em] text-muted-foreground uppercase">
                        {label.caption}
                    </span>
                    <strong className="text-[0.8125rem] font-bold text-foreground capitalize">
                        {label.value}
                    </strong>
                </div>
            )}
            <input
                type="search"
                value={searchValue}
                onChange={(event) => setSearchValue(event.target.value)}
                placeholder={searchPlaceholder}
                className="h-10 min-w-0 flex-1 rounded-[0.625rem] border border-border bg-background px-3 text-xs text-foreground transition-[border-color,box-shadow] outline-none placeholder:text-muted-foreground focus:border-gold focus:ring-[3px] focus:ring-[rgba(246,176,0,0.16)]"
            />
            {filters.map((filter) =>
                filter.type === 'date' ? (
                    <input
                        key={filter.name}
                        type="date"
                        value={filterValues[filter.name] ?? ''}
                        onChange={(event) =>
                            setFilterValues((prev) => ({
                                ...prev,
                                [filter.name]: event.target.value,
                            }))
                        }
                        title={filter.label}
                        className="h-10 min-w-[10rem] rounded-[0.625rem] border border-border bg-background px-3 text-xs text-foreground transition-[border-color,box-shadow] outline-none focus:border-gold focus:ring-[3px] focus:ring-[rgba(246,176,0,0.16)]"
                    />
                ) : (
                    <select
                        key={filter.name}
                        value={filterValues[filter.name] ?? ''}
                        onChange={(event) =>
                            setFilterValues((prev) => ({
                                ...prev,
                                [filter.name]: event.target.value,
                            }))
                        }
                        className="h-10 min-w-[10rem] rounded-[0.625rem] border border-border bg-background px-3 text-xs text-foreground transition-[border-color,box-shadow] outline-none focus:border-gold focus:ring-[3px] focus:ring-[rgba(246,176,0,0.16)]"
                    >
                        <option value="">
                            {filter.placeholder ??
                                `Tous les ${filter.label.toLowerCase()}`}
                        </option>
                        {filter.options.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                ),
            )}
            {appearance === 'compact' ? (
                <Button
                    type="button"
                    onClick={applyFilters}
                    variant="kora"
                    size="kora"
                    className="shrink-0"
                >
                    <Filter className="size-4" />
                    Filtrer
                </Button>
            ) : (
                <button
                    type="button"
                    onClick={applyFilters}
                    className="btn btn-navy cash-filter-submit shrink-0"
                >
                    <Filter className="size-4" />
                    Filtrer
                </button>
            )}
            {hasActiveFilters &&
                (appearance === 'compact' ? (
                    <Button
                        type="button"
                        variant="kora-secondary"
                        size="kora"
                        onClick={resetFilters}
                        className="shrink-0"
                    >
                        <X className="size-4" />
                        Réinitialiser
                    </Button>
                ) : (
                    <button
                        type="button"
                        onClick={resetFilters}
                        className="btn btn-ghost cash-filter-reset shrink-0"
                    >
                        <X className="size-4" />
                        Réinitialiser
                    </button>
                ))}
        </div>
    );
}
