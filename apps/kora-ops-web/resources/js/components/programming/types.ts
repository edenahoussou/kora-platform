export type Option = { uuid: string; label: string };
export type StopTime = {
    stop_uuid: string;
    arrival_minutes: number | null;
    departure_minutes: number | null;
    timezone: string;
};
export type Slot = {
    key: string;
    direction: 'outbound' | 'return';
    line_uuid: string | null;
    timezone: string;
    time: string;
    days: number[];
    interval_weeks: number;
    duration_minutes: number;
    preparation_minutes: number;
    stops: StopTime[];
    bus_uuid: string | null;
    driver_uuid: string | null;
    secondary_driver_uuid: string | null;
    conductor_uuid: string | null;
    mechanic_uuid: string | null;
    return_key: string | null;
    return_day_offset: number;
    turnaround_minutes: number;
};
export type References = {
    lines: (Option & {
        code: string;
        duration: number | null;
        bidirectional: boolean;
        stops: (Option & { country: string })[];
    })[];
    agencies: Option[];
    buses: (Option & { capacity: number; model: string; status: string })[];
    people: (Option & { roles: string[] })[];
    timezone: string;
    timezones: string[];
};
export type Template = {
    uuid: string;
    name: string;
    status: 'draft' | 'available' | 'archived';
    revision: number;
    draft: { slots: Slot[] } | null;
    versions: {
        uuid: string;
        number: number;
        slots: Slot[];
        createdAt: string;
    }[];
    programmeCount: number;
};
export type Exception = {
    date: string;
    slot_key: string;
    action: 'exclude' | 'change' | 'add';
    time?: string;
    bus_uuid?: string | null;
};
export type Programme = {
    uuid: string;
    name: string;
    templateUuid: string;
    templateName: string;
    versionUuid: string;
    version: number;
    startsOn: string;
    endsOn: string;
    horizonDays: number;
    automatic: boolean;
    revision: number;
    prepared: number;
    published: number;
    exceptions: Exception[];
};
export type State =
    'ready' | 'blocked' | 'unassigned' | 'published' | 'excluded';
export type PlanningItem = {
    uuid: string;
    revision: number;
    date: string;
    scheduledAt: string;
    arrivalAt: string;
    snapshot: Slot;
    isException: boolean;
    programmeUuid: string;
    programmeName: string;
    departureUuid: string | null;
    departureStatus: string | null;
    protected: boolean;
    manual: boolean;
    state: State;
    blockers: string[];
    warnings: string[];
    occupancy: { booked: number; capacity: number | null; ratio: number | null } | null;
    history?: { event: string; at: string; actor: string | null }[];
};
export type Run = {
    uuid: string;
    programmeUuid: string;
    operation: string;
    status: string;
    processed: number;
    result: {
        created?: number;
        errors?: Record<string, string>;
        message?: string;
    };
    at: string;
};
export type Preview = {
    token: string;
    counts: { create: number; existing: number; excluded: number };
    rows: {
        key: string;
        date: string;
        time: string;
        direction: string;
        kind: string;
        state: State;
        blockers: string[];
        warnings: string[];
    }[];
};
export type ChangePreview = {
    token: string;
    applied: boolean;
    rows: {
        uuid: string | null;
        date: string;
        before: string | null;
        after: string | null;
        kind: string;
        revision: number;
    }[];
};
