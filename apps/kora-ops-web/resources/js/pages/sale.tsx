import { Head, router } from '@inertiajs/react';
import { lazy, Suspense, useCallback, useMemo, useState } from 'react';
import type { SyntheticEvent } from 'react';
import { toast } from 'sonner';
import { DepartureList } from '@/components/sale/departure-list';
import { ItineraryList } from '@/components/sale/itinerary-list';
import { RouteSearch } from '@/components/sale/route-search';
import { SalePageHeading } from '@/components/sale/sale-page-heading';
import { SaleProgress } from '@/components/sale/sale-progress';
import { SaleSummaryCard } from '@/components/sale/sale-summary-card';
import type {
    SaleItinerary,
    SalePassengerForm,
    SalePayerType,
    SaleProps,
    SaleSearch,
} from '@/components/sale/sale-types';
import { useCounterSale } from '@/components/sale/use-counter-sale';
import { sale } from '@/routes';

const CounterSaleSuccess = lazy(() =>
    import('@/components/sale/counter-sale-success').then(
        (m) => ({ default: m.CounterSaleSuccess }),
    ),
);
const PassengerFinalization = lazy(() =>
    import('@/components/sale/passenger-finalization').then(
        (m) => ({ default: m.PassengerFinalization }),
    ),
);
const PaymentStep = lazy(() =>
    import('@/components/sale/payment-step').then(
        (m) => ({ default: m.PaymentStep }),
    ),
);
const SeatPicker = lazy(() =>
    import('@/components/sale/seat-picker').then((m) => ({ default: m.SeatPicker })),
);

function itineraryKey(itinerary: SaleItinerary): string {
    return itinerary.legs
        .map(
            (leg) =>
                `${leg.departureUuid}:${leg.originStop.uuid}:${leg.destinationStop.uuid}`,
        )
        .join('|');
}

export default function SalePage(props: SaleProps) {
    const {
        cities,
        search: initialSearch,
        departures,
        itineraries,
        selectedDeparture,
        seats,
        quote,
        itineraryQuote,
        reservation,
        permissions,
        cashSession,
        mobileMoneyAccounts,
        companyAccounts,
        groupBookingSettings,
    } = props;
    const [search, setSearch] = useState<SaleSearch>({
        date: initialSearch.date,
        from: initialSearch.from,
        to: initialSearch.to,
    });
    const [isRoundTrip, setIsRoundTrip] = useState(false);
    const [returnDate, setReturnDate] = useState<string | null>(null);
    const [selectedItinerary, setSelectedItinerary] =
        useState<SaleItinerary | null>(null);
    const [selectedSeatUuids, setSelectedSeatUuids] = useState<string[]>(
        reservation?.segments.flatMap((segment) =>
            segment.seatUuid ? [segment.seatUuid] : [],
        ) ?? [],
    );
    const [passengerCount, setPassengerCount] = useState(
        reservation?.segments.length ?? quote?.passengerCount ?? 1,
    );
    const [payerType, setPayerType] = useState<SalePayerType>(
        (reservation?.payerType as SalePayerType) ?? 'personal',
    );
    const [selectedCompanyAccountUuid, setSelectedCompanyAccountUuid] =
        useState<string | null>(reservation?.companyAccount?.uuid ?? null);
    const [isSeatPickerOpen, setIsSeatPickerOpen] = useState(false);
    const [isLoadingSeats, setIsLoadingSeats] = useState(false);
    const logicalPassengerCount = reservation
        ? new Set(
            reservation.segments.map(
                (segment, index) => segment.passengerIndex ?? index,
            ),
        ).size
        : 0;
    const [paymentStepReservationUuid, setPaymentStepReservationUuid] =
        useState<string | null>(() =>
            reservation &&
                reservation.passengers.length === logicalPassengerCount &&
                logicalPassengerCount > 0
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
        calculateItineraryQuote,
        holdSeats,
        holdItinerarySeats,
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
        ? isSaleCompleted
            ? 'tickets'
            : isPaymentStepOpen
                ? 'payment'
                : 'passengers'
        : selectedDeparture
            ? quote
                ? 'payer'
                : 'seats'
            : 'search';

    const onSearch = (event: SyntheticEvent<HTMLFormElement>) => {
        submitSearch(event);
        setSelectedSeatUuids([]);
        setPassengerCount(1);
        setIsSeatPickerOpen(false);
        setSelectedItinerary(null);
    };

    const onSelectDeparture = (departure: SaleProps['departures'][number]) => {
        setSelectedSeatUuids([]);
        setPassengerCount(1);
        setIsSeatPickerOpen(true);
        setSelectedItinerary(null);
        selectDeparture(departure, {
            onStart: () => setIsLoadingSeats(true),
            onFinish: () => setIsLoadingSeats(false),
        });
    };

    const onSelectItinerary = (itinerary: SaleItinerary) => {
        setSelectedItinerary(itinerary);
        setSelectedSeatUuids([]);
        setPassengerCount(1);

        if (itinerary.isDirect) {
            // For direct itineraries, use the existing departure flow
            const leg = itinerary.legs[0];
            const departure = departures.find(
                (d) => d.uuid === leg.departureUuid,
            );

            if (departure) {
                onSelectDeparture(departure);
            }
        } else {
            // For multi-leg itineraries, calculate the itinerary quote first
            calculateItineraryQuote(1, itinerary, () => {
                setIsSeatPickerOpen(true);
            });
        }
    };

    const onCalculateQuote = () => {
        if (!selectedDeparture || selectedSeatUuids.length !== passengerCount) {
            return;
        }

        calculateQuote(passengerCount, selectedDeparture);
    };

    const onCalculateItineraryQuote = () => {
        if (!selectedItinerary || !itineraryQuote) {
            return;
        }

        calculateItineraryQuote(passengerCount, selectedItinerary);
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

        const payer =
            payerType === 'company' && selectedCompanyAccountUuid
                ? {
                    type: 'company' as const,
                    company_account_uuid: selectedCompanyAccountUuid,
                }
                : undefined;

        holdSeats(
            selectedDeparture,
            quote.uuid,
            selectedSeatUuids,
            () => setIsSeatPickerOpen(false),
            payer,
        );
    };

    const onHoldItinerary = () => {
        if (!itineraryQuote || !selectedItinerary) {
            return;
        }

        // For multi-leg, we need seat assignments per passenger per leg
        // This is a simplified version — the seat picker handles the full assignment
        const assignments = Array.from({ length: passengerCount }, (_, i) => ({
            passenger_index: i,
            seat_assignments: selectedItinerary.legs.map((leg) => ({
                leg_order: leg.legOrder,
                seat_uuid: selectedSeatUuids[i] ?? '',
            })),
        }));

        const payer =
            payerType === 'company' && selectedCompanyAccountUuid
                ? {
                    type: 'company' as const,
                    company_account_uuid: selectedCompanyAccountUuid,
                }
                : undefined;

        holdItinerarySeats(
            itineraryQuote.uuid,
            assignments,
            () => setIsSeatPickerOpen(false),
            payer,
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

    const isMultiLegFlow = selectedItinerary !== null && !selectedItinerary.isDirect;
    const selectedLegsKey = selectedItinerary ? itineraryKey(selectedItinerary) : null;

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
                <SaleProgress stage={stage} />
                <div className={isSaleCompleted ? undefined : 'grid gap-5'}>
                    {!isSaleCompleted && (
                        <RouteSearch
                            cities={cities}
                            value={search}
                            processing={searchForm.processing}
                            isRoundTrip={isRoundTrip}
                            returnDate={returnDate}
                            onChange={(nextSearch) => {
                                setSearch(nextSearch);
                                searchForm.setData(nextSearch);
                            }}
                            onRoundTripChange={setIsRoundTrip}
                            onReturnDateChange={setReturnDate}
                            onSubmit={onSearch}
                        />
                    )}
                    {reservation && reservation.payment ? (
                        <Suspense fallback={<div className="sale-skeleton" />}>
                            <CounterSaleSuccess
                                departure={selectedDeparture}
                                reservation={reservation}
                                onNewSale={startNewSale}
                                releasing={releaseForm.processing}
                            />
                        </Suspense>
                    ) : reservation && isPaymentStepOpen ? (
                        <Suspense fallback={<div className="sale-skeleton" />}>
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
                        </Suspense>
                    ) : reservation ? (
                        <Suspense fallback={<div className="sale-skeleton" />}>
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
                        </Suspense>
                    ) : (
                        <div className="sale-layout">
                            {itineraries.length > 0 ? (
                                <ItineraryList
                                    itineraries={itineraries}
                                    selectedLegsKey={selectedLegsKey}
                                    onSelect={onSelectItinerary}
                                />
                            ) : (
                                <DepartureList
                                    departures={departures}
                                    selectedUuid={selectedDeparture?.uuid ?? null}
                                    onSelect={onSelectDeparture}
                                />
                            )}
                            <SaleSummaryCard
                                departure={selectedDeparture}
                                passengerCount={passengerCount}
                                selectedSeatCodes={selectedSeatCodes}
                                quote={quote}
                                itineraryQuote={itineraryQuote ?? null}
                                selectedItinerary={selectedItinerary}
                                reservation={reservation}
                                processing={
                                    quoteForm.processing || holdForm.processing
                                }
                                onQuote={isMultiLegFlow ? onCalculateItineraryQuote : onCalculateQuote}
                                onHold={isMultiLegFlow ? onHoldItinerary : onHold}
                                companyAccounts={companyAccounts}
                                payerType={payerType}
                                selectedCompanyAccountUuid={selectedCompanyAccountUuid}
                                onPayerTypeChange={setPayerType}
                                onCompanyAccountChange={setSelectedCompanyAccountUuid}
                            />
                        </div>
                    )}
                </div>
            </main>
            {isSeatPickerOpen && selectedDeparture && (
                <Suspense fallback={null}>
                    <SeatPicker
                        departure={selectedDeparture}
                        seats={seats}
                        selectedSeatUuids={selectedSeatUuids}
                        passengerCount={passengerCount}
                        maxPassengers={groupBookingSettings.maximumPassengers}
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
                </Suspense>
            )}
        </>
    );
}

SalePage.layout = { breadcrumbs: [{ title: 'Vente guichet', href: '/sale' }] };
