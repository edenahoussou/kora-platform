import 'leaflet/dist/leaflet.css';
import { usePage } from '@inertiajs/react';
import type * as Leaflet from 'leaflet';
import {
    Maximize2,
    Minimize2,
    LocateFixed,
    MapPin,
    Route as RouteIcon,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    parcelTrackingEventMeta,
    parcelTrackingPointLabel,
} from '@/components/parcels/parcel-tracking-meta';
import { cn } from '@/lib/utils';
import type {
    ParcelTrackingPoint,
    ParcelTrackingVehicle,
} from '@/types/parcel-tracking';

type Props = {
    planned: ParcelTrackingPoint[];
    traveled: ParcelTrackingPoint[];
    current: ParcelTrackingPoint | null;
    vehicle: ParcelTrackingVehicle | null;
    progressRatio: number;
    className?: string;
};

type MapConfig = {
    osm?: {
        tileUrl?: string;
        attribution?: string;
    };
};

type LatLng = [number, number];

type RoadRoute = {
    geometry: LatLng[];
    distanceKm: number;
    durationMinutes: number;
};

type RouteState = 'idle' | 'loading' | 'ready' | 'error';

const KORA = '#001d4e';
const GOLD = '#f6b000';
const FALLBACK_TILE = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const FALLBACK_ATTRIBUTION =
    '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';
const OSRM_ROUTE_URL = 'https://router.project-osrm.org/route/v1/driving';

const DATE_FORMATTER = new Intl.DateTimeFormat('fr-FR', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'Africa/Porto-Novo',
});

function hasCoordinates(point: ParcelTrackingPoint): boolean {
    return point.latitude !== null && point.longitude !== null;
}

function escapeHtml(value: string): string {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function formatDate(value: string | null): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? '' : DATE_FORMATTER.format(date);
}

function pointKey(point: ParcelTrackingPoint): string {
    return `${point.latitude?.toFixed(5)}|${point.longitude?.toFixed(5)}|${point.role}|${point.kind ?? ''}`;
}

function tooltipHtml(point: ParcelTrackingPoint): string {
    const role =
        point.role === 'current' && point.kind === 'vehicle'
            ? 'Position du bus'
            : point.kind
              ? parcelTrackingEventMeta(point.kind).label
              : parcelTrackingPointLabel(point.role);
    const place = [point.name, point.city, point.country]
        .filter((value): value is string => Boolean(value))
        .join(' · ');
    const date = formatDate(point.observed_at);

    return `
        <div style="min-width: 168px; font-family: inherit;">
            <div style="font-size: 11px; font-weight: 800; color: ${KORA}; line-height: 1.3;">
                ${escapeHtml(role)}
            </div>
            <div style="margin-top: 3px; font-size: 11px; font-weight: 600; color: #334155; line-height: 1.35;">
                ${escapeHtml(place || 'Localisation du colis')}
            </div>
            ${date ? `<div style="margin-top: 4px; font-size: 10px; font-weight: 700; color: #64748b;">${escapeHtml(date)}</div>` : ''}
            ${
                point.role === 'current' && point.kind === 'vehicle'
                    ? `<div style="margin-top: 4px; font-size: 9px; font-weight: 700; color: ${point.fresh ? '#047857' : '#b45309'}; text-transform: uppercase; letter-spacing: 0.06em;">${point.fresh ? 'Position GPS récente' : 'Dernière position connue'}</div>`
                    : ''
            }
        </div>
    `;
}

function routeDistanceLabel(route: RoadRoute): string {
    return `${new Intl.NumberFormat('fr-FR', {
        maximumFractionDigits: 1,
    }).format(route.distanceKm)} km`;
}

function routeDurationLabel(route: RoadRoute): string {
    const minutes = Math.max(1, Math.round(route.durationMinutes));

    if (minutes < 60) {
        return `${minutes} min`;
    }

    return `${Math.floor(minutes / 60)} h ${String(minutes % 60).padStart(2, '0')}`;
}

/**
 * Tracé routier entre les arrêts géolocalisés du parcours.
 *
 * Le trajet n'est jamais dessiné en ligne droite : il suit le réseau
 * routier existant via OSRM. En cas d'indisponibilité, aucune ligne
 * inventée n'est affichée.
 */
function useRoadRoute(points: ParcelTrackingPoint[]): {
    route: RoadRoute | null;
    state: RouteState;
} {
    const [result, setResult] = useState<{
        signature: string;
        route: RoadRoute | null;
        state: RouteState;
    } | null>(null);
    const geolocated = useMemo(() => points.filter(hasCoordinates), [points]);
    const signature = geolocated
        .map((point) => `${point.longitude},${point.latitude}`)
        .join(';');

    useEffect(() => {
        if (geolocated.length < 2) {
            return;
        }

        const controller = new AbortController();

        fetch(
            `${OSRM_ROUTE_URL}/${signature}?overview=full&geometries=geojson&steps=false`,
            { signal: controller.signal },
        )
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error(`OSRM ${response.status}`);
                }

                const payload = (await response.json()) as {
                    code?: string;
                    routes?: Array<{
                        distance: number;
                        duration: number;
                        geometry?: { coordinates?: number[][] };
                    }>;
                };

                const candidate = payload.routes?.[0];
                const coordinates = candidate?.geometry?.coordinates;

                if (
                    payload.code !== 'Ok' ||
                    !candidate ||
                    !coordinates ||
                    coordinates.length < 2
                ) {
                    throw new Error('OSRM sans géométrie');
                }

                setResult({
                    signature,
                    route: {
                        geometry: coordinates.map(([longitude, latitude]) => [
                            latitude,
                            longitude,
                        ]),
                        distanceKm: candidate.distance / 1000,
                        durationMinutes: candidate.duration / 60,
                    },
                    state: 'ready',
                });
            })
            .catch((reason: unknown) => {
                if (
                    reason instanceof DOMException &&
                    reason.name === 'AbortError'
                ) {
                    return;
                }

                setResult({ signature, route: null, state: 'error' });
            });

        return () => controller.abort();
        // `signature` résume exactement les coordonnées utilisées.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [signature]);

    if (geolocated.length < 2) {
        return { route: null, state: 'idle' };
    }

    if (result?.signature === signature) {
        return { route: result.route, state: result.state };
    }

    return { route: null, state: 'loading' };
}

function sliceRoute(geometry: LatLng[], ratio: number): LatLng[] {
    if (geometry.length < 2) {
        return [];
    }

    if (ratio >= 0.995) {
        return geometry;
    }

    if (ratio <= 0.01) {
        return [geometry[0]];
    }

    const end = Math.max(
        2,
        Math.min(geometry.length, Math.round(geometry.length * ratio)),
    );

    return geometry.slice(0, end);
}

export function ParcelTrackingMap({
    planned,
    traveled,
    current,
    vehicle,
    progressRatio,
    className,
}: Props) {
    const containerRef = useRef<HTMLDivElement>(null);
    const mapRef = useRef<Leaflet.Map | null>(null);
    const groupRef = useRef<Leaflet.LayerGroup | null>(null);
    const [expanded, setExpanded] = useState(false);
    const [ready, setReady] = useState(false);
    const { mapConfig } = usePage().props as { mapConfig?: MapConfig };
    const tileUrl = mapConfig?.osm?.tileUrl || FALLBACK_TILE;
    const attribution = mapConfig?.osm?.attribution || FALLBACK_ATTRIBUTION;

    const plannedGeolocated = useMemo(
        () => planned.filter(hasCoordinates),
        [planned],
    );
    const traveledGeolocated = useMemo(
        () => traveled.filter(hasCoordinates),
        [traveled],
    );
    const { route, state: routeState } = useRoadRoute(plannedGeolocated);
    const hasGeometry =
        plannedGeolocated.length > 0 ||
        traveledGeolocated.length > 0 ||
        current !== null;

    useEffect(() => {
        if (!containerRef.current || mapRef.current) {
            return;
        }

        let disposed = false;
        let resizeObserver: ResizeObserver | null = null;

        void import('leaflet').then((module) => {
            if (disposed || !containerRef.current) {
                return;
            }

            const L = (module.default ?? module) as typeof Leaflet;
            const instance = L.map(containerRef.current, {
                zoomControl: false,
                attributionControl: true,
                scrollWheelZoom: false,
                preferCanvas: false,
            });

            L.tileLayer(tileUrl, {
                attribution,
                maxZoom: 19,
            }).addTo(instance);
            L.control.zoom({ position: 'bottomright' }).addTo(instance);
            L.control
                .scale({ imperial: false, position: 'bottomleft' })
                .addTo(instance);
            groupRef.current = L.layerGroup().addTo(instance);
            mapRef.current = instance;

            // Le dialogue s'ouvre dans un conteneur qui vient d'être monté :
            // sans invalidation, Leaflet peut conserver une vue 0×0.
            resizeObserver = new ResizeObserver(() => {
                if (!disposed && mapRef.current) {
                    mapRef.current.invalidateSize({ animate: false });
                }
            });
            resizeObserver.observe(containerRef.current);

            setReady(true);
        });

        return () => {
            disposed = true;
            resizeObserver?.disconnect();
            resizeObserver = null;
            mapRef.current?.remove();
            mapRef.current = null;
            groupRef.current = null;
            setReady(false);
        };
    }, [attribution, tileUrl]);

    useEffect(() => {
        const instance = mapRef.current;
        const group = groupRef.current;

        if (!ready || !instance || !group) {
            return;
        }

        let disposed = false;

        void import('leaflet').then((module) => {
            if (disposed || !mapRef.current || !groupRef.current) {
                return;
            }

            const L = (module.default ?? module) as typeof Leaflet;
            const layerGroup = groupRef.current;

            layerGroup.clearLayers();

            const plannedPath = route?.geometry ?? [];

            if (plannedPath.length > 1) {
                L.polyline(plannedPath, {
                    color: KORA,
                    weight: 4.5,
                    opacity: 0.62,
                    dashArray: '7 7',
                    lineCap: 'round',
                    lineJoin: 'round',
                }).addTo(layerGroup);
            }

            const traveledPath = route
                ? sliceRoute(route.geometry, progressRatio)
                : [];

            if (traveledPath.length > 1) {
                L.polyline(traveledPath, {
                    color: KORA,
                    weight: 5,
                    opacity: 1,
                    lineCap: 'round',
                    lineJoin: 'round',
                }).addTo(layerGroup);
            }

            const addPoint = (point: ParcelTrackingPoint) => {
                if (point.latitude === null || point.longitude === null) {
                    return null;
                }

                const isCurrent = point.role === 'current';
                const isDestination = point.role === 'destination';
                const isOrigin = point.role === 'origin';

                const color = isCurrent
                    ? GOLD
                    : isDestination
                      ? KORA
                      : isOrigin
                        ? KORA
                        : '#64748b';
                const radius = isCurrent
                    ? 8
                    : isOrigin || isDestination
                      ? 6
                      : 4;

                const marker = L.circleMarker(
                    [point.latitude, point.longitude],
                    {
                        radius,
                        color: isCurrent ? KORA : '#ffffff',
                        weight: isCurrent ? 3 : 2,
                        fillColor: color,
                        fillOpacity: 1,
                    },
                ).addTo(layerGroup);

                marker.bindTooltip(tooltipHtml(point), {
                    direction: 'top',
                    offset: [0, -8],
                    opacity: 1,
                    sticky: true,
                    className: 'kora-parcel-tracking-tooltip',
                });

                marker.on('click', () => {
                    marker
                        .bindPopup(tooltipHtml(point), {
                            closeButton: false,
                            offset: [0, -4],
                            className: 'kora-parcel-tracking-popup',
                        })
                        .openPopup();
                });

                return marker;
            };

            const seen = new Set<string>();

            for (const point of [
                ...planned,
                ...traveled,
                ...(current ? [current] : []),
            ]) {
                if (!hasCoordinates(point)) {
                    continue;
                }

                const key = pointKey(point);

                if (seen.has(key)) {
                    continue;
                }

                seen.add(key);
                addPoint(point);
            }

            const endpointLabel = (
                point: ParcelTrackingPoint | undefined,
                label: string,
            ) => {
                if (
                    !point ||
                    point.latitude === null ||
                    point.longitude === null
                ) {
                    return;
                }

                const icon = L.divIcon({
                    className: '',
                    iconSize: [0, 0],
                    html: `<div style="transform: translate(-50%, -170%); white-space: nowrap; border-radius: 999px; background: rgba(255,255,255,.96); color: ${KORA}; border: 1px solid rgba(0,29,78,.18); padding: 3px 8px; font-size: 10px; font-weight: 800; box-shadow: 0 5px 14px -8px rgba(0,29,78,.55);">${escapeHtml(label)}</div>`,
                });

                L.marker([point.latitude, point.longitude], {
                    icon,
                    interactive: false,
                    zIndexOffset: 500,
                }).addTo(layerGroup);
            };

            endpointLabel(plannedGeolocated[0], 'Départ');
            endpointLabel(
                plannedGeolocated[plannedGeolocated.length - 1],
                'Arrivée',
            );

            if (
                vehicle?.registration_number &&
                current?.role === 'current' &&
                current.latitude !== null &&
                current.longitude !== null
            ) {
                const latitude = current.latitude;
                const longitude = current.longitude;
                const label = L.divIcon({
                    className: '',
                    iconSize: [0, 0],
                    html: `<div style="transform: translate(-50%, -135%); white-space: nowrap; border-radius: 7px; background: ${KORA}; color: #fff; border: 2px solid ${GOLD}; padding: 2px 7px; font-size: 10px; font-weight: 800; box-shadow: 0 4px 12px rgba(0,0,0,.28);">${escapeHtml(vehicle.registration_number)}</div>`,
                });
                L.marker([latitude, longitude], {
                    icon: label,
                    interactive: false,
                }).addTo(layerGroup);
            }

            const boundsPoints = route?.geometry?.length
                ? route.geometry
                : [
                      ...plannedGeolocated,
                      ...traveledGeolocated,
                      ...(current ? [current] : []),
                  ]
                      .filter(hasCoordinates)
                      .map(
                          (point) =>
                              [point.latitude, point.longitude] as LatLng,
                      );

            if (boundsPoints.length > 0) {
                instance.fitBounds(L.latLngBounds(boundsPoints), {
                    padding: [42, 42],
                    maxZoom: 12,
                });
            }
        });

        return () => {
            disposed = true;
        };
    }, [
        current,
        planned,
        plannedGeolocated,
        progressRatio,
        ready,
        route,
        traveled,
        traveledGeolocated,
        vehicle,
    ]);

    useEffect(() => {
        const instance = mapRef.current;

        if (!instance) {
            return;
        }

        const timer = window.setTimeout(() => {
            instance.invalidateSize();
        }, 180);

        return () => window.clearTimeout(timer);
    }, [expanded]);

    if (!hasGeometry) {
        return (
            <div
                className={cn(
                    'flex min-h-52 flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-border bg-muted/25 px-6 py-10 text-center',
                    className,
                )}
            >
                <MapPin className="size-8 text-muted-foreground/40" />
                <div>
                    <p className="text-sm font-bold text-foreground">
                        Coordonnées indisponibles
                    </p>
                    <p className="mt-1 max-w-md text-xs leading-5 text-muted-foreground">
                        Les arrêts de ce colis n’ont pas encore de latitude et
                        longitude. Le parcours reste consultable sous forme
                        d’étapes nommées, sans position GPS inventée.
                    </p>
                </div>
            </div>
        );
    }

    return (
        <div
            className={cn(
                'overflow-hidden rounded-2xl border border-border bg-card',
                className,
            )}
        >
            <div className="relative">
                <div
                    ref={containerRef}
                    role="region"
                    aria-label="Carte du parcours du colis"
                    className={cn(
                        'w-full bg-muted transition-[height] duration-300',
                        expanded ? 'h-[540px]' : 'h-[340px]',
                    )}
                />

                <div className="pointer-events-none absolute top-3 left-3 z-[1000] flex max-w-[15rem] flex-col gap-1">
                    <div className="inline-flex w-fit items-center gap-1.5 rounded-lg border border-border/70 bg-white/96 px-2.5 py-1.5 text-[10px] font-black text-kora shadow-sm backdrop-blur">
                        <RouteIcon className="size-3 text-gold" />
                        Réseau routier existant
                    </div>
                    {route && routeState === 'ready' && (
                        <div className="inline-flex w-fit items-center gap-2 rounded-lg border border-border/70 bg-white/92 px-2.5 py-1 text-[9px] font-bold text-muted-foreground shadow-sm backdrop-blur">
                            <span>{routeDistanceLabel(route)}</span>
                            <span className="text-border">·</span>
                            <span>{routeDurationLabel(route)}</span>
                        </div>
                    )}
                    {routeState === 'loading' && (
                        <div className="inline-flex w-fit items-center gap-1.5 rounded-lg border border-border/70 bg-white/92 px-2.5 py-1 text-[9px] font-bold text-muted-foreground shadow-sm backdrop-blur">
                            <span className="size-1.5 animate-pulse rounded-full bg-gold" />
                            Calcul du tracé routier
                        </div>
                    )}
                    {routeState === 'error' && (
                        <div className="inline-flex w-fit items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50/95 px-2.5 py-1 text-[9px] font-bold text-amber-800 shadow-sm backdrop-blur">
                            Tracé routier indisponible — aucune ligne inventée
                        </div>
                    )}
                </div>

                <div className="absolute top-3 right-3 z-[1000] flex items-center gap-1 rounded-xl border border-black/5 bg-white/95 p-1 shadow-lg backdrop-blur">
                    <button
                        type="button"
                        onClick={() => setExpanded((value) => !value)}
                        className="grid size-8 place-items-center rounded-lg text-kora transition hover:bg-kora/10"
                        aria-label={
                            expanded ? 'Réduire la carte' : 'Agrandir la carte'
                        }
                    >
                        {expanded ? (
                            <Minimize2 className="size-3.5" />
                        ) : (
                            <Maximize2 className="size-3.5" />
                        )}
                    </button>
                    <button
                        type="button"
                        onClick={() => {
                            const instance = mapRef.current;

                            if (!instance || plannedGeolocated.length === 0) {
                                return;
                            }

                            void import('leaflet').then((module) => {
                                const L = (module.default ??
                                    module) as typeof Leaflet;
                                const points = route?.geometry?.length
                                    ? route.geometry
                                    : [
                                          ...plannedGeolocated,
                                          ...traveledGeolocated,
                                          ...(current ? [current] : []),
                                      ]
                                          .filter(hasCoordinates)
                                          .map(
                                              (point) =>
                                                  [
                                                      point.latitude,
                                                      point.longitude,
                                                  ] as LatLng,
                                          );
                                instance.fitBounds(L.latLngBounds(points), {
                                    padding: [42, 42],
                                    maxZoom: 12,
                                });
                            });
                        }}
                        className="grid size-8 place-items-center rounded-lg text-kora transition hover:bg-kora/10"
                        aria-label="Recentrer sur le parcours"
                    >
                        <LocateFixed className="size-3.5" />
                    </button>
                </div>
            </div>
        </div>
    );
}
