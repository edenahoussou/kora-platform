export type PromotionBenefitType = 'percentage' | 'fixed_amount';
export type PromotionScopeType = 'network' | 'channel' | 'line' | 'segment';
export type PromotionChannel = 'app' | 'counter';
export type PromotionStatus = 'draft' | 'active' | 'inactive' | 'archived';
export type PromotionDisplayStatus = PromotionStatus | 'scheduled' | 'expired';

export type PromotionLineOption = {
    uuid: string;
    code: string;
    name: string;
    stops: Array<{ uuid: string; name: string; city: string }>;
};

export type PromotionRecord = {
    uuid: string;
    name: string;
    code: string;
    benefitType: PromotionBenefitType;
    benefitValue: number;
    scopeType: PromotionScopeType;
    channel: PromotionChannel | null;
    line: { uuid: string; code: string; name: string } | null;
    originStopUuid: string | null;
    originStopName: string | null;
    destinationStopUuid: string | null;
    destinationStopName: string | null;
    priority: number;
    startsAt: string | null;
    endsAt: string | null;
    status: PromotionStatus;
    displayStatus: PromotionDisplayStatus;
    maxUses: number | null;
    usedCount: number;
    redemptionsCount: number;
    createdAt: string | null;
};

export type PromotionConflict = {
    uuid: string;
    name: string;
    code: string;
    priority: number;
    status: string;
    startsAt: string | null;
    endsAt: string | null;
};

export type PromotionRedemptionRecord = {
    uuid: string;
    reservationUuid: string | null;
    discountAmount: number;
    createdAt: string | null;
};

export type PromotionHistoryEntry = {
    event: string;
    at: string | null;
    actor: string | null;
};

export type PromotionDetail = {
    promotion: PromotionRecord;
    redemptions: PromotionRedemptionRecord[];
    history: PromotionHistoryEntry[];
};

export type PromotionFormValues = {
    name: string;
    code: string;
    benefit_type: PromotionBenefitType;
    benefit_value: string;
    scope_type: PromotionScopeType;
    channel: PromotionChannel | '';
    line_id: string;
    origin_stop_id: string;
    destination_stop_id: string;
    priority: string;
    starts_at: string;
    ends_at: string;
    max_uses: string;
    force: boolean;
};

export function formatFcfa(amount: number): string {
    return `${new Intl.NumberFormat('fr-FR').format(amount)} FCFA`;
}

export function formatBenefit(promotion: PromotionRecord): string {
    return promotion.benefitType === 'percentage'
        ? `${promotion.benefitValue} %`
        : formatFcfa(promotion.benefitValue);
}

export function formatPeriod(period: {
    startsAt: string | null;
    endsAt: string | null;
}): string {
    const format = (iso: string | null): string =>
        iso
            ? new Intl.DateTimeFormat('fr-FR', {
                  day: '2-digit',
                  month: 'short',
                  year: 'numeric',
              }).format(new Date(iso))
            : '—';

    return `${format(period.startsAt)} → ${period.endsAt ? format(period.endsAt) : 'sans fin'}`;
}
