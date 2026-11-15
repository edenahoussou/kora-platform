import type { Meta, StoryObj } from '@storybook/react-vite';
import { ItineraryCard } from '@/components/sale/itinerary-card';
import { SaleJourneySummary } from '@/components/sale/sale-journey-summary';
import { SaleSummaryCard } from '@/components/sale/sale-summary-card';
import type {
    SaleItinerary,
    SaleItineraryLeg,
    SaleReservation,
} from '@/components/sale/sale-types';

const leg: SaleItineraryLeg = {
    legOrder: 1,
    departureUuid: 'schedule-demo',
    departureDate: '2026-09-07',
    departureTime: '08:00',
    departureAt: '2026-09-09T23:30:00+00:00',
    arrivalAt: '2026-09-10T03:15:00+01:00',
    departureTimezone: 'Africa/Accra',
    arrivalTimezone: 'Africa/Porto-Novo',
    line: {
        uuid: 'line-demo',
        code: 'KORA-118',
        name: 'Accra → Parakou',
        isInternational: true,
    },
    bus: { uuid: 'bus-demo', registrationNumber: 'KORA-001', seatCount: 52 },
    originStop: {
        uuid: 'accra',
        name: 'Accra gare',
        city: 'Accra',
        country: 'GH',
        stopOrder: 2,
    },
    destinationStop: {
        uuid: 'parakou',
        name: 'Parakou gare',
        city: 'Parakou',
        country: 'BJ',
        stopOrder: 3,
    },
    amount: 15000,
    availableSeatsCount: 20,
    serviceClassCode: 'confort',
    serviceClassName: 'Confort',
};
const itinerary: SaleItinerary = {
    isDirect: false,
    connectionsCount: 1,
    totalAmount: 25000,
    currency: 'XOF',
    departureAt: leg.departureAt,
    arrivalAt: '2026-09-11T02:00:00+01:00',
    connectionMarginMinutes: 60,
    legs: [
        leg,
        {
            ...leg,
            legOrder: 2,
            originStop: leg.destinationStop,
            destinationStop: {
                ...leg.destinationStop,
                uuid: 'malanville',
                name: 'Malanville gare',
                city: 'Malanville',
                stopOrder: 4,
            },
            departureAt: '2026-09-10T23:00:00+01:00',
            arrivalAt: '2026-09-11T02:00:00+01:00',
            departureTimezone: 'Africa/Porto-Novo',
            amount: 10000,
        },
    ],
};

const meta = {
    title: 'Vente guichet/Horaires par arrêt',
    parameters: { layout: 'fullscreen' },
} satisfies Meta;
export default meta;
type Story = StoryObj<typeof meta>;

export const ConnectionAtDayTwo: Story = {
    render: () => (
        <div className="sale-page grid gap-6 p-6 lg:grid-cols-[minmax(0,1fr)_340px]">
            <ItineraryCard
                itinerary={itinerary}
                selected
                isNextDeparture={false}
                now={0}
                onSelect={() => {}}
            />
            <SaleSummaryCard
                selectedItinerary={itinerary}
                passengerCount={1}
                selectedSeatCodes={['2A']}
                quote={null}
                reservation={null}
                processing={false}
            />
        </div>
    ),
};

const reservation: SaleReservation = {
    uuid: 'reservation-demo',
    status: 'held',
    expiresAt: null,
    heldAt: null,
    totalAmount: 25000,
    currency: 'XOF',
    channel: 'counter',
    purchaseType: 'personal',
    groupSize: 1,
    promotionDiscount: null,
    payerType: 'personal',
    payerSnapshot: null,
    ticketIssuance: {
        status: 'not_started',
        error: null,
        startedAt: null,
        issuedAt: null,
    },
    companyAccount: null,
    passengerAllocations: [25000],
    departureUuid: leg.departureUuid,
    payment: null,
    qrPayload: null,
    fiscalization: null,
    tickets: [],
    passengers: [],
    draftPassengers: [],
    segments: itinerary.legs.map((item) => ({
        uuid: `segment-${item.legOrder}`,
        seatUuid: 'seat-demo',
        seatCode: '2A',
        originStop: item.originStop.name,
        destinationStop: item.destinationStop.name,
        originStopCity: item.originStop.city,
        destinationStopCity: item.destinationStop.city,
        departureDate: item.departureDate,
        departureAt: item.departureAt,
        arrivalAt: item.arrivalAt,
        departureTimezone: item.departureTimezone,
        arrivalTimezone: item.arrivalTimezone,
        lineCode: item.line.code,
        status: 'held',
        passengerIndex: 0,
        legOrder: item.legOrder,
        direction: 'outbound',
    })),
};

export const BasketAfterHold: Story = {
    render: () => (
        <div className="sale-page mx-auto max-w-sm p-4">
            <SaleJourneySummary
                departure={null}
                reservation={reservation}
                variant="passengers"
            />
        </div>
    ),
};

export const MissingStopSchedule: Story = {
    render: () => (
        <div className="sale-page mx-auto max-w-sm p-4">
            <SaleJourneySummary
                departure={null}
                reservation={{
                    ...reservation,
                    segments: reservation.segments.map((item) => ({
                        ...item,
                        departureAt: null,
                        arrivalAt: null,
                    })),
                }}
                variant="passengers"
            />
        </div>
    ),
};
