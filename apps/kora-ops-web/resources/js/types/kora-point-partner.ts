export type PartnerOrganization = {
    uuid: string;
    code: string;
    name: string;
    logo_url: string | null;
    country: 'BJ' | 'TG' | 'CI';
    registration_identifier: string | null;
    tax_identifier: string | null;
    status: 'active' | 'suspended' | 'closed';
    phone: string | null;
    address: string | null;
    commission_basis_points: number;
    ceiling_amount: number;
    allocated_amount: number;
    available_allocation: number;
    settlement_calendar: {
        frequency: 'daily' | 'weekly' | 'monthly';
        weekday?: number | null;
        day?: number | null;
        time?: string;
        timezone?: string;
    } | null;
};

export type PartnerPoint = {
    uuid: string;
    code: string;
    name: string;
    city: string;
    department: string | null;
    country: string;
    status: string;
    raw_status: 'active' | 'suspended' | 'closed';
    type: string;
    organization_uuid: string | null;
    logo_url: string | null;
    responsible_name: string;
    phone: string | null;
    address: string | null;
    credit_limit: number;
    outstanding_amount: number;
    current_exposure: number;
    usage_percentage: number;
    commission_rate_bps: number;
    daily_sales_amount: number;
    monthly_sales_amount: number;
    gross_sales_amount: number;
    earned_commission_amount: number;
    sales_count: number;
    overdue: boolean;
    terms: {
        commission_basis_points: number;
        ceiling_amount: number;
        settlement_calendar: PartnerOrganization['settlement_calendar'];
    };
};

export type PartnerSale = {
    uuid: string;
    point_uuid: string;
    point_name: string;
    point_code: string;
    status: string;
    gross_amount: number;
    commission_amount: number;
    commission_reversed: number;
    net_amount: number;
    outstanding_amount: number;
    refunded_amount: number;
    paid_at: string;
    due_at: string;
};

export type PartnerDocument = {
    uuid: string;
    kind: string;
    title: string;
    reference: string | null;
    signed_at: string | null;
    expires_at: string | null;
    download_url: string;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};
