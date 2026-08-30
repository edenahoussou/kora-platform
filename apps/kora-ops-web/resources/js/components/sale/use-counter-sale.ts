import { router, useForm } from '@inertiajs/react';
import { useCallback } from 'react';
import type { SyntheticEvent } from 'react';
import { toast } from 'sonner';
import { sale } from '@/routes';
import {
    draft,
    hold,
    passengers,
    payment,
    quote,
    release,
} from '@/routes/sale';
import type { CounterSalePaymentPayload } from './payment-step';
import type {
    SaleDeparture,
    SalePassengerForm,
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
        seat_uuid: passenger.seatUuid,
    };
}

function queryFrom(search: SaleSearch, departure?: SaleDeparture | null) {
    return {
        date: search.date ?? departure?.departureDate ?? null,
        from: search.from ?? undefined,
        to: search.to ?? undefined,
        departure_uuid: departure?.uuid,
        origin_stop_uuid: departure?.originStop.uuid,
        destination_stop_uuid: departure?.destinationStop.uuid,
    };
}

export function useCounterSale(props: SaleProps) {
    const searchForm = useForm<SaleSearch>({ ...props.search });
    const quoteForm = useForm({
        ...queryFrom(props.search, props.selectedDeparture),
        passenger_count: 1,
    });
    const holdForm = useForm({
        ...queryFrom(props.search, props.selectedDeparture),
        price_quote_uuid: props.quote?.uuid ?? '',
        seats: [] as Array<{ seat_uuid: string }>,
    });
    const passengersForm = useForm<{
        date: string | null;
        from: string | undefined;
        to: string | undefined;
        departure_uuid: string | undefined;
        origin_stop_uuid: string | undefined;
        destination_stop_uuid: string | undefined;
        passengers: SalePassengerForm[];
    }>({
        ...queryFrom(searchForm.data, props.selectedDeparture),
        passengers: [],
    });
    const draftForm = useForm<{
        passengers: ReturnType<typeof passengerToSnakeCase>[];
    }>({
        passengers: [],
    });
    const transformDraft = draftForm.transform;
    const patchDraft = draftForm.patch;
    const releaseForm = useForm({
        ...queryFrom(props.search, props.selectedDeparture),
        quote_uuid: props.quote?.uuid ?? undefined,
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
            sale.url(),
            { ...searchForm.data },
            {
                only: [
                    'search',
                    'departures',
                    'selectedDeparture',
                    'seats',
                    'quote',
                    'reservation',
                ],
                preserveState: true,
                preserveScroll: true,
            },
        );
    };
    const selectDeparture = (
        departure: SaleDeparture,
        options?: { onStart?: () => void; onFinish?: () => void },
    ) =>
        router.get(sale.url(), queryFrom(searchForm.data, departure), {
            only: ['selectedDeparture', 'seats', 'quote', 'reservation'],
            preserveState: true,
            preserveScroll: true,
            ...options,
        });
    const calculateQuote = (
        passengerCount: number,
        selectedDeparture: SaleDeparture,
        onSuccess?: () => void,
    ) => {
        quoteForm.transform(() => ({
            ...queryFrom(searchForm.data, selectedDeparture),
            passenger_count: passengerCount,
        }));
        quoteForm.post(quote.url(), { preserveScroll: true, onSuccess });
    };
    const holdSeats = (
        selectedDeparture: SaleDeparture,
        quoteUuid: string,
        seatUuids: string[],
        onSuccess?: () => void,
    ) => {
        holdForm.transform(() => ({
            ...queryFrom(searchForm.data, selectedDeparture),
            price_quote_uuid: quoteUuid,
            seats: seatUuids.map((seatUuid) => ({ seat_uuid: seatUuid })),
        }));
        holdForm.post(hold.url(), { preserveScroll: true, onSuccess });
    };
    const savePassengers = (
        reservationUuid: string,
        passengerData: SalePassengerForm[],
        onSuccess?: () => void,
    ) => {
        passengersForm.transform(() => ({
            ...queryFrom(searchForm.data, props.selectedDeparture),
            passengers: passengerData.map(passengerToSnakeCase),
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
            ...queryFrom(searchForm.data, props.selectedDeparture),
            quote_uuid: options.clearQuote
                ? undefined
                : (props.quote?.uuid ?? undefined),
        }));
        releaseForm.post(release.url(reservationUuid), {
            preserveScroll: true,
            onSuccess: options.onSuccess,
            onError: options.onError,
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
        passengersForm,
        draftForm,
        releaseForm,
        paymentForm,
        search,
        selectDeparture,
        calculateQuote,
        holdSeats,
        savePassengers,
        saveDraft,
        releaseHold,
        recordPayment,
    };
}
