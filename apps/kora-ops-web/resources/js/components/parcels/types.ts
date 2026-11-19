export type DepositHubKind = 'agency' | 'partner_point';

export type DepositHub = {
    uuid: string;
    kind: DepositHubKind;
    enabled: boolean;
    supports_weighing: boolean;
    agency_uuid: string | null;
    kora_point_uuid: string | null;
    stop_uuid: string | null;
    name: string | null;
    city: string | null;
    archived_at: string | null;
};

export type InternalAgencyOption = {
    uuid: string;
    name: string;
    city: string | null;
    country: string | null;
};

export type PartnerPointOption = {
    uuid: string;
    agency_uuid: string | null;
    agency_name: string | null;
    agency_country: string | null;
    address: string | null;
    responsible_name: string | null;
};

export type StopOption = {
    uuid: string;
    name: string;
    city: string | null;
    country: string | null;
    stop_order: number;
    line_uuid: string | null;
    line_code: string | null;
    line_name: string | null;
};

export type ParcelTariff = {
    uuid: string;
    base_amount: number;
    step_amount: number;
    step_grams: number;
    maximum_grams: number;
    return_amount: number;
    created_at: string | null;
};

export type ParcelCorridor = {
    uuid: string;
    origin_hub_uuid: string | null;
    destination_hub_uuid: string | null;
    enabled: boolean;
    wallet_cash_enabled: boolean;
    tariffs: ParcelTariff[];
};

export type ParcelConfiguration = {
    hubs: DepositHub[];
    corridors: ParcelCorridor[];
    options: {
        internal_agencies: InternalAgencyOption[];
        partner_points: PartnerPointOption[];
        stops: StopOption[];
    };
    permissions: {
        canConfigure: boolean;
        canPublishTariffs: boolean;
        canIntake: boolean;
    };
};

export type ParcelConfigurationResponse = {
    data: ParcelConfiguration;
};

export type DepositHubPayload = {
    agency_uuid?: string;
    kora_point_uuid?: string;
    stop_uuid: string;
    enabled: boolean;
    supports_weighing: boolean;
};

export type CorridorPayload = {
    origin_hub_uuid: string;
    destination_hub_uuid: string;
    enabled: boolean;
    wallet_cash_enabled: boolean;
};

export type TariffPayload = {
    base_amount: number;
    step_amount: number;
    step_grams: number;
    maximum_grams: number;
    return_amount: number;
};

export type CapacityDeparture = {
    uuid: string;
    line_code: string | null;
    line_name: string | null;
    departure_at: string;
    status: string;
    capacity_grams: number | null;
    reserved_grams: number;
    available_grams: number | null;
    corridor_uuids: string[];
};

export type CapacityConfigurationResponse = {
    data: {
        horizon_days: number;
        departures: CapacityDeparture[];
    };
};

export type ParcelIntakePayload = {
    deposit_hub_uuid: string;
    destination_hub_uuid: string;
    idempotency_key: string;
    sender_name: string;
    sender_phone: string;
    recipient_name: string;
    recipient_phone: string;
    contents: string;
    weight_grams: number;
};

export type ParcelIntakeResult = {
    uuid: string;
    reference: string;
    tracking_url: string | null;
    qr_payload: string | null;
    status: string;
    origin: string | null;
    destination: string | null;
    sender_name: string;
    recipient_name: string;
    contents: string;
    weight_grams: number | null;
    declared_grams: number;
    quote: {
        net: number;
        tax: number;
        total: number;
        currency: string;
    } | null;
    expires_at: string;
};

export type ParcelIntakeResponse = {
    data: ParcelIntakeResult;
};

export type LaravelValidationErrors = Record<string, string[]>;

export type ParcelListItem = {
    uuid: string;
    reference: string;
    tracking_url: string | null;
    qr_payload: string | null;
    status: string;
    inspection_state?: 'pending_inspection' | 'accepted' | 'refused';
    inspection?: {
        state: 'pending_inspection' | 'accepted' | 'refused';
        category: string | null;
        refusal_reason: string | null;
        inspected_at: string | null;
        hub_uuid: string | null;
        hub_name: string | null;
    };
    origin: string | null;
    destination: string | null;
    sender_name: string;
    recipient_name: string;
    contents: string;
    weight_grams: number | null;
    declared_grams: number;
    deposit_hub_uuid: string | null;
    deposit_hub: {
        uuid: string;
        kind: DepositHubKind;
        city: string | null;
    } | null;
    selling_agency: {
        uuid: string;
        name: string;
    } | null;
    withdraw_by: string | null;
    return_handed_over_at: string | null;
    expires_at: string;
    updated_at: string | null;
    departure_uuid: string | null;
    eta: string | null;
    tracking_updated_at: string | null;
    incident_open: boolean;
    quote: {
        uuid: string;
        net: number;
        tax: number;
        total: number;
        currency: string;
    } | null;
    timeline?: { kind: string; observed_at: string }[];
};

export type ParcelListMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

export type ParcelListResponse = {
    data: ParcelListItem[];
    meta: ParcelListMeta;
};
