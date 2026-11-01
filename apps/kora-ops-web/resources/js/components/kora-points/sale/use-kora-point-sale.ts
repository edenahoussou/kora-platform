import { router, useForm } from '@inertiajs/react';
import { useCallback, useRef } from 'react';
import type { SyntheticEvent } from 'react';
import { toast } from 'sonner';
import type { CounterSalePaymentPayload } from '@/components/sale/payment-step';
import type {
    SaleItinerary,
    SalePassengerForm,
    SalePayerType,
    SaleProps,
    SaleSearch,
} from '@/components/sale/sale-types';
import { uuid } from '@/lib/utils';
import koraPoint from '@/routes/kora-point';
import type { FinancialHealth, KoraPointProfile } from '@/types/kora-points';

function passengerToSnakeCase(passenger: SalePassengerForm) {
    return {
        first_name: passenger.firstName,
        last_name: passenger.lastName,
        phone: passenger.phone,
        email: passenger.email,
        document_type: passenger.documentType,
        document_number: passenger.documentNumber,
        nationality: passenger.nationality,
        birth_date: passenger.birthDate,
        passenger_index: passenger.passengerIndex,
        yellow_fever_vaccinated: passenger.yellowFeverVaccinated,
        seat_uuid: passenger.seatUuid,
    };
}

function firstErrorMessage(
    errors: Record<string, string>,
    fallback: string,
): string {
    return (
        Object.values(errors).find((value) => typeof value === 'string') ??
        fallback
    );
}

export type ItinerarySeatAssignment = {
    passenger_index: number;
    seat_assignments: Array<{
        leg_order: number;
        direction?: 'outbound' | 'inbound';
        seat_uuid: string;
    }>;
};

export type KoraPointSaleProps = SaleProps & {
    point: KoraPointProfile;
    health: FinancialHealth;
    commissionEarned?: {
        commission_amount: number;
        net_amount: number;
        gross_amount: number;
    } | null;
};

export function useKoraPointSale(props: KoraPointSaleProps) {
    const holdIdempotencyKey = useRef(uuid());
    const searchForm = useForm<SaleSearch>({ ...props.search });
    const quoteForm = useForm({
        date: searchForm.data.date ?? null,
        from: searchForm.data.from ?? undefined,
        to: searchForm.data.to ?? undefined,
        trip_type: searchForm.data.tripType,
        return_date:
            searchForm.data.tripType === 'round_trip'
                ? searchForm.data.returnDate
                : null,
        passenger_count: 1,
        outbound_legs: [] as Array<{
            departure_uuid: string;
            origin_stop_uuid: string;
            destination_stop_uuid: string;
        }>,
        inbound_legs: [] as Array<{
            departure_uuid: string;
            origin_stop_uuid: string;
            destination_stop_uuid: string;
        }>,
    });
    const holdForm = useForm({
        date: searchForm.data.date ?? null,
        from: searchForm.data.from ?? undefined,
        to: searchForm.data.to ?? undefined,
        trip_type: searchForm.data.tripType,
        return_date:
            searchForm.data.tripType === 'round_trip'
                ? searchForm.data.returnDate
                : null,
        itinerary_quote_uuid: props.itineraryQuote?.uuid ?? '',
        idempotency_key: '',
        passengers: [] as ItinerarySeatAssignment[],
        payer: null as {
            type: SalePayerType;
            phone?: string;
            name?: string;
        } | null,
    });
    const confirmForm = useForm({
        date: searchForm.data.date ?? null,
        from: searchForm.data.from ?? undefined,
        to: searchForm.data.to ?? undefined,
        trip_type: searchForm.data.tripType,
        return_date:
            searchForm.data.tripType === 'round_trip'
                ? searchForm.data.returnDate
                : null,
        passenger_count: 1,
        outbound_legs: [] as Array<{
            departure_uuid: string;
            origin_stop_uuid: string;
            destination_stop_uuid: string;
        }>,
        inbound_legs: [] as Array<{
            departure_uuid: string;
            origin_stop_uuid: string;
            destination_stop_uuid: string;
        }>,
        passengers: [] as ItinerarySeatAssignment[],
        idempotency_key: '',
        payer: null as {
            type: SalePayerType;
            phone?: string;
            name?: string;
        } | null,
    });
    const passengersForm = useForm<{
        date: string | null;
        from: string | undefined;
        to: string | undefined;
        passengers: SalePassengerForm[];
        information_confirmed: boolean;
    }>({
        date: searchForm.data.date ?? null,
        from: searchForm.data.from ?? undefined,
        to: searchForm.data.to ?? undefined,
        passengers: [],
        information_confirmed: false,
    });
    const releaseForm = useForm({
        date: searchForm.data.date ?? null,
        from: searchForm.data.from ?? undefined,
        to: searchForm.data.to ?? undefined,
        itinerary_quote_uuid: props.itineraryQuote?.uuid ?? undefined,
    });
    const paymentForm = useForm<CounterSalePaymentPayload>({
        method: 'cash',
        amount: 0,
        idempotency_key: '',
        cash_received_amount: null,
        mobile_money_network: null,
        agency_payment_account_uuid: null,
        payer_phone: '',
        payer_name: '',
        provider_reference: '',
        verified_manually: false,
    });

    const search = (event: SyntheticEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            koraPoint.sale.url(),
            {
                date: searchForm.data.date,
                from: searchForm.data.from,
                to: searchForm.data.to,
                trip_type: searchForm.data.tripType,
                return_date:
                    searchForm.data.tripType === 'round_trip'
                        ? searchForm.data.returnDate
                        : null,
                service_class: searchForm.data.serviceClass ?? null,
            },
            {
                only: [
                    'search',
                    'outboundItineraries',
                    'inboundItineraries',
                    'itineraryQuote',
                    'itineraryLegSeats',
                    'reservation',
                ],
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const calculateItineraryQuote = (
        passengerCount: number,
        outbound: SaleItinerary,
        inbound: SaleItinerary | null,
        onSuccess?: () => void,
    ) => {
        const toLegPayload = (leg: SaleItinerary['legs'][number]) => ({
            departure_uuid: leg.departureUuid,
            origin_stop_uuid: leg.originStop.uuid,
            destination_stop_uuid: leg.destinationStop.uuid,
        });

        quoteForm.transform(() => ({
            date:
                searchForm.data.date ?? outbound.legs[0]?.departureDate ?? null,
            from: searchForm.data.from ?? undefined,
            to: searchForm.data.to ?? undefined,
            trip_type: inbound ? 'round_trip' : 'one_way',
            return_date: inbound
                ? (searchForm.data.returnDate ??
                  inbound.legs[0]?.departureDate ??
                  null)
                : null,
            passenger_count: passengerCount,
            outbound_legs: outbound.legs.map(toLegPayload),
            inbound_legs: inbound ? inbound.legs.map(toLegPayload) : [],
        }));

        quoteForm.post(koraPoint.sale.quote.url(), {
            preserveScroll: true,
            onSuccess,
            onError: (errors) => {
                toast.error(
                    firstErrorMessage(
                        errors as Record<string, string>,
                        'Le devis n’a pas pu être calculé. Vérifiez votre sélection et réessayez.',
                    ),
                );
            },
        });
    };

    const fetchSeats = (
        outbound: SaleItinerary,
        inbound: SaleItinerary | null,
        onSuccess?: () => void,
    ) => {
        const toLegPayload = (leg: SaleItinerary['legs'][number]) => ({
            departure_uuid: leg.departureUuid,
            origin_stop_uuid: leg.originStop.uuid,
            destination_stop_uuid: leg.destinationStop.uuid,
        });

        router.post(
            koraPoint.sale.seats.url(),
            {
                date:
                    searchForm.data.date ??
                    outbound.legs[0]?.departureDate ??
                    null,
                from: searchForm.data.from ?? undefined,
                to: searchForm.data.to ?? undefined,
                trip_type: inbound ? 'round_trip' : 'one_way',
                return_date: inbound
                    ? (searchForm.data.returnDate ??
                      inbound.legs[0]?.departureDate ??
                      null)
                    : null,
                outbound_legs: outbound.legs.map(toLegPayload),
                inbound_legs: inbound ? inbound.legs.map(toLegPayload) : [],
            },
            {
                only: ['itineraryLegSeats'],
                preserveState: true,
                preserveScroll: true,
                onSuccess,
                onError: (errors) => {
                    toast.error(
                        firstErrorMessage(
                            errors as Record<string, string>,
                            'Impossible de charger les places. Vérifiez votre sélection et réessayez.',
                        ),
                    );
                },
            },
        );
    };

    const confirmSale = (
        outbound: SaleItinerary,
        inbound: SaleItinerary | null,
        passengerCount: number,
        seatAssignments: ItinerarySeatAssignment[],
        onSuccess?: () => void,
        payer?: {
            type: SalePayerType;
            phone?: string;
            name?: string;
        },
    ) => {
        const toLegPayload = (leg: SaleItinerary['legs'][number]) => ({
            departure_uuid: leg.departureUuid,
            origin_stop_uuid: leg.originStop.uuid,
            destination_stop_uuid: leg.destinationStop.uuid,
        });

        confirmForm.transform(() => ({
            date:
                searchForm.data.date ?? outbound.legs[0]?.departureDate ?? null,
            from: searchForm.data.from ?? undefined,
            to: searchForm.data.to ?? undefined,
            trip_type: inbound ? 'round_trip' : 'one_way',
            return_date: inbound
                ? (searchForm.data.returnDate ??
                  inbound.legs[0]?.departureDate ??
                  null)
                : null,
            passenger_count: passengerCount,
            outbound_legs: outbound.legs.map(toLegPayload),
            inbound_legs: inbound ? inbound.legs.map(toLegPayload) : [],
            passengers: seatAssignments,
            idempotency_key: '',
            payer: payer ?? null,
        }));
        confirmForm.post(koraPoint.sale.confirm.url(), {
            preserveScroll: true,
            onSuccess,
            onError: (errors) => {
                toast.error(
                    firstErrorMessage(
                        errors as Record<string, string>,
                        'La confirmation de la vente a échoué. Vérifiez votre sélection et réessayez.',
                    ),
                );
            },
        });
    };

    const holdItinerarySeats = (
        itineraryQuoteUuid: string,
        seatAssignments: ItinerarySeatAssignment[],
        onSuccess?: () => void,
        payer?: {
            type: SalePayerType;
            phone?: string;
            name?: string;
        },
    ) => {
        holdForm.transform(() => ({
            date: searchForm.data.date ?? null,
            from: searchForm.data.from ?? undefined,
            to: searchForm.data.to ?? undefined,
            trip_type: searchForm.data.tripType,
            return_date:
                searchForm.data.tripType === 'round_trip'
                    ? searchForm.data.returnDate
                    : null,
            itinerary_quote_uuid: itineraryQuoteUuid,
            idempotency_key: holdIdempotencyKey.current,
            passengers: seatAssignments,
            payer: payer ?? null,
        }));

        holdForm.post(koraPoint.sale.hold.url(), {
            preserveScroll: true,
            onSuccess: () => {
                holdIdempotencyKey.current = uuid();
                onSuccess?.();
            },
            onError: (errors) => {
                toast.error(
                    firstErrorMessage(
                        errors as Record<string, string>,
                        'Impossible de réserver ces sièges. Réessayez.',
                    ),
                );
            },
        });
    };

    const savePassengers = (
        reservationUuid: string,
        passengerList: SalePassengerForm[],
        onSuccess?: () => void,
    ) => {
        passengersForm.transform(() => ({
            date: searchForm.data.date ?? null,
            from: searchForm.data.from ?? undefined,
            to: searchForm.data.to ?? undefined,
            passengers: passengerList.map(passengerToSnakeCase),
            information_confirmed: true,
        }));

        passengersForm.post(
            koraPoint.sale.passengers.url({ reservation: reservationUuid }),
            {
                preserveScroll: true,
                onSuccess,
                onError: (errors) => {
                    toast.error(
                        firstErrorMessage(
                            errors as Record<string, string>,
                            'Vérifiez les informations passagers avant de continuer.',
                        ),
                    );
                },
            },
        );
    };

    const recordPayment = (
        reservationUuid: string,
        payload: CounterSalePaymentPayload,
        onSuccess?: () => void,
    ) => {
        paymentForm.setData(payload);
        paymentForm.transform(() => payload);
        paymentForm.post(
            koraPoint.sale.payment.url({ reservation: reservationUuid }),
            {
                preserveScroll: true,
                onSuccess,
                onError: (errors) => {
                    toast.error(
                        firstErrorMessage(
                            errors as Record<string, string>,
                            'L’encaissement a échoué. Vérifiez le montant et réessayez.',
                        ),
                    );
                },
            },
        );
    };

    const releaseHold = useCallback(
        (reservationUuid: string, onSuccess?: () => void) => {
            releaseForm.post(
                koraPoint.sale.release.url({ reservation: reservationUuid }),
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        toast.success(
                            'Réservation annulée et places libérées.',
                        );
                        onSuccess?.();
                    },
                    onError: (errors) => {
                        toast.error(
                            firstErrorMessage(
                                errors as Record<string, string>,
                                'Impossible de libérer cette réservation.',
                            ),
                        );
                    },
                },
            );
        },
        [releaseForm],
    );

    const retryTicketIssuance = (reservationUuid: string) => {
        router.post(
            koraPoint.sale.tickets.retry.url({ reservation: reservationUuid }),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Émission des billets relancée.');
                },
                onError: () => {
                    toast.error('L’émission des billets a échoué. Réessayez.');
                },
            },
        );
    };

    const startNewSale = useCallback(() => {
        if (props.reservation && !props.reservation.payment) {
            releaseHold(props.reservation.uuid, () => {
                router.visit(koraPoint.sale.url());
            });

            return;
        }

        router.visit(koraPoint.sale.url());
    }, [props.reservation, releaseHold]);

    const reopenSeatSelection = useCallback(
        (reservationUuid: string) => {
            releaseHold(reservationUuid, () => {
                router.visit(koraPoint.sale.url());
            });
        },
        [releaseHold],
    );

    return {
        searchForm,
        quoteForm,
        holdForm,
        confirmForm,
        passengersForm,
        paymentForm,
        releaseForm,
        search,
        calculateItineraryQuote,
        fetchSeats,
        confirmSale,
        holdItinerarySeats,
        savePassengers,
        recordPayment,
        releaseHold,
        retryTicketIssuance,
        startNewSale,
        reopenSeatSelection,
    };
}
