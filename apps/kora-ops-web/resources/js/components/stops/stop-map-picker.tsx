import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';
import { MapPin, Navigation } from 'lucide-react';
import { useEffect, useRef } from 'react';

// Vite bundle : les URLs par défaut du marqueur Leaflet ne se résolvent pas seules.
L.Icon.Default.mergeOptions({
    iconUrl: markerIcon,
    iconRetinaUrl: markerIcon2x,
    shadowUrl: markerShadow,
});

type StopMapPickerProps = {
    latitude: number | null;
    longitude: number | null;
    onChange: (latitude: number, longitude: number) => void;
    tileUrl?: string;
    attribution?: string;
    fallbackCenter?: [number, number];
    height?: number;
};

export function StopMapPicker({
    latitude,
    longitude,
    onChange,
    tileUrl = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    attribution = '© OpenStreetMap contributors',
    fallbackCenter = [6.3703, 2.3912],
    height = 280,
}: StopMapPickerProps) {
    const containerRef = useRef<HTMLDivElement>(null);
    const mapRef = useRef<L.Map | null>(null);
    const markerRef = useRef<L.Marker | null>(null);
    const onChangeRef = useRef(onChange);

    useEffect(() => {
        onChangeRef.current = onChange;
    }, [onChange]);

    useEffect(() => {
        if (!containerRef.current || mapRef.current) {
            return;
        }

        const initialCenter: [number, number] =
            latitude !== null && longitude !== null
                ? [latitude, longitude]
                : fallbackCenter;

        const map = L.map(containerRef.current, {
            zoomControl: true,
            scrollWheelZoom: true,
        }).setView(initialCenter, latitude !== null && longitude !== null ? 14 : 7);

        L.tileLayer(tileUrl, { attribution, maxZoom: 19 }).addTo(map);

        map.on('click', (event: L.LeafletMouseEvent) => {
            const lat = Math.round(event.latlng.lat * 1e6) / 1e6;
            const lng = Math.round(event.latlng.lng * 1e6) / 1e6;
            onChangeRef.current(lat, lng);
        });

        mapRef.current = map;

        // Force relayout after container render
        setTimeout(() => {
            map.invalidateSize();
        }, 150);

        return () => {
            map.remove();
            mapRef.current = null;
            markerRef.current = null;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        const map = mapRef.current;

        if (!map) {
            return;
        }

        if (latitude === null || longitude === null) {
            if (markerRef.current) {
                markerRef.current.remove();
                markerRef.current = null;
            }

            return;
        }

        const latLng: [number, number] = [latitude, longitude];

        if (markerRef.current) {
            markerRef.current.setLatLng(latLng);
            map.panTo(latLng);
        } else {
            const marker = L.marker(latLng, { draggable: true }).addTo(map);

            marker.on('dragend', () => {
                const pos = marker.getLatLng();
                const nextLat = Math.round(pos.lat * 1e6) / 1e6;
                const nextLng = Math.round(pos.lng * 1e6) / 1e6;
                onChangeRef.current(nextLat, nextLng);
            });

            markerRef.current = marker;
            map.setView(latLng, Math.max(map.getZoom(), 15));
        }
    }, [latitude, longitude]);

    const recenter = () => {
        if (!mapRef.current) {
            return;
        }

        if (latitude !== null && longitude !== null) {
            mapRef.current.setView([latitude, longitude], 15);
        } else {
            mapRef.current.setView(fallbackCenter, 7);
        }
    };

    return (
        <div className="relative overflow-hidden rounded-xl border border-border">
            <div
                ref={containerRef}
                style={{ height }}
                className="w-full bg-muted/40"
            />
            <div className="absolute top-2 right-2 z-[400] flex items-center gap-1.5 rounded-lg bg-card/90 p-1 shadow-xs backdrop-blur-xs">
                <button
                    type="button"
                    onClick={recenter}
                    className="flex items-center gap-1 rounded-md px-2 py-1 text-[0.6875rem] font-bold text-muted-foreground hover:bg-muted hover:text-foreground"
                    title="Recentrer la carte"
                >
                    <Navigation className="size-3" />
                    Recentrer
                </button>
            </div>
            <div className="absolute bottom-2 left-2 z-[400] flex items-center gap-1 rounded-md bg-card/90 px-2 py-1 text-[0.625rem] font-bold text-muted-foreground shadow-xs backdrop-blur-xs">
                <MapPin className="size-3 text-gold" />
                {latitude !== null && longitude !== null ? (
                    <span>
                        {latitude.toFixed(6)}, {longitude.toFixed(6)} (glissez le marqueur ou cliquez)
                    </span>
                ) : (
                    <span>Cliquez sur la carte pour positionner l'arrêt</span>
                )}
            </div>
        </div>
    );
}
