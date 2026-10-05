import { useForm } from '@inertiajs/react';
import { useRef } from 'react';
import { toast } from 'sonner';
import type { SaleSearch } from '@/components/sale/sale-types';
import { uuid } from '@/lib/utils';
import companyPortal from '@/routes/company-portal';
import type { CompanyPortalPassengerSelection } from './roster-passenger-picker';

export type CompanyPortalLegRequest = {
    departure_uuid: string;
    origin_stop_uuid: string;
    destination_stop_uuid: string;
};

export type CompanyPortalSeatAssignments = Array<{
    passenger_index: number;
    seat_assignments: Array<{
        leg_order: number;
        direction: 'outbound' | 'inbound';
        seat_uuid: string;
    }>;
}>;

type SearchContext = Pick<
    SaleSearch,
    'date' | 'from' | 'to' | 'tripType' | 'returnDate'
>;

function reportErrors(errors: Record<string, string>, fallback: string): void {
    toast.error(Object.values(errors).find(Boolean) ?? fallback);
}

export function useCompanyPortalSale() {
    const holdIdempotencyKey = useRef(uuid());
    const paymentIdempotencyKey = useRef<{
        reservationUuid: string;
        value: string;
    } | null>(null);
    const quoteForm = useForm({
        outbound_legs: [] as CompanyPortalLegRequest[],
        inbound_legs: [] as CompanyPortalLegRequest[],
        passenger_count: 1,
        date: '',
        from: '',
        to: '',
        trip_type: 'one_way',
        return_date: '',
    });
    const seatsForm = useForm({
        itinerary_quote_uuid: '',
        date: '',
        from: '',
        to: '',
        trip_type: 'one_way',
        return_date: '',
    });
    const holdForm = useForm({
        itinerary_quote_uuid: '',
        idempotency_key: '',
        passengers: [] as CompanyPortalSeatAssignments,
        date: '',
        from: '',
        to: '',
        trip_type: 'one_way',
        return_date: '',
    });
    const passengersForm = useForm({
        passengers: [] as CompanyPortalPassengerSelection[],
    });
    const paymentForm = useForm({
        idempotency_key: '',
    });
    const releaseForm = useForm({});

    const calculateQuote = (
        payload: {
            outbound_legs: CompanyPortalLegRequest[];
            inbound_legs: CompanyPortalLegRequest[];
            passenger_count: number;
        } & SearchContext,
    ) => {
        quoteForm.transform(() => ({
            outbound_legs: payload.outbound_legs,
            inbound_legs: payload.inbound_legs,
            passenger_count: payload.passenger_count,
            date: payload.date ?? '',
            from: payload.from ?? '',
            to: payload.to ?? '',
            trip_type: payload.tripType,
            return_date: payload.returnDate ?? '',
        }));
        quoteForm.post(companyPortal.sale.quote.url(), {
            preserveScroll: true,
            onSuccess: () => {
                holdIdempotencyKey.current = uuid();
            },
            onError: (errors) =>
                reportErrors(
                    errors as Record<string, string>,
                    'Le devis n’a pas pu être calculé.',
                ),
        });
    };

    const fetchSeats = (
        quoteUuid: string,
        context: SearchContext,
        onSuccess?: () => void,
    ) => {
        seatsForm.transform(() => ({
            itinerary_quote_uuid: quoteUuid,
            date: context.date ?? '',
            from: context.from ?? '',
            to: context.to ?? '',
            trip_type: context.tripType,
            return_date: context.returnDate ?? '',
        }));
        seatsForm.post(companyPortal.sale.seats.url(), {
            preserveScroll: true,
            onSuccess,
            onError: (errors) =>
                reportErrors(
                    errors as Record<string, string>,
                    'Les places disponibles n’ont pas pu être chargées.',
                ),
        });
    };

    const holdSeats = (
        quoteUuid: string,
        assignments: CompanyPortalSeatAssignments,
        context: SearchContext,
    ) => {
        holdForm.transform(() => ({
            itinerary_quote_uuid: quoteUuid,
            idempotency_key: holdIdempotencyKey.current,
            passengers: assignments,
            date: context.date ?? '',
            from: context.from ?? '',
            to: context.to ?? '',
            trip_type: context.tripType,
            return_date: context.returnDate ?? '',
        }));
        holdForm.post(companyPortal.sale.hold.url(), {
            preserveScroll: true,
            onError: (errors) =>
                reportErrors(
                    errors as Record<string, string>,
                    'Les sièges n’ont pas pu être réservés.',
                ),
        });
    };

    const savePassengers = (
        reservationUuid: string,
        passengerSelections: CompanyPortalPassengerSelection[],
        onSuccess?: () => void,
    ) => {
        passengersForm.transform(() => ({
            passengers: passengerSelections.map((passenger) => ({
                traveler_uuid: passenger.travelerUuid,
                passenger_index: passenger.passengerIndex,
                document_type: passenger.documentType || null,
                document_number: passenger.documentNumber || null,
                nationality: passenger.nationality || null,
                birth_date: passenger.birthDate || null,
                yellow_fever_vaccinated: passenger.yellowFeverVaccinated,
            })),
        }));
        passengersForm.post(
            companyPortal.sale.passengers.url({
                reservation: reservationUuid,
            }),
            {
                preserveScroll: true,
                onSuccess,
                onError: (errors) =>
                    reportErrors(
                        errors as Record<string, string>,
                        'Les voyageurs n’ont pas pu être enregistrés.',
                    ),
            },
        );
    };

    const recordPayment = (reservationUuid: string) => {
        if (
            paymentIdempotencyKey.current?.reservationUuid !== reservationUuid
        ) {
            paymentIdempotencyKey.current = {
                reservationUuid,
                value: uuid(),
            };
        }

        const idempotencyKey = paymentIdempotencyKey.current.value;
        paymentForm.transform(() => ({ idempotency_key: idempotencyKey }));
        paymentForm.post(
            companyPortal.sale.payment.url({
                reservation: reservationUuid,
            }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    paymentIdempotencyKey.current = null;
                },
                onError: (errors) =>
                    reportErrors(
                        errors as Record<string, string>,
                        'Le débit du crédit entreprise a échoué.',
                    ),
            },
        );
    };

    const releaseHold = (reservationUuid: string, onSuccess?: () => void) => {
        releaseForm.post(
            companyPortal.sale.release.url({
                reservation: reservationUuid,
            }),
            {
                preserveScroll: true,
                onSuccess,
            },
        );
    };

    return {
        quoteForm,
        seatsForm,
        holdForm,
        passengersForm,
        paymentForm,
        releaseForm,
        calculateQuote,
        fetchSeats,
        holdSeats,
        savePassengers,
        recordPayment,
        releaseHold,
    };
}
