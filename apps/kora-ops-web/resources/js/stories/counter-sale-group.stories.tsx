import type { Meta, StoryObj } from '@storybook/react-vite';
import { SaleSummaryCard } from '@/components/sale/sale-summary-card';
import type {
    SaleCompanyAccount,
    SaleDeparture,
    SaleQuote,
} from '@/components/sale/sale-types';

const departure: SaleDeparture = {
    uuid: 'dep-counter-demo',
    departureDate: '2026-09-12',
    departureTime: '08:00',
    estimatedArrivalAt: '2026-09-12T14:00:00+00:00',
    status: 'scheduled',
    line: {
        uuid: 'line-counter-demo',
        code: 'KORA-118',
        name: 'Cotonou → Abidjan',
        estimatedDurationMinutes: 360,
        isInternational: true,
    },
    bus: {
        uuid: 'bus-counter-demo',
        registrationNumber: 'KORA-001',
        brand: 'KORA',
        model: 'Coach',
        seatCount: 52,
    },
    originStop: {
        uuid: 'stop-origin-demo',
        name: 'Gare de Cotonou',
        city: 'Cotonou',
        country: 'BJ',
        stopOrder: 1,
    },
    destinationStop: {
        uuid: 'stop-destination-demo',
        name: 'Gare d’Abidjan',
        city: 'Abidjan',
        country: 'CI',
        stopOrder: 2,
    },
    availableSeatsCount: 28,
    sellability: { isSellable: true, reason: 'within_grace', saleClosesAt: '2026-09-12T07:30:00+00:00' },
    farePreview: { final_amount: 10000, currency: 'XOF', service_class_name: 'Confort' },
};

const quote = (passengerCount: number, totalAmount: number, discountAmount = 0): SaleQuote => ({
    uuid: `quote-${passengerCount}`,
    baseAmount: passengerCount * 10000,
    coefficient: 1,
    finalAmount: 10000,
    currency: 'XOF',
    passengerCount,
    subtotalAmount: passengerCount * 10000,
    adjustedSubtotalAmount: passengerCount * 10000,
    dynamicAdjustmentAmount: 0,
    groupDiscount: {
        applied: discountAmount > 0,
        threshold: discountAmount > 0 ? 10 : null,
        percentage: discountAmount > 0 ? 10 : 0,
        amount: discountAmount,
    },
    isGroup: passengerCount >= 5,
    passengerAllocations: Array.from({ length: passengerCount }, () => Math.floor(totalAmount / passengerCount)),
    totalAmount,
    expiresAt: '2026-09-12T07:45:00+00:00',
});

const companyAccounts: SaleCompanyAccount[] = [
    {
        uuid: 'company-solvable',
        name: 'Société Atlantique',
        type: 'company',
        reference: 'ATL-001',
        phone: '+22997000000',
        email: 'travel@atlantique.example',
        monthlyCreditLimit: 500000,
        availableCredit: 250000,
        referent: { uuid: 'referent-1', name: 'Mariam K.' },
    },
    {
        uuid: 'company-insufficient',
        name: 'Crédit insuffisant',
        type: 'ngo',
        reference: 'NGO-009',
        phone: null,
        email: null,
        monthlyCreditLimit: 50000,
        availableCredit: 5000,
        referent: null,
    },
];

const meta = {
    title: 'Vente guichet / achats groupés',
    component: SaleSummaryCard,
    parameters: { layout: 'centered' },
    args: {
        departure,
        passengerCount: 1,
        selectedSeatCodes: ['2A'],
        quote: quote(1, 10000),
        reservation: null,
        processing: false,
        companyAccounts,
        payerType: 'personal',
        selectedCompanyAccountUuid: null,
    },
} satisfies Meta<typeof SaleSummaryCard>;

export default meta;
type Story = StoryObj<typeof meta>;

export const OneTraveller: Story = {};
export const GroupOfFive: Story = {
    args: { passengerCount: 5, selectedSeatCodes: ['2A', '2B', '3A', '3B', '4A'], quote: quote(5, 50000) },
};
export const GroupOfTenWithDiscount: Story = {
    args: {
        passengerCount: 10,
        selectedSeatCodes: ['2A', '2B', '3A', '3B', '4A', '4B', '5A', '5B', '6A', '6B'],
        quote: quote(10, 90000, 10000),
    },
};
export const CompanyCreditSolvable: Story = {
    args: {
        passengerCount: 5,
        selectedSeatCodes: ['2A', '2B', '3A', '3B', '4A'],
        quote: quote(5, 50000),
        payerType: 'company',
        selectedCompanyAccountUuid: 'company-solvable',
    },
};
export const CompanyCreditInsufficient: Story = {
    args: {
        passengerCount: 10,
        selectedSeatCodes: ['2A', '2B', '3A', '3B', '4A', '4B', '5A', '5B', '6A', '6B'],
        quote: quote(10, 90000, 10000),
        payerType: 'company',
        selectedCompanyAccountUuid: 'company-insufficient',
    },
};
