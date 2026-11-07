import { router, useForm } from '@inertiajs/react';
import { useCallback } from 'react';
import type { SyntheticEvent } from 'react';
import { toast } from 'sonner';
import { sale } from '@/routes';
import {
    confirm as saleConfirm,
    draft,
    hold,
    passengers,
    payment,
    promoCode as salePromoCode,
    quote,
    release,
    seats as saleSeats,
} from '@/routes/sale';
import type { CounterSalePaymentPayload } from './payment-step';
import type {
    SaleItinerary,
    SalePassengerForm,
    SalePayerType,
    SaleProps,
    SaleSearch,
} from './sale-types';

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

export function useCounterSale(props: SaleProps) {
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
        passengers: [] as ItinerarySeatAssignment[],
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
            company_account_uuid?: string;
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
    const draftForm = useForm<{
        passengers: ReturnType<typeof passengerToSnakeCase>[];
    }>({
        passengers: [],
    });
    const transformDraft = draftForm.transform;
    const patchDraft = draftForm.patch;
    const releaseForm = useForm({
        date: searchForm.data.date ?? null,
        from: searchForm.data.from ?? undefined,
        to: searchForm.data.to ?? undefined,
        itinerary_quote_uuid: props.itineraryQuote?.uuid ?? undefined,
    });
    const promoForm = useForm({
        promo_code: null as string | null,
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
        wallet_uuid: null as string | null,
        wallet_amount: null as number | null,
        points_amount: null as number | null,
        wallet_holder_phone: '',
        wallet_holder_confirmed: false,
    });
    const search = (event: SyntheticEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            sale.url(),
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
        promoCode?: string,
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
            promo_code: promoCode?.trim() ? promoCode.trim() : null,
            outbound_legs: outbound.legs.map(toLegPayload),
            inbound_legs: inbound ? inbound.legs.map(toLegPayload) : [],
        }));
        quoteForm.post(quote.url(), {
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
    const holdItinerarySeats = (
        itineraryQuoteUuid: string,
        seatAssignments: ItinerarySeatAssignment[],
        onSuccess?: () => void,
        payer?: {
            type: SalePayerType;
            company_account_uuid?: string;
        },
    ) => {
        holdForm.transform(() => ({
            date: searchForm.data.date,
            from: searchForm.data.from ?? undefined,
            to: searchForm.data.to ?? undefined,
            trip_type: searchForm.data.tripType,
            return_date:
                searchForm.data.tripType === 'round_trip'
                    ? searchForm.data.returnDate
                    : null,
            itinerary_quote_uuid: itineraryQuoteUuid,
            passengers: seatAssignments,
            ...(payer
                ? {
                      payer: {
                          type: payer.type,
                          ...(payer.company_account_uuid
                              ? {
                                    company_account_uuid:
                                        payer.company_account_uuid,
                                }
                              : {}),
                      },
                  }
                : {}),
        }));
        holdForm.post(hold.url(), {
            preserveScroll: true,
            onSuccess,
            onError: (errors) => {
                toast.error(
                    firstErrorMessage(
                        errors as Record<string, string>,
                        'Le blocage des places a échoué. Vérifiez votre sélection et réessayez.',
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
            saleSeats.url(),
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
            company_account_uuid?: string;
        },
        promoCode?: string,
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
            promo_code: promoCode?.trim() ? promoCode.trim() : null,
            outbound_legs: outbound.legs.map(toLegPayload),
            inbound_legs: inbound ? inbound.legs.map(toLegPayload) : [],
            passengers: seatAssignments,
            idempotency_key: '',
            payer: payer ?? null,
        }));
        confirmForm.post(saleConfirm.url(), {
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
    const savePassengers = (
        reservationUuid: string,
        passengerData: SalePassengerForm[],
        onSuccess?: () => void,
    ) => {
        passengersForm.transform(() => ({
            date: searchForm.data.date ?? null,
            from: searchForm.data.from ?? undefined,
            to: searchForm.data.to ?? undefined,
            passengers: passengerData.map(passengerToSnakeCase),
            information_confirmed: true,
        }));
        passengersForm.post(passengers.url(reservationUuid), {
            preserveScroll: true,
            onSuccess,
        });
    };
    const saveDraft = useCallback(
        (
            reservationUuid: string,
            passengerData: SalePassengerForm[],
            onSuccess?: () => void,
        ) => {
            transformDraft(() => ({
                passengers: passengerData.map(passengerToSnakeCase),
            }));
            patchDraft(draft.url(reservationUuid), {
                preserveScroll: true,
                onSuccess,
            });
        },
        [patchDraft, transformDraft],
    );
    const releaseHold = (
        reservationUuid: string,
        options: {
            clearQuote?: boolean;
            onSuccess?: () => void;
            onError?: () => void;
        } = {},
    ) => {
        releaseForm.transform(() => ({
            date: searchForm.data.date ?? null,
            from: searchForm.data.from ?? undefined,
            to: searchForm.data.to ?? undefined,
            itinerary_quote_uuid: options.clearQuote
                ? undefined
                : (props.itineraryQuote?.uuid ?? undefined),
        }));
        releaseForm.post(release.url(reservationUuid), {
            preserveScroll: true,
            onSuccess: options.onSuccess,
            onError: options.onError,
        });
    };
    const updatePromoCode = (
        reservationUuid: string,
        code: string | null,
        onSuccess?: () => void,
    ) => {
        promoForm.transform(() => ({
            promo_code: code?.trim() ? code.trim() : null,
            date: searchForm.data.date ?? null,
            from: searchForm.data.from ?? undefined,
            to: searchForm.data.to ?? undefined,
            trip_type: searchForm.data.tripType,
            return_date:
                searchForm.data.tripType === 'round_trip'
                    ? (searchForm.data.returnDate ?? null)
                    : null,
            itinerary_quote_uuid: props.itineraryQuote?.uuid ?? undefined,
        }));
        promoForm.put(salePromoCode.url(reservationUuid), {
            preserveScroll: true,
            onSuccess,
            onError: (errors) => {
                toast.error(
                    firstErrorMessage(
                        errors as Record<string, string>,
                        'Le code promo n’a pas pu être appliqué. Vérifiez le code et réessayez.',
                    ),
                );
            },
        });
    };
    const recordPayment = (
        reservationUuid: string,
        paymentData: CounterSalePaymentPayload,
        onSuccess?: () => void,
    ) => {
        paymentForm.transform(() => paymentData);
        paymentForm.post(payment.url(reservationUuid), {
            preserveScroll: true,
            onSuccess,
            onError: (errors) => {
                const errorRecord = errors as Record<string, string>;
                const message =
                    errorRecord?.reservation ??
                    errorRecord?.cash_session ??
                    errorRecord?.amount ??
                    errorRecord?.cash_received_amount ??
                    errorRecord?.idempotency_key ??
                    errorRecord?.method ??
                    (errorRecord
                        ? Object.values(errorRecord).find(
                              (v) => typeof v === 'string',
                          )
                        : null) ??
                    'Le paiement n’a pas pu être enregistré.Réessayez.';
                toast.error(message);
            },
        });
    };

    return {
        searchForm,
        quoteForm,
        holdForm,
        confirmForm,
        passengersForm,
        draftForm,
        releaseForm,
        promoForm,
        paymentForm,
        search,
        calculateItineraryQuote,
        holdItinerarySeats,
        fetchSeats,
        confirmSale,
        savePassengers,
        saveDraft,
        releaseHold,
        updatePromoCode,
        recordPayment,
    };
}
