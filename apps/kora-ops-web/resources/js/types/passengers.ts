export type PassengerTicket = {
    uuid: string;
    shortCode: string;
    route: string;
    departureTime: string;
    lineCode: string;
    seatCode: string;
    amount: number;
    amountLabel: string;
    status: string;
    statusLabel: string;
    issuedAt: string;
};

export type PassengerDocument = {
    type: string | null;
    typeLabel: string;
    number: string;
    nationality: string | null;
    country: string;
};

export type PassengerSummary = {
    phone: string;
    firstName: string;
    lastName: string;
    fullName: string;
    email: string | null;
    nationality: string | null;
    nationalityLabel: string;
    documentType: string | null;
    documentTypeLabel: string;
    documentNumber: string | null;
    birthDate: string | null;
    hasAccount: boolean;
    ticketsCount: number;
    totalSpend: number;
    totalSpendLabel: string;
    lastRoute: string | null;
    lastSeenAt: string | null;
    lastSeenRelative: string | null;
    status: 'active' | 'to_verify';
    statusLabel: string;
    tickets?: PassengerTicket[];
    documents?: PassengerDocument[];
};

export type PaginatedPassengers = {
    data: PassengerSummary[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
};

export type PassengerFilters = {
    q?: string | null;
    status?: string | null;
    route?: string | null;
    frequency?: string | null;
};
