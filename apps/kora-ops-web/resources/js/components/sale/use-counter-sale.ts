import { router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { sale } from '@/routes';
import { hold, quote } from '@/routes/sale';
import type { SaleDeparture, SaleProps, SaleSearch } from './sale-types';

function queryFrom(search: SaleSearch, departure?: SaleDeparture | null) {
    return {
        date: search.date ?? departure?.departureDate,
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
    const search = (event: FormEvent<HTMLFormElement>) => {
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

    return {
        searchForm,
        quoteForm,
        holdForm,
        search,
        selectDeparture,
        calculateQuote,
        holdSeats,
    };
}
