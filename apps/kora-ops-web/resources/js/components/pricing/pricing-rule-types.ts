export type PricingRuleScope = 'network' | 'line' | 'segment';

export type PricingRuleDirection = 'both' | 'outbound' | 'return';

export type PricingRuleRecord = {
    uuid: string;
    scope: PricingRuleScope;
    direction: PricingRuleDirection;
    lineUuid: string | null;
    lineCode: string | null;
    lineName: string | null;
    originStopUuid: string | null;
    originStopName: string | null;
    destinationStopUuid: string | null;
    destinationStopName: string | null;
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
