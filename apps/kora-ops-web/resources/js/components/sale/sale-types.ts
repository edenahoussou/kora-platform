export type SaleStage = 'search' | 'seats' | 'held';

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
    farePreview: { final_amount?: number; currency?: string } | null;
};

export type SaleSeat = {
    uuid: string;
    code: string;
    type: string;
    sellable: boolean;
    availabilityStatus: 'available' | 'occupied' | 'blocked';
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
    permissions: { canCreate: boolean };
};
