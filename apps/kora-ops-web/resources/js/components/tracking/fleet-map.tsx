import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import {
    Compass,
    Crosshair,
    Layers,
    Maximize2,
    Route,
    Sparkles,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import type { FleetTrackingSession } from '@/types/tracking';
import {
    INCIDENT_STATUS_META,
    INCIDENT_TYPE_META,
    TRACKING_STATE_META,
    countActiveIncidents,
} from './fleet-tracking-badge';

export type FleetMapCommand = {
    nonce: number;
    kind: 'flyTo' | 'fitRoute';
    latitude?: number;
    longitude?: number;
    zoom?: number;
};

type Props = {
    sessions: FleetTrackingSession[];
    selectedSessionUuid: string | null;
    onSelectSession: (session: FleetTrackingSession) => void;
    osmTileUrl?: string;
    osmAttribution?: string;
    isDarkMode?: boolean;
    mapCommand?: FleetMapCommand | null;
    onMapCommandHandled?: () => void;
    className?: string;
};

const TILES = {
    dark: {
        url: 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
        attribution:
            '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
        subdomains: ['a', 'b', 'c', 'd'],
    },
    light: {
        url: 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
        attribution:
            '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
        subdomains: ['a', 'b', 'c', 'd'],
    },
    osm: {
        url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        attribution:
            '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        subdomains: ['a', 'b', 'c'],
    },
};

// Fallback center: Cotonou, Bénin
const DEFAULT_CENTER: [number, number] = [7.5, 2.2];
const DEFAULT_ZOOM = 7;

function buildMarkerHtml(session: FleetTrackingSession, isSelected: boolean): string {
    const meta = TRACKING_STATE_META[session.state] ?? TRACKING_STATE_META.session_closed;
    const heading = session.position?.heading_degrees ?? 0;
    const speed = session.position?.speed_kmh ? `${Math.round(session.position.speed_kmh)} km/h` : '';
    const activeIncidents = countActiveIncidents(session);

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
                    <svg class="h-4 w-4" style="color: #ffffff;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M8 6v6"/><path d="M15 6v6"/><path d="M2 12h19.6"/><path d="M18 18h3s.5-1.7.8-2.8c.1-.4.2-.8.2-1.2 0-.4-.1-.8-.2-1.2l-1.4-5C20.1 6.8 19.1 6 18 6H4a2 2 0 0 0-2 2v10h3"/><path d="M9 18h5"/><circle cx="7" cy="18" r="2"/><circle cx="16" cy="18" r="2"/>
                    </svg>
                </div>
                <div style="position: absolute; inset: 0; transform: rotate(${heading}deg); pointer-events: none;">
                    <div style="position: absolute; top: -6px; left: 50%; transform: translateX(-50%); width: 0; height: 0; border-left: 4px solid transparent; border-right: 4px solid transparent; border-bottom: 6px solid ${meta.colorHex}; filter: drop-shadow(0 1px 1px rgba(0,0,0,0.45));"></div>
                </div>
                ${
                    activeIncidents > 0
                        ? `<span class="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-0.5 text-[8px] font-black leading-none text-white border border-white shadow-md z-10">${activeIncidents}</span>`
                        : ''
                }
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

function buildIncidentMarkerHtml(colorHex: string, dimmed: boolean): string {
    return `
        <div style="opacity: ${dimmed ? 0.55 : 1}; display: flex; align-items: center; justify-content: center;">
            <div style="width: 22px; height: 22px; border-radius: 6px; background-color: ${colorHex}; border: 2px solid #ffffff; box-shadow: 0 3px 10px rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center;">
                <svg viewBox="0 0 24 24" style="width: 13px; height: 13px; color: #ffffff;" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><line x1="12" x2="12" y1="9" y2="13"/><line x1="12" x2="12.01" y1="17" y2="17"/>
                </svg>
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
    mapCommand = null,
    onMapCommandHandled,
    className,
}: Props) {
    const containerRef = useRef<HTMLDivElement>(null);
    const mapRef = useRef<L.Map | null>(null);
    const tileLayerRef = useRef<L.TileLayer | null>(null);
    const markersRef = useRef<Record<string, L.Marker>>({});
    const polylineRef = useRef<L.Polyline | null>(null);
    const historyDotsRef = useRef<L.CircleMarker[]>([]);
    const corridorRef = useRef<L.Polyline | null>(null);
    const plannedLineRef = useRef<L.Polyline | null>(null);
    const stopMarkersRef = useRef<L.CircleMarker[]>([]);
    const incidentMarkersRef = useRef<L.Marker[]>([]);
    const autoFittedSessionRef = useRef<string | null>(null);
    const [activeTileType, setActiveTileType] = useState<'ops' | 'osm'>('osm');
    const [showLegend, setShowLegend] = useState<boolean>(true);
    const [tileErrorCount, setTileErrorCount] = useState(0);

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

        let tileConfig = TILES.osm;

        if (activeTileType === 'ops') {
            tileConfig = isDarkMode ? TILES.dark : TILES.light;
        }

        const tileLayer = L.tileLayer(tileConfig.url, {
            attribution: tileConfig.attribution,
            maxZoom: 19,
            subdomains: tileConfig.subdomains as any,
        }).addTo(map);

        tileLayerRef.current = tileLayer;

        // Auto-fallback to OSM if the OPS tiles fail to load
        tileLayer.on('tileerror', () => {
            setTileErrorCount((prev) => prev + 1);

            if (activeTileType !== 'osm' && tileErrorCount > 5) {
                tileLayer.setUrl(TILES.osm.url);
                setActiveTileType('osm');
            }
        });

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

        let nextUrl = TILES.osm.url;
        let nextAttr = TILES.osm.attribution;

        if (activeTileType === 'ops') {
            nextUrl = isDarkMode ? TILES.dark.url : TILES.light.url;
            nextAttr = isDarkMode ? TILES.dark.attribution : TILES.light.attribution;
        } else if (osmTileUrl) {
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

    const fitRouteBounds = (session: FleetTrackingSession) => {
        const map = mapRef.current;

        if (!map) {
            return;
        }

        const points: [number, number][] = [];

        (session.planned_stops ?? []).forEach((stop) => {
            if (stop.latitude && stop.longitude) {
                points.push([stop.latitude, stop.longitude]);
            }
        });

        (session.recent_positions ?? []).forEach((point) => {
            points.push([point.latitude, point.longitude]);
        });

        if (session.position?.latitude && session.position?.longitude) {
            points.push([session.position.latitude, session.position.longitude]);
        }

        if (points.length === 0) {
            return;
        }

        map.flyToBounds(L.latLngBounds(points), {
            padding: [60, 60],
            duration: 0.9,
            maxZoom: 12,
        });
    };

    const selectedSession =
        sessions.find((s) => s.session_uuid === selectedSessionUuid) ?? null;
    const hasPlannedRoute = (selectedSession?.planned_stops?.length ?? 0) >= 2;
    const hasMappedIncidents = (selectedSession?.incidents ?? []).some(
        (i) => i.latitude !== null && i.longitude !== null && i.status !== 'cancelled',
    );

    // Planned route corridor + actual trajectory + incidents when a session is selected
    useEffect(() => {
        const map = mapRef.current;

        if (!map) {
            return;
        }

        // Remove old overlays
        if (polylineRef.current) {
            polylineRef.current.remove();
            polylineRef.current = null;
        }

        if (corridorRef.current) {
            corridorRef.current.remove();
            corridorRef.current = null;
        }

        if (plannedLineRef.current) {
            plannedLineRef.current.remove();
            plannedLineRef.current = null;
        }

        historyDotsRef.current.forEach((dot) => dot.remove());
        historyDotsRef.current = [];
        stopMarkersRef.current.forEach((stop) => stop.remove());
        stopMarkersRef.current = [];
        incidentMarkersRef.current.forEach((marker) => marker.remove());
        incidentMarkersRef.current = [];

        const selected = sessions.find((s) => s.session_uuid === selectedSessionUuid);

        if (!selected) {
            autoFittedSessionRef.current = null;

            return;
        }

        // Planned itinerary: corridor band + dashed gold line + stop markers
        const plannedStops = [...(selected.planned_stops ?? [])]
            .filter((stop) => stop.latitude && stop.longitude)
            .sort((a, b) => a.order - b.order);

        if (plannedStops.length >= 2) {
            const routeLatLngs: [number, number][] = plannedStops.map((stop) => [
                stop.latitude,
                stop.longitude,
            ]);

            corridorRef.current = L.polyline(routeLatLngs, {
                color: '#f6b000',
                weight: 16,
                opacity: isDarkMode ? 0.12 : 0.16,
                lineJoin: 'round',
                interactive: false,
            }).addTo(map);

            plannedLineRef.current = L.polyline(routeLatLngs, {
                color: '#f6b000',
                weight: 3,
                opacity: 0.95,
                dashArray: '2, 9',
                lineCap: 'round',
            }).addTo(map);

            plannedStops.forEach((stop, index) => {
                const isTerminus = index === 0 || index === plannedStops.length - 1;
                const marker = L.circleMarker([stop.latitude, stop.longitude], {
                    radius: isTerminus ? 6 : 4,
                    fillColor: isTerminus ? '#f6b000' : '#ffffff',
                    color: '#f6b000',
                    weight: 2,
                    opacity: 1,
                    fillOpacity: isTerminus ? 1 : 0.9,
                }).addTo(map);

                marker.bindTooltip(
                    `<strong>${stop.order}. ${stop.name}</strong> — ${stop.city}${stop.km_from_origin !== null ? ` • km ${stop.km_from_origin}` : ''}`,
                    { direction: 'top', offset: [0, -6] },
                );

                stopMarkersRef.current.push(marker);
            });
        }

        // Incident markers (cancelled incidents stay hidden on the map)
        (selected.incidents ?? [])
            .filter((incident) => incident.status !== 'cancelled')
            .forEach((incident) => {
                if (incident.latitude === null || incident.longitude === null) {
                    return;
                }

                const statusMeta = INCIDENT_STATUS_META[incident.status];
                const typeMeta = INCIDENT_TYPE_META[incident.type];
                const dimmed = incident.status === 'resolved';

                const marker = L.marker([incident.latitude, incident.longitude], {
                    icon: L.divIcon({
                        html: buildIncidentMarkerHtml(statusMeta.markerColor, dimmed),
                        className: 'kora-incident-div-icon',
                        iconSize: [22, 22],
                        iconAnchor: [11, 11],
                    }),
                }).addTo(map);

                marker.bindTooltip(
                    `<strong>${typeMeta.label}</strong> • ${statusMeta.label}<br>${incident.description}`,
                    { direction: 'top', offset: [0, -12] },
                );

                incidentMarkersRef.current.push(marker);
            });

        // Actual trajectory polyline + breadcrumbs
        if (selected.recent_positions && selected.recent_positions.length >= 2) {
            const sorted = [...selected.recent_positions].sort(
                (a, b) => new Date(a.recorded_at).getTime() - new Date(b.recorded_at).getTime(),
            );

            const latLngs: [number, number][] = sorted.map((p) => [p.latitude, p.longitude]);

            const line = L.polyline(latLngs, {
                color: isDarkMode ? '#4f8edc' : '#0057b8',
                weight: 4,
                opacity: 0.85,
                dashArray: '8, 6',
                lineJoin: 'round',
            }).addTo(map);

            polylineRef.current = line;

            sorted.forEach((point, index) => {
                const isLast = index === sorted.length - 1;

                if (isLast) {
                    return;
                }

                const dot = L.circleMarker([point.latitude, point.longitude], {
                    radius: 4,
                    fillColor: isDarkMode ? '#4f8edc' : '#0057b8',
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
        }

        // Frame the planned route once per selection so ops sees itinerary vs trajectory
        if (
            plannedStops.length >= 2 &&
            autoFittedSessionRef.current !== selected.session_uuid
        ) {
            autoFittedSessionRef.current = selected.session_uuid;
            fitRouteBounds(selected);
        }
    }, [selectedSessionUuid, sessions, isDarkMode]);

    // External commands from the detail sheet (locate incident, fit route)
    useEffect(() => {
        const map = mapRef.current;

        if (!map || !mapCommand) {
            return;
        }

        const selected = sessions.find((s) => s.session_uuid === selectedSessionUuid);

        if (mapCommand.kind === 'fitRoute' && selected) {
            fitRouteBounds(selected);
        } else if (
            mapCommand.kind === 'flyTo' &&
            mapCommand.latitude !== undefined &&
            mapCommand.longitude !== undefined
        ) {
            map.flyTo([mapCommand.latitude, mapCommand.longitude], mapCommand.zoom ?? 14, {
                duration: 0.8,
            });
        }

        onMapCommandHandled?.();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [mapCommand]);

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

                {hasPlannedRoute && selectedSession && (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => fitRouteBounds(selectedSession)}
                        className="h-9 gap-1.5 rounded-xl border-gold/50 bg-background/95 px-3 text-xs font-bold text-foreground shadow-lg backdrop-blur hover:bg-gold/10"
                        title="Cadrer la vue sur l'itinéraire prévu complet"
                    >
                        <Route className="size-3.5 text-gold" />
                        <span>Itinéraire</span>
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

                    {/* Selected-session overlay legend */}
                    {selectedSession && (hasPlannedRoute || hasMappedIncidents) && (
                        <div className="mt-3 space-y-1.5 border-t border-border/50 pt-2.5">
                            <span className="text-[0.625rem] font-black uppercase tracking-wider text-muted-foreground">
                                Itinéraire du trajet
                            </span>
                            {hasPlannedRoute && (
                                <div className="flex items-center gap-2">
                                    <span
                                        className="inline-block w-5 shrink-0"
                                        style={{
                                            borderTop: '3px dashed #f6b000',
                                        }}
                                    />
                                    <span className="font-semibold text-[0.6875rem] text-foreground">
                                        Itinéraire prévu
                                    </span>
                                </div>
                            )}
                            <div className="flex items-center gap-2">
                                <span
                                    className="inline-block w-5 shrink-0"
                                    style={{ borderTop: '3px solid #0057b8' }}
                                />
                                <span className="font-semibold text-[0.6875rem] text-foreground">
                                    Trajectoire réelle (GPS)
                                </span>
                            </div>
                            {hasMappedIncidents && (
                                <div className="flex items-center gap-2">
                                    <span className="flex size-4 shrink-0 items-center justify-center rounded bg-rose-500 text-white">
                                        <span className="text-[9px] font-black leading-none">!</span>
                                    </span>
                                    <span className="font-semibold text-[0.6875rem] text-foreground">
                                        Incident signalé
                                    </span>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
