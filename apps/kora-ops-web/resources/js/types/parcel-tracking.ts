export type ParcelTrackingRole =
    'origin' | 'waypoint' | 'destination' | 'event' | 'current';

export type ParcelTrackingPoint = {
    name: string | null;
    city: string | null;
    country: string | null;
    latitude: number;
    longitude: number;
    role: ParcelTrackingRole;
    kind: string | null;
    observed_at: string | null;
    fresh: boolean | null;
};

export type ParcelTrackingEvent = {
    kind: string;
    observed_at: string;
    city: string | null;
    hub_name: string | null;
    public: boolean;
};

export type ParcelTrackingVehicle = {
    registration_number: string | null;
    line_code: string | null;
    line_name: string | null;
    departure_at: string | null;
    status: string | null;
    position: {
        latitude: number;
        longitude: number;
        speed_kmh: number | null;
        heading_degrees: number | null;
        recorded_at: string;
        age_seconds: number;
        fresh: boolean;
    } | null;
};

export type ParcelTracking = {
    reference: string;
    status: string;
    origin: string | null;
    destination: string | null;
    weight_grams: number | null;
    eta: string | null;
    withdraw_by: string | null;
    last_event_at: string | null;
    incident_open: boolean;
    planned: ParcelTrackingPoint[];
    traveled: ParcelTrackingPoint[];
    current: ParcelTrackingPoint | null;
    progress_ratio: number;
    events: ParcelTrackingEvent[];
    vehicle: ParcelTrackingVehicle | null;
};

export type ParcelTrackingResponse = {
    data: ParcelTracking;
};
