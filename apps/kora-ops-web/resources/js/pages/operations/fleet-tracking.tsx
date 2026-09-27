import { Head, router, usePage } from '@inertiajs/react';
import {
    AlertOctagon,
    Bus,
    Clock,
    Gauge,
    Map as MapIcon,
    Radio,
    RefreshCw,
    Search,
    SquareSplitHorizontal,
    Table as TableIcon,
    X,
} from 'lucide-react';
import { lazy, Suspense, useEffect, useMemo, useState, useSyncExternalStore } from 'react';
import { toast } from 'sonner';
import { KoraTooltip } from '@/components/kora-tooltip';
import type { FleetMapCommand } from '@/components/tracking/fleet-map';
import { FleetSidePanel } from '@/components/tracking/fleet-side-panel';
import { FleetTrackingTable } from '@/components/tracking/fleet-tracking-table';
import { FleetVehicleDetail } from '@/components/tracking/fleet-vehicle-detail';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type {
    FleetTrackingPageProps,
    FleetTrackingSession,
    TrackingIncident,
} from '@/types/tracking';

// Leaflet touche `window` au chargement : la carte n'est jamais
// importée côté SSR — le chunk est résolu au premier rendu client.
const FleetMap = lazy(() =>
    import('@/components/tracking/fleet-map').then((module) => ({
        default: module.FleetMap,
    })),
);

export default function FleetTrackingPage({
    sessions = [],
    lines = [],
    agencies = [],
    canViewNetwork = false,
    filters: initialFilters = {},
    pollingIntervalMs = 15000,
}: FleetTrackingPageProps) {
    const { mapConfig } = usePage().props as any;

    // View mode: 'hybrid' | 'map' | 'table'
    const [viewMode, setViewMode] = useState<'hybrid' | 'map' | 'table'>(
        initialFilters.view || 'hybrid',
    );

    // Selected session for detail panel
    const [selectedSessionUuid, setSelectedSessionUuid] = useState<string | null>(null);
    const [detailOpen, setDetailOpen] = useState(false);

    // One-shot map command issued from the detail sheet
    const [mapCommand, setMapCommand] = useState<FleetMapCommand | null>(null);

    // Side panel collapse state
    const [sidePanelCollapsed, setSidePanelCollapsed] = useState(false);

    // Filter states
    const [searchQuery, setSearchQuery] = useState(initialFilters.q || '');
    const [selectedLine, setSelectedLine] = useState(initialFilters.line || 'all');
    const [selectedState, setSelectedState] = useState(initialFilters.state || 'all');
    const [selectedAgency, setSelectedAgency] = useState(initialFilters.agency || 'all');

    // Auto-refresh & Live tick
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [lastSyncTime, setLastSyncTime] = useState<Date>(new Date());

    // Dark mode detection
    const [isDarkMode, setIsDarkMode] = useState(false);

    // `false` côté serveur et à l'hydratation, `true` ensuite — garde la
    // carte (Leaflet) hors du graphe SSR sans risque de mismatch.
    const isHydrated = useSyncExternalStore(
        () => () => { },
        () => true,
        () => false,
    );

    useEffect(() => {
        const checkDark = () => {
            setIsDarkMode(document.documentElement.classList.contains('dark'));
        };

        checkDark();

        const observer = new MutationObserver(checkDark);

        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class'],
        });

        return () => observer.disconnect();
    }, []);

    // Auto-polling des positions réelles
    useEffect(() => {
        if (pollingIntervalMs <= 0) {
            return;
        }

        const interval = setInterval(() => {
            router.reload({
                only: ['sessions'],
                onStart: () => setIsRefreshing(true),
                onFinish: () => {
                    setIsRefreshing(false);
                    setLastSyncTime(new Date());
                },
            });
        }, pollingIntervalMs);

        return () => clearInterval(interval);
    }, [pollingIntervalMs]);

    // Manual Refresh
    const handleManualRefresh = () => {
        setIsRefreshing(true);

        router.reload({
            only: ['sessions'],
            onFinish: () => {
                setIsRefreshing(false);
                setLastSyncTime(new Date());
                toast.success('Données de flotte rafraîchies.');
            },
        });
    };

    // Filtered Sessions
    const filteredSessions = useMemo(() => {
        return sessions.filter((s) => {
            // Text search
            if (searchQuery.trim()) {
                const q = searchQuery.toLowerCase();
                const busMatch = s.bus.registration_number.toLowerCase().includes(q);
                const lineMatch =
                    s.departure.line_code?.toLowerCase().includes(q) ||
                    s.departure.line_name?.toLowerCase().includes(q) ||
                    s.departure.origin?.toLowerCase().includes(q) ||
                    s.departure.destination?.toLowerCase().includes(q);
                const condMatch = s.conductor.name.toLowerCase().includes(q);
                const termMatch = s.terminal.serial_number?.toLowerCase().includes(q);

                if (!busMatch && !lineMatch && !condMatch && !termMatch) {
                    return false;
                }
            }

            // Line filter
            if (selectedLine !== 'all' && s.departure.line_code !== selectedLine) {
                return false;
            }

            // State filter
            if (selectedState !== 'all' && s.state !== selectedState) {
                return false;
            }

            // Agency filter
            if (selectedAgency !== 'all' && s.agency?.uuid !== selectedAgency) {
                return false;
            }

            return true;
        });
    }, [sessions, searchQuery, selectedLine, selectedState, selectedAgency]);

    // KPIs calculation
    const kpis = useMemo(() => {
        const total = sessions.length;
        const live = sessions.filter((s) => s.state === 'live').length;
        const stale = sessions.filter((s) => s.state === 'stale').length;
        const alert = sessions.filter(
            (s) => s.state === 'gps_unavailable' || s.state === 'terminal_offline',
        ).length;

        const movingBuses = sessions.filter((s) => (s.position?.speed_kmh ?? 0) > 5);
        const avgSpeed =
            movingBuses.length > 0
                ? Math.round(
                    movingBuses.reduce(
                        (acc, s) => acc + (s.position?.speed_kmh ?? 0),
                        0,
                    ) / movingBuses.length,
                )
                : 0;

        return { total, live, stale, alert, avgSpeed };
    }, [sessions]);

    // Selection handlers
    const handleSelectSession = (session: FleetTrackingSession) => {
        setSelectedSessionUuid(session.session_uuid);
        setDetailOpen(true);
    };

    const handleSwitchToMapWithSession = (session?: FleetTrackingSession) => {
        if (session) {
            setSelectedSessionUuid(session.session_uuid);
        }

        setViewMode('hybrid');
    };

    const handleShowRoute = (session: FleetTrackingSession) => {
        setSelectedSessionUuid(session.session_uuid);
        setViewMode('hybrid');
        setMapCommand({ nonce: Date.now(), kind: 'fitRoute' });
        setDetailOpen(false);
    };

    const handleLocateIncident = (incident: TrackingIncident) => {
        if (incident.latitude === null || incident.longitude === null) {
            return;
        }

        setViewMode('hybrid');
        setMapCommand({
            nonce: Date.now(),
            kind: 'flyTo',
            latitude: incident.latitude,
            longitude: incident.longitude,
            zoom: 13,
        });
        setDetailOpen(false);
    };

    const selectedSession = useMemo(() => {
        return (
            sessions.find((s) => s.session_uuid === selectedSessionUuid) ?? null
        );
    }, [sessions, selectedSessionUuid]);

    const mapSkeleton = (
        <div className="flex h-full w-full items-center justify-center bg-muted/40">
            <MapIcon className="h-6 w-6 animate-pulse text-muted-foreground/40" />
        </div>
    );

    const renderFleetMap = (className: string) =>
        isHydrated ? (
            <Suspense fallback={mapSkeleton}>
                <FleetMap
                    sessions={filteredSessions}
                    selectedSessionUuid={selectedSessionUuid}
                    onSelectSession={handleSelectSession}
                    osmTileUrl={mapConfig?.osm?.tileUrl}
                    osmAttribution={mapConfig?.osm?.attribution}
                    isDarkMode={isDarkMode}
                    mapCommand={mapCommand}
                    onMapCommandHandled={() => setMapCommand(null)}
                    className={className}
                />
            </Suspense>
        ) : (
            mapSkeleton
        );

    return (
        <>
            <Head title="Suivi de flotte — Exploitation" />

            <div className="flex flex-col gap-5 min-h-[calc(100vh-8rem)]">
                {/* Top Command Bar */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2 text-[0.6875rem] font-bold text-muted-foreground uppercase tracking-widest mb-1.5">
                            <span>KORA OPS</span>
                            <span>/</span>
                            <span>Exploitation</span>
                            <span>/</span>
                            <span className="text-primary dark:text-gold">Suivi de flotte</span>
                        </div>
                        <h1 className="text-[1.8125rem] font-extrabold tracking-[-0.035em] text-foreground flex items-center gap-3">
                            Suivi de flotte
                        </h1>
                        <p className="mt-1 text-xs text-muted-foreground max-w-2xl">
                            Supervision télémétrique en direct des bus en circulation, de leur vitesse, cap et fraîcheur GPS.
                        </p>
                    </div>

                    {/* View Switcher & Action Controls */}
                    <div className="flex flex-wrap items-center gap-2.5">
                        {/* View Switcher Buttons */}
                        <div className="flex items-center rounded-xl border border-border/70 bg-card p-1 shadow-sm">
                            <button
                                type="button"
                                onClick={() => setViewMode('hybrid')}
                                className={cn(
                                    'rounded-lg p-1.5 text-xs font-bold transition-all flex items-center gap-1.5',
                                    viewMode === 'hybrid'
                                        ? 'bg-primary text-white shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                                title="Vue Hybride : Carte + Liste escamotable"
                            >
                                <SquareSplitHorizontal className="size-4" />
                                <span className="hidden md:inline">Hybride</span>
                            </button>

                            <button
                                type="button"
                                onClick={() => setViewMode('map')}
                                className={cn(
                                    'rounded-lg p-1.5 text-xs font-bold transition-all flex items-center gap-1.5',
                                    viewMode === 'map'
                                        ? 'bg-primary text-white shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                                title="Carte Plein Écran"
                            >
                                <MapIcon className="size-4" />
                                <span className="hidden md:inline">Carte</span>
                            </button>

                            <button
                                type="button"
                                onClick={() => setViewMode('table')}
                                className={cn(
                                    'rounded-lg p-1.5 text-xs font-bold transition-all flex items-center gap-1.5',
                                    viewMode === 'table'
                                        ? 'bg-primary text-white shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                                title="Tableau Opérations complet"
                            >
                                <TableIcon className="size-4" />
                                <span className="hidden md:inline">Tableau</span>
                            </button>
                        </div>

                        {/* Sync Time & Refresh Button */}
                        <div className="flex items-center gap-2">
                            <span
                                className="text-[0.625rem] text-muted-foreground hidden lg:inline font-mono"
                                suppressHydrationWarning
                            >
                                Synchro : {lastSyncTime.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                            </span>

                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleManualRefresh}
                                disabled={isRefreshing}
                                className="h-9 gap-1.5 rounded-xl border-border/70 px-3 text-xs font-bold"
                                title="Rafraîchir les positions"
                            >
                                <RefreshCw
                                    className={cn('size-3.5', isRefreshing && 'animate-spin text-primary')}
                                />
                                <span className="hidden sm:inline">Rafraîchir</span>
                            </Button>
                        </div>
                    </div>
                </div>

                {/* Real-time KPI Metric Grid */}
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    {/* Total Active */}
                    <KoraTooltip
                        side="bottom"
                        content={
                            <>
                                <span className="block font-black">En circulation</span>
                                Sessions de suivi actives : un bus par départ en
                                cours dont le terminal embarqué a ouvert une
                                session de tracking.
                            </>
                        }
                    >
                        <button
                            type="button"
                            onClick={() => setSelectedState('all')}
                            className={cn(
                                'w-full text-left rounded-2xl border p-4 transition-all duration-200 cursor-pointer',
                                selectedState === 'all'
                                    ? 'border-primary ring-2 ring-primary/20 bg-primary/5 dark:bg-primary/10 shadow-sm'
                                    : 'border-border/60 bg-card hover:border-border hover:shadow-sm',
                            )}
                        >
                            <div className="flex items-center justify-between text-muted-foreground mb-1">
                                <span className="text-[0.625rem] font-black uppercase tracking-wider">
                                    En circulation
                                </span>
                                <Bus className="size-4 text-primary" />
                            </div>
                            <div className="flex items-baseline gap-2">
                                <span className="text-2xl font-black font-mono tracking-tight text-foreground">
                                    {kpis.total}
                                </span>
                                <span className="text-[0.625rem] font-bold text-muted-foreground">
                                    bus actifs
                                </span>
                            </div>
                        </button>
                    </KoraTooltip>

                    {/* Live Fixes */}
                    <KoraTooltip
                        side="bottom"
                        content={
                            <>
                                <span className="block font-black">En direct</span>
                                Bus dont la dernière trame GPS a été reçue il y a
                                moins de 90 secondes — position considérée temps
                                réel.
                            </>
                        }
                    >
                        <button
                            type="button"
                            onClick={() => setSelectedState('live')}
                            className={cn(
                                'w-full text-left rounded-2xl border p-4 transition-all duration-200 cursor-pointer',
                                selectedState === 'live'
                                    ? 'border-emerald-500 ring-2 ring-emerald-500/20 bg-emerald-500/5 dark:bg-emerald-500/10 shadow-sm'
                                    : 'border-border/60 bg-card hover:border-border hover:shadow-sm',
                            )}
                        >
                            <div className="flex items-center justify-between text-muted-foreground mb-1">
                                <span className="text-[0.625rem] font-black uppercase tracking-wider">
                                    En direct (&lt; 90s)
                                </span>
                                <Radio className="size-4 text-emerald-500" />
                            </div>
                            <div className="flex items-baseline gap-2">
                                <span className="text-2xl font-black font-mono tracking-tight text-emerald-600 dark:text-emerald-400">
                                    {kpis.live}
                                </span>
                                <span className="text-[0.625rem] font-bold text-emerald-600/70 dark:text-emerald-400/70">
                                    optimaux
                                </span>
                            </div>
                        </button>
                    </KoraTooltip>

                    {/* Stale Positions */}
                    <KoraTooltip
                        side="bottom"
                        content={
                            <>
                                <span className="block font-black">Signal ancien</span>
                                Dernière trame GPS reçue il y a plus de 90 s — la
                                position affichée peut être en retard sur la
                                position réelle du bus.
                            </>
                        }
                    >
                        <button
                            type="button"
                            onClick={() => setSelectedState('stale')}
                            className={cn(
                                'w-full text-left rounded-2xl border p-4 transition-all duration-200 cursor-pointer',
                                selectedState === 'stale'
                                    ? 'border-amber-500 ring-2 ring-amber-500/20 bg-amber-500/5 dark:bg-amber-500/10 shadow-sm'
                                    : 'border-border/60 bg-card hover:border-border hover:shadow-sm',
                            )}
                        >
                            <div className="flex items-center justify-between text-muted-foreground mb-1">
                                <span className="text-[0.625rem] font-black uppercase tracking-wider">
                                    Signal ancien (&gt; 90s)
                                </span>
                                <Clock className="size-4 text-amber-500" />
                            </div>
                            <div className="flex items-baseline gap-2">
                                <span className="text-2xl font-black font-mono tracking-tight text-amber-600 dark:text-amber-400">
                                    {kpis.stale}
                                </span>
                                <span className="text-[0.625rem] font-bold text-amber-600/70 dark:text-amber-400/70">
                                    retardés
                                </span>
                            </div>
                        </button>
                    </KoraTooltip>

                    {/* GPS Alert / Offline */}
                    <KoraTooltip
                        side="bottom"
                        content={
                            <>
                                <span className="block font-black">Anomalies / Hors ligne</span>
                                Terminal P501 injoignable depuis plus de 5 min
                                (hors ligne) ou GPS sans fix satellite — bus à
                                contacter ou à surveiller.
                            </>
                        }
                    >
                        <button
                            type="button"
                            onClick={() => setSelectedState('gps_unavailable')}
                            className={cn(
                                'w-full text-left rounded-2xl border p-4 transition-all duration-200 cursor-pointer',
                                selectedState === 'gps_unavailable' || selectedState === 'terminal_offline'
                                    ? 'border-rose-500 ring-2 ring-rose-500/20 bg-rose-500/5 dark:bg-rose-500/10 shadow-sm'
                                    : 'border-border/60 bg-card hover:border-border hover:shadow-sm',
                            )}
                        >
                            <div className="flex items-center justify-between text-muted-foreground mb-1">
                                <span className="text-[0.625rem] font-black uppercase tracking-wider">
                                    Anomalies / Hors ligne
                                </span>
                                <AlertOctagon className="size-4 text-rose-500" />
                            </div>
                            <div className="flex items-baseline gap-2">
                                <span className="text-2xl font-black font-mono tracking-tight text-rose-600 dark:text-rose-400">
                                    {kpis.alert}
                                </span>
                                <span className="text-[0.625rem] font-bold text-rose-600/70 dark:text-rose-400/70">
                                    à surveiller
                                </span>
                            </div>
                        </button>
                    </KoraTooltip>

                    {/* Average Speed */}
                    <KoraTooltip
                        side="bottom"
                        content={
                            <>
                                <span className="block font-black">Vitesse moyenne de flotte</span>
                                Σ vitesses instantanées GPS ÷ nombre de bus en
                                mouvement (&gt; 5 km/h). Les bus à l'arrêt sont
                                exclus du calcul.
                            </>
                        }
                    >
                        <div className="rounded-2xl border border-border/60 bg-card p-4 shadow-sm col-span-2 sm:col-span-1 h-full">
                            <div className="flex items-center justify-between text-muted-foreground mb-1">
                                <span className="text-[0.625rem] font-black uppercase tracking-wider">
                                    Vitesse moyenne
                                </span>
                                <Gauge className="size-4 text-digital" />
                            </div>
                            <div className="flex items-baseline gap-2">
                                <span className="text-2xl font-black font-mono tracking-tight text-foreground">
                                    {kpis.avgSpeed}
                                </span>
                                <span className="text-[0.625rem] font-bold text-muted-foreground">
                                    km/h en route
                                </span>
                            </div>
                        </div>
                    </KoraTooltip>
                </div>

                {/* Persistent Filter Bar */}
                <div className="rounded-2xl border border-border/60 bg-card p-3 shadow-sm flex flex-col md:flex-row items-stretch md:items-center gap-2.5">
                    {/* Search */}
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                        <Input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder="Rechercher par immatriculation, ligne, trajet ou convoyeur…"
                            className="h-10 pl-9 pr-3 rounded-xl bg-background border-border/60 text-xs focus-visible:ring-1 focus-visible:ring-primary"
                        />
                    </div>

                    {/* Line Filter */}
                    <div className="w-full md:w-52 shrink-0">
                        <Select value={selectedLine} onValueChange={setSelectedLine}>
                            <SelectTrigger className="h-10 rounded-xl bg-background border-border/60 text-xs font-medium">
                                <SelectValue placeholder="Toutes les lignes" />
                            </SelectTrigger>
                            <SelectContent className="z-[600]">
                                <SelectItem value="all">Toutes les lignes</SelectItem>
                                {lines.map((line) => (
                                    <SelectItem key={line.uuid} value={line.code}>
                                        {line.code} — {line.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {/* State Filter */}
                    <div className="w-full md:w-48 shrink-0">
                        <Select value={selectedState} onValueChange={setSelectedState}>
                            <SelectTrigger className="h-10 rounded-xl bg-background border-border/60 text-xs font-medium">
                                <SelectValue placeholder="Tous les états" />
                            </SelectTrigger>
                            <SelectContent className="z-[600]">
                                <SelectItem value="all">Tous les signaux</SelectItem>
                                <SelectItem value="live">En direct (&lt; 90s)</SelectItem>
                                <SelectItem value="stale">Position ancienne</SelectItem>
                                <SelectItem value="gps_unavailable">GPS indisponible</SelectItem>
                                <SelectItem value="terminal_offline">Terminal hors ligne</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Agency Filter (if authorized) */}
                    {canViewNetwork && agencies.length > 0 && (
                        <div className="w-full md:w-48 shrink-0">
                            <Select value={selectedAgency} onValueChange={setSelectedAgency}>
                                <SelectTrigger className="h-10 rounded-xl bg-background border-border/60 text-xs font-medium">
                                    <SelectValue placeholder="Toutes les agences" />
                                </SelectTrigger>
                                <SelectContent className="z-[600]">
                                    <SelectItem value="all">Toutes les agences</SelectItem>
                                    {agencies.map((agency) => (
                                        <SelectItem key={agency.uuid} value={agency.uuid}>
                                            {agency.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    {/* Reset Filters */}
                    {(searchQuery || selectedLine !== 'all' || selectedState !== 'all' || selectedAgency !== 'all') && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                setSearchQuery('');
                                setSelectedLine('all');
                                setSelectedState('all');
                                setSelectedAgency('all');
                            }}
                            className="h-10 px-3 text-xs font-bold text-muted-foreground hover:text-foreground shrink-0"
                        >
                            <X className="size-3.5 mr-1" />
                            Effacer
                        </Button>
                    )}
                </div>

                {/* Main Views Container */}
                <div className="flex-1 min-h-[580px] lg:min-h-[640px] flex">
                    {/* View: Hybrid Dispatch (Side Panel + Map) */}
                    {viewMode === 'hybrid' && (
                        <div className="flex w-full h-[640px] lg:h-[720px] rounded-2xl overflow-hidden border border-border/60 shadow-sm bg-card">
                            <FleetSidePanel
                                sessions={filteredSessions}
                                selectedSessionUuid={selectedSessionUuid}
                                onSelectSession={handleSelectSession}
                                collapsed={sidePanelCollapsed}
                                onToggleCollapse={() => setSidePanelCollapsed((prev) => !prev)}
                            />

                            <div className="relative flex-1 h-full min-w-0">
                                {sessions.length === 0 && (
                                    <div className="pointer-events-none absolute inset-0 z-[410] flex items-center justify-center p-6">
                                        <div className="rounded-2xl border border-border/70 bg-background/90 px-6 py-4 text-center shadow-xl backdrop-blur">
                                            <p className="text-sm font-bold text-foreground">
                                                Aucun bus en circulation
                                            </p>
                                            <p className="mt-1 max-w-xs text-xs text-muted-foreground">
                                                Une session de suivi s'ouvre automatiquement au départ effectif d'un bus dont le terminal P501 est actif.
                                            </p>
                                        </div>
                                    </div>
                                )}
                                {renderFleetMap('h-full rounded-none border-0')}
                            </div>
                        </div>
                    )}

                    {/* View: Full Map */}
                    {viewMode === 'map' && (
                        <div className="relative w-full h-[640px] lg:h-[720px] rounded-2xl overflow-hidden border border-border/60 shadow-sm">
                            {sessions.length === 0 && (
                                <div className="pointer-events-none absolute inset-0 z-[410] flex items-center justify-center p-6">
                                    <div className="rounded-2xl border border-border/70 bg-background/90 px-6 py-4 text-center shadow-xl backdrop-blur">
                                        <p className="text-sm font-bold text-foreground">
                                            Aucun bus en circulation
                                        </p>
                                        <p className="mt-1 max-w-xs text-xs text-muted-foreground">
                                            Une session de suivi s'ouvre automatiquement au départ effectif d'un bus dont le terminal P501 est actif.
                                        </p>
                                    </div>
                                </div>
                            )}
                            {renderFleetMap('h-full border-0')}
                        </div>
                    )}

                    {/* View: Table Operations */}
                    {viewMode === 'table' && (
                        <div className="w-full">
                            <FleetTrackingTable
                                sessions={filteredSessions}
                                onSelectSession={handleSelectSession}
                                onSwitchToMap={handleSwitchToMapWithSession}
                            />
                        </div>
                    )}
                </div>
            </div>

            {/* Slide-over telemetry sheet */}
            <FleetVehicleDetail
                session={selectedSession}
                open={detailOpen}
                onClose={() => setDetailOpen(false)}
                onCenterOnMap={(s) => {
                    handleSwitchToMapWithSession(s);
                    setDetailOpen(false);
                }}
                onShowRoute={handleShowRoute}
                onLocateIncident={handleLocateIncident}
            />
        </>
    );
}

FleetTrackingPage.layout = {
    breadcrumbs: [
        { title: 'Exploitation', href: '/departures' },
        { title: 'Suivi de flotte', href: '/operations/fleet-tracking' },
    ] as BreadcrumbItem[],
};
