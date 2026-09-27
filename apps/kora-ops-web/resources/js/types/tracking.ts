export type TrackingLiveState =
    | 'live'
    | 'stale'
    | 'gps_unavailable'
    | 'terminal_offline'
    | 'session_closed';

export type TrackingPositionQuality = 'accurate' | 'degraded';

export type TrackingPositionSource = 'gps' | 'network' | 'fused' | 'unknown';

export type TrackingPositionStatus =
    | 'fix'
    | 'degraded'
    | 'gps_unavailable'
    | 'stale';

export type TrackingPositionPayload = {
    latitude: number;
    longitude: number;
    accuracy_meters: number | null;
    speed_kmh: number | null;
    heading_degrees: number | null;
    quality: TrackingPositionQuality | null;
    recorded_at: string;
    age_seconds: number | null;
    source?: TrackingPositionSource;
};

export type TrackingDeparturePayload = {
    uuid: string;
    line_code: string | null;
    line_name?: string;
    origin?: string;
    destination?: string;
    date: string | null;
    time: string | null;
    status: string;
};

export type TrackingBusPayload = {
    uuid: string;
    registration_number: string;
    model?: string;
    seat_count?: number;
};

export type TrackingTerminalPayload = {
    uuid: string;
    type: string;
    serial_number?: string;
};

export type TrackingConductorPayload = {
    uuid: string;
    name: string;
    phone?: string;
};

export type TrackingHistoricalPoint = {
    uuid: string;
    latitude: number;
    longitude: number;
    speed_kmh: number | null;
    heading_degrees: number | null;
    accuracy_meters: number | null;
    recorded_at: string;
    source: TrackingPositionSource;
};

export type FleetTrackingSession = {
    session_uuid: string;
    state: TrackingLiveState;
    departure: TrackingDeparturePayload;
    bus: TrackingBusPayload;
    terminal: TrackingTerminalPayload;
    conductor: TrackingConductorPayload;
    position: TrackingPositionPayload | null;
    opened_at: string;
    agency?: {
        uuid: string;
        name: string;
        code: string;
    };
    recent_positions?: TrackingHistoricalPoint[];
};

export type FleetTrackingPageProps = {
    sessions: FleetTrackingSession[];
    lines: Array<{
        uuid: string;
        code: string;
        name: string;
    }>;
    agencies: Array<{
        uuid: string;
        name: string;
        code: string;
    }>;
    canViewNetwork: boolean;
    userAgencyUuid?: string | null;
    filters: {
        q?: string;
        line?: string;
        status?: string;
        state?: string;
        agency?: string;
        view?: 'hybrid' | 'map' | 'table';
    };
    pollingIntervalMs: number;
};
