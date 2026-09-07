import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import { AlertTriangle, MapPin, Route as RouteIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export type MapPoint = {
    id: string;
    label: string;
    latitude: number | null;
    longitude: number | null;
};

type MapPickerProps = {
    points: MapPoint[];
    onPointsChange: (points: MapPoint[]) => void;
    provider: 'openstreetmap' | 'google';
    osmTileUrl: string;
    osmAttribution: string;
    googleApiKey: string | null;
    /** [latitude, longitude] — default view before any point has coordinates. */
    fallbackCenter?: [number, number];
    height?: number;
};

// Haversine — same formula the backend would use to compute km_from_origin;
// kept in sync manually since this is a client-side *preview* only. The
// authoritative distance is still whatever the form submits/stores.
function haversineKm(a: MapPoint, b: MapPoint): number | null {
    if (
        a.latitude === null ||
        a.longitude === null ||
        b.latitude === null ||
        b.longitude === null
    ) {
        return null;
    }

    const toRad = (deg: number) => (deg * Math.PI) / 180;
    const earthRadiusKm = 6371;
    const dLat = toRad(b.latitude - a.latitude);
    const dLon = toRad(b.longitude - a.longitude);
    const lat1 = toRad(a.latitude);
    const lat2 = toRad(b.latitude);

    const h =
        Math.sin(dLat / 2) ** 2 +
        Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLon / 2) ** 2;

    return earthRadiusKm * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
}

/** Cumulative distance from the first point with coordinates, per point. */
function cumulativeDistances(points: MapPoint[]): Array<number | null> {
    let total = 0;
    let previous: MapPoint | null = null;

    return points.map((point) => {
        if (point.latitude === null || point.longitude === null) {
            return null;
        }

        if (previous) {
            const leg = haversineKm(previous, point);

            if (leg !== null) {
                total += leg;
            }
        }

        previous = point;

        return Math.round(total * 10) / 10;
    });
}

let googleLoaderPromise: Promise<void> | null = null;

function loadGoogleMaps(apiKey: string): Promise<void> {
    if (typeof window !== 'undefined' && (window as any).google?.maps) {
        return Promise.resolve();
    }

    googleLoaderPromise ??= new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}`;
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () =>
            reject(new Error('Échec du chargement de Google Maps JS API'));
        document.head.appendChild(script);
    });

    return googleLoaderPromise;
}

/**
 * Live map for positioning stops. Supports two providers behind one
 * interface — the active one is resolved server-side (admin setting, with
 * MAP_PROVIDER as the .env fallback) and passed in as a prop, never chosen
 * by this component itself.
 *
 * OpenStreetMap (via Leaflet) is the fully-implemented path: free, no API
 * key. The Google Maps path is a thinner prototype — it dynamically loads
 * the JS SDK and supports the same click/drag placement, but hasn't been
 * exercised against a real billed API key in this environment, so treat it
 * as a wireframe of the integration shape rather than production-ready.
 */
export function MapPicker({
    points,
    onPointsChange,
    provider,
    osmTileUrl,
    osmAttribution,
    googleApiKey,
    fallbackCenter = [6.3703, 2.3912], // Cotonou
    height = 420,
}: MapPickerProps) {
    if (provider === 'google') {
        return (
            <GoogleMapPicker
                points={points}
                onPointsChange={onPointsChange}
                apiKey={googleApiKey}
                fallbackCenter={fallbackCenter}
                height={height}
            />
        );
    }

    return (
        <LeafletMapPicker
            points={points}
            onPointsChange={onPointsChange}
            tileUrl={osmTileUrl}
            attribution={osmAttribution}
            fallbackCenter={fallbackCenter}
            height={height}
        />
    );
}

function firstKnownCenter(
    points: MapPoint[],
    fallback: [number, number],
): [number, number] {
    const withCoords = points.find(
        (p) => p.latitude !== null && p.longitude !== null,
    );

    return withCoords
        ? [withCoords.latitude as number, withCoords.longitude as number]
        : fallback;
}

function LeafletMapPicker({
    points,
    onPointsChange,
    tileUrl,
    attribution,
    fallbackCenter,
    height,
}: {
    points: MapPoint[];
    onPointsChange: (points: MapPoint[]) => void;
    tileUrl: string;
    attribution: string;
    fallbackCenter: [number, number];
    height: number;
}) {
    const containerRef = useRef<HTMLDivElement>(null);
    const mapRef = useRef<L.Map | null>(null);
    const markersRef = useRef<Record<string, L.Marker>>({});
    const polylineRef = useRef<L.Polyline | null>(null);
    const pointsRef = useRef(points);
    const onPointsChangeRef = useRef(onPointsChange);
    const [activePointId, setActivePointId] = useState<string | null>(
        points[0]?.id ?? null,
    );
    const activePointIdRef = useRef(activePointId);

    useEffect(() => {
        pointsRef.current = points;
    }, [points]);

    useEffect(() => {
        onPointsChangeRef.current = onPointsChange;
    }, [onPointsChange]);

    useEffect(() => {
        activePointIdRef.current = activePointId;
    }, [activePointId]);

    // Map init — runs once. Markers/polyline are synced in the effect below,
    // not recreated here, so panning/zoom state survives point edits.
    useEffect(() => {
        if (!containerRef.current || mapRef.current) {
            return;
        }

        const map = L.map(containerRef.current).setView(
            firstKnownCenter(pointsRef.current, fallbackCenter),
            firstKnownCenter(pointsRef.current, fallbackCenter) ===
                fallbackCenter
                ? 7
                : 12,
        );

        L.tileLayer(tileUrl, { attribution, maxZoom: 19 }).addTo(map);

        map.on('click', (event: L.LeafletMouseEvent) => {
            const targetId = activePointIdRef.current;

            if (!targetId) {
                return;
            }

            const next = pointsRef.current.map((point) =>
                point.id === targetId
                    ? {
                          ...point,
                          latitude: Math.round(event.latlng.lat * 1e6) / 1e6,
                          longitude: Math.round(event.latlng.lng * 1e6) / 1e6,
                      }
                    : point,
            );
            onPointsChangeRef.current(next);
        });

        mapRef.current = map;

        return () => {
            map.remove();
            mapRef.current = null;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Sync markers + polyline whenever points change.
    useEffect(() => {
        const map = mapRef.current;

        if (!map) {
            return;
        }

        const seenIds = new Set<string>();

        points.forEach((point, index) => {
            seenIds.add(point.id);

            if (point.latitude === null || point.longitude === null) {
                markersRef.current[point.id]?.remove();
                delete markersRef.current[point.id];

                return;
            }

            const latLng: L.LatLngExpression = [
                point.latitude,
                point.longitude,
            ];
            const existing = markersRef.current[point.id];

            if (existing) {
                existing.setLatLng(latLng);
                existing.setTooltipContent(`${index + 1}. ${point.label}`);
            } else {
                const marker = L.marker(latLng, { draggable: true })
                    .addTo(map)
                    .bindTooltip(`${index + 1}. ${point.label}`, {
                        permanent: false,
                    });

                marker.on('click', () => setActivePointId(point.id));
                marker.on('dragend', () => {
                    const pos = marker.getLatLng();
                    const next = pointsRef.current.map((p) =>
                        p.id === point.id
                            ? {
                                  ...p,
                                  latitude: Math.round(pos.lat * 1e6) / 1e6,
                                  longitude: Math.round(pos.lng * 1e6) / 1e6,
                              }
                            : p,
                    );
                    onPointsChangeRef.current(next);
                });

                markersRef.current[point.id] = marker;
            }
        });

        // Remove markers for points no longer present.
        Object.keys(markersRef.current).forEach((id) => {
            if (!seenIds.has(id)) {
                markersRef.current[id].remove();
                delete markersRef.current[id];
            }
        });

        // Trajectory line through ordered points with known coordinates.
        polylineRef.current?.remove();
        const line = points
            .filter((p) => p.latitude !== null && p.longitude !== null)
            .map((p) => [p.latitude, p.longitude]) as L.LatLngExpression[];

        if (line.length > 1) {
            polylineRef.current = L.polyline(line, {
                color: '#c9a227',
                weight: 3,
                dashArray: '6 6',
            }).addTo(map);
        } else {
            polylineRef.current = null;
        }
    }, [points]);

    return (
        <div className="map-picker">
            <div
                ref={containerRef}
                className="map-picker-canvas"
                style={{ height }}
            />
            <MapPickerLegend
                points={points}
                activePointId={activePointId}
                onSelectPoint={setActivePointId}
                onCoordinateEdit={(id, latitude, longitude) =>
                    onPointsChange(
                        points.map((p) =>
                            p.id === id ? { ...p, latitude, longitude } : p,
                        ),
                    )
                }
            />
        </div>
    );
}

function GoogleMapPicker({
    points,
    onPointsChange,
    apiKey,
    fallbackCenter,
    height,
}: {
    points: MapPoint[];
    onPointsChange: (points: MapPoint[]) => void;
    apiKey: string | null;
    fallbackCenter: [number, number];
    height: number;
}) {
    const containerRef = useRef<HTMLDivElement>(null);
    const mapRef = useRef<any>(null);
    const markersRef = useRef<Record<string, any>>({});
    const [status, setStatus] = useState<'loading' | 'ready' | 'error'>(
        apiKey ? 'loading' : 'error',
    );
    const pointsRef = useRef(points);
    const onPointsChangeRef = useRef(onPointsChange);
    const [activePointId, setActivePointId] = useState<string | null>(
        points[0]?.id ?? null,
    );
    const activePointIdRef = useRef(activePointId);

    useEffect(() => {
        pointsRef.current = points;
    }, [points]);

    useEffect(() => {
        onPointsChangeRef.current = onPointsChange;
    }, [onPointsChange]);

    useEffect(() => {
        activePointIdRef.current = activePointId;
    }, [activePointId]);

    useEffect(() => {
        if (!apiKey || !containerRef.current) {
            return;
        }

        let cancelled = false;

        loadGoogleMaps(apiKey)
            .then(() => {
                if (cancelled || !containerRef.current) {
                    return;
                }

                const google = (window as any).google;
                const center = firstKnownCenter(
                    pointsRef.current,
                    fallbackCenter,
                );
                const map = new google.maps.Map(containerRef.current, {
                    center: { lat: center[0], lng: center[1] },
                    zoom: 12,
                });

                map.addListener('click', (event: any) => {
                    const targetId = activePointIdRef.current;

                    if (!targetId) {
                        return;
                    }

                    const next = pointsRef.current.map((point) =>
                        point.id === targetId
                            ? {
                                  ...point,
                                  latitude:
                                      Math.round(event.latLng.lat() * 1e6) /
                                      1e6,
                                  longitude:
                                      Math.round(event.latLng.lng() * 1e6) /
                                      1e6,
                              }
                            : point,
                    );
                    onPointsChangeRef.current(next);
                });

                mapRef.current = map;
                setStatus('ready');
            })
            .catch(() => setStatus('error'));

        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [apiKey]);

    useEffect(() => {
        const map = mapRef.current;

        if (status !== 'ready' || !map) {
            return;
        }

        const google = (window as any).google;
        const seenIds = new Set<string>();

        points.forEach((point) => {
            seenIds.add(point.id);

            if (point.latitude === null || point.longitude === null) {
                markersRef.current[point.id]?.setMap(null);
                delete markersRef.current[point.id];

                return;
            }

            const position = { lat: point.latitude, lng: point.longitude };
            const existing = markersRef.current[point.id];

            if (existing) {
                existing.setPosition(position);
            } else {
                const marker = new google.maps.Marker({
                    position,
                    map,
                    draggable: true,
                    label: point.label.slice(0, 1).toUpperCase(),
                });

                marker.addListener('click', () => setActivePointId(point.id));
                marker.addListener('dragend', () => {
                    const pos = marker.getPosition();
                    const next = pointsRef.current.map((p) =>
                        p.id === point.id
                            ? {
                                  ...p,
                                  latitude: Math.round(pos.lat() * 1e6) / 1e6,
                                  longitude: Math.round(pos.lng() * 1e6) / 1e6,
                              }
                            : p,
                    );
                    onPointsChangeRef.current(next);
                });

                markersRef.current[point.id] = marker;
            }
        });

        Object.keys(markersRef.current).forEach((id) => {
            if (!seenIds.has(id)) {
                markersRef.current[id].setMap(null);
                delete markersRef.current[id];
            }
        });
    }, [points, status]);

    if (status === 'error') {
        return (
            <div className="map-picker">
                <div
                    className="map-picker-canvas map-picker-unavailable"
                    style={{ height }}
                >
                    <AlertTriangle className="size-5" />
                    <p>
                        {apiKey
                            ? 'Échec du chargement de Google Maps.'
                            : 'Clé API Google Maps non configurée (MAP_GOOGLE_MAPS_API_KEY). Bascule sur OpenStreetMap dans les paramètres de carte.'}
                    </p>
                </div>
                <MapPickerLegend
                    points={points}
                    activePointId={activePointId}
                    onSelectPoint={setActivePointId}
                    onCoordinateEdit={(id, latitude, longitude) =>
                        onPointsChange(
                            points.map((p) =>
                                p.id === id ? { ...p, latitude, longitude } : p,
                            ),
                        )
                    }
                />
            </div>
        );
    }

    return (
        <div className="map-picker">
            <div
                ref={containerRef}
                className="map-picker-canvas"
                style={{ height }}
            />
            {status === 'loading' && (
                <p className="map-picker-status">Chargement de Google Maps…</p>
            )}
            <MapPickerLegend
                points={points}
                activePointId={activePointId}
                onSelectPoint={setActivePointId}
                onCoordinateEdit={(id, latitude, longitude) =>
                    onPointsChange(
                        points.map((p) =>
                            p.id === id ? { ...p, latitude, longitude } : p,
                        ),
                    )
                }
            />
        </div>
    );
}

function MapPickerLegend({
    points,
    activePointId,
    onSelectPoint,
    onCoordinateEdit,
}: {
    points: MapPoint[];
    activePointId: string | null;
    onSelectPoint: (id: string) => void;
    onCoordinateEdit: (
        id: string,
        latitude: number | null,
        longitude: number | null,
    ) => void;
}) {
    const distances = cumulativeDistances(points);

    return (
        <div className="map-picker-legend">
            <p className="map-picker-hint">
                <MapPin className="size-3.5" />
                Sélectionnez un arrêt puis cliquez sur la carte (ou glissez le
                marqueur) pour le positionner. Les coordonnées restent
                modifiables manuellement ci-dessous.
            </p>
            <div className="map-picker-points">
                {points.map((point, index) => (
                    <div
                        key={point.id}
                        className={
                            'map-picker-point' +
                            (point.id === activePointId
                                ? ' map-picker-point-active'
                                : '')
                        }
                        onClick={() => onSelectPoint(point.id)}
                    >
                        <span className="map-picker-point-index">
                            {index + 1}
                        </span>
                        <span className="map-picker-point-label">
                            {point.label}
                        </span>
                        <input
                            className="control map-picker-coord"
                            type="number"
                            step="0.000001"
                            placeholder="Latitude"
                            value={point.latitude ?? ''}
                            onClick={(e) => e.stopPropagation()}
                            onChange={(e) =>
                                onCoordinateEdit(
                                    point.id,
                                    e.target.value === ''
                                        ? null
                                        : Number(e.target.value),
                                    point.longitude,
                                )
                            }
                        />
                        <input
                            className="control map-picker-coord"
                            type="number"
                            step="0.000001"
                            placeholder="Longitude"
                            value={point.longitude ?? ''}
                            onClick={(e) => e.stopPropagation()}
                            onChange={(e) =>
                                onCoordinateEdit(
                                    point.id,
                                    point.latitude,
                                    e.target.value === ''
                                        ? null
                                        : Number(e.target.value),
                                )
                            }
                        />
                        <span className="map-picker-point-distance">
                            {distances[index] === null ? (
                                '—'
                            ) : (
                                <>
                                    <RouteIcon className="size-3" />
                                    {distances[index]} km
                                </>
                            )}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}
