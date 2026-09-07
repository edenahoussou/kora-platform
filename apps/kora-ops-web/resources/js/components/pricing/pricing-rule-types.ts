export type PricingRuleRecord = {
    uuid: string;
    lineUuid: string | null;
    lineCode: string | null;
    lineName: string | null;
    type: string;
    percentage: number;
    priority: number;
    conditions: {
        fillRateMin: number | null;
        fillRateMax: number | null;
        hoursBeforeDepartureMin: number | null;
        hoursBeforeDepartureMax: number | null;
    };
    startsAt: string | null;
    endsAt: string | null;
    status: string;
};
