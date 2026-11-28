import type { ParcelListItem } from '@/components/parcels/types';

export type HubType = 'agency' | 'partner_point';

export interface ParcelHub {
    uuid: string;
    code: string;
    name: string;
    city: string;
    region: string;
    type: HubType;
    lineCode?: string;
    address: string;
    phone: string;
    isFrequent?: boolean;
}

export interface ParcelItineraryLeg {
    uuid: string;
    departureRef: string;
    lineCode: string;
    originCity: string;
    originHubName: string;
    destinationCity: string;
    destinationHubName: string;
    departureTime: string;
    arrivalTime: string;
    busRegistration: string;
    busModel: string;
    driverName: string;
    availableCargoKg: number;
    totalCargoKg: number;
    status: 'boarding' | 'ready' | 'scheduled';
}

export interface ParcelItineraryStop {
    uuid: string;
    name: string;
    city: string;
    country: string | null;
    stop_order: number;
    position: number;
    arrival_at: string | null;
    departure_at: string | null;
    timezone: string;
    is_origin: boolean;
    is_destination: boolean;
}

export interface ParcelItinerary {
    uuid: string;
    dayOffset: 0 | 1; // 0 = Aujourd'hui, 1 = Demain
    dayLabel: string;
    originCity: string;
    destinationHubUuid: string;
    destinationCity: string;
    legs: ParcelItineraryLeg[];
    stops?: ParcelItineraryStop[];
    transferHubCity?: string;
    transferWaitMinutes?: number;
    totalDurationLabel: string;
    minAvailableCargoKg: number;
    isNextDeparture?: boolean;
    tariffMultiplier: number;
}

export type ParcelNatureId =
    'standard' | 'documents' | 'fragile' | 'express_valeur';

export interface ParcelNatureOption {
    id: ParcelNatureId;
    label: string;
    badge: string;
    description: string;
    basePrice: number;
    pricePerKg: number;
}

export interface PreRegisteredParcel {
    uuid: string;
    trackingNumber: string;
    securityCode: string;
    senderName: string;
    senderPhone: string;
    recipientName: string;
    recipientPhone: string;
    originHub: ParcelHub;
    destinationHub: ParcelHub;
    nature: ParcelNatureId;
    declaredWeightKg: number;
    declaredValueFcfa: number;
    notes: string;
    paymentStatus: 'pending_at_counter' | 'prepaid_online';
    createdAt: string;
    suggestedItineraryUuid?: string;
}

export type PaymentMethod = 'cash' | 'mobile_money' | 'company_account';

export type ParcelMobileMoneyNetwork =
    | 'mtn_momo'
    | 'moov_flooz'
    | 'celtiis_cash'
    | 'tmoney_tg'
    | 'moov_flooz_tg'
    | 'wave_ci'
    | 'orange_money_ci'
    | 'mtn_momo_ci'
    | 'moov_flooz_ci';

export interface ParcelAgencyPaymentAccount {
    uuid: string;
    label: string;
    network: ParcelMobileMoneyNetwork;
    accountIdentifier: string;
}

export interface ParcelCompanyAccount {
    id: string;
    name: string;
    type: 'company' | 'ngo' | 'embassy';
    reference: string;
    label: string;
}

export interface ParcelQuoteBreakdown {
    basePrice: number;
    chargeableWeight: number;
    weightSurcharge: number;
    transferFee: number;
    subtotalHt: number;
    vat18: number;
    totalTtc: number;
}

export interface KnownParcelClient {
    id: string;
    name: string;
    phone: string;
    accountType: 'Client fidèle' | 'Marchand Kora' | 'Compte Entreprise';
    shipmentsCount: number;
    companyAccountId?: string;
    companyName?: string;
    defaultRecipientName?: string;
    defaultRecipientPhone?: string;
}

export interface ParcelIntakeDraft {
    originHub: ParcelHub;
    destinationHub: ParcelHub | null;
    dispatchWindow: 'today' | 'tomorrow' | 'all';
    selectedItinerary: ParcelItinerary | null;
    deferToNextAvailableCargo: boolean;

    // Acteurs
    knownClientId?: string;
    senderName: string;
    senderPhone: string;
    recipientName: string;
    recipientPhone: string;
    notifyWhatsApp: boolean;
    notifySms: boolean;

    // Colis
    nature: ParcelNatureId;
    weightKg: number;
    dimensions: string;
    declaredValueFcfa: number;
    prohibitedAccepted: boolean;
    notes: string;

    // Paiement (aligné sur le module Vente au Guichet)
    paymentMethod: PaymentMethod;
    cashTendered: number;
    payerName: string;
    payerPhone: string;
    momoPhone: string;
    momoProvider: ParcelMobileMoneyNetwork | '';
    agencyPaymentAccountUuid: string;
    momoReference: string;
    momoVerifiedManually: boolean;
    companyAccountId: string;

    // Reprise
    preRegistrationUuid?: string;
}

export interface FinalizedParcel {
    parcelUuid: string;
    trackingNumber: string;
    issuedAt: string;
    draft: ParcelIntakeDraft;
    quote: ParcelQuoteBreakdown;
    receiptNumber: string;
    agentName: string;
    agentSite: string;
    labelUrl: string;
    receiptUrl: string;
    receiptParcel: ParcelListItem;
}
