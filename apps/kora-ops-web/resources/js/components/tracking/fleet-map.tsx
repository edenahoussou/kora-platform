import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import {
    Compass,
    Crosshair,
    Layers,
    Maximize2,
    Sparkles,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import type { FleetTrackingSession } from '@/types/tracking';
import { TRACKING_STATE_META } from './fleet-tracking-badge';

type Props = {
    sessions: FleetTrackingSession[];
    selectedSessionUuid: string | null;
    onSelectSession: (session: FleetTrackingSession) => void;
    osmTileUrl?: string;
    osmAttribution?: string;
    isDarkMode?: boolean;
    className?: string;
};

const TILES = {
    dark: {
        url: 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
        attribution:
            '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
    },
    light: {
        url: 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
        attribution:
            '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
    },
};

// Fallback center: Cotonou, Bénin
const DEFAULT_CENTER: [number, number] = [7.5, 2.2];
const DEFAULT_ZOOM = 7;

function buildMarkerHtml(session: FleetTrackingSession, isSelected: boolean): string {
    const meta = TRACKING_STATE_META[session.state] ?? TRACKING_STATE_META.session_closed;
    const heading = session.position?.heading_degrees ?? 0;
    const speed = session.position?.speed_kmh ? `${Math.round(session.position.speed_kmh)} km/h` : '';

    return `
        <div class="kora-fleet-marker group cursor-pointer ${isSelected ? 'is-selected ring-4 ring-gold ring-offset-2' : ''}" style="display: flex; flex-direction: column; align-items: center; justify-content: center;">
            <div class="relative flex items-center justify-center">
                ${
                    session.state === 'live'
                        ? `<span class="absolute inline-flex h-10 w-10 animate-ping rounded-full bg-emerald-400/30"></span>`
                        : session.state === 'stale'
                          ? `<span class="absolute inline-flex h-9 w-9 animate-pulse rounded-full bg-amber-400/20"></span>`
                          : ''
                }
                <div class="relative flex h-8 w-8 items-center justify-center rounded-full shadow-lg transition-transform duration-300 group-hover:scale-110" style="background-color: ${meta.colorHex}; border: 2px solid #ffffff; box-shadow: 0 4px 14px rgba(0,0,0,0.35);">
                    <svg style="transform: rotate(${heading}deg); transition: transform 0.4s ease;" class="h-4 w-4 text-white" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2L4.5 20.29l.71.71L12 18l6.79 3 .71-.71z"/>
                    </svg>
                </div>
            </div>
            <div class="mt-1 flex flex-col items-center pointer-events-none">
                <span class="rounded-md bg-kora/90 px-1.5 py-0.5 text-[10px] font-extrabold tracking-tight text-white shadow-md border border-white/20 whitespace-nowrap">
                    ${session.bus.registration_number}
                </span>
                ${
                    speed
                        ? `<span class="rounded bg-black/75 px-1 text-[8px] font-bold text-amber-300 backdrop-blur-sm -mt-0.5">${speed}</span>`
                        : ''
                }
            </div>
        </div>
    `;
}

export function FleetMap({
    sessions,
    selectedSessionUuid,
    onSelectSession,
    osmTileUrl,
    osmAttribution,
    isDarkMode = false,
    className,
}: Props) {
    const containerRef = useRef<HTMLDivElement>(null);
    const mapRef = useRef<L.Map | null>(null);
    const tileLayerRef = useRef<L.TileLayer | null>(null);
    const markersRef = useRef<Record<string, L.Marker>>({});
    const polylineRef = useRef<L.Polyline | null>(null);
    const historyDotsRef = useRef<L.CircleMarker[]>([]);
    const [activeTileType, setActiveTileType] = useState<'ops' | 'osm'>('ops');
    const [showLegend, setShowLegend] = useState<boolean>(true);

    // Initial map mount
    useEffect(() => {
        if (!containerRef.current || mapRef.current) {
            return;
        }

        const map = L.map(containerRef.current, {
            zoomControl: false,
            attributionControl: false,
        }).setView(DEFAULT_CENTER, DEFAULT_ZOOM);

        L.control.zoom({ position: 'bottomright' }).addTo(map);
        L.control
            .attribution({
                position: 'bottomleft',
                prefix: false,
            })
            .addTo(map);

        const tileConfig = isDarkMode ? TILES.dark : TILES.light;
        const tileLayer = L.tileLayer(tileConfig.url, {
            attribution: tileConfig.attribution,
            maxZoom: 19,
        }).addTo(map);

        tileLayerRef.current = tileLayer;
        mapRef.current = map;

        return () => {
            map.remove();
            mapRef.current = null;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Theme or tile provider change
    useEffect(() => {
        if (!mapRef.current || !tileLayerRef.current) {
            return;
        }

        let nextUrl = isDarkMode ? TILES.dark.url : TILES.light.url;
        let nextAttr = isDarkMode ? TILES.dark.attribution : TILES.light.attribution;

        if (activeTileType === 'osm' && osmTileUrl) {
            nextUrl = osmTileUrl;
            nextAttr = osmAttribution || '';
        }

        tileLayerRef.current.setUrl(nextUrl);
        tileLayerRef.current.options.attribution = nextAttr;
    }, [isDarkMode, activeTileType, osmTileUrl, osmAttribution]);

    // Markers update
    useEffect(() => {
        const map = mapRef.current;

        if (!map) {
            return;
        }

        const seenUuids = new Set<string>();

        sessions.forEach((session) => {
            if (!session.position?.latitude || !session.position?.longitude) {
                markersRef.current[session.session_uuid]?.remove();
                delete markersRef.current[session.session_uuid];

                return;
            }

            seenUuids.add(session.session_uuid);
            const latLng: [number, number] = [
                session.position.latitude,
                session.position.longitude,
            ];
            const isSelected = session.session_uuid === selectedSessionUuid;

            const icon = L.divIcon({
                html: buildMarkerHtml(session, isSelected),
                className: 'custom-fleet-div-icon',
                iconSize: [60, 60],
                iconAnchor: [30, 20],
            });

            const existing = markersRef.current[session.session_uuid];

            if (existing) {
                existing.setLatLng(latLng);
                existing.setIcon(icon);
            } else {
                const marker = L.marker(latLng, { icon }).addTo(map);
                marker.on('click', () => onSelectSession(session));
                markersRef.current[session.session_uuid] = marker;
            }
        });

        // Clean up removed markers
        Object.keys(markersRef.current).forEach((uuid) => {
            if (!seenUuids.has(uuid)) {
                markersRef.current[uuid]?.remove();
                delete markersRef.current[uuid];
            }
        });
    }, [sessions, selectedSessionUuid, onSelectSession]);

    // Trajectory breadcrumbs update when a session is selected
    useEffect(() => {
        const map = mapRef.current;

        if (!map) {
            return;
        }

        // Remove old polyline and dots
        if (polylineRef.current) {
            polylineRef.current.remove();
            polylineRef.current = null;
        }

        historyDotsRef.current.forEach((dot) => dot.remove());
        historyDotsRef.current = [];

        const selected = sessions.find((s) => s.session_uuid === selectedSessionUuid);

        if (!selected || !selected.recent_positions || selected.recent_positions.length < 2) {
            return;
        }

        const sorted = [...selected.recent_positions].sort(
            (a, b) => new Date(a.recorded_at).getTime() - new Date(b.recorded_at).getTime(),
        );

        const latLngs: [number, number][] = sorted.map((p) => [p.latitude, p.longitude]);

        // Draw trajectory polyline
        const line = L.polyline(latLngs, {
            color: '#0057b8',
            weight: 4,
            opacity: 0.8,
            dashArray: '8, 6',
            lineJoin: 'round',
        }).addTo(map);

        polylineRef.current = line;

        // Draw breadcrumb dots
        sorted.forEach((point, index) => {
            const isLast = index === sorted.length - 1;

            if (isLast) {
                return;
            } // Current position already has the bus marker

            const dot = L.circleMarker([point.latitude, point.longitude], {
                radius: 4,
                fillColor: '#0057b8',
                color: '#ffffff',
                weight: 1.5,
                opacity: 0.9,
                fillOpacity: 0.7,
            }).addTo(map);

            dot.bindTooltip(
                `${point.speed_kmh ?? 0} km/h • ${new Date(point.recorded_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}`,
                { direction: 'top', offset: [0, -6] },
            );

            historyDotsRef.current.push(dot);
        });
    }, [selectedSessionUuid, sessions]);

    // Center on selected vehicle or fit all
    const fitAllBounds = () => {
        const map = mapRef.current;

        if (!map) {
            return;
        }

        const validCoords = sessions
            .filter((s) => s.position?.latitude && s.position?.longitude)
            .map((s) => [s.position!.latitude, s.position!.longitude] as [number, number]);

        if (validCoords.length === 0) {
            map.setView(DEFAULT_CENTER, DEFAULT_ZOOM);

            return;
        }

        if (validCoords.length === 1) {
            map.setView(validCoords[0], 13);

            return;
        }

        const bounds = L.latLngBounds(validCoords);
        map.fitBounds(bounds, { padding: [50, 50], maxZoom: 14 });
    };

    const centerSelected = () => {
        const map = mapRef.current;

        if (!map || !selectedSessionUuid) {
            return;
        }

        const selected = sessions.find((s) => s.session_uuid === selectedSessionUuid);

        if (selected?.position?.latitude && selected?.position?.longitude) {
            map.flyTo(
                [selected.position.latitude, selected.position.longitude],
                14,
                { duration: 0.8 },
            );
        }
    };

    return (
        <div className={`relative h-full w-full overflow-hidden rounded-2xl border border-border/60 bg-muted/20 ${className ?? ''}`}>
            {/* The Map DOM Container */}
            <div ref={containerRef} className="h-full w-full" />

            {/* Quick Floating Map Toolbar */}
            <div className="absolute top-4 right-4 z-[400] flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    onClick={fitAllBounds}
                    className="h-9 gap-1.5 rounded-xl border-border/80 bg-background/90 px-3 text-xs font-bold shadow-lg backdrop-blur hover:bg-background"
                    title="Recadrer sur toute la flotte active"
                >
                    <Maximize2 className="size-3.5 text-primary" />
                    <span>Flotte entière</span>
                </Button>

                {selectedSessionUuid && (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={centerSelected}
                        className="h-9 gap-1.5 rounded-xl border-gold/50 bg-background/95 px-3 text-xs font-bold text-foreground shadow-lg backdrop-blur hover:bg-gold/10"
                        title="Centrer la vue sur le bus sélectionné"
                    >
                        <Crosshair className="size-3.5 text-gold" />
                        <span>Centrer bus</span>
                    </Button>
                )}

                <Button
                    variant="outline"
                    size="icon"
                    onClick={() =>
                        setActiveTileType((prev) => (prev === 'ops' ? 'osm' : 'ops'))
                    }
                    className="size-9 rounded-xl border-border/80 bg-background/90 shadow-lg backdrop-blur hover:bg-background"
                    title={`Style carte : ${activeTileType === 'ops' ? 'Ops Haute Précision' : 'OpenStreetMap standard'}`}
                >
                    <Layers className="size-4" />
                </Button>

                <Button
                    variant="outline"
                    size="icon"
                    onClick={() => setShowLegend((v) => !v)}
                    className="size-9 rounded-xl border-border/80 bg-background/90 shadow-lg backdrop-blur hover:bg-background"
                    title="Afficher / Masquer la légende des signaux"
                >
                    <Compass className="size-4" />
                </Button>
            </div>

            {/* Floating Live Indicator Badge */}
            <div className="absolute top-4 left-4 z-[400] pointer-events-none flex items-center gap-2">
                <div className="flex items-center gap-2 rounded-xl border border-border/80 bg-background/90 px-3 py-1.5 shadow-lg backdrop-blur">
                    <span className="relative flex size-2.5">
                        <span className="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                        <span className="relative inline-flex size-2.5 rounded-full bg-emerald-500" />
                    </span>
                    <span className="text-[0.6875rem] font-extrabold uppercase tracking-wider text-foreground">
                        {sessions.length} bus supervisés
                    </span>
                </div>
            </div>

            {/* Collapsible Signal Legend */}
            {showLegend && (
                <div className="absolute bottom-6 left-4 z-[400] rounded-xl border border-border/80 bg-background/95 p-3 shadow-xl backdrop-blur max-w-xs transition-all">
                    <div className="flex items-center justify-between pb-2 mb-2 border-b border-border/50">
                        <span className="text-[0.625rem] font-black uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <Sparkles className="size-3 text-gold" /> Légende des signaux
                        </span>
                        <button
                            type="button"
                            onClick={() => setShowLegend(false)}
                            className="text-muted-foreground hover:text-foreground text-xs"
                        >
                            &times;
                        </button>
                    </div>
                    <div className="space-y-1.5 text-xs">
                        <div className="flex items-center gap-2">
                            <span className="size-2 rounded-full bg-emerald-500 shrink-0" />
                            <span className="font-semibold text-[0.6875rem] text-foreground">En direct</span>
                            <span className="text-[0.625rem] text-muted-foreground ml-auto">&lt; 90s</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="size-2 rounded-full bg-amber-500 shrink-0" />
                            <span className="font-semibold text-[0.6875rem] text-foreground">Position ancienne</span>
                            <span className="text-[0.625rem] text-muted-foreground ml-auto">&gt; 90s</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="size-2 rounded-full bg-rose-500 shrink-0" />
                            <span className="font-semibold text-[0.6875rem] text-foreground">GPS indisponible</span>
                            <span className="text-[0.625rem] text-muted-foreground ml-auto">Perte satellite</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="size-2 rounded-full bg-slate-400 shrink-0" />
                            <span className="font-semibold text-[0.6875rem] text-foreground">Terminal hors ligne</span>
                            <span className="text-[0.625rem] text-muted-foreground ml-auto">&gt; 5 min</span>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
