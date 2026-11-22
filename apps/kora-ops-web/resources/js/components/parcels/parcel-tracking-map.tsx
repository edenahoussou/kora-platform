import 'leaflet/dist/leaflet.css';
import { usePage } from '@inertiajs/react';
import type * as Leaflet from 'leaflet';
import { Maximize2, Minimize2, LocateFixed, MapPin } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
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
    className?: string;
};

type MapConfig = {
    osm?: {
        tileUrl?: string;
        attribution?: string;
    };
};

const KORA = '#001d4e';
const GOLD = '#f6b000';
const FALLBACK_TILE = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const FALLBACK_ATTRIBUTION =
    '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

const DATE_FORMATTER = new Intl.DateTimeFormat('fr-FR', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'Africa/Porto-Novo',
});

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
    return `${point.latitude.toFixed(5)}|${point.longitude.toFixed(5)}|${point.role}|${point.kind ?? ''}`;
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

export function ParcelTrackingMap({
    planned,
    traveled,
    current,
    vehicle,
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

    const hasGeometry = planned.length > 0 || traveled.length > 0 || current;

    useEffect(() => {
        if (!containerRef.current || mapRef.current) {
            return;
        }

        let disposed = false;

        void import('leaflet').then((module) => {
            if (disposed || !containerRef.current) {
                return;
            }

            const L = (module.default ?? module) as typeof Leaflet;
            const instance = L.map(containerRef.current, {
                zoomControl: false,
                attributionControl: true,
                scrollWheelZoom: false,
                preferCanvas: true,
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
            setReady(true);
        });

        return () => {
            disposed = true;
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
            const group = groupRef.current;

            group.clearLayers();

            if (planned.length > 1) {
                L.polyline(
                    planned.map((point) => [point.latitude, point.longitude]),
                    {
                        color: GOLD,
                        weight: 8,
                        opacity: 0.9,
                        dashArray: '10 7',
                        lineCap: 'round',
                        lineJoin: 'round',
                    },
                ).addTo(group);
            }

            if (traveled.length > 1) {
                L.polyline(
                    traveled.map((point) => [point.latitude, point.longitude]),
                    {
                        color: KORA,
                        weight: 3.5,
                        opacity: 0.95,
                        lineCap: 'round',
                        lineJoin: 'round',
                    },
                ).addTo(group);
            }

            const addPoint = (point: ParcelTrackingPoint) => {
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
                ).addTo(group);

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
                const key = pointKey(point);

                if (seen.has(key)) {
                    continue;
                }

                seen.add(key);
                addPoint(point);
            }

            if (vehicle?.registration_number && current?.role === 'current') {
                const label = L.divIcon({
                    className: '',
                    iconSize: [0, 0],
                    html: `<div style="transform: translate(-50%, -100%); white-space: nowrap; border-radius: 7px; background: ${KORA}; color: #fff; border: 2px solid ${GOLD}; padding: 2px 7px; font-size: 10px; font-weight: 800; box-shadow: 0 4px 12px rgba(0,0,0,.28);">${escapeHtml(vehicle.registration_number)}</div>`,
                });
                L.marker([current.latitude, current.longitude], {
                    icon: label,
                    interactive: false,
                }).addTo(group);
            }

            const points = [
                ...planned,
                ...traveled,
                ...(current ? [current] : []),
            ];

            if (points.length > 0) {
                const bounds = L.latLngBounds(
                    points.map(
                        (point) =>
                            [point.latitude, point.longitude] as [
                                number,
                                number,
                            ],
                    ),
                );

                instance.fitBounds(bounds, {
                    padding: [38, 38],
                    maxZoom: 10,
                });
            }
        });

        return () => {
            disposed = true;
        };
    }, [current, planned, ready, traveled, vehicle]);

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
                        expanded ? 'h-[540px]' : 'h-[320px]',
                    )}
                />

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

                            if (!instance || planned.length === 0) {
                                return;
                            }

                            void import('leaflet').then((module) => {
                                const L = (module.default ??
                                    module) as typeof Leaflet;
                                const points = [
                                    ...planned,
                                    ...traveled,
                                    ...(current ? [current] : []),
                                ];
                                const bounds = L.latLngBounds(
                                    points.map(
                                        (point) =>
                                            [
                                                point.latitude,
                                                point.longitude,
                                            ] as [number, number],
                                    ),
                                );
                                instance.fitBounds(bounds, {
                                    padding: [38, 38],
                                    maxZoom: 10,
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
