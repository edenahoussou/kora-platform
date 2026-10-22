export type BreakEvenStatus = 'atteint' | 'sous_seuil' | 'non_calculable';

export type DistanceSource = 'gps' | 'estimated' | 'mixed';

export type CostSourceStatus = 'available' | 'pending' | 'out_of_scope';

export type ProfitabilityCardAxis =
    | 'bus'
    | 'line'
    | 'departure'
    | 'agency'
    | 'period';

export type ProfitabilityEntityType = 'bus' | 'line' | 'agency';

export type ProfitabilityEntity = {
    type: ProfitabilityEntityType;
    uuid: string;
    label: string;
};

export type ProfitabilityCard = {
    key: string;
    axisType: ProfitabilityCardAxis;
    label: string;
    subtitle: string;
    revenueNet: number;
    costDirect: number;
    costDistributed: number;
    costTotal: number;
    costFixed: number;
    costVariable: number;
    contribution: number;
    marginPercent: number | null;
    seatsSold: number;
    capacity: number | null;
    contributionPerSeat: number | null;
    breakEvenSeats: number | null;
    fillRate: number | null;
    breakEvenStatus: BreakEvenStatus;
    km: number;
    costPerKm: number | null;
    revenuePerSeat: number | null;
    resultPerSeat: number | null;
    gpsCoverage: number;
    distanceSource: DistanceSource;
    departureCount: number;
};

export type ProfitabilityBreakEven = {
    contributionPerSeat: number | null;
    breakEvenSeats: number | null;
    seatsSold: number;
    capacity: number | null;
    fillRate: number | null;
    status: BreakEvenStatus;
};

export type ProfitabilityPnlRow = {
    key: string;
    label: string;
    source: string;
    amount: number | null;
    sharePercent: number | null;
    evolutionPercent: number | null;
    status: CostSourceStatus;
};

export type ProfitabilityTcoRow = {
    key: string;
    label: string;
    tco: number;
    km: number;
    costPerKm: number | null;
    revenuePerSeat: number | null;
    resultPerSeat: number | null;
    gpsCoverage: number;
    distanceSource: DistanceSource;
};

export type ProfitabilityControl = {
    key: string;
    label: string;
    value: string;
    detail: string;
    status: 'ok' | 'warning' | 'info';
};

export type ProfitabilityTimelinePoint = {
    key: string;
    label: string;
    revenue: number;
    cost: number;
    revenueCum: number;
    costCum: number;
    prevRevenueCum: number | null;
};

export type ProfitabilityTimeline = {
    bucket: 'day' | 'week' | 'month';
    points: ProfitabilityTimelinePoint[];
    breakEvenDay: string | null;
};

export type ProfitabilityPeriod = {
    preset: string;
    from: string;
    to: string;
    label: string;
    timezone: string;
    bucket: 'day' | 'week' | 'month';
    comparisonFrom: string;
    comparisonTo: string;
};

export type ProfitabilityDetail = {
    card: ProfitabilityCard | null;
    axis: string;
    revenue: {
        gross?: number;
        deductions?: number;
        net?: number;
        total?: number;
        direct?: number;
        distributed?: number;
    };
    costs: {
        total?: number;
        direct?: number;
        distributed?: number;
        fixed?: number;
        variable?: number;
    };
    categories: Record<string, number>;
    departures: {
        uuid: string;
        label: string;
        scheduledAt: string;
        status: string;
        revenueNet: number;
        costTotal: number;
        seatsSold: number;
        distanceKm: number;
        distanceSource: DistanceSource;
        coveragePercent: number;
    }[];
};

export type ProfitabilityProps = {
    currency: string;
    axis: string;
    generatedAt: string;
    entity: ProfitabilityEntity | null;
    hero: {
        revenueNet: number;
        costAssigned: number;
        costUnassigned: number;
        networkUnassigned: number;
        result: number;
        marginPercent: number | null;
        coveragePercent: number;
        breakEven: ProfitabilityBreakEven;
    };
    kpis: {
        revenueNet: number;
        costAssigned: number;
        costUnassigned: number;
        coveragePercent: number;
        tco: number;
        costPerKm: number | null;
    };
    cards: ProfitabilityCard[];
    breakEvenCards: ProfitabilityCard[];
    pnl: ProfitabilityPnlRow[];
    tco: ProfitabilityTcoRow[];
    timeline: ProfitabilityTimeline;
    reconciliation: {
        cashGross: number;
        cashDeductions: number;
        cashNet: number;
        serviceNet: number;
        timingGap: number;
        label: string;
    } | null;
    controls: ProfitabilityControl[];
    empty: boolean;
    period: ProfitabilityPeriod;
    periodOptions: Record<string, string>;
    axisOptions: Record<string, string>;
    detail: ProfitabilityDetail | null;
};
