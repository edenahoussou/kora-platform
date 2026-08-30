export type SaleStage = 'search' | 'seats' | 'passengers' | 'payment';

export type SalePaymentMethod = 'cash' | 'mobile_money';

export type SaleMobileMoneyNetwork = 'mtn_momo' | 'moov_flooz' | 'celtiis_cash';

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
};

export type SaleCity = {
    city: string;
    country: string;
    stopsCount: number;
};

export type SaleSearch = {
    date: string | null;
    from: string | null;
    to: string | null;
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
    seatUuid: string | null;
    seatCode: string | null;
};

export type SalePassengerForm = {
    firstName: string;
    lastName: string;
    phone: string;
    email: string;
    documentType: string;
    documentNumber: string;
    nationality: string;
    birthDate: string;
    seatUuid: string;
    seatCode: string;
};

export type SalePassengerDraft = {
    firstName: string;
    lastName: string;
    phone: string;
    email: string;
    documentType: string;
    documentNumber: string;
    nationality: string;
    birthDate: string;
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
    totalAmount: number;
    expiresAt: string | null;
};

export type SaleReservation = {
    uuid: string;
    status: string;
    expiresAt: string | null;
    totalAmount: number;
    currency: string;
    channel: string | null;
    departureUuid: string | null;
    payment: SalePayment | null;
    qrPayload: SaleReceiptQrPayload | null;
    fiscalization: {
        status: string | null;
        koraReference: string | null;
        mecefUid: string | null;
    } | null;
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
    }>;
};

export type SaleProps = {
    cities: SaleCity[];
    search: SaleSearch;
    departures: SaleDeparture[];
    selectedDeparture: SaleDeparture | null;
    seats: SaleSeat[];
    quote: SaleQuote | null;
    reservation: SaleReservation | null;
    cashSession: SaleCashSession;
    mobileMoneyAccounts: SaleMobileMoneyAccount[];
    permissions: { canCreate: boolean };
};
