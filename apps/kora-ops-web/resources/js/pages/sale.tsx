import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { DepartureList } from '@/components/sale/departure-list';
import { HoldConfirmation } from '@/components/sale/hold-confirmation';
import { RouteSearch } from '@/components/sale/route-search';
import { SalePageHeading } from '@/components/sale/sale-page-heading';
import { SaleProgress } from '@/components/sale/sale-progress';
import { SaleSummaryCard } from '@/components/sale/sale-summary-card';
import type { SaleProps, SaleSearch } from '@/components/sale/sale-types';
import { SeatPicker } from '@/components/sale/seat-picker';
import { useCounterSale } from '@/components/sale/use-counter-sale';

export default function SalePage(props: SaleProps) {
    const {
        cities,
        search: initialSearch,
        departures,
        selectedDeparture,
        seats,
        quote,
        reservation,
        permissions,
    } = props;
    const [search, setSearch] = useState<SaleSearch>({
        date: initialSearch.date,
        from: initialSearch.from,
        to: initialSearch.to,
    });
    const [selectedSeatUuids, setSelectedSeatUuids] = useState<string[]>(
        reservation?.segments.flatMap((segment) =>
            segment.seatUuid ? [segment.seatUuid] : [],
        ) ?? [],
    );
    const [isSeatPickerOpen, setIsSeatPickerOpen] = useState(false);
    const [isLoadingSeats, setIsLoadingSeats] = useState(false);
    const {
        searchForm,
        quoteForm,
        holdForm,
        search: submitSearch,
        selectDeparture,
        calculateQuote,
        holdSeats,
    } = useCounterSale({ ...props, search });

    const selectedSeatCodes = useMemo(
        () =>
            seats
                .filter((seat) => selectedSeatUuids.includes(seat.uuid))
                .map((seat) => seat.code),
        [seats, selectedSeatUuids],
    );
    const stage = reservation ? 'held' : selectedDeparture ? 'seats' : 'search';

    const onSearch = (event: FormEvent<HTMLFormElement>) => {
        submitSearch(event);
        setSelectedSeatUuids([]);
        setIsSeatPickerOpen(false);
    };

    const onSelectDeparture = (departure: SaleProps['departures'][number]) => {
        setSelectedSeatUuids([]);
        setIsSeatPickerOpen(true);
        selectDeparture(departure, {
            onStart: () => setIsLoadingSeats(true),
            onFinish: () => setIsLoadingSeats(false),
        });
    };

    const onCalculateQuote = () => {
        if (!selectedDeparture || selectedSeatUuids.length === 0) {
            return;
        }

        calculateQuote(selectedSeatUuids.length, selectedDeparture);
    };

    const onHold = () => {
        if (
            !selectedDeparture ||
            !quote ||
            selectedSeatUuids.length !== quote.passengerCount
        ) {
            return;
        }

        holdSeats(selectedDeparture, quote.uuid, selectedSeatUuids, () =>
            setIsSeatPickerOpen(false),
        );
    };

    return (
        <>
            <Head title="Vente au guichet" />
            <main className="w-full max-w-full overflow-x-hidden">
                <SalePageHeading />
                {!permissions.canCreate && (
                    <div className="mb-5 rounded-[0.9rem] border border-gold/30 bg-gold/10 px-4 py-3 text-[0.75rem] font-bold text-foreground">
                        Votre profil peut consulter les départs, mais ne dispose
                        pas de l’autorisation de créer une vente guichet.
                    </div>
                )}
                <SaleProgress stage={stage} />
                <div className="grid gap-5">
                    <RouteSearch
                        cities={cities}
                        value={search}
                        processing={searchForm.processing}
                        onChange={(nextSearch) => {
                            setSearch(nextSearch);
                            searchForm.setData(nextSearch);
                        }}
                        onSubmit={onSearch}
                    />
                    {reservation ? (
                        <HoldConfirmation reservation={reservation} />
                    ) : (
                        <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_19rem]">
                            <DepartureList
                                departures={departures}
                                selectedUuid={selectedDeparture?.uuid ?? null}
                                onSelect={onSelectDeparture}
                            />
                            <SaleSummaryCard
                                departure={selectedDeparture}
                                selectedSeatCodes={selectedSeatCodes}
                                quote={quote}
                                reservation={reservation}
                                processing={
                                    quoteForm.processing || holdForm.processing
                                }
                                onQuote={onCalculateQuote}
                                onHold={onHold}
                            />
                        </div>
                    )}
                </div>
            </main>
            {isSeatPickerOpen && selectedDeparture && (
                <SeatPicker
                    departure={selectedDeparture}
                    seats={seats}
                    selectedSeatUuids={selectedSeatUuids}
                    loading={isLoadingSeats}
                    onToggleSeat={(seat) =>
                        setSelectedSeatUuids((current) =>
                            current.includes(seat.uuid)
                                ? current.filter((uuid) => uuid !== seat.uuid)
                                : [...current, seat.uuid],
                        )
                    }
                    onClose={() => setIsSeatPickerOpen(false)}
                    onConfirm={() => setIsSeatPickerOpen(false)}
                />
            )}
        </>
    );
}

SalePage.layout = { breadcrumbs: [{ title: 'Vente guichet', href: '/sale' }] };
