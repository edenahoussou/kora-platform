export type SaleStage =
    | 'search'
    | 'seats'
    | 'payer'
    | 'passengers'
    | 'payment'
    | 'tickets';

export type SalePaymentMethod = 'cash' | 'mobile_money' | 'company_credit';

export type SaleBusAmenity = {
    code: string;
    label: string;
    icon: string | null;
};

export type SaleMobileMoneyNetwork = 'mtn_momo' | 'moov_flooz' | 'celtiis_cash';

export type SaleCompanyAccount = {
    uuid: string;
    name: string;
    type: 'company' | 'ngo' | 'embassy';
    reference: string;
    phone: string | null;
    email: string | null;
    monthlyCreditLimit: number;
    availableCredit: number;
    referent: { uuid: string; name: string } | null;
};

export type SaleGroupBookingSettings = {
    minimumPassengers: number;
    maximumPassengers: number;
};

export type SalePayerType = 'personal' | 'company';

export type SaleMobileMoneyAccount = {
    uuid: string;
    network: SaleMobileMoneyNetwork;
    label: string;
    accountIdentifier: string;
    ledgerUuid?: string | null;
};

export type SaleCashLedger = {
    uuid: string;
    kind: 'cash' | 'mobile_money';
    status: 'open' | 'pending_approval' | 'closed';
    openingBalance: number;
    expectedBalance: number;
    actualBalance: number | null;
    variance: number | null;
    account: SaleMobileMoneyAccount | null;
    media?: SaleCashMedia[];
};

export type SaleCashMedia = {
    uuid: string;
    name: string;
    mimeType: string;
    size: number;
    createdAt: string | null;
    url: string;
};

export type SaleCashSession = {
    isOpen: boolean;
    canOpen: boolean;
    uuid: string | null;
    businessDate: string | null;
    totalCash: number;
    totalMobileMoney: number;
    status?: 'open' | 'pending_approval' | 'closed' | null;
    ledgers?: SaleCashLedger[];
};

export type SalePayment = {
    uuid: string;
    method: SalePaymentMethod | null;
    network: SaleMobileMoneyNetwork | null;
    providerReference: string | null;
    payerPhone: string | null;
    payerName: string | null;
    amount: number;
    cashReceivedAmount: number | null;
    changeAmount: number | null;
    agencyAccount: {
        uuid: string;
        label: string;
        accountIdentifier: string;
    } | null;
    verifiedAt: string | null;
    confirmedAt: string | null;
    verifiedByName: string | null;
    verifiedByUuid: string | null;
    agencyName: string | null;
    agencyCode: string | null;
};

export type SaleReceiptQrPayload = {
    version: 1;
    type: 'reservation_confirmation';
    reservationCode: string;
    reservationUuid: string;
    agencyCode: string | null;
    agencyName: string | null;
    confirmedAt: string | null;
    agentUuid: string | null;
};

export type SaleTicket = {
    uuid: string;
    shortCode: string;
    ticketNumber: string;
    qrCode: string;
    seatCode: string | null;
    passengerUuid: string | null;
    passengerIndex: number | null;
    passengerName: string | null;
    allocationAmount: number;
    transactionReference: string;
    status: string;
    legs: Array<{
        legOrder: number;
        originStop: string | null;
        destinationStop: string | null;
        seatCode: string | null;
    }>;
};

export type SaleCity = {
    city: string;
    country: string;
    stopsCount: number;
};

export type SaleTripType = 'one_way' | 'round_trip';

export type SaleServiceClass = {
    uuid: string;
    code: string;
    name: string;
};

export type SaleSearch = {
    date: string | null;
    from: string | null;
    to: string | null;
    tripType: SaleTripType;
    returnDate: string | null;
    serviceClass: string | null;
    hasDate?: boolean;
};

export type SaleSellabilityReason =
    | 'before_cutoff'
    | 'within_grace'
    | 'past_cutoff'
    | 'boarding'
    | 'departed'
    | 'arrived'
    | 'cancelled';

export type SaleDepartureSellability = {
    isSellable: boolean;
    reason: SaleSellabilityReason;
    saleClosesAt: string;
};

export type SalePassenger = {
    uuid: string;
    firstName: string;
    lastName: string;
    phone: string | null;
    email: string | null;
    documentType: string | null;
    documentNumber: string | null;
    nationality: string | null;
    birthDate: string | null;
    passengerIndex: number | null;
    yellowFeverVaccinated: boolean | null;
    seatUuid: string | null;
    seatCode: string | null;
};

export type SalePassengerForm = {
    passengerIndex: number;
    firstName: string;
    lastName: string;
    phone: string;
    email: string;
    documentType: string;
    documentNumber: string;
    nationality: string;
    birthDate: string;
    yellowFeverVaccinated: boolean | null;
    seatUuid: string;
    seatCode: string;
};

export type SalePassengerDraft = {
    passengerIndex: number;
    firstName: string;
    lastName: string;
    phone: string;
    email: string;
    documentType: string;
    documentNumber: string;
    nationality: string;
    birthDate: string;
    yellowFeverVaccinated: boolean | null;
    seatUuid: string | null;
    seatCode: string | null;
};

export type SaleStop = {
    uuid: string;
    name: string;
    city: string;
    country: string;
    stopOrder: number;
};

export type SaleDeparture = {
    uuid: string;
    departureDate: string;
    departureTime: string;
    estimatedArrivalAt: string | null;
    status: string;
    line: {
        uuid: string;
        code: string;
        name: string;
        estimatedDurationMinutes: number | null;
        isInternational: boolean;
    };
    bus: {
        uuid: string;
        registrationNumber: string;
        brand: string | null;
        model: string | null;
        seatCount: number;
        amenities?: SaleBusAmenity[];
    };
    originStop: SaleStop;
    destinationStop: SaleStop;
    availableSeatsCount: number;
    sellability: SaleDepartureSellability;
    farePreview: {
        final_amount?: number;
        currency?: string;
        service_class_code?: string | null;
        service_class_name?: string | null;
    } | null;
};

export type SaleItineraryLeg = {
    direction?: 'outbound' | 'inbound';
    legOrder: number;
    departureUuid: string;
    departureDate: string;
    departureTime: string;
    line: {
        uuid: string;
        code: string;
        name: string;
        estimatedDurationMinutes?: number | null;
        isInternational: boolean;
    };
    bus: {
        uuid: string;
        registrationNumber: string;
        brand?: string | null;
        model?: string | null;
        seatCount?: number;
        amenities?: SaleBusAmenity[];
    };
    originStop: SaleStop;
    destinationStop: SaleStop;
    amount: number;
    availableSeatsCount: number;
    serviceClassCode: string | null;
    serviceClassName: string | null;
    departureAt: string;
    arrivalAt: string;
};

export type SaleItinerary = {
    isDirect: boolean;
    connectionsCount: number;
    totalAmount: number;
    currency: string;
    departureAt: string;
    arrivalAt: string;
    connectionMarginMinutes: number;
    legs: SaleItineraryLeg[];
};

export type SaleSeat = {
    uuid: string;
    code: string;
    type: string;
    sellable: boolean;
    availabilityStatus: 'available' | 'reserved' | 'paid' | 'blocked';
    expiresAt: string | null;
};

export type SaleQuote = {
    uuid: string;
    baseAmount: number;
    coefficient: number;
    finalAmount: number;
    currency: string;
    passengerCount: number;
    subtotalAmount: number;
    adjustedSubtotalAmount: number;
    dynamicAdjustmentAmount: number;
    groupDiscount: {
        applied: boolean;
        threshold: number | null;
        percentage: number;
        amount: number;
    };
    isGroup: boolean;
    promotions?: Array<Record<string, unknown>>;
    promotionDiscountAmount?: number;
    passengerAllocations: number[];
    totalAmount: number;
    expiresAt: string | null;
};

export type SaleItineraryQuoteLeg = SaleItineraryLeg & {
    amount: number;
    currency: string;
};

export type SaleItineraryQuote = SaleQuote & {
    groupDiscountAmount: number;
    legs: SaleItineraryQuoteLeg[];
};

export type SaleItineraryLegSeat = {
    legOrder: number;
    direction: 'outbound' | 'inbound';
    seats: SaleSeat[];
};

export type SaleSelectedItineraryLeg = {
    direction: 'outbound' | 'inbound';
    legOrder: number;
    departureUuid: string;
};

export type SaleReservation = {
    uuid: string;
    status: string;
    expiresAt: string | null;
    totalAmount: number;
    currency: string;
    channel: string | null;
    purchaseType: string | null;
    groupSize: number | null;
    payerType: string | null;
    payerSnapshot: {
        type: string;
        display_name: string;
        phone: string | null;
        account_uuid: string;
        company_reference?: string | null;
        referent_uuid?: string | null;
        referent_name?: string | null;
        captured_at: string;
    } | null;
    ticketIssuance: {
        status: 'not_started' | 'pending' | 'processing' | 'failed' | 'issued';
        error: string | null;
        startedAt: string | null;
        issuedAt: string | null;
    };
    companyAccount: {
        uuid: string;
        name: string;
        type: string;
        reference: string;
    } | null;
    passengerAllocations: number[];
    departureUuid: string | null;
    payment: SalePayment | null;
    qrPayload: SaleReceiptQrPayload | null;
    fiscalization: {
        status: string | null;
        koraReference: string | null;
        mecefUid: string | null;
    } | null;
    refunds?: Array<{
        uuid: string;
        amount: number;
        currency: string;
        destination: string;
        status: string;
        reference: string;
    }>;
    tickets: SaleTicket[];
    passengers: SalePassenger[];
    draftPassengers: SalePassengerDraft[];
    segments: Array<{
        uuid: string;
        seatUuid: string | null;
        seatCode: string | null;
        originStop: string | null;
        destinationStop: string | null;
        status: string;
        passengerIndex?: number | null;
        legOrder?: number;
        direction?: 'outbound' | 'inbound';
    }>;
};

export type SaleProps = {
    cities: SaleCity[];
    serviceClasses: SaleServiceClass[];
    search: SaleSearch;
    outboundItineraries: SaleItinerary[];
    inboundItineraries?: SaleItinerary[];
    selectedOutboundItinerary?: SaleItinerary | null;
    selectedInboundItinerary?: SaleItinerary | null;
    seats: SaleSeat[];
    itineraryLegSeats?: SaleItineraryLegSeat[];
    quote: SaleQuote | null;
    itineraryQuote?: SaleItineraryQuote | null;
    reservation: SaleReservation | null;
    cashSession: SaleCashSession;
    mobileMoneyAccounts: SaleMobileMoneyAccount[];
    companyAccounts: SaleCompanyAccount[];
    groupBookingSettings: SaleGroupBookingSettings;
    permissions: { canCreate: boolean };
};
