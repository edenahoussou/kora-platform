import {
    Armchair,
    Check,
    Droplets,
    Luggage,
    MapPin,
    Plug,
    Search,
    ShieldCheck,
    Snowflake,
    Sparkles,
    Tv,
    Utensils,
    Wifi,
    X,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export type AmenityOption = {
    id: number;
    code: string;
    label: string;
    icon: string | null;
};

interface BusAmenityPickerProps {
    amenities: AmenityOption[];
    selectedIds: number[];
    onChange: (ids: number[]) => void;
    className?: string;
}

const iconMap: Record<string, LucideIcon> = {
    wifi: Wifi,
    snowflake: Snowflake,
    air_conditioning: Snowflake,
    armchair: Armchair,
    reclining_seats: Armchair,
    plug: Plug,
    usb: Plug,
    tv: Tv,
    utensils: Utensils,
    droplets: Droplets,
    luggage: Luggage,
    'shield-check': ShieldCheck,
    'map-pin': MapPin,
};

export function BusAmenityPicker({
    amenities,
    selectedIds,
    onChange,
    className,
}: BusAmenityPickerProps) {
    const [search, setSearch] = useState('');

    const filteredAmenities = useMemo(() => {
        if (!search.trim()) {
            return amenities;
        }

        const q = search.toLowerCase().trim();

        return amenities.filter(
            (a) =>
                a.label.toLowerCase().includes(q) ||
                a.code.toLowerCase().includes(q),
        );
    }, [amenities, search]);

    const toggle = (id: number) => {
        if (selectedIds.includes(id)) {
            onChange(selectedIds.filter((item) => item !== id));
        } else {
            onChange([...selectedIds, id]);
        }
    };

    const selectAll = () => {
        const allIds = Array.from(
            new Set([...selectedIds, ...filteredAmenities.map((a) => a.id)]),
        );
        onChange(allIds);
    };

    const deselectAll = () => {
        if (search.trim()) {
            const filteredSet = new Set(filteredAmenities.map((a) => a.id));
            onChange(selectedIds.filter((id) => !filteredSet.has(id)));
        } else {
            onChange([]);
        }
    };

    return (
        <div className={cn('space-y-2.5', className)}>
            {/* Hidden inputs to guarantee standard form payload submission */}
            {selectedIds.map((id) => (
                <input
                    key={`hidden-amenity-${id}`}
                    type="hidden"
                    name="amenity_ids[]"
                    value={id}
                />
            ))}

            {/* Header & Controls */}
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-2">
                    <span className="text-xs font-semibold text-muted-foreground">
                        Équipements à bord :
                    </span>
                    <Badge
                        variant="secondary"
                        className={cn(
                            'font-mono text-[11px] font-bold transition-colors',
                            selectedIds.length > 0
                                ? 'bg-gold/20 text-[var(--kora)] ring-1 ring-gold/40 dark:text-gold'
                                : 'text-muted-foreground',
                        )}
                    >
                        {selectedIds.length} sélectionné
                        {selectedIds.length > 1 ? 's' : ''}
                    </Badge>
                </div>

                <div className="flex items-center gap-1.5 text-xs">
                    <button
                        type="button"
                        onClick={selectAll}
                        className="rounded px-2 py-1 font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    >
                        Tout cocher
                    </button>
                    <span className="text-border">|</span>
                    <button
                        type="button"
                        onClick={deselectAll}
                        disabled={selectedIds.length === 0}
                        className="rounded px-2 py-1 font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:opacity-40"
                    >
                        Effacer
                    </button>
                </div>
            </div>

            {/* Quick search if > 6 amenities */}
            {amenities.length > 6 && (
                <div className="relative">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Rechercher une commodité (ex: wifi, clim)…"
                        className="h-9 rounded-lg border-0 bg-muted/70 pl-8.5 text-xs shadow-[inset_0_0_0_1px_var(--border)] focus-visible:bg-background"
                    />
                    {search && (
                        <button
                            type="button"
                            onClick={() => setSearch('')}
                            className="absolute top-1/2 right-2.5 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            aria-label="Effacer le filtre"
                        >
                            <X className="size-3.5" />
                        </button>
                    )}
                </div>
            )}

            {/* Interactive chip grid with constrained max-height */}
            <div className="max-h-48 overflow-y-auto rounded-[0.875rem] bg-muted/40 p-2.5 shadow-[inset_0_0_0_1px_var(--border)]">
                {filteredAmenities.length === 0 ? (
                    <div className="p-4 text-center text-xs text-muted-foreground">
                        Aucune commodité ne correspond à votre recherche.
                    </div>
                ) : (
                    <div className="flex flex-wrap gap-1.5">
                        {filteredAmenities.map((amenity) => {
                            const isSelected = selectedIds.includes(amenity.id);
                            const IconComponent =
                                (amenity.icon && iconMap[amenity.icon]) ||
                                (iconMap[amenity.code] ?? Sparkles);

                            return (
                                <button
                                    key={amenity.id}
                                    type="button"
                                    onClick={() => toggle(amenity.id)}
                                    aria-pressed={isSelected}
                                    className={cn(
                                        'group inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium transition-all duration-150 select-none',
                                        isSelected
                                            ? 'bg-gold/20 font-semibold text-foreground shadow-xs ring-1 ring-gold dark:bg-gold/25'
                                            : 'border border-border/80 bg-background/80 text-muted-foreground hover:border-gold/50 hover:bg-background hover:text-foreground',
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'grid size-4 place-items-center rounded-full transition-colors',
                                            isSelected
                                                ? 'bg-gold text-[var(--kora)]'
                                                : 'text-muted-foreground group-hover:text-foreground',
                                        )}
                                    >
                                        {isSelected ? (
                                            <Check className="size-3 stroke-[3]" />
                                        ) : (
                                            <IconComponent className="size-3" />
                                        )}
                                    </span>
                                    <span>{amenity.label}</span>
                                </button>
                            );
                        })}
                    </div>
                )}
            </div>
        </div>
    );
}
