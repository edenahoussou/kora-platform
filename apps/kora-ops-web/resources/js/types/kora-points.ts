/**
 * KORA POINT VOYAGE — Shared domain types.
 * Used by real Inertia pages and UI components.
 */

export type PointStatus =
    | 'ready'
    | 'warning_threshold'
    | 'limit_reached'
    | 'overdue'
    | 'suspended'
    | 'closed';

export type SettlementStatus = 'open' | 'in_settlement' | 'settled';

export type DemoScenarioId =
    | 'autonomous_healthy'
    | 'autonomous_warning_80'
    | 'autonomous_limit_reached'
    | 'autonomous_overdue'
    | 'org_attached_healthy';

export type KoraPointTerms = {
    commission_rate_bps: number; // e.g. 300 = 3.0%
    credit_limit_amount: number; // e.g. 1 500 000 FCFA
    settlement_schedule: {
        frequency: 'daily' | 'weekly' | 'monthly';
        day_of_week?: number;
        day_of_month?: number;
        time: string;
        timezone: string;
    };
    grace_period_days: number;
};

export type KoraPointProfile = {
    uuid: string;
    code: string;
    name: string;
    city: string;
    country: string;
    address: string;
    manager_name: string;
    manager_phone: string;
    manager_email: string;
    status: PointStatus;
    organization: {
        uuid: string;
        code: string;
        name: string;
    } | null;
    terms: KoraPointTerms;
};

export type FinancialHealth = {
    credit_limit: number;
    current_exposure: number; // Total net encours
    available_balance: number; // Plafond - Encours
    usage_percentage: number;
    next_due_date: string;
    next_due_label: string;
    is_overdue: boolean;
    sales_allowed: boolean;
    block_reason: string | null;
};

export type DailyActivity = {
    date: string;
    day_short: string;
    sales_amount: number;
    commission_amount: number;
    tickets_count: number;
    is_peak?: boolean;
};

export type DailyMetrics = {
    today_sales_amount: number;
    today_tickets_count: number;
    today_commission_amount: number;
    total_due_amount: number; // Encours total à reverser
    activity_7d: DailyActivity[];
};

export type PointPriorityAlert = {
    id: string;
    level: 'info' | 'warning' | 'critical' | 'success';
    title: string;
    description: string;
    time_label: string;
    action_label?: string;
    action_target?: string;
};

export type PointSaleRecord = {
    uuid: string;
    ticket_number: string;
    short_code: string;
    created_at: string;
    date_label: string;
    seller_name: string;
    departure_ref: string;
    service_class: string;
    departure_time: string;
    route_label: string;
    origin_city: string;
    destination_city: string;
    seat_number: string;
    passenger_name: string;
    passenger_phone: string;
    payment_method: 'CASH' | 'MOBILE_MONEY';
    amount_paid: number;
    commission_rate_bps: number;
    commission_amount: number;
    settlement_status: SettlementStatus;
    settlement_ref?: string;
    refundable: boolean;
};

export type SaleDepartureMock = {
    uuid: string;
    ref: string;
    service_class: 'Standard' | 'Confort VIP' | 'Express';
    departure_time: string;
    arrival_time: string;
    duration: string;
    available_seats: number;
    total_seats: number;
    unit_price: number;
    origin: string;
    destination: string;
    stops: string[];
};

export type PointCashMovement = {
    id: string;
    time_label: string;
    label: string;
    amount: number;
    type: 'OPENING' | 'SALE_CASH' | 'SALE_MOBILE' | 'EXPENSE' | 'DEPOSIT';
    method: 'CASH' | 'MOBILE_MONEY';
    balance_after: number;
};

export type BrouillardMovement = {
    id: string;
    date_label?: string;
    time_label: string;
    ticket_reference: string;
    passenger_name: string;
    passenger_phone: string;
    method: 'cash' | 'mobile_money';
    network: string | null;
    network_label: string | null;
    reference: string;
    gross_amount: number;
    commission_amount: number;
    net_amount: number;
};

export type CashBrouillardFilters = {
    from: string;
    to: string;
    channel: string;
    q: string;
};

export type DailyCashBrouillard = {
    date_label: string;
    gross_total: number;
    commission_total: number;
    net_total: number;
    cash_total: number;
    mobile_money_total: number;
    tickets_count: number;
    movements: BrouillardMovement[];
};

export type PointCashSession = {
    status: 'OPEN' | 'CLOSED';
    opened_at: string;
    closed_at?: string;
    operator_name: string;
    opening_amount: number;
    cash_collected: number;
    mobile_money_collected: number;
    expected_closing_cash: number;
    actual_closing_cash?: number;
    variance_amount?: number;
    closing_note?: string;
    movements: PointCashMovement[];
};

export type PointConvention = {
    ref: string;
    title: string;
    signed_at: string;
    expires_at: string;
    status: 'ACTIVE' | 'EXPIRED' | 'PENDING';
    file_name: string;
    file_size: string;
    commission_rate_bps: number;
    credit_limit_amount: number;
};

export type PointDocument = {
    uuid: string;
    title: string;
    category: 'IDENTITE' | 'REGISTRE' | 'FISCAL' | 'LOCALISATION' | 'AUTRE';
    file_name: string;
    file_size: string;
    uploaded_at: string;
    status: 'VALID' | 'PENDING' | 'EXPIRED';
};

export type PointAssignedUser = {
    uuid: string;
    name: string;
    email: string;
    phone: string;
    role_label: string;
    assigned_at: string;
    is_primary: boolean;
};

export type PointCommissionRecord = {
    uuid: string;
    sale_uuid: string;
    ticket_number: string;
    date_label: string;
    departure_ref: string;
    route_label: string;
    seller_name: string;
    amount_paid: number;
    commission_rate_bps: number;
    initial_commission: number;
    correction_amount: number; // e.g. -270 if ticket refunded
    net_commission: number;
    settlement_status: SettlementStatus;
    has_refund_correction: boolean;
    refund_ref?: string;
    refund_reason?: string;
};

export type PointSettlementStatus =
    'DRAFT' | 'DECLARED' | 'VALIDATED' | 'REJECTED' | 'PARTIALLY_VALIDATED';

export type PointSettlementMethod =
    'BANK_TRANSFER' | 'BANK_DEPOSIT' | 'CHECK' | 'MOBILE_MONEY';

export type SettlementPeriodPreview = {
    from: string;
    to: string;
    tickets_count: number;
    gross_amount: number;
    commission_amount: number;
    net_amount: number;
    sale_uuids: string[];
};

export type KoraBankAccount = {
    bank: string;
    country: string;
    flag: string;
    rib: string;
    account_number: string;
    account_name: string;
    swift?: string;
};

export type KoraMobileMoneyMerchant = {
    network: string;
    label: string;
    merchant_code: string;
    phone: string;
    country: string;
    flag: string;
};

export type KoraPaymentDetails = {
    bank_accounts: KoraBankAccount[];
    check_order: string;
    mobile_money_merchants: KoraMobileMoneyMerchant[];
};

export type PointSettlementRecord = {
    uuid: string;
    reference: string;
    point_uuid: string;
    point_name: string;
    created_by_name: string;
    period_start: string;
    period_end: string;
    created_at: string;
    declared_at?: string;
    validated_at?: string;
    amount_expected: number;
    amount_declared: number;
    amount_validated?: number;
    payment_method: PointSettlementMethod;
    payment_reference: string;
    proof_file_name?: string;
    proof_file_size?: string;
    status: PointSettlementStatus;
    sales_count: number;
    tickets_covered: string[];
    notes?: string;
    rejection_reason?: string;
    timeline: {
        step: string;
        date_label: string;
        actor: string;
        comment?: string;
    }[];
};

export type PointRefundStatus =
    'REQUESTED' | 'APPROVED_BY_FINANCE' | 'FUNDS_RESTITUTED' | 'REJECTED';

export type PointRefundReason =
    | 'TRIP_CANCELLED'
    | 'PASSENGER_SICKNESS'
    | 'DUPLICATE_PURCHASE'
    | 'SCHEDULE_CHANGE';

export type PointRefundRecord = {
    uuid: string;
    reference: string;
    ticket_number: string;
    sale_uuid: string;
    passenger_name: string;
    passenger_phone: string;
    departure_ref: string;
    route_label: string;
    original_amount: number;
    retained_fee: number;
    refund_amount: number;
    commission_clawback: number;
    reason: PointRefundReason;
    reason_label: string;
    status: PointRefundStatus;
    requested_at: string;
    requested_by: string;
    approved_at?: string;
    approved_by?: string;
    restituted_at?: string;
    restituted_by?: string;
    receipt_signed?: boolean;
    rejection_reason?: string;
    proof_document?: string;
    timeline: {
        step: string;
        date_label: string;
        actor: string;
        detail?: string;
    }[];
};

export type OrganizationSubPoint = {
    uuid: string;
    code: string;
    name: string;
    city: string;
    manager_name: string;
    phone: string;
    status: 'ACTIVE' | 'WARNING_THRESHOLD' | 'LIMIT_REACHED' | 'SUSPENDED';
    daily_sales_amount: number;
    daily_tickets_count: number;
    monthly_sales_amount: number;
    credit_limit: number;
    current_exposure: number;
    usage_percentage: number;
    commission_rate_bps: number;
    monthly_commission_earned: number;
    last_settlement_date: string;
};

export type GroupSettlementBreakdown = {
    subpoint_uuid: string;
    subpoint_name: string;
    current_exposure: number;
    amount_allocated: number;
};

export type GroupSettlementRecord = {
    uuid: string;
    reference: string;
    organization_name: string;
    created_by_name: string;
    created_at: string;
    total_amount_expected: number;
    total_amount_declared: number;
    amount_validated?: number;
    payment_method: PointSettlementMethod;
    payment_reference: string;
    proof_file_name?: string;
    status: 'DECLARED' | 'VALIDATED' | 'REJECTED';
    breakdown: GroupSettlementBreakdown[];
    notes?: string;
    timeline: {
        step: string;
        date_label: string;
        actor: string;
        comment?: string;
    }[];
};

export type AdminNetworkPoint = {
    uuid: string;
    code: string;
    name: string;
    type: 'AUTONOMOUS' | 'ORGANIZATION_ATTACHED';
    organization_name?: string;
    city: string;
    department: string;
    manager_name: string;
    phone: string;
    status: 'ACTIVE' | 'WARNING_THRESHOLD' | 'LIMIT_REACHED' | 'SUSPENDED';
    daily_sales_amount: number;
    monthly_sales_amount: number;
    current_exposure: number;
    credit_limit: number;
    usage_percentage: number;
    commission_rate_bps: number;
    settlement_frequency_days: number;
    grace_period_days: number;
    created_at: string;
};

export type AdminOrganization = {
    uuid: string;
    code: string;
    name: string;
    legal_name: string;
    headquarters_city: string;
    rccm: string;
    ifu: string;
    points_count: number;
    active_points_count: number;
    total_monthly_sales: number;
    total_exposure: number;
    credit_limit: number;
    default_terms: {
        commission_rate_bps: number;
        credit_limit: number;
        settlement_frequency_days: number;
        grace_period_days: number;
    };
    users: {
        uuid: string;
        name: string;
        role: 'ORG_MANAGER' | 'ORG_TREASURER' | 'ORG_CONTROLLER';
        role_label: string;
        email: string;
        phone: string;
        status: 'ACTIVE' | 'SUSPENDED';
    }[];
    documents: {
        uuid: string;
        name: string;
        type: 'CONVENTION' | 'RCCM' | 'IFU' | 'AMENDMENT';
        uploaded_at: string;
        size: string;
        url?: string;
    }[];
};

export type KoraNotificationCategory =
    'FINANCE' | 'SALES' | 'THRESHOLD' | 'SYSTEM';

export type KoraNotificationItem = {
    uuid: string;
    category: KoraNotificationCategory;
    level: 'info' | 'warning' | 'critical' | 'success';
    title: string;
    description: string;
    created_at: string;
    date_label: string;
    read: boolean;
    action_label?: string;
    action_target?: string;
};

export type KoraPointUserRole =
    | 'AGENT_POINT'
    | 'RESPONSABLE_ORG'
    | 'TRESORIER_ORG'
    | 'ADMIN_KORA'
    | 'FINANCE_KORA';

export type KoraPersona = {
    role: KoraPointUserRole;
    name: string;
    roleLabel: string;
    initials: string;
    contextLabel: string;
    badgeTone: 'emerald' | 'navy' | 'gold' | 'blue';
    defaultPage: string;
};
