export type PhysicalStopRecord = {
    uuid: string;
    code: string;
    name: string;
    city: string;
    country: string;
    latitude: number | null;
    longitude: number | null;
};

export type LineStopRecord = {
    uuid: string;
    name: string;
    city: string;
    country: string;
    stopOrder: number;
    kmFromOrigin: number;
    legDistanceKm?: number;
    latitude: number | null;
    longitude: number | null;
    status: 'active' | 'draft' | 'archived';
    physicalStopUuid?: string | null;
};

export type LineFareRecord = {
    uuid: string;
    originName: string;
    destinationName: string;
    serviceClassName: string;
    baseAmount: number;
    currency: string;
};

export type LineRecord = {
    uuid: string;
    code: string;
    name: string;
    type: 'nationale' | 'internationale';
    bidirectional: boolean;
    itinerary: string;
    originCity: string;
    destinationCity: string;
    stopsCount: number;
    activeStopsCount: number;
    segmentsCount: number;
    distanceKm: number | null;
    estimatedDurationMinutes: number | null;
    status: 'active' | 'draft' | 'archived';
    countriesCrossed: string[];
    stops: LineStopRecord[];
    fares: LineFareRecord[];
    updatedAt: string | null;
};
