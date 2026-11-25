import {
    ArrowRight,
    Building2,
    CalendarDays,
    ChevronDown,
    MapPin,
    QrCode,
    Search,
    ShieldCheck,
    Store,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { cn } from '@/lib/utils';
import type { ParcelHub } from './types';

interface Props {
    originHub: ParcelHub;
    destinationHubs: ParcelHub[];
    mode: 'new' | 'lookup';
    onModeChange: (mode: 'new' | 'lookup') => void;
    selectedDestination: ParcelHub | null;
    onSelectDestination: (hub: ParcelHub | null) => void;
    dispatchWindow: 'today' | 'tomorrow' | 'all';
    onDispatchWindowChange: (window: 'today' | 'tomorrow' | 'all') => void;
    lookupQuery: string;
    onLookupQueryChange: (query: string) => void;
    pendingCount: number;
    destinationInputRef?: React.RefObject<HTMLInputElement | null>;
    lookupInputRef?: React.RefObject<HTMLInputElement | null>;
}

const DISPATCH_WINDOWS: Array<{
    value: 'today' | 'tomorrow' | 'all';
    label: string;
}> = [
    { value: 'today', label: "Aujourd'hui" },
    { value: 'tomorrow', label: 'Demain' },
    { value: 'all', label: 'Tous' },
];

export function ParcelCounterHero({
    originHub,
    destinationHubs,
    mode,
    onModeChange,
    selectedDestination,
    onSelectDestination,
    dispatchWindow,
    onDispatchWindowChange,
    lookupQuery,
    onLookupQueryChange,
    pendingCount,
    destinationInputRef,
    lookupInputRef,
}: Props) {
    const [destQuery, setDestQuery] = useState('');
    const [dropdownOpen, setDropdownOpen] = useState(false);
    const [highlightedIndex, setHighlightedIndex] = useState(0);
    const containerRef = useRef<HTMLDivElement>(null);

    const frequentHubs = useMemo(
        () => destinationHubs.filter((h) => h.isFrequent),
        [destinationHubs],
    );

    const filteredHubs = useMemo(() => {
        const q = destQuery.trim().toLowerCase();

        if (!q) {
            return destinationHubs;
        }

        return destinationHubs.filter(
            (hub) =>
                hub.city.toLowerCase().includes(q) ||
                hub.name.toLowerCase().includes(q) ||
                hub.code.toLowerCase().includes(q) ||
                hub.region.toLowerCase().includes(q),
        );
    }, [destQuery, destinationHubs]);

    // Close dropdown on click outside
    useEffect(() => {
        const handleClickOutside = (e: MouseEvent) => {
            if (
                containerRef.current &&
                !containerRef.current.contains(e.target as Node)
            ) {
                setDropdownOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);

        return () =>
            document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const chooseHub = (hub: ParcelHub) => {
        onSelectDestination(hub);
        setDestQuery('');
        setDropdownOpen(false);
    };

    const handleComboboxKeyDown = (
        e: React.KeyboardEvent<HTMLInputElement>,
    ) => {
        if (!dropdownOpen && (e.key === 'ArrowDown' || e.key === 'Enter')) {
            setDropdownOpen(true);

            return;
        }

        if (!dropdownOpen) {
            return;
        }

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setHighlightedIndex((prev) =>
                Math.min(prev + 1, Math.max(0, filteredHubs.length - 1)),
            );
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setHighlightedIndex((prev) => Math.max(prev - 1, 0));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const target = filteredHubs[highlightedIndex];

            if (target) {
                chooseHub(target);
            }
        } else if (e.key === 'Escape') {
            setDropdownOpen(false);
        }
    };

    return (
        <section className="search-route premium-route kora-sale-parcel-chrome">
            {/* Top Row: Caption + Dual-mode pills */}
            <div className="mb-4 flex flex-wrap items-start justify-between gap-4">
                <div className="route-caption mb-0">
                    <span className="section-kicker text-gold!">
                        Expédition &amp; Réseau Interurbain
                    </span>
                    <strong className="mt-1 text-lg text-white sm:text-xl">
                        {mode === 'new'
                            ? 'Où expédiez-vous ce colis ?'
                            : 'Finaliser un colis pré-enregistré'}
                    </strong>
                </div>

                {/* Mode switcher styled natively like .trip-type-segmented */}
                <div
                    role="tablist"
                    aria-label="Mode du guichet colis"
                    className="trip-type-segmented mb-0! border-white/15! bg-white/10!"
                >
                    <button
                        type="button"
                        role="tab"
                        aria-selected={mode === 'new'}
                        onClick={() => onModeChange('new')}
                        className={cn(
                            'trip-type-pill flex items-center gap-1.5 text-white/75! hover:text-white!',
                            mode === 'new' && 'is-active text-kora!',
                        )}
                    >
                        <span>Nouvel envoi</span>
                        <kbd className="hidden rounded bg-black/15 px-1.5 py-0.5 font-mono text-[9px] font-bold sm:inline-block">
                            Alt+N
                        </kbd>
                    </button>
                    <button
                        type="button"
                        role="tab"
                        aria-selected={mode === 'lookup'}
                        onClick={() => onModeChange('lookup')}
                        className={cn(
                            'trip-type-pill flex items-center gap-1.5 text-white/75! hover:text-white!',
                            mode === 'lookup' && 'is-active text-kora!',
                        )}
                    >
                        <span>Pré-enregistrement</span>
                        {pendingCount > 0 && (
                            <span
                                className={cn(
                                    'inline-flex size-4 items-center justify-center rounded-full text-[9px] font-black',
                                    mode === 'lookup'
                                        ? 'bg-kora text-gold'
                                        : 'bg-gold text-kora',
                                )}
                            >
                                {pendingCount}
                            </span>
                        )}
                        <kbd className="hidden rounded bg-black/15 px-1.5 py-0.5 font-mono text-[9px] font-bold sm:inline-block">
                            Alt+R
                        </kbd>
                    </button>
                </div>
            </div>

            {mode === 'new' ? (
                <div className="space-y-3">
                    {/* Origin -> Destination Combobox -> Window */}
                    <div className="grid gap-3 xl:grid-cols-[minmax(0,0.9fr)_2rem_minmax(0,1.4fr)_auto]">
                        {/* Origin (locked to connected hub) */}
                        <div className="grid gap-1.5">
                            <span className="flex items-center gap-1.5 text-[0.625rem] font-extrabold text-white/60 uppercase">
                                <MapPin className="size-3.5 text-gold" />
                                Agence / Point de départ
                            </span>
                            <div className="flex h-12 items-center justify-between gap-2 rounded-[0.8rem] border border-white/15 bg-white/10 px-3 text-white">
                                <div className="min-w-0">
                                    <p className="truncate text-xs font-bold text-white">
                                        {originHub.city} · {originHub.name}
                                    </p>
                                    <p className="truncate text-[10px] text-white/60">
                                        {originHub.code} · Site actif
                                    </p>
                                </div>
                                <span className="inline-flex shrink-0 items-center gap-1 rounded-md bg-emerald-400/20 px-1.5 py-0.5 text-[9px] font-bold text-emerald-300">
                                    <ShieldCheck className="size-3" />
                                    Vérifié
                                </span>
                            </div>
                        </div>

                        {/* Arrow connector */}
                        <div className="hidden h-12 items-center justify-center self-end text-gold xl:flex">
                            <ArrowRight className="size-4" />
                        </div>

                        {/* Scalable Destination Combobox (27+ stops) */}
                        <div
                            ref={containerRef}
                            className="relative grid gap-1.5"
                        >
                            <span className="flex items-center justify-between text-[0.625rem] font-extrabold text-white/60 uppercase">
                                <span className="flex items-center gap-1.5">
                                    <MapPin className="size-3.5 text-gold" />
                                    Destination &amp; point de retrait{' '}
                                    <span className="required-asterisk">*</span>
                                </span>
                                <kbd className="py-0.2 rounded bg-white/10 px-1.5 font-mono text-[9px] text-white/75">
                                    Alt+D
                                </kbd>
                            </span>

                            <div className="relative">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-gold" />
                                <input
                                    ref={destinationInputRef}
                                    type="text"
                                    value={
                                        dropdownOpen
                                            ? destQuery
                                            : selectedDestination
                                              ? `${selectedDestination.city} — ${selectedDestination.name}`
                                              : destQuery
                                    }
                                    onFocus={() => {
                                        setDropdownOpen(true);
                                        setHighlightedIndex(0);
                                    }}
                                    onChange={(e) => {
                                        setDestQuery(e.target.value);
                                        setDropdownOpen(true);
                                        setHighlightedIndex(0);
                                    }}
                                    onKeyDown={handleComboboxKeyDown}
                                    placeholder={`Rechercher parmi ${destinationHubs.length} arrêts, agences ou points relais...`}
                                    className="h-12 w-full rounded-[0.8rem] border border-white/20 bg-white/12 pr-14 pl-9 text-xs font-bold text-white placeholder:font-normal placeholder:text-white/55 focus:border-gold focus:bg-white/15 focus:ring-2 focus:ring-gold/30 focus:outline-none"
                                />
                                <div className="absolute top-1/2 right-2 flex -translate-y-1/2 items-center gap-1">
                                    {selectedDestination && !dropdownOpen && (
                                        <button
                                            type="button"
                                            onClick={() => {
                                                onSelectDestination(null);
                                                setDestQuery('');
                                                setDropdownOpen(true);
                                                destinationInputRef?.current?.focus();
                                            }}
                                            aria-label="Effacer la destination"
                                            className="rounded-md p-1 text-white/60 hover:bg-white/15 hover:text-white"
                                        >
                                            <X className="size-3.5" />
                                        </button>
                                    )}
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setDropdownOpen((open) => !open)
                                        }
                                        aria-label="Afficher la liste des destinations"
                                        className="rounded-md p-1 text-white/70 hover:bg-white/15 hover:text-white"
                                    >
                                        <ChevronDown
                                            className={cn(
                                                'size-4 transition-transform duration-150',
                                                dropdownOpen && 'rotate-180',
                                            )}
                                        />
                                    </button>
                                </div>

                                {/* Dropdown List */}
                                {dropdownOpen && (
                                    <div className="absolute right-0 left-0 z-50 mt-2 max-h-72 overflow-y-auto rounded-2xl border border-border bg-card p-1.5 text-foreground shadow-2xl">
                                        <div className="flex items-center justify-between px-2.5 py-1.5 text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                            <span>
                                                {filteredHubs.length} point(s)
                                                trouvé(s)
                                            </span>
                                            <span>↑↓ Naviguer · ↵ Valider</span>
                                        </div>

                                        {filteredHubs.length === 0 ? (
                                            <div className="px-3 py-6 text-center text-xs text-muted-foreground">
                                                Aucune destination trouvée pour
                                                « {destQuery} ».
                                            </div>
                                        ) : (
                                            filteredHubs.map((hub, idx) => {
                                                const isActive =
                                                    idx === highlightedIndex;
                                                const isSelected =
                                                    selectedDestination?.uuid ===
                                                    hub.uuid;

                                                return (
                                                    <button
                                                        key={hub.uuid}
                                                        type="button"
                                                        onMouseEnter={() =>
                                                            setHighlightedIndex(
                                                                idx,
                                                            )
                                                        }
                                                        onClick={() =>
                                                            chooseHub(hub)
                                                        }
                                                        className={cn(
                                                            'flex w-full cursor-pointer items-center justify-between gap-3 rounded-xl px-3 py-2 text-left transition-colors',
                                                            isActive
                                                                ? 'bg-gold/15 text-foreground'
                                                                : 'hover:bg-muted/70',
                                                            isSelected &&
                                                                'ring-1 ring-gold',
                                                        )}
                                                    >
                                                        <div className="flex min-w-0 items-center gap-2.5">
                                                            <span
                                                                className={cn(
                                                                    'flex size-7 shrink-0 items-center justify-center rounded-lg text-xs',
                                                                    hub.type ===
                                                                        'agency'
                                                                        ? 'bg-kora text-gold'
                                                                        : 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
                                                                )}
                                                            >
                                                                {hub.type ===
                                                                'agency' ? (
                                                                    <Building2 className="size-3.5" />
                                                                ) : (
                                                                    <Store className="size-3.5" />
                                                                )}
                                                            </span>
                                                            <div className="min-w-0">
                                                                <p className="truncate text-xs font-bold text-foreground">
                                                                    {hub.city}{' '}
                                                                    <span className="font-normal text-muted-foreground">
                                                                        —{' '}
                                                                        {
                                                                            hub.name
                                                                        }
                                                                    </span>
                                                                </p>
                                                                <p className="truncate text-[10px] text-muted-foreground">
                                                                    {hub.region}{' '}
                                                                    ·{' '}
                                                                    {
                                                                        hub.lineCode
                                                                    }
                                                                </p>
                                                            </div>
                                                        </div>

                                                        <div className="flex shrink-0 items-center gap-1.5">
                                                            <span className="rounded bg-muted px-1.5 py-0.5 font-mono text-[9px] font-bold text-muted-foreground">
                                                                {hub.code}
                                                            </span>
                                                            <span
                                                                className={cn(
                                                                    'rounded-full px-2 py-0.5 text-[9px] font-bold',
                                                                    hub.type ===
                                                                        'agency'
                                                                        ? 'bg-kora/10 text-kora dark:bg-gold/15 dark:text-gold'
                                                                        : 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
                                                                )}
                                                            >
                                                                {hub.type ===
                                                                'agency'
                                                                    ? 'Agence'
                                                                    : 'KORA POINT'}
                                                            </span>
                                                        </div>
                                                    </button>
                                                );
                                            })
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Dispatch Window Filter (Today / Tomorrow / All) */}
                        <div className="grid gap-1.5">
                            <span className="flex items-center gap-1.5 text-[0.625rem] font-extrabold text-white/60 uppercase">
                                <CalendarDays className="size-3.5 text-gold" />
                                Fenêtre d&apos;expédition
                            </span>
                            <div className="flex h-12 items-center rounded-[0.8rem] border border-white/15 bg-white/10 p-1">
                                {DISPATCH_WINDOWS.map((opt) => (
                                    <button
                                        key={opt.value}
                                        type="button"
                                        onClick={() =>
                                            onDispatchWindowChange(opt.value)
                                        }
                                        className={cn(
                                            'h-full flex-1 cursor-pointer rounded-[0.55rem] px-3 text-xs font-bold transition-all',
                                            dispatchWindow === opt.value
                                                ? 'bg-gold text-kora shadow-xs'
                                                : 'text-white/75 hover:text-white',
                                        )}
                                    >
                                        {opt.label}
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Frequent Destinations Quick Strip */}
                    <div className="flex flex-wrap items-center gap-2 pt-1">
                        <span className="text-[10px] font-bold tracking-wider text-white/55 uppercase">
                            Axes fréquents :
                        </span>
                        {frequentHubs.map((hub) => {
                            const active =
                                selectedDestination?.uuid === hub.uuid;

                            return (
                                <button
                                    key={hub.uuid}
                                    type="button"
                                    onClick={() => chooseHub(hub)}
                                    className={cn(
                                        'cursor-pointer rounded-full border px-2.5 py-1 text-[11px] font-bold transition-all',
                                        active
                                            ? 'border-gold bg-gold text-kora shadow-xs'
                                            : 'border-white/15 bg-white/8 text-white/85 hover:border-gold/60 hover:bg-white/15',
                                    )}
                                >
                                    {hub.city}
                                </button>
                            );
                        })}
                        <span className="ml-auto hidden text-[10px] text-white/55 sm:inline">
                            {destinationHubs.length} destinations actives sur le
                            réseau
                        </span>
                    </div>
                </div>
            ) : (
                /* Lookup Mode inside the Navy Hero */
                <div className="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto]">
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-gold" />
                        <input
                            ref={lookupInputRef}
                            type="text"
                            value={lookupQuery}
                            onChange={(e) =>
                                onLookupQueryChange(e.target.value)
                            }
                            placeholder="Scannez le QR Code client ou saisissez la référence (ex: KC-20261125-4812), le nom ou le téléphone..."
                            className="h-12 w-full rounded-[0.8rem] border border-white/20 bg-white/12 pr-4 pl-10 text-xs font-bold text-white placeholder:font-normal placeholder:text-white/55 focus:border-gold focus:bg-white/15 focus:ring-2 focus:ring-gold/30 focus:outline-none"
                        />
                    </div>
                    <button
                        type="button"
                        onClick={() => onLookupQueryChange('KC-20261125-4812')}
                        className="btn btn-primary h-12! px-5! text-xs!"
                    >
                        <QrCode className="size-4" />
                        <span>Simuler un Scan QR</span>
                    </button>
                </div>
            )}
        </section>
    );
}
