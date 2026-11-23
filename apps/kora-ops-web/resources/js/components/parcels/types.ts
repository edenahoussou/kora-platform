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
    deposit_allowed: boolean;
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
    deposit_kind: DepositHubKind;
    version: number;
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
    origin_hub_kind: DepositHubKind | null;
    destination_hub_uuid: string | null;
    enabled: boolean;
    wallet_cash_enabled: boolean;
    tariffs: ParcelTariff[];
};

export type ParcelConfiguration = {
    currency: string;
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
    deposit_kind: DepositHubKind;
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

export type QuoteTier = {
    kind: 'base' | 'step' | 'return';
    from_grams: number;
    to_grams: number;
    amount: number;
};

export type ParcelQuote = {
    estimated: boolean;
    weight_grams: number;
    currency: string;
    deposit_kind: DepositHubKind;
    tariff_uuid: string;
    tariff_version: number;
    maximum_grams: number;
    base_amount: number;
    step_amount: number;
    step_grams: number;
    extra_steps: number;
    tiers: QuoteTier[];
    total: number;
    net: number;
    tax: number;
    tax_code: string;
    tax_rate: string;
    fiscal_group: string;
};

export type ParcelQuoteResponse = {
    data: ParcelQuote;
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
        tariff_uuid?: string | null;
        tariff_version?: number | null;
        deposit_kind?: DepositHubKind | null;
        base_amount?: number | null;
        step_amount?: number | null;
        step_grams?: number | null;
        maximum_grams?: number | null;
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
    can_pay?: boolean;
    can_receipt?: boolean;
    can_label?: boolean;
    can_ready?: boolean;
    can_assign?: boolean;
    can_manifest?: boolean;
    can_receive?: boolean;
    can_release?: boolean;
    can_reissue?: boolean;
    can_override_handoff?: boolean;
    can_return_charge?: boolean;
    can_pay_return?: boolean;
    can_print_handoff_proof?: boolean;
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
    handoff?: {
        received_at: string | null;
        received_by: string | null;
        delivered_at: string | null;
        delivered_by: string | null;
        hub_uuid: string | null;
        hub_name: string | null;
        outcome: 'withdrawn' | 'returned' | null;
        method: 'code' | 'identity' | null;
        identity_type: string | null;
        attempts: number;
        max_attempts: number;
        attempts_remaining: number;
    };
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

export type ParcelListKpis = {
    total: number;
    awaiting_reception: number;
    available: number;
    returns_to_handle: number;
    withdrawn_today: number;
};

export type ParcelIndicatorsResponse = {
    data: Record<string, number>;
    meta: ParcelListKpis;
};

export type ParcelPaymentAccount = {
    uuid: string;
    network: string;
    label: string;
};

export type ParcelPaymentOptions = {
    agency: { uuid: string; name: string } | null;
    hub: { uuid: string; kind: DepositHubKind; name: string | null };
    currency: string;
    cash_session: {
        uuid: string;
        opened_at: string | null;
        cash_register: string | null;
    } | null;
    methods: {
        code: 'cash' | 'mobile_money';
        label: string;
        available: boolean;
        accounts?: ParcelPaymentAccount[];
    }[];
};

export type ParcelPaymentOptionsResponse = {
    data: ParcelPaymentOptions;
};

export type ParcelPaymentResult = {
    uuid: string;
    status: string;
    method: string;
    amount: number;
    currency: string;
    provider: string | null;
    provider_reference: string | null;
    confirmed_at: string | null;
    next_action?: string;
};

export type ParcelReceipt = {
    kind: 'receipt' | 'return_receipt';
    receipt_status: 'provisional' | 'certified';
    receipt_label: string;
    reference: string;
    issued_at: string | null;
    currency: string;
    qr_payload: string;
    parties: {
        sender_name: string;
        sender_phone: string;
        recipient_name: string;
        recipient_phone: string;
    };
    route: {
        origin: string | null;
        destination: string | null;
        corridor_uuid: string | null;
    };
    parcel: {
        weight_grams: number | null;
        declared_grams: number;
        contents: string;
    };
    charge: {
        net: number;
        tax: number;
        total: number;
        tax_code: string;
        tax_rate: string;
        fiscal_group: string;
        tariff_uuid: string | null;
        tariff_version: number | null;
        deposit_kind: DepositHubKind | null;
    };
    payment: {
        uuid: string;
        method: string | null;
        provider: string | null;
        confirmed_at: string | null;
        cash_received_amount: number | null;
        change_amount: number | null;
    };
    fiscal: {
        status: string;
        certified: boolean;
        code: string | null;
        qr_payload: string | null;
        invoice_uuid: string | null;
    };
};

export type ParcelReceiptResponse = {
    data: ParcelReceipt;
};

export type ParcelAssignmentDeparture = {
    departure_uuid: string;
    line_code: string | null;
    line_name: string | null;
    bus_registration: string | null;
    departure_date: string;
    departure_time: string;
    status: string;
    origin_stop: string | null;
    destination_stop: string | null;
    capacity_grams: number | null;
    reserved_grams: number;
    remaining_grams: number | null;
    required_grams: number;
    eligible: boolean;
    reason: string | null;
    is_current: boolean;
};

export type ParcelAssignmentOptions = {
    parcel_uuid: string;
    status: string;
    return_leg: boolean;
    required_grams: number;
    current_departure_uuid: string | null;
    departures: ParcelAssignmentDeparture[];
};

export type ParcelAssignmentOptionsResponse = {
    data: ParcelAssignmentOptions;
};

export type ParcelRuntimeSettings = {
    parcelIntakeExpiryHours: number;
    parcelMinimumWeightGrams: number;
    parcelMaximumWeightGrams: number;
    parcelCashDeclarationThresholdFcfa: number;
    parcelFirstReminderDays: number;
    parcelSecondReminderDays: number;
    parcelReturnDays: number;
    parcelHandoffMaxAttempts: number;
    parcelFiscalAlertHours: number;
    parcelRoutingHorizonDays: number;
    parcelIdentityOverrideEnabled: boolean;
};

export type ParcelSettingsResponse = {
    data: {
        values: ParcelRuntimeSettings;
        permissions: {
            canManage: boolean;
        };
    };
};

export type ParcelSupervisionStatusRow = {
    status: string;
    count: number;
};

export type ParcelSupervisionCorridorRow = {
    uuid: string;
    label: string;
    enabled: boolean;
    count: number;
};

export type ParcelSupervisionIncident = {
    uuid: string;
    parcel_uuid: string | null;
    parcel_reference: string | null;
    kind: string;
    blocking: boolean;
    description: string;
    opened_at: string | null;
};

export type ParcelSupervisionCapacityDeparture = {
    uuid: string;
    line_code: string | null;
    label: string;
    departure_at: string;
    capacity_grams: number | null;
    reserved_grams: number;
    available_grams: number | null;
};

export type ParcelSupervisionAlert = {
    code: string;
    severity: string;
    message: string;
};

export type ParcelSupervisionFinanceItem = {
    uuid: string;
    parcel_uuid: string | null;
    parcel_reference: string | null;
    amount?: number;
    currency?: string;
    kora_reference?: string | null;
    status?: string;
    status_label?: string;
    total?: number;
    detected_at?: string | null;
    requested_at?: string | null;
};

export type ParcelSupervisionFinanceQueue = {
    count: number;
    total_amount: number;
    items: ParcelSupervisionFinanceItem[];
};

export type ParcelSupervision = {
    generated_at: string;
    meta: {
        total: number;
        awaiting_reception: number;
        available: number;
        returns_to_handle: number;
        open_incidents: number;
        withdrawn_today: number;
    };
    by_status: ParcelSupervisionStatusRow[];
    by_corridor: ParcelSupervisionCorridorRow[];
    incidents: {
        open: number;
        blocking: number;
        recent: ParcelSupervisionIncident[];
    };
    capacity: {
        horizon_days: number;
        departures_count: number;
        without_capacity_count: number;
        saturated_count: number;
        available_grams: number;
        without_capacity: ParcelSupervisionCapacityDeparture[];
        saturated: ParcelSupervisionCapacityDeparture[];
    };
    config_health: {
        hubs_without_corridor: Array<{ uuid: string; label: string }>;
        hubs_without_departure: Array<{ uuid: string; label: string }>;
        hubs_without_weighing: Array<{ uuid: string; label: string }>;
        corridors_without_tariff: Array<{ uuid: string; label: string }>;
        departures_without_capacity: ParcelSupervisionCapacityDeparture[];
        alerts: ParcelSupervisionAlert[];
    };
    finance: {
        reconciliations: ParcelSupervisionFinanceQueue;
        pending_refunds: ParcelSupervisionFinanceQueue;
        pending_invoices: ParcelSupervisionFinanceQueue;
    } | null;
    permissions: {
        can_finance: boolean;
        can_configure: boolean;
    };
};

export type ParcelSupervisionResponse = {
    data: ParcelSupervision;
};
