import { Head, router } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';
import type { SyntheticEvent } from 'react';
import { toast } from 'sonner';
import { CounterSaleSuccess } from '@/components/sale/counter-sale-success';
import { DepartureList } from '@/components/sale/departure-list';
import { PassengerFinalization } from '@/components/sale/passenger-finalization';
import { PaymentStep } from '@/components/sale/payment-step';
import { RouteSearch } from '@/components/sale/route-search';
import { SalePageHeading } from '@/components/sale/sale-page-heading';
import { SaleProgress } from '@/components/sale/sale-progress';
import { SaleSummaryCard } from '@/components/sale/sale-summary-card';
import type {
    SalePassengerForm,
    SaleProps,
    SaleSearch,
} from '@/components/sale/sale-types';
import { SeatPicker } from '@/components/sale/seat-picker';
import { useCounterSale } from '@/components/sale/use-counter-sale';
import { sale } from '@/routes';

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
        cashSession,
        mobileMoneyAccounts,
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
    const [passengerCount, setPassengerCount] = useState(
        reservation?.segments.length ?? quote?.passengerCount ?? 1,
    );
    const [isSeatPickerOpen, setIsSeatPickerOpen] = useState(false);
    const [isLoadingSeats, setIsLoadingSeats] = useState(false);
    const [paymentStepReservationUuid, setPaymentStepReservationUuid] =
        useState<string | null>(() =>
            reservation &&
            reservation.passengers.length === reservation.segments.length
                ? reservation.uuid
                : null,
        );
    const isPaymentStepOpen = reservation?.uuid === paymentStepReservationUuid;
    const isSaleCompleted =
        reservation !== null && reservation.payment !== null;
    const {
        searchForm,
        quoteForm,
        holdForm,
        passengersForm,
        draftForm,
        releaseForm,
        paymentForm,
        search: submitSearch,
        selectDeparture,
        calculateQuote,
        holdSeats,
        savePassengers,
        saveDraft,
        releaseHold,
        recordPayment,
    } = useCounterSale({ ...props, search });

    const activeReservationUuid = reservation?.uuid;
    const savePassengerDraft = useCallback(
        (passengers: SalePassengerForm[]) => {
            if (!activeReservationUuid) {
                return;
            }

            saveDraft(activeReservationUuid, passengers);
        },
        [activeReservationUuid, saveDraft],
    );

    const startNewSale = () => {
        if (
            reservation === null ||
            !window.confirm(
                'Abandonner cette vente en cours et libérer les sièges bloqués ?',
            )
        ) {
            return;
        }

        releaseHold(reservation.uuid, {
            onSuccess: () => {
                router.get(sale.url(), {}, { preserveState: false });
            },
        });
    };

    const selectedSeatCodes = useMemo(
        () =>
            seats
                .filter((seat) => selectedSeatUuids.includes(seat.uuid))
                .map((seat) => seat.code),
        [seats, selectedSeatUuids],
    );
    const stage = reservation
        ? isPaymentStepOpen
            ? 'payment'
            : 'passengers'
        : selectedDeparture
          ? 'seats'
          : 'search';

    const onSearch = (event: SyntheticEvent<HTMLFormElement>) => {
        submitSearch(event);
        setSelectedSeatUuids([]);
        setPassengerCount(1);
        setIsSeatPickerOpen(false);
    };

    const onSelectDeparture = (departure: SaleProps['departures'][number]) => {
        setSelectedSeatUuids([]);
        setPassengerCount(1);
        setIsSeatPickerOpen(true);
        selectDeparture(departure, {
            onStart: () => setIsLoadingSeats(true),
            onFinish: () => setIsLoadingSeats(false),
        });
    };

    const onCalculateQuote = () => {
        if (!selectedDeparture || selectedSeatUuids.length !== passengerCount) {
            return;
        }

        calculateQuote(passengerCount, selectedDeparture);
    };

    const onHold = () => {
        if (
            !selectedDeparture ||
            !quote ||
            selectedSeatUuids.length !== quote.passengerCount ||
            selectedSeatUuids.length !== passengerCount
        ) {
            return;
        }

        holdSeats(selectedDeparture, quote.uuid, selectedSeatUuids, () =>
            setIsSeatPickerOpen(false),
        );
    };

    const reopenSeatSelection = (reservationUuid: string) => {
        setSelectedSeatUuids([]);
        setPassengerCount(1);
        setIsSeatPickerOpen(false);

        releaseHold(reservationUuid, {
            clearQuote: true,
            onSuccess: () => {
                toast.warning(
                    'Votre blocage a expiré. Sélectionnez à nouveau vos places.',
                );
                setIsSeatPickerOpen(true);
            },
            onError: () => {
                toast.error(
                    'Impossible de recharger les places. Veuillez réessayer.',
                );
            },
        });
    };

    return (
        <>
            <Head title="Vente au guichet" />
            <main className="w-full max-w-full overflow-x-hidden">
                <SalePageHeading
                    action={
                        reservation && !reservation.payment ? (
                            <button
                                type="button"
                                className="btn btn-ghost"
                                onClick={startNewSale}
                                disabled={releaseForm.processing}
                            >
                                {releaseForm.processing
                                    ? 'Abandon…'
                                    : 'Nouvelle vente'}
                            </button>
                        ) : undefined
                    }
                />
                {!permissions.canCreate && (
                    <div className="mb-5 rounded-[0.9rem] border border-gold/30 bg-gold/10 px-4 py-3 text-[0.75rem] font-bold text-foreground">
                        Votre profil peut consulter les départs, mais ne dispose
                        pas de l’autorisation de créer une vente guichet.
                    </div>
                )}
                {!isSaleCompleted && <SaleProgress stage={stage} />}
                <div className={isSaleCompleted ? undefined : 'grid gap-5'}>
                    {!isSaleCompleted && (
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
                    )}
                    {reservation && reservation.payment ? (
                        <CounterSaleSuccess
                            departure={selectedDeparture}
                            reservation={reservation}
                            onNewSale={startNewSale}
                            releasing={releaseForm.processing}
                        />
                    ) : reservation && isPaymentStepOpen ? (
                        <PaymentStep
                            departure={selectedDeparture}
                            reservation={reservation}
                            cashSession={cashSession}
                            mobileMoneyAccounts={mobileMoneyAccounts}
                            processing={paymentForm.processing}
                            errors={paymentForm.errors}
                            onSubmit={(payload) =>
                                recordPayment(reservation.uuid, payload)
                            }
                            onBack={() => setPaymentStepReservationUuid(null)}
                            onHoldExpired={() =>
                                reopenSeatSelection(reservation.uuid)
                            }
                        />
                    ) : reservation ? (
                        <PassengerFinalization
                            key={reservation.uuid}
                            departure={selectedDeparture}
                            reservation={reservation}
                            processing={passengersForm.processing}
                            releasing={releaseForm.processing}
                            errors={passengersForm.errors}
                            onDraftChange={savePassengerDraft}
                            draftSaving={draftForm.processing}
                            draftSaved={draftForm.recentlySuccessful}
                            onSubmit={(passengers: SalePassengerForm[]) =>
                                savePassengers(
                                    reservation.uuid,
                                    passengers,
                                    () =>
                                        setPaymentStepReservationUuid(
                                            reservation.uuid,
                                        ),
                                )
                            }
                            onBack={() => releaseHold(reservation.uuid)}
                            onHoldExpired={() =>
                                reopenSeatSelection(reservation.uuid)
                            }
                        />
                    ) : (
                        <div className="sale-layout">
                            <DepartureList
                                departures={departures}
                                selectedUuid={selectedDeparture?.uuid ?? null}
                                onSelect={onSelectDeparture}
                            />
                            <SaleSummaryCard
                                departure={selectedDeparture}
                                passengerCount={passengerCount}
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
                    passengerCount={passengerCount}
                    loading={isLoadingSeats}
                    processing={quoteForm.processing || holdForm.processing}
                    onToggleSeat={(seat) =>
                        setSelectedSeatUuids((current) =>
                            current.includes(seat.uuid)
                                ? current.filter((uuid) => uuid !== seat.uuid)
                                : current.length >= passengerCount
                                  ? current
                                  : [...current, seat.uuid],
                        )
                    }
                    onPassengerCountChange={(count) => {
                        setPassengerCount(count);
                        setSelectedSeatUuids((current) =>
                            current.slice(0, count),
                        );
                    }}
                    onClose={() => setIsSeatPickerOpen(false)}
                    onConfirm={() => {
                        if (selectedSeatUuids.length !== passengerCount) {
                            return;
                        }

                        setIsSeatPickerOpen(false);
                    }}
                />
            )}
        </>
    );
}

SalePage.layout = { breadcrumbs: [{ title: 'Vente guichet', href: '/sale' }] };
