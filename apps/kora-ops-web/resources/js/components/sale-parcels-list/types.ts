export type CounterParcelScope =
    'all' | 'outbound' | 'inbound' | 'preregistered' | 'incidents';

export type CounterParcelDirection = 'outbound' | 'inbound' | 'network';

export type CounterParcelStatus =
    | 'preregistered'
    | 'paid'
    | 'ready'
    | 'loaded'
    | 'in_transit'
    | 'arrived'
    | 'available'
    | 'withdrawn'
    | 'refused'
    | 'return_pending';

export type CounterPaymentMethod =
    'cash' | 'mobile_money' | 'company_account' | 'pending';

export interface CounterParcelMovement {
    id: string;
    label: string;
    location: string;
    actor: string;
    timestamp: string;
    done: boolean;
}

export interface CounterParcelRecord {
    uuid: string;
    reference: string;
    direction: CounterParcelDirection;
    status: CounterParcelStatus;
    nature: 'standard' | 'documents' | 'fragile' | 'express_valeur';
    contents: string;
    originCity: string;
    originHubName: string;
    destinationCity: string;
    destinationHubName: string;
    lineCode: string;
    departureRef: string | null;
    departureLabel: string | null;
    senderName: string;
    senderPhone: string;
    recipientName: string;
    recipientPhone: string;
    weightKg: number | null;
    declaredWeightKg: number;
    dimensions: string;
    declaredValueFcfa: number;
    amountFcfa: number;
    paymentMethod: CounterPaymentMethod;
    paymentChannelLabel: string;
    paid: boolean;
    incidentOpen: boolean;
    incidentNote?: string;
    securityCode: string;
    withdrawDeadline?: string;
    createdAt: string;
    updatedAt: string;
    movements: CounterParcelMovement[];
}
