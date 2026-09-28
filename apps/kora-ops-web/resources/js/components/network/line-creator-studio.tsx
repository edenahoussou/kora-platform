import 'leaflet/dist/leaflet.css';
import { useForm } from '@inertiajs/react';
import L from 'leaflet';
import {
    ArrowDown,
    ArrowLeftRight,
    ArrowUp,
    Check,
    Clock,
    Compass,
    Info,
    MapPin,
    Maximize2,
    Navigation,
    RotateCcw,
    Route,
    Search,
    Trash2,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import network from '@/routes/network';
import type {
    PlaceResult,
    RouteCalculationResult,
    RoutePoint,
} from './route-calculator';
import {
    calculateRoadRoute,
    haversineKm,
    reverseGeocode,
    searchPlaces,
} from './route-calculator';
import type {
    LineRecord,
    LineStopRecord,
    PhysicalStationRecord,
} from './types';

let stopIdCounter = 0;
function createStopUuid(): string {
    stopIdCounter += 1;

    return `stop-${stopIdCounter}-${Math.random().toString(36).slice(2, 8)}`;
}

type StopPayload = {
    uuid?: string;
    stop_order: number;
    km_from_origin: number;
    latitude: number | null;
    longitude: number | null;
    name: string;
    city: string;
    country: string;
    physical_stop_uuid?: string;
    physical_stop?: {
        name: string;
        city: string;
        country: string;
        latitude: number | null;
        longitude: number | null;
    };
};

type StudioFormData = {
    code: string;
    name: string;
    type: string;
    bidirectional: boolean;
    status: string;
    distance_km: number | null;
    estimated_duration_minutes: number | null;
    countries_crossed: string[];
    stops: StopPayload[];
};

type Props = {
    open: boolean;
    onClose: () => void;
    physicalStations: PhysicalStationRecord[];
    initialLine?: LineRecord | null;
};

// Coordonnées par défaut : Centre Bénin (Dassa / Savè)
const DEFAULT_CENTER: [number, number] = [7.5, 2.2];
const DEFAULT_ZOOM = 7;

export function LineCreatorStudio({
    open,
    onClose,
    physicalStations,
    initialLine,
}: Props) {
    // Soumission Inertia vers le backend (payload construit à la sauvegarde)
    const form = useForm<StudioFormData>({
        code: initialLine?.code ?? '',
        name: initialLine?.name ?? '',
        type: initialLine?.type ?? 'nationale',
        bidirectional: initialLine?.bidirectional ?? true,
        status: initialLine?.status ?? 'draft',
        distance_km: null,
        estimated_duration_minutes: null,
        countries_crossed: [],
        stops: [],
    });

    // Métadonnées de la ligne
    const [name, setName] = useState(initialLine?.name ?? '');
    const [code, setCode] = useState(initialLine?.code ?? '');
    const [type, setType] = useState<
        'nationale' | 'internationale' | 'regionale'
    >(initialLine?.type ?? 'nationale');
    const [bidirectional, setBidirectional] = useState<boolean>(
        initialLine?.bidirectional ?? true,
    );
    const [activeDirection, setActiveDirection] = useState<
        'outbound' | 'return'
    >('outbound');

    // Arrêts ordonnés du sens Aller
    const [stops, setStops] = useState<LineStopRecord[]>(
        initialLine?.stops ?? [],
    );

    // Calcul d'itinéraire
    const [routeResult, setRouteResult] =
        useState<RouteCalculationResult | null>(null);
    // Cumul kilométrique du sens Aller (autorité pour km_from_origin au save)
    const outboundKmRef = useRef<number[]>([]);

    // Recherche de gare
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState<PhysicalStationRecord[]>(
        [],
    );
    const [placeResults, setPlaceResults] = useState<PlaceResult[]>([]);
    const [isSearching, setIsSearching] = useState(false);
    const [isPlacesLoading, setIsPlacesLoading] = useState(false);

    // Arrêt sélectionné / en surbrillance
    const [selectedStopUuid, setSelectedStopUuid] = useState<string | null>(
        null,
    );

    // Parcours d'onboarding du studio (masquable)
    const [onboardingDismissed, setOnboardingDismissed] = useState(false);

    // Carte Leaflet
    const mapContainerRef = useRef<HTMLDivElement>(null);
    const mapInstanceRef = useRef<L.Map | null>(null);
    const markersRef = useRef<Record<string, L.Marker>>({});
    const polylineRef = useRef<L.Polyline | null>(null);
    const polylineHaloRef = useRef<L.Polyline | null>(null);

    // Arrêts effectivement affichés selon le sens choisi (Aller ou Retour inversé)
    const displayStops = useMemo(() => {
        if (activeDirection === 'outbound') {
            return stops;
        }

        // Sens retour : ordre inversé
        const reversed = [...stops].reverse();

        return reversed.map((st, idx) => ({
            ...st,
            stopOrder: idx + 1,
        }));
    }, [stops, activeDirection]);

    const activeRouteResult = displayStops.length < 2 ? null : routeResult;

    // Cumul/étape affichés : privilégier le tracé calculé quand il couvre tous les arrêts
    const allGeolocated = displayStops.every(
        (s) => s.latitude !== null && s.longitude !== null,
    );
    const kmAt = (idx: number): number =>
        allGeolocated && routeResult?.cumulativeDistances[idx] !== undefined
            ? routeResult.cumulativeDistances[idx]
            : (displayStops[idx]?.kmFromOrigin ?? 0);
    const legKmAt = (idx: number): number | undefined =>
        allGeolocated && routeResult?.legDistances[idx] !== undefined
            ? routeResult.legDistances[idx]
            : displayStops[idx]?.legDistanceKm;

    // Ajout centralisé d'un arrêt avec inférence intelligente
    const addStop = useCallback(
        (
            stopData: Omit<
                LineStopRecord,
                | 'uuid'
                | 'stopOrder'
                | 'kmFromOrigin'
                | 'legDistanceKm'
                | 'status'
            >,
        ) => {
            const nextOrder = stops.length + 1;
            const newStop: LineStopRecord = {
                ...stopData,
                uuid: createStopUuid(),
                stopOrder: nextOrder,
                kmFromOrigin: 0,
                legDistanceKm: 0,
                status: 'active',
            };

            const nextStops = [...stops, newStop];
            setStops(nextStops);
            setSelectedStopUuid(newStop.uuid);

            if (!code && nextStops.length >= 2) {
                const originCity = nextStops[0].city
                    .substring(0, 3)
                    .toUpperCase();
                const destCity = nextStops[nextStops.length - 1].city
                    .substring(0, 3)
                    .toUpperCase();
                setCode(`${originCity}-${destCity}`);
            }

            const countries = Array.from(
                new Set(nextStops.map((s) => s.country)),
            );
            const origin = nextStops[0];
            const terminus = nextStops[nextStops.length - 1];

            if (countries.length > 1) {
                setType('internationale');
            } else if (
                nextStops.length >= 2 &&
                origin.latitude !== null &&
                origin.longitude !== null &&
                terminus.latitude !== null &&
                terminus.longitude !== null &&
                haversineKm(
                    origin.latitude,
                    origin.longitude,
                    terminus.latitude,
                    terminus.longitude,
                ) > 200
            ) {
                setType('nationale');
            }
        },
        [stops, code],
    );

    // Recalcul du tracé routier à chaque modification d'arrêts
    const geoStops = displayStops.filter(
        (s): s is LineStopRecord & { latitude: number; longitude: number } =>
            s.latitude !== null && s.longitude !== null,
    );
    const stopsSignature = geoStops
        .map((s) => `${s.uuid}@${s.latitude},${s.longitude}`)
        .join('|');
    const [computedSignature, setComputedSignature] = useState('');
    const isCalculatingRoute =
        geoStops.length >= 2 && computedSignature !== stopsSignature;

    useEffect(() => {
        const signature = geoStops
            .map((s) => `${s.uuid}@${s.latitude},${s.longitude}`)
            .join('|');

        if (geoStops.length < 2) {
            return;
        }

        let isCancelled = false;

        const routePoints: RoutePoint[] = geoStops.map((s) => ({
            id: s.uuid,
            label: s.name,
            city: s.city,
            country: s.country,
            latitude: s.latitude,
            longitude: s.longitude,
        }));

        calculateRoadRoute(routePoints).then((result) => {
            if (isCancelled) {
                return;
            }

            setRouteResult(result);
            setComputedSignature(signature);

            // Le cumul de l'aller fait foi pour km_from_origin au save
            if (activeDirection === 'outbound') {
                outboundKmRef.current = result.cumulativeDistances;
            }
        });

        return () => {
            isCancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [stopsSignature, activeDirection]);

    const addStopRef = useRef(addStop);

    useEffect(() => {
        addStopRef.current = addStop;
    }, [addStop]);

    // Initialisation de la carte Leaflet
    useEffect(() => {
        if (!open || !mapContainerRef.current || mapInstanceRef.current) {
            return;
        }

        const map = L.map(mapContainerRef.current, {
            zoomControl: false,
            attributionControl: false,
        }).setView(DEFAULT_CENTER, DEFAULT_ZOOM);

        L.control.zoom({ position: 'bottomright' }).addTo(map);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(map);

        // Clic sur la carte : ajout immédiat d'un arrêt
        map.on('click', async (e: L.LeafletMouseEvent) => {
            const lat = Math.round(e.latlng.lat * 1000000) / 1000000;
            const lng = Math.round(e.latlng.lng * 1000000) / 1000000;

            const geo = await reverseGeocode(lat, lng);

            addStopRef.current({
                name: geo.label,
                city: geo.city,
                country: geo.country,
                latitude: lat,
                longitude: lng,
            });
        });

        mapInstanceRef.current = map;

        // Redimensionnement automatique pour éviter tout glitch d'affichage
        setTimeout(() => {
            map.invalidateSize();
        }, 200);

        return () => {
            map.remove();
            mapInstanceRef.current = null;
            markersRef.current = {};
            polylineRef.current = null;
            polylineHaloRef.current = null;
        };
    }, [open]);

    // Synchronisation des marqueurs sur la carte
    useEffect(() => {
        const map = mapInstanceRef.current;

        if (!map) {
            return;
        }

        const currentMarkerUuids = new Set<string>();

        displayStops.forEach((stop, index) => {
            if (stop.latitude === null || stop.longitude === null) {
                return;
            }

            currentMarkerUuids.add(stop.uuid);
            const isFirst = index === 0;
            const isLast =
                index === displayStops.length - 1 && displayStops.length > 1;
            const isSelected = stop.uuid === selectedStopUuid;

            const markerHtml = `
                <div class="kora-stop-marker flex flex-col items-center cursor-pointer transition-transform duration-200 ${isSelected ? 'scale-125 z-50' : 'hover:scale-110'}">
                    <div class="relative flex items-center justify-center">
                        <div class="size-8 rounded-full border-2 border-white shadow-xl flex items-center justify-center font-extrabold text-xs ${
                            isFirst
                                ? 'bg-[var(--kora)] text-gold ring-4 ring-gold/40'
                                : isLast
                                  ? 'bg-[var(--kora)] text-white ring-4 ring-[var(--digital)]/40'
                                  : 'bg-gold text-[var(--kora)]'
                        }">
                            ${(index + 1).toString().padStart(2, '0')}
                        </div>
                    </div>
                    <div class="mt-1 px-1.5 py-0.5 rounded bg-black/80 text-[10px] font-bold text-white shadow whitespace-nowrap backdrop-blur-sm pointer-events-none">
                        ${stop.name}
                    </div>
                </div>
            `;

            const icon = L.divIcon({
                html: markerHtml,
                className: 'custom-stop-marker',
                iconSize: [36, 48],
                iconAnchor: [18, 24],
            });

            const existingMarker = markersRef.current[stop.uuid];

            if (existingMarker) {
                existingMarker.setLatLng([stop.latitude, stop.longitude]);
                existingMarker.setIcon(icon);
            } else {
                const marker = L.marker([stop.latitude, stop.longitude], {
                    icon,
                    draggable: activeDirection === 'outbound',
                }).addTo(map);

                marker.on('click', () => {
                    setSelectedStopUuid(stop.uuid);
                });

                // Déplacement par drag & drop : capture nouvelle position GPS
                marker.on('dragend', async () => {
                    const pos = marker.getLatLng();
                    const newLat = Math.round(pos.lat * 1000000) / 1000000;
                    const newLng = Math.round(pos.lng * 1000000) / 1000000;

                    const geo = await reverseGeocode(newLat, newLng);

                    setStops((prev) =>
                        prev.map((s) =>
                            s.uuid === stop.uuid
                                ? {
                                      ...s,
                                      latitude: newLat,
                                      longitude: newLng,
                                      city: geo.city,
                                      country: geo.country,
                                  }
                                : s,
                        ),
                    );
                });

                markersRef.current[stop.uuid] = marker;
            }
        });

        // Nettoyage des marqueurs supprimés
        Object.keys(markersRef.current).forEach((uuid) => {
            if (!currentMarkerUuids.has(uuid)) {
                markersRef.current[uuid].remove();
                delete markersRef.current[uuid];
            }
        });
    }, [displayStops, selectedStopUuid, activeDirection]);

    // Tracé de la polyline (route routière)
    useEffect(() => {
        const map = mapInstanceRef.current;

        if (!map) {
            return;
        }

        polylineRef.current?.remove();
        polylineHaloRef.current?.remove();

        if (
            !activeRouteResult ||
            activeRouteResult.geometryCoordinates.length < 2
        ) {
            polylineRef.current = null;
            polylineHaloRef.current = null;

            return;
        }

        // Halo lumineux extérieur
        polylineHaloRef.current = L.polyline(
            activeRouteResult.geometryCoordinates,
            {
                color: '#c9a227',
                weight: 7,
                opacity: 0.5,
                lineCap: 'round',
                lineJoin: 'round',
            },
        ).addTo(map);

        // Ligne principale de la route
        polylineRef.current = L.polyline(
            activeRouteResult.geometryCoordinates,
            {
                color: '#001d4e',
                weight: 4,
                opacity: 0.95,
                lineCap: 'round',
                lineJoin: 'round',
            },
        ).addTo(map);
    }, [activeRouteResult]);

    // Ajuster automatiquement les limites de la carte pour englober le trajet
    const fitRouteBounds = () => {
        const map = mapInstanceRef.current;
        const geoStops = displayStops.filter(
            (s) => s.latitude !== null && s.longitude !== null,
        );

        if (!map || geoStops.length === 0) {
            return;
        }

        const bounds = L.latLngBounds(
            geoStops.map(
                (s) => [s.latitude!, s.longitude!] as [number, number],
            ),
        );
        map.fitBounds(bounds, { padding: [50, 50], maxZoom: 14 });
    };

    // Recherche de gares physiques + recherche de lieux sur la carte (debounced OSM)
    const placesSearchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(
        null,
    );

    const handleSearchQueryChange = (query: string) => {
        setSearchQuery(query);
        const trimmed = query.trim();

        if (!trimmed) {
            setSearchResults([]);
            setPlaceResults([]);
            setIsSearching(false);
            setIsPlacesLoading(false);

            return;
        }

        setIsSearching(true);
        const lower = trimmed.toLowerCase();
        const filtered = physicalStations.filter(
            (st) =>
                st.latitude !== null &&
                st.longitude !== null &&
                (st.name.toLowerCase().includes(lower) ||
                    st.city.toLowerCase().includes(lower) ||
                    st.code.toLowerCase().includes(lower)),
        );
        setSearchResults(filtered);

        // Recherche de lieux OpenStreetMap débounced (respect politique Nominatim ~1 req/s)
        if (placesSearchTimeoutRef.current) {
            clearTimeout(placesSearchTimeoutRef.current);
        }

        if (trimmed.length < 3) {
            setPlaceResults([]);
            setIsPlacesLoading(false);

            return;
        }

        setIsPlacesLoading(true);
        placesSearchTimeoutRef.current = setTimeout(async () => {
            try {
                const results = await searchPlaces(trimmed);
                setPlaceResults(results);
            } catch {
                setPlaceResults([]);
            } finally {
                setIsPlacesLoading(false);
            }
        }, 600);
    };

    const clearSearchState = () => {
        setSearchQuery('');
        setSearchResults([]);
        setPlaceResults([]);
        setIsSearching(false);
        setIsPlacesLoading(false);
    };

    // Ajouter une gare depuis la liste des résultats de recherche
    const handleAddPhysicalStation = (station: PhysicalStationRecord) => {
        if (station.latitude === null || station.longitude === null) {
            return;
        }

        addStop({
            name: station.name,
            city: station.city,
            country: station.country,
            latitude: station.latitude,
            longitude: station.longitude,
            physicalStopUuid: station.uuid,
        });

        clearSearchState();

        // Centrer la carte sur la nouvelle gare
        mapInstanceRef.current?.flyTo(
            [station.latitude, station.longitude],
            12,
            {
                duration: 0.8,
            },
        );
    };

    // Ajouter un lieu trouvé sur la carte (géocodage OSM) — physical stop résolu côté backend
    const handleAddMapPlace = (place: PlaceResult) => {
        addStop({
            name: place.label,
            city: place.city,
            country: place.country,
            latitude: place.latitude,
            longitude: place.longitude,
        });

        clearSearchState();

        // Positionner la carte sur le lieu pour ajustement fin du marqueur
        mapInstanceRef.current?.flyTo([place.latitude, place.longitude], 15, {
            duration: 0.9,
        });
    };

    // Réordonner un arrêt vers le haut
    const moveStopUp = (index: number) => {
        if (index <= 0) {
            return;
        }

        const newStops = [...stops];
        const temp = newStops[index - 1];
        newStops[index - 1] = newStops[index];
        newStops[index] = temp;

        // Mise à jour de stopOrder
        newStops.forEach((s, idx) => {
            s.stopOrder = idx + 1;
        });

        setStops(newStops);
    };

    // Réordonner un arrêt vers le bas
    const moveStopDown = (index: number) => {
        if (index >= stops.length - 1) {
            return;
        }

        const newStops = [...stops];
        const temp = newStops[index + 1];
        newStops[index + 1] = newStops[index];
        newStops[index] = temp;

        newStops.forEach((s, idx) => {
            s.stopOrder = idx + 1;
        });

        setStops(newStops);
    };

    // Supprimer un arrêt
    const removeStop = (stopUuid: string) => {
        const filtered = stops.filter((s) => s.uuid !== stopUuid);
        filtered.forEach((s, idx) => {
            s.stopOrder = idx + 1;
        });
        setStops(filtered);

        if (selectedStopUuid === stopUuid) {
            setSelectedStopUuid(null);
        }
    };

    // Centrer la carte sur un arrêt précis
    const focusOnStop = (stop: LineStopRecord) => {
        setSelectedStopUuid(stop.uuid);

        if (stop.latitude === null || stop.longitude === null) {
            return;
        }

        mapInstanceRef.current?.flyTo([stop.latitude, stop.longitude], 13, {
            duration: 0.7,
        });
    };

    // Sauvegarde de la ligne vers le backend (création ou mise à jour)
    const handleSave = () => {
        if (stops.length < 2) {
            return;
        }

        const origin = stops[0];
        const terminus = stops[stops.length - 1];
        const totalDistance =
            activeDirection === 'outbound'
                ? (activeRouteResult?.totalDistanceKm ??
                  outboundKmRef.current.at(-1) ??
                  terminus.kmFromOrigin)
                : (outboundKmRef.current.at(-1) ?? terminus.kmFromOrigin);
        const duration = activeRouteResult?.estimatedDurationMinutes ?? null;
        const countries = Array.from(new Set(stops.map((s) => s.country)));

        const stopsPayload: StopPayload[] = stops.map((s, idx) => ({
            ...(s.uuid.startsWith('stop-') ? {} : { uuid: s.uuid }),
            stop_order: idx + 1,
            km_from_origin: outboundKmRef.current[idx] ?? s.kmFromOrigin,
            latitude: s.latitude,
            longitude: s.longitude,
            name: s.name,
            city: s.city,
            country: s.country,
            ...(s.physicalStopUuid
                ? { physical_stop_uuid: s.physicalStopUuid }
                : {
                      physical_stop: {
                          name: s.name,
                          city: s.city,
                          country: s.country,
                          latitude: s.latitude,
                          longitude: s.longitude,
                      },
                  }),
        }));

        form.transform((): StudioFormData => ({
            code:
                code.trim() ||
                `${origin.city.substring(0, 3).toUpperCase()}-${terminus.city.substring(0, 3).toUpperCase()}`,
            name: name.trim() || `Liaison ${origin.city} → ${terminus.city}`,
            type,
            bidirectional,
            status: initialLine?.status ?? 'draft',
            distance_km:
                totalDistance !== null ? Math.round(totalDistance) : null,
            estimated_duration_minutes:
                duration !== null ? Math.round(duration) : null,
            countries_crossed: countries,
            stops: stopsPayload,
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => onClose(),
        };

        if (initialLine) {
            form.put(network.update.url({ line: initialLine.uuid }), options);
        } else {
            form.post(network.store.url(), options);
        }
    };

    if (!open) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-50 flex animate-in flex-col bg-background duration-200 fade-in">
            {/* Header du Studio */}
            <header className="flex h-16 shrink-0 items-center justify-between border-b border-border bg-card px-6">
                <div className="flex items-center gap-3">
                    <span className="grid size-10 place-items-center rounded-xl bg-gold text-[var(--kora)] shadow-sm">
                        <Route className="size-5" />
                    </span>
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="text-[0.625rem] font-black tracking-widest text-gold uppercase">
                                Studio Cartographique
                            </span>
                            <span className="rounded-full bg-emerald-500/15 px-2 py-0.5 text-[0.625rem] font-bold text-emerald-600 dark:text-emerald-400">
                                Calcul routier actif
                            </span>
                        </div>
                        <h2 className="text-base font-extrabold tracking-tight text-foreground">
                            {initialLine
                                ? `Modifier ${initialLine.code} · ${initialLine.name}`
                                : 'Composer une nouvelle ligne commerciale'}
                        </h2>
                    </div>
                </div>

                <div className="flex items-center gap-3">
                    <button
                        type="button"
                        onClick={onClose}
                        className="btn btn-ghost"
                    >
                        Annuler
                    </button>
                    <button
                        type="button"
                        onClick={handleSave}
                        className="btn btn-primary"
                        disabled={stops.length < 2 || form.processing}
                    >
                        <Check className="size-4" />
                        {form.processing
                            ? 'Enregistrement…'
                            : 'Enregistrer la ligne'}
                    </button>
                </div>
            </header>

            {/* Erreurs de validation renvoyées par le backend */}
            {Object.keys(form.errors).length > 0 && (
                <div className="mx-6 mt-4 shrink-0 rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-xs text-rose-600 dark:text-rose-400">
                    <strong className="mb-1 block font-extrabold">
                        Impossible d'enregistrer la ligne
                    </strong>
                    <ul className="list-disc space-y-0.5 pl-4">
                        {Object.entries(form.errors).map(([key, message]) => (
                            <li key={key}>{message}</li>
                        ))}
                    </ul>
                </div>
            )}

            {/* Corps du Studio : 2 colonnes (Timeline & Métadonnées | Carte Leaflet) */}
            <div className="flex flex-1 overflow-hidden">
                {/* Volet Gauche : Panneau de contrôle et timeline des arrêts */}
                <aside className="z-10 flex w-[440px] shrink-0 flex-col overflow-hidden border-r border-border bg-card shadow-lg">
                    {/* Onglets de sens si ligne Aller-Retour */}
                    {bidirectional && (
                        <div className="flex gap-1.5 border-b border-border bg-muted/40 p-1.5">
                            <button
                                type="button"
                                onClick={() => setActiveDirection('outbound')}
                                className={cn(
                                    'flex flex-1 items-center justify-center gap-2 rounded-lg px-3 py-2 text-xs font-bold transition-all',
                                    activeDirection === 'outbound'
                                        ? 'bg-card text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                <Navigation className="size-3.5 rotate-45 text-[var(--kora)] dark:text-gold" />
                                Sens Aller ({stops[0]?.city || 'Origine'} →{' '}
                                {stops[stops.length - 1]?.city || 'Terminus'})
                            </button>
                            <button
                                type="button"
                                onClick={() => setActiveDirection('return')}
                                className={cn(
                                    'flex flex-1 items-center justify-center gap-2 rounded-lg px-3 py-2 text-xs font-bold transition-all',
                                    activeDirection === 'return'
                                        ? 'bg-card text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                <ArrowLeftRight className="size-3.5 text-gold" />
                                Sens Retour (Inversé)
                            </button>
                        </div>
                    )}

                    {/* Zone de contenu défilable */}
                    <div className="flex-1 space-y-5 overflow-y-auto p-5">
                        {/* 0. Parcours d'onboarding guidé */}
                        {!onboardingDismissed && (
                            <div className="relative space-y-2.5 rounded-xl border border-gold/30 bg-muted/30 p-3.5">
                                <button
                                    type="button"
                                    onClick={() => setOnboardingDismissed(true)}
                                    className="absolute top-2.5 right-2.5 rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                                    title="Masquer le guide"
                                >
                                    <X className="size-3.5" />
                                </button>
                                <span className="block text-[0.625rem] font-black tracking-widest text-gold uppercase">
                                    Comment composer votre ligne
                                </span>
                                <ol className="space-y-2">
                                    {[
                                        {
                                            done: stops.length >= 2,
                                            current: stops.length < 2,
                                            label: 'Placez vos arrêts',
                                            hint: 'Recherchez une gare ou un lieu, ou cliquez directement sur la carte.',
                                        },
                                        {
                                            done:
                                                stops.length >= 2 &&
                                                stops.every(
                                                    (s) =>
                                                        s.kmFromOrigin > 0 ||
                                                        s.stopOrder === 1,
                                                ),
                                            current: stops.length >= 2,
                                            label: 'Ordonnez et ajustez le parcours',
                                            hint: 'Réordonnez la timeline, glissez les marqueurs pour affiner chaque point.',
                                        },
                                        {
                                            done: false,
                                            current: stops.length >= 2,
                                            label: 'Vérifiez et enregistrez',
                                            hint: 'Distances, durées et sens retour sont calculés automatiquement.',
                                        },
                                    ].map((step, idx) => (
                                        <li
                                            key={idx}
                                            className="flex items-start gap-2.5"
                                        >
                                            <span
                                                className={cn(
                                                    'grid size-5 shrink-0 place-items-center rounded-full text-[0.625rem] font-black',
                                                    step.done
                                                        ? 'bg-gold text-[var(--kora)]'
                                                        : step.current
                                                          ? 'border-2 border-gold text-gold'
                                                          : 'border border-border text-muted-foreground',
                                                )}
                                            >
                                                {step.done ? (
                                                    <Check className="size-3" />
                                                ) : (
                                                    idx + 1
                                                )}
                                            </span>
                                            <div>
                                                <strong
                                                    className={cn(
                                                        'block text-[0.6875rem] font-bold',
                                                        step.current
                                                            ? 'text-foreground'
                                                            : 'text-muted-foreground',
                                                    )}
                                                >
                                                    {step.label}
                                                </strong>
                                                <span className="block text-[0.625rem] leading-4 text-muted-foreground">
                                                    {step.hint}
                                                </span>
                                            </div>
                                        </li>
                                    ))}
                                </ol>
                            </div>
                        )}

                        {/* 1. Métadonnées de la ligne */}
                        <div className="space-y-3 rounded-xl border border-border/80 bg-muted/30 p-4">
                            <div className="flex items-center justify-between">
                                <span className="text-[0.625rem] font-black tracking-wider text-muted-foreground uppercase">
                                    Identité de la ligne
                                </span>
                                <span className="rounded bg-gold/15 px-2 py-0.5 text-[0.6875rem] font-extrabold text-[var(--kora)] dark:text-gold">
                                    {type.toUpperCase()}
                                </span>
                            </div>

                            <div className="grid grid-cols-3 gap-2.5">
                                <div className="col-span-1 space-y-1">
                                    <Label
                                        htmlFor="studio-code"
                                        className="text-xs"
                                    >
                                        Code
                                    </Label>
                                    <Input
                                        id="studio-code"
                                        value={code}
                                        onChange={(e) =>
                                            setCode(
                                                e.target.value.toUpperCase(),
                                            )
                                        }
                                        placeholder="COT-PAR"
                                        className="h-9 text-xs font-bold uppercase"
                                    />
                                    {form.errors.code && (
                                        <p className="text-[0.6875rem] font-semibold text-rose-500">
                                            {form.errors.code}
                                        </p>
                                    )}
                                </div>
                                <div className="col-span-2 space-y-1">
                                    <Label
                                        htmlFor="studio-name"
                                        className="text-xs"
                                    >
                                        Nom commercial
                                    </Label>
                                    <Input
                                        id="studio-name"
                                        value={name}
                                        onChange={(e) =>
                                            setName(e.target.value)
                                        }
                                        placeholder="Ex: Ligne Express Nord"
                                        className="h-9 text-xs"
                                    />
                                    {form.errors.name && (
                                        <p className="text-[0.6875rem] font-semibold text-rose-500">
                                            {form.errors.name}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="flex items-center justify-between pt-1">
                                <label className="flex cursor-pointer items-center gap-2 text-xs font-medium">
                                    <input
                                        type="checkbox"
                                        checked={bidirectional}
                                        onChange={(e) =>
                                            setBidirectional(e.target.checked)
                                        }
                                        className="rounded border-border accent-gold"
                                    />
                                    Ligne Aller-Retour (Bidirectionnelle)
                                </label>
                                <select
                                    value={type}
                                    onChange={(e) =>
                                        setType(e.target.value as any)
                                    }
                                    className="h-8 rounded-lg border border-border bg-card px-2 text-xs font-medium"
                                >
                                    <option value="nationale">Nationale</option>
                                    <option value="internationale">
                                        Internationale
                                    </option>
                                    <option value="regionale">Régionale</option>
                                </select>
                            </div>
                        </div>

                        {/* 2. Synthèse métrique calculée en temps réel */}
                        <div className="grid grid-cols-3 gap-2.5">
                            <div className="rounded-xl border border-border/80 bg-card p-3 shadow-sm">
                                <span className="flex items-center gap-1 text-[0.625rem] font-bold text-muted-foreground uppercase">
                                    <Route className="size-3 text-gold" />{' '}
                                    Distance
                                </span>
                                <strong className="mt-1 block text-base font-extrabold text-foreground">
                                    {activeRouteResult
                                        ? `${activeRouteResult.totalDistanceKm} km`
                                        : '—'}
                                </strong>
                                <small className="text-[0.625rem] text-muted-foreground">
                                    {activeRouteResult?.isRoadRoute
                                        ? 'Réseau routier réel'
                                        : 'Vol d’oiseau estimé'}
                                </small>
                            </div>
                            <div className="rounded-xl border border-border/80 bg-card p-3 shadow-sm">
                                <span className="flex items-center gap-1 text-[0.625rem] font-bold text-muted-foreground uppercase">
                                    <Clock className="size-3 text-gold" /> Durée
                                    est.
                                </span>
                                <strong className="mt-1 block text-base font-extrabold text-foreground">
                                    {activeRouteResult &&
                                    activeRouteResult.estimatedDurationMinutes >
                                        0
                                        ? `${Math.floor(activeRouteResult.estimatedDurationMinutes / 60)}h${(activeRouteResult.estimatedDurationMinutes % 60).toString().padStart(2, '0')}`
                                        : '—'}
                                </strong>
                                <small className="text-[0.625rem] text-muted-foreground">
                                    Vitesse moyenne ~60 km/h
                                </small>
                            </div>
                            <div className="rounded-xl border border-border/80 bg-card p-3 shadow-sm">
                                <span className="flex items-center gap-1 text-[0.625rem] font-bold text-muted-foreground uppercase">
                                    <MapPin className="size-3 text-gold" />{' '}
                                    Arrêts
                                </span>
                                <strong className="mt-1 block text-base font-extrabold text-foreground">
                                    {stops.length} arrêts
                                </strong>
                                <small className="text-[0.625rem] text-muted-foreground">
                                    {stops.length > 1
                                        ? `${(stops.length * (stops.length - 1)) / 2} segments`
                                        : '0 segment'}
                                </small>
                            </div>
                        </div>

                        {/* 3. Recherche et ajout rapide de gare physique */}
                        <div className="relative">
                            <div className="relative">
                                <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                                <Input
                                    value={searchQuery}
                                    onChange={(e) =>
                                        handleSearchQueryChange(e.target.value)
                                    }
                                    placeholder="Gare, ville ou lieu (ex: Savalou, Zagnanado, Lomé)…"
                                    className="h-10 rounded-xl pl-9 text-xs"
                                />
                            </div>

                            {/* Dropdown résultats de recherche : gares du réseau + lieux OSM */}
                            {isSearching &&
                                (searchResults.length > 0 ||
                                    placeResults.length > 0 ||
                                    isPlacesLoading) && (
                                    <div className="absolute top-11 right-0 left-0 z-30 max-h-72 overflow-y-auto rounded-xl border border-border bg-card p-1 shadow-xl">
                                        {searchResults.length > 0 && (
                                            <>
                                                <span className="block px-2.5 pt-2 pb-1 text-[0.625rem] font-black tracking-widest text-muted-foreground uppercase">
                                                    Gares du réseau
                                                </span>
                                                {searchResults.map(
                                                    (station) => (
                                                        <button
                                                            key={station.uuid}
                                                            type="button"
                                                            onClick={() =>
                                                                handleAddPhysicalStation(
                                                                    station,
                                                                )
                                                            }
                                                            className="flex w-full items-center justify-between rounded-lg p-2.5 text-left text-xs transition-colors hover:bg-muted"
                                                        >
                                                            <div className="flex items-center gap-2">
                                                                <span className="grid size-6 place-items-center rounded-md bg-gold/20 text-[var(--kora)] dark:text-gold">
                                                                    <MapPin className="size-3.5" />
                                                                </span>
                                                                <div>
                                                                    <strong className="block font-semibold text-foreground">
                                                                        {
                                                                            station.name
                                                                        }
                                                                    </strong>
                                                                    <span className="text-[0.6875rem] text-muted-foreground">
                                                                        {
                                                                            station.city
                                                                        }
                                                                        ,{' '}
                                                                        {
                                                                            station.country
                                                                        }{' '}
                                                                        ·{' '}
                                                                        {
                                                                            station.code
                                                                        }
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <span className="rounded bg-muted px-1.5 py-0.5 text-[0.625rem] font-bold text-muted-foreground">
                                                                + Ajouter
                                                            </span>
                                                        </button>
                                                    ),
                                                )}
                                            </>
                                        )}

                                        {(placeResults.length > 0 ||
                                            isPlacesLoading) && (
                                            <>
                                                <span className="mt-1 block border-t border-border/50 px-2.5 pt-2 pb-1 text-[0.625rem] font-black tracking-widest text-muted-foreground uppercase">
                                                    Lieux sur la carte
                                                </span>
                                                {isPlacesLoading &&
                                                    placeResults.length ===
                                                        0 && (
                                                        <div className="flex items-center gap-2 p-2.5 text-xs text-muted-foreground">
                                                            <span className="size-3 animate-spin rounded-full border-2 border-gold border-t-transparent" />
                                                            Recherche de lieux…
                                                        </div>
                                                    )}
                                                {placeResults.map(
                                                    (place, index) => (
                                                        <button
                                                            key={`${place.latitude}-${place.longitude}-${index}`}
                                                            type="button"
                                                            onClick={() =>
                                                                handleAddMapPlace(
                                                                    place,
                                                                )
                                                            }
                                                            className="flex w-full items-center justify-between rounded-lg p-2.5 text-left text-xs transition-colors hover:bg-muted"
                                                        >
                                                            <div className="flex min-w-0 items-center gap-2">
                                                                <span className="grid size-6 shrink-0 place-items-center rounded-md bg-digital/15 text-[var(--digital)]">
                                                                    <Compass className="size-3.5" />
                                                                </span>
                                                                <div className="min-w-0">
                                                                    <strong className="block truncate font-semibold text-foreground">
                                                                        {
                                                                            place.label
                                                                        }
                                                                    </strong>
                                                                    <span className="block truncate text-[0.6875rem] text-muted-foreground">
                                                                        {place.detail ||
                                                                            `${place.city}, ${place.country}`}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <span className="shrink-0 rounded bg-muted px-1.5 py-0.5 text-[0.625rem] font-bold text-muted-foreground">
                                                                + Ajouter
                                                            </span>
                                                        </button>
                                                    ),
                                                )}
                                            </>
                                        )}
                                    </div>
                                )}
                        </div>

                        {/* 4. Timeline ordonnée des arrêts */}
                        <div className="space-y-2.5">
                            <div className="flex items-center justify-between">
                                <span className="text-[0.6875rem] font-black tracking-wider text-muted-foreground uppercase">
                                    Parcours ordonné ({displayStops.length}{' '}
                                    arrêt{displayStops.length > 1 ? 's' : ''})
                                </span>
                                {displayStops.length > 0 && (
                                    <button
                                        type="button"
                                        onClick={fitRouteBounds}
                                        className="inline-flex items-center gap-1 text-[0.6875rem] font-semibold text-[var(--kora)] hover:underline dark:text-gold"
                                    >
                                        <Maximize2 className="size-3" />{' '}
                                        Recentrer
                                    </button>
                                )}
                            </div>

                            {displayStops.length === 0 ? (
                                <div className="rounded-xl border border-dashed border-border/80 p-6 text-center text-xs text-muted-foreground">
                                    <MapPin className="mx-auto mb-2 size-6 text-gold opacity-75" />
                                    <p className="font-semibold text-foreground">
                                        Aucun arrêt sélectionné
                                    </p>
                                    <p className="mt-1">
                                        Cliquez directement sur la carte pour
                                        déposer votre premier arrêt, ou utilisez
                                        la barre de recherche ci-dessus.
                                    </p>
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    {displayStops.map((stop, index) => {
                                        const isFirst = index === 0;
                                        const isLast =
                                            index === displayStops.length - 1 &&
                                            displayStops.length > 1;
                                        const isSelected =
                                            stop.uuid === selectedStopUuid;

                                        return (
                                            <article
                                                key={stop.uuid}
                                                onClick={() =>
                                                    focusOnStop(stop)
                                                }
                                                className={cn(
                                                    'group relative cursor-pointer rounded-xl border p-3 transition-all',
                                                    isSelected
                                                        ? 'border-gold bg-gold/5 shadow-md'
                                                        : 'border-border/60 bg-card hover:border-border hover:shadow-sm',
                                                )}
                                            >
                                                <div className="flex items-start justify-between gap-2">
                                                    <div className="flex items-start gap-2.5">
                                                        <span
                                                            className={cn(
                                                                'grid size-7 shrink-0 place-items-center rounded-lg text-xs font-black',
                                                                isFirst
                                                                    ? 'bg-[var(--kora)] text-gold'
                                                                    : isLast
                                                                      ? 'bg-[var(--kora)] text-white'
                                                                      : 'bg-gold text-[var(--kora)]',
                                                            )}
                                                        >
                                                            {(index + 1)
                                                                .toString()
                                                                .padStart(
                                                                    2,
                                                                    '0',
                                                                )}
                                                        </span>
                                                        <div className="min-w-0">
                                                            <div className="flex items-center gap-1.5">
                                                                <strong className="block truncate text-xs font-bold text-foreground">
                                                                    {stop.name}
                                                                </strong>
                                                                {isFirst && (
                                                                    <span className="py-0.2 rounded bg-emerald-500/10 px-1.5 text-[0.5625rem] font-bold text-emerald-600">
                                                                        DÉPART
                                                                    </span>
                                                                )}
                                                                {isLast && (
                                                                    <span className="py-0.2 rounded bg-digital/10 px-1.5 text-[0.5625rem] font-bold text-[var(--digital)]">
                                                                        ARRIVÉE
                                                                    </span>
                                                                )}
                                                            </div>
                                                            <span className="mt-0.5 block truncate text-[0.6875rem] text-muted-foreground">
                                                                {stop.city},{' '}
                                                                {stop.country}
                                                                {stop.latitude !==
                                                                    null &&
                                                                    stop.longitude !==
                                                                        null &&
                                                                    ` · GPS ${stop.latitude.toFixed(4)}, ${stop.longitude.toFixed(4)}`}
                                                            </span>
                                                        </div>
                                                    </div>

                                                    {/* Actions pour réordonner ou supprimer */}
                                                    {activeDirection ===
                                                        'outbound' && (
                                                        <div className="flex items-center gap-1 opacity-70 transition-opacity group-hover:opacity-100">
                                                            <button
                                                                type="button"
                                                                onClick={(
                                                                    e,
                                                                ) => {
                                                                    e.stopPropagation();
                                                                    moveStopUp(
                                                                        index,
                                                                    );
                                                                }}
                                                                disabled={
                                                                    isFirst
                                                                }
                                                                className="rounded p-1 hover:bg-muted disabled:opacity-30"
                                                                title="Monter"
                                                            >
                                                                <ArrowUp className="size-3.5" />
                                                            </button>
                                                            <button
                                                                type="button"
                                                                onClick={(
                                                                    e,
                                                                ) => {
                                                                    e.stopPropagation();
                                                                    moveStopDown(
                                                                        index,
                                                                    );
                                                                }}
                                                                disabled={
                                                                    isLast ||
                                                                    index ===
                                                                        displayStops.length -
                                                                            1
                                                                }
                                                                className="rounded p-1 hover:bg-muted disabled:opacity-30"
                                                                title="Descendre"
                                                            >
                                                                <ArrowDown className="size-3.5" />
                                                            </button>
                                                            <button
                                                                type="button"
                                                                onClick={(
                                                                    e,
                                                                ) => {
                                                                    e.stopPropagation();
                                                                    removeStop(
                                                                        stop.uuid,
                                                                    );
                                                                }}
                                                                className="rounded p-1 text-rose-500 hover:bg-rose-500/10"
                                                                title="Supprimer"
                                                            >
                                                                <Trash2 className="size-3.5" />
                                                            </button>
                                                        </div>
                                                    )}
                                                </div>

                                                {/* Détail kilométrique de l'étape */}
                                                <div className="mt-2.5 flex items-center justify-between border-t border-border/40 pt-2 text-[0.6875rem]">
                                                    <span className="text-muted-foreground">
                                                        Distance depuis
                                                        l’origine :{' '}
                                                        <strong className="font-bold text-foreground">
                                                            {kmAt(index)} km
                                                        </strong>
                                                    </span>
                                                    {index > 0 &&
                                                        legKmAt(index) !==
                                                            undefined && (
                                                            <span className="font-semibold text-gold">
                                                                +
                                                                {legKmAt(index)}{' '}
                                                                km
                                                            </span>
                                                        )}
                                                </div>
                                            </article>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Footer du volet gauche */}
                    <div className="flex items-center justify-between border-t border-border bg-muted/20 p-4 text-xs">
                        <span className="flex items-center gap-1.5 text-muted-foreground">
                            <Info className="size-3.5 text-gold" />
                            Glissez un marqueur sur la carte pour repositionner
                            l'arrêt.
                        </span>
                    </div>
                </aside>

                {/* Volet Droit : Carte Leaflet Interactive */}
                <main className="relative flex-1 bg-muted/30">
                    <div ref={mapContainerRef} className="h-full w-full" />

                    {/* Badge flottant d'instruction */}
                    <div className="absolute top-4 left-4 z-20 flex items-center gap-2 rounded-xl border border-border/80 bg-card/90 px-3.5 py-2 text-xs font-semibold text-foreground shadow-lg backdrop-blur-md">
                        <span className="grid size-5 place-items-center rounded-full bg-gold/20 text-gold">
                            <MapPin className="size-3" />
                        </span>
                        <span>
                            Mode interactif : Cliquez n’importe où sur la carte
                            pour ajouter un arrêt
                        </span>
                    </div>

                    {/* Outils flottants en haut à droite */}
                    <div className="absolute top-4 right-4 z-20 flex items-center gap-2">
                        {displayStops.length > 0 && (
                            <button
                                type="button"
                                onClick={fitRouteBounds}
                                className="inline-flex items-center gap-1.5 rounded-xl border border-border/80 bg-card/90 px-3 py-2 text-xs font-bold text-foreground shadow-lg backdrop-blur-md transition-colors hover:bg-card"
                            >
                                <Maximize2 className="size-3.5 text-gold" />
                                Adapter la vue
                            </button>
                        )}
                        {stops.length > 0 && activeDirection === 'outbound' && (
                            <button
                                type="button"
                                onClick={() => {
                                    if (
                                        window.confirm(
                                            'Voulez-vous réinitialiser tous les arrêts ?',
                                        )
                                    ) {
                                        setStops([]);
                                        setSelectedStopUuid(null);
                                    }
                                }}
                                className="inline-flex items-center gap-1.5 rounded-xl border border-border/80 bg-card/90 px-3 py-2 text-xs font-bold text-rose-500 shadow-lg backdrop-blur-md transition-colors hover:bg-card"
                            >
                                <RotateCcw className="size-3.5" />
                                Réinitialiser
                            </button>
                        )}
                    </div>

                    {/* Indicateur de calcul en cours */}
                    {isCalculatingRoute && (
                        <div className="absolute bottom-6 left-6 z-20 flex animate-pulse items-center gap-2 rounded-xl bg-[var(--kora)] px-3.5 py-2 text-xs font-bold text-white shadow-xl">
                            <span className="size-2 animate-ping rounded-full bg-gold" />
                            Calcul du tracé routier OSRM en cours…
                        </div>
                    )}
                </main>
            </div>
        </div>
    );
}
