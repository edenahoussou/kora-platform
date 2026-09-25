export type ManifestPassenger = {
    uuid: string;
    firstName: string;
    lastName: string;
    phone: string | null;
    nationality: string | null;
    documentTypeLabel: string | null;
    documentNumber: string | null;
    birthDate: string | null;
};

export type ManifestTicket = {
    uuid: string;
    shortCode: string;
    status: string;
    statusLabel: string;
    issuedAt: string | null;
};

export type ManifestDepartureRef = {
    uuid: string;
    lineCode: string | null;
    departureDate: string | null;
    departureTime: string | null;
};

export type ManifestReservationRef = {
    uuid: string;
    reference: string | null;
    /** null when the reservation's passenger count wasn't loaded for this row. */
    isGroupPurchase: boolean | null;
};

export type JourneyStatus = {
    value: string;
    label: string;
};

/**
 * One row of a manifest (by departure or by reservation) or of the ticket
 * search list — same shape everywhere so the detail dialog and status
 * badges are fully reusable across the three screens.
 */
export type ManifestRow = {
    segmentUuid: string | null;
    passenger: ManifestPassenger | null;
    seatCode: string | null;
    originStop: string | null;
    destinationStop: string | null;
    reservation: ManifestReservationRef | null;
    journeyStatus: JourneyStatus | null;
    /** null = no ticket issued yet, not an error. */
    ticket: ManifestTicket | null;
    departure: ManifestDepartureRef | null;
};
