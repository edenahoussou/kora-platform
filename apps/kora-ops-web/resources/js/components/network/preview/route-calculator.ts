export type LatLng = [number, number]; // [lat, lng]

export type RoutePoint = {
    id: string;
    label: string;
    city: string;
    country: string;
    latitude: number;
    longitude: number;
};

export type RouteCalculationResult = {
    totalDistanceKm: number;
    cumulativeDistances: number[]; // km from origin for each point
    legDistances: number[]; // km between each point
    estimatedDurationMinutes: number;
    geometryCoordinates: LatLng[]; // path for polyline
    isRoadRoute: boolean;
};

/** Formule Haversine entre deux coordonnées géographiques (km). */
export function haversineKm(
    lat1: number,
    lon1: number,
    lat2: number,
    lon2: number,
): number {
    const toRad = (deg: number) => (deg * Math.PI) / 180;
    const earthRadiusKm = 6371;
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    const a =
        Math.sin(dLat / 2) ** 2 +
        Math.cos(toRad(lat1)) *
        Math.cos(toRad(lat2)) *
        Math.sin(dLon / 2) ** 2;
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

    return Math.round(earthRadiusKm * c * 10) / 10;
}

/** Calcule les distances d'un itinéraire par vol d'oiseau majoré pour la route (+18%). */
export function calculateFallbackRoute(
    points: RoutePoint[],
): RouteCalculationResult {
    if (points.length < 2) {
        return {
            totalDistanceKm: 0,
            cumulativeDistances: points.map(() => 0),
            legDistances: points.map(() => 0),
            estimatedDurationMinutes: 0,
            geometryCoordinates: points.map((p) => [p.latitude, p.longitude]),
            isRoadRoute: false,
        };
    }

    let accumulated = 0;
    const cumulative: number[] = [0];
    const legs: number[] = [0];
    const roadFactor = 1.18; // Majoration moyenne géodésie -> réseau routier Afrique de l'Ouest

    for (let i = 1; i < points.length; i++) {
        const prev = points[i - 1];
        const curr = points[i];
        const direct = haversineKm(
            prev.latitude,
            prev.longitude,
            curr.latitude,
            curr.longitude,
        );
        const roadEst = Math.round(direct * roadFactor * 10) / 10;
        accumulated += roadEst;
        cumulative.push(Math.round(accumulated * 10) / 10);
        legs.push(roadEst);
    }

    const totalKm = cumulative[cumulative.length - 1];
    const duration = estimateBusDurationMinutes(totalKm, points.length);

    return {
        totalDistanceKm: totalKm,
        cumulativeDistances: cumulative,
        legDistances: legs,
        estimatedDurationMinutes: duration,
        geometryCoordinates: points.map((p) => [p.latitude, p.longitude]),
        isRoadRoute: false,
    };
}

/** Estime le temps de trajet d'un autocar commercial (vitesse moyenne 60 km/h + 8 min d'arrêt). */
export function estimateBusDurationMinutes(
    distanceKm: number,
    stopsCount: number,
): number {
    if (distanceKm <= 0) {
        return 0;
    }

    const drivingMinutes = (distanceKm / 60) * 60;
    const intermediateStops = Math.max(0, stopsCount - 2);
    const dwellMinutes = intermediateStops * 8;

    return Math.round(drivingMinutes + dwellMinutes);
}

/** Interroge le service de routage OSRM avec mise en cache et repli Haversine. */
export async function calculateRoadRoute(
    points: RoutePoint[],
): Promise<RouteCalculationResult> {
    if (points.length < 2) {
        return calculateFallbackRoute(points);
    }

    const coordsStr = points
        .map((p) => `${p.longitude},${p.latitude}`)
        .join(';');
    const url = `https://router.project-osrm.org/route/v1/driving/${coordsStr}?overview=full&geometries=geojson&steps=false`;

    try {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 3500);
        const res = await fetch(url, { signal: controller.signal });
        clearTimeout(timeout);

        if (!res.ok) {
            return calculateFallbackRoute(points);
        }

        const data = await res.json();

        if (!data.routes || data.routes.length === 0) {
            return calculateFallbackRoute(points);
        }

        const primaryRoute = data.routes[0];
        const totalDistanceKm = Math.round(primaryRoute.distance / 100) / 10;
        const geoCoords: LatLng[] = primaryRoute.geometry.coordinates.map(
            (c: [number, number]) => [c[1], c[0]],
        );

        // Legs OSRM
        const legs: number[] = [0];
        const cumulative: number[] = [0];
        let runningTotal = 0;

        if (primaryRoute.legs && primaryRoute.legs.length === points.length - 1) {
            primaryRoute.legs.forEach((leg: { distance: number }) => {
                const legKm = Math.round(leg.distance / 100) / 10;
                runningTotal += legKm;
                legs.push(legKm);
                cumulative.push(Math.round(runningTotal * 10) / 10);
            });
        } else {
            const fallback = calculateFallbackRoute(points);
            legs.push(...fallback.legDistances.slice(1));
            cumulative.push(...fallback.cumulativeDistances.slice(1));
        }

        const durationMinutes = Math.round(
            estimateBusDurationMinutes(totalDistanceKm, points.length),
        );

        return {
            totalDistanceKm,
            cumulativeDistances: cumulative,
            legDistances: legs,
            estimatedDurationMinutes: durationMinutes,
            geometryCoordinates: geoCoords,
            isRoadRoute: true,
        };
    } catch {
        return calculateFallbackRoute(points);
    }
}

/** Tente un géocodage inverse simplifié par coordonnée GPS (OpenStreetMap Nominatim ou catalogue). */
export async function reverseGeocode(
    lat: number,
    lng: number,
): Promise<{ city: string; country: string; label: string }> {
    try {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 2000);
        const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}&accept-language=fr`;
        const res = await fetch(url, {
            signal: controller.signal,
            headers: { 'User-Agent': 'KoraPlatform-NetworkStudio/1.0' },
        });
        clearTimeout(timeout);

        if (res.ok) {
            const data = await res.json();
            const addr = data.address || {};
            const city =
                addr.city ||
                addr.town ||
                addr.village ||
                addr.municipality ||
                addr.suburb ||
                'Localité';
            const countryCode = (addr.country_code || 'bj').toUpperCase();
            const name =
                data.name ||
                addr.road ||
                `Arrêt ${city}`;

            return {
                city,
                country: countryCode,
                label: name,
            };
        }
    } catch {
        // Fallback local en cas d'indisponibilité réseau
    }

    return {
        city: 'Localité',
        country: 'BJ',
        label: `Arrêt (${lat.toFixed(3)}, ${lng.toFixed(3)})`,
    };
}

export type PlaceResult = {
    label: string;
    detail: string;
    city: string;
    country: string;
    latitude: number;
    longitude: number;
    kind: string;
};

/** Recherche directe de lieux sur la carte (géocodage avant Nominatim, CEDEAO). */
export async function searchPlaces(query: string): Promise<PlaceResult[]> {
    const trimmed = query.trim();

    if (trimmed.length < 3) {
        return [];
    }

    try {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 4000);
        const url =
            `https://nominatim.openstreetmap.org/search?format=jsonv2&q=${encodeURIComponent(trimmed)}` +
            `&countrycodes=bj,tg,ci,gh,ng,bf,ne&limit=6&accept-language=fr&addressdetails=1`;
        const res = await fetch(url, {
            signal: controller.signal,
            headers: { 'User-Agent': 'KoraPlatform-NetworkStudio/1.0' },
        });
        clearTimeout(timeout);

        if (!res.ok) {
            return [];
        }

        const data = await res.json();

        return (data as Array<Record<string, unknown>>)
            .map((item) => {
                const parts = String(item.display_name || '')
                    .split(',')
                    .map((p) => p.trim())
                    .filter(Boolean);
                const addr = (item.address || {}) as Record<string, string>;

                return {
                    label: parts[0] || 'Lieu',
                    detail: parts.slice(1, 3).join(', '),
                    city:
                        addr.city ||
                        addr.town ||
                        addr.village ||
                        addr.municipality ||
                        addr.county ||
                        parts[1] ||
                        'Localité',
                    country: (addr.country_code || '').toUpperCase() || 'BJ',
                    latitude: Number(item.lat),
                    longitude: Number(item.lon),
                    kind: String(item.type || item.class || 'lieu'),
                };
            })
            .filter(
                (place) =>
                    Number.isFinite(place.latitude) &&
                    Number.isFinite(place.longitude),
            );
    } catch {
        return [];
    }
}
