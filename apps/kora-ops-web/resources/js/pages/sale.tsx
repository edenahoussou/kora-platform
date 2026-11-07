import { Head, router } from '@inertiajs/react';
import { lazy, Suspense, useCallback, useMemo, useState } from 'react';
import type { SyntheticEvent } from 'react';
import { toast } from 'sonner';
import { ItineraryList } from '@/components/sale/itinerary-list';
import { RouteSearch } from '@/components/sale/route-search';
import { SalePageHeading } from '@/components/sale/sale-page-heading';
import { SaleProgress } from '@/components/sale/sale-progress';
import { SaleSummaryCard } from '@/components/sale/sale-summary-card';
import type {
    SaleItinerary,
    SaleItineraryLeg,
    SalePassengerForm,
    SalePayerType,
    SaleProps,
    SaleSearch,
} from '@/components/sale/sale-types';
import { SeatPicker } from '@/components/sale/seat-picker';
import { useCounterSale } from '@/components/sale/use-counter-sale';
import { cn } from '@/lib/utils';
import { sale } from '@/routes';

const CounterSaleSuccess = lazy(() =>
    import('@/components/sale/counter-sale-success').then((m) => ({
        default: m.CounterSaleSuccess,
    })),
);
const PassengerFinalization = lazy(() =>
    import('@/components/sale/passenger-finalization').then((m) => ({
        default: m.PassengerFinalization,
    })),
);
const PaymentStep = lazy(() =>
    import('@/components/sale/payment-step').then((m) => ({
        default: m.PaymentStep,
    })),
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
        outboundItineraries,
        inboundItineraries,
        itineraryQuote,
        itineraryLegSeats,
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
        tripType: initialSearch.tripType ?? 'one_way',
        returnDate: initialSearch.returnDate ?? null,
        serviceClass: initialSearch.serviceClass ?? null,
    });
    const [selectedOutbound, setSelectedOutbound] =
        useState<SaleItinerary | null>(null);
    const [selectedInbound, setSelectedInbound] =
        useState<SaleItinerary | null>(null);
    const [passengerCount, setPassengerCount] = useState(
        reservation?.segments.length ?? itineraryQuote?.passengerCount ?? 1,
    );
    const [payerType, setPayerType] = useState<SalePayerType>(
        (reservation?.payerType as SalePayerType) ?? 'personal',
    );
    const [promoCode, setPromoCode] = useState(
        reservation?.promotionDiscount?.code ?? '',
    );
    const [selectedCompanyAccountUuid, setSelectedCompanyAccountUuid] =
        useState<string | null>(reservation?.companyAccount?.uuid ?? null);
    const [isSeatPickerOpen, setIsSeatPickerOpen] = useState(false);
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
        confirmForm,
        passengersForm,
        draftForm,
        releaseForm,
        promoForm,
        paymentForm,
        search: submitSearch,
        fetchSeats,
        confirmSale,
        savePassengers,
        saveDraft,
        releaseHold,
        updatePromoCode,
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

    const stage = reservation
        ? isSaleCompleted
            ? 'tickets'
            : isPaymentStepOpen
              ? 'payment'
              : 'passengers'
        : selectedOutbound
          ? 'seats'
          : 'search';

    const applyPromoCode = (code: string | null) => {
        if (!reservation) {
            return;
        }

        updatePromoCode(reservation.uuid, code, () => {
            if (code === null) {
                setPromoCode('');
            }
        });
    };

    const onSearch = (event: SyntheticEvent<HTMLFormElement>) => {
        submitSearch(event);
        setPassengerCount(1);
        setIsSeatPickerOpen(false);
        setSelectedOutbound(null);
        setSelectedInbound(null);
    };

    const onSelectOutboundItinerary = (itinerary: SaleItinerary) => {
        setSelectedOutbound(itinerary);
        setPassengerCount(1);

        // The return trip must depart strictly after the new outbound trip
        // (same rule enforced server-side). Clear an already-selected
        // return that would now be incoherent.
        const outboundDepartureAt = itinerary.legs[0]?.departureAt;
        const inboundDepartureAt = selectedInbound?.legs[0]?.departureAt;

        if (
            outboundDepartureAt &&
            inboundDepartureAt &&
            new Date(inboundDepartureAt).getTime() <=
                new Date(outboundDepartureAt).getTime()
        ) {
            setSelectedInbound(null);
        }
    };

    const onSelectInboundItinerary = (itinerary: SaleItinerary) => {
        setSelectedInbound(itinerary);
    };

    // The return trip must depart strictly after the outbound trip ends.
    // For a multi-leg outbound, the outbound only ends at the arrival of its
    // last leg, so we compare the inbound's first departure against the
    // outbound's arrival (not the outbound's first departure). Same rule as
    // the backend's validateRoundTripConsistency.
    const compatibleInboundItineraries = useMemo(() => {
        if (!inboundItineraries) {
            return [];
        }

        if (!selectedOutbound) {
            return inboundItineraries;
        }

        const outboundArrivalAt = selectedOutbound.arrivalAt;

        if (!outboundArrivalAt) {
            return inboundItineraries;
        }

        const outboundArrivalTimestamp = new Date(outboundArrivalAt).getTime();

        return inboundItineraries.filter((itinerary) => {
            const inboundDepartureAt = itinerary.legs[0]?.departureAt;

            return (
                inboundDepartureAt !== undefined &&
                new Date(inboundDepartureAt).getTime() >
                    outboundArrivalTimestamp
            );
        });
    }, [inboundItineraries, selectedOutbound]);

    const inboundHiddenByOutboundSelection =
        selectedOutbound !== null &&
        (inboundItineraries?.length ?? 0) > 0 &&
        compatibleInboundItineraries.length === 0;

    const handleOpenSeatPicker = () => {
        if (!selectedOutbound) {
            return;
        }

        fetchSeats(selectedOutbound, selectedInbound, () => {
            setIsSeatPickerOpen(true);
        });
    };

    // Legs derived from the selected itineraries, renumbered sequentially
    // (outbound 1..N, inbound N+1..M) so leg_order is consistent between
    // the frontend seat picker and the backend confirm endpoint.
    const seatPickerLegs: SaleItineraryLeg[] = useMemo(() => {
        if (!selectedOutbound) {
            return [];
        }

        return [...selectedOutbound.legs, ...(selectedInbound?.legs ?? [])].map(
            (leg, index) => ({ ...leg, legOrder: index + 1 }),
        );
    }, [selectedOutbound, selectedInbound]);

    const handleSeatPickerConfirm = (
        assignments: Record<number, Record<number, string>>,
    ) => {
        const seatAssignments = Array.from(
            { length: passengerCount },
            (_, i) => {
                const passengerLegs = assignments[i] ?? {};

                return {
                    passenger_index: i,
                    seat_assignments: seatPickerLegs
                        .map((leg) => ({
                            leg_order: leg.legOrder,
                            direction: (leg.direction ?? 'outbound') as
                                'outbound' | 'inbound',
                            seat_uuid: passengerLegs[leg.legOrder] ?? '',
                        }))
                        .filter((a) => a.seat_uuid),
                };
            },
        );

        const payer =
            payerType === 'company' && selectedCompanyAccountUuid
                ? {
                      type: 'company' as const,
                      company_account_uuid: selectedCompanyAccountUuid,
                  }
                : undefined;

        confirmSale(
            selectedOutbound!,
            selectedInbound,
            passengerCount,
            seatAssignments,
            () => setIsSeatPickerOpen(false),
            payer,
            promoCode,
        );
    };

    const reopenSeatSelection = (reservationUuid: string) => {
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

    const selectedLegsKey = selectedOutbound
        ? itineraryKey(selectedOutbound)
        : null;

    const showSeatPicker =
        isSeatPickerOpen &&
        selectedOutbound !== null &&
        (itineraryLegSeats?.length ?? 0) > 0;
    const hasSelection = selectedOutbound !== null;

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
                        pas de l'autorisation de créer une vente guichet.
                    </div>
                )}
                <SaleProgress stage={stage} />
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
                        <Suspense fallback={<div className="sale-skeleton" />}>
                            <CounterSaleSuccess
                                departure={null}
                                reservation={reservation}
                                onNewSale={startNewSale}
                                releasing={releaseForm.processing}
                            />
                        </Suspense>
                    ) : reservation && isPaymentStepOpen ? (
                        <Suspense fallback={<div className="sale-skeleton" />}>
                            <PaymentStep
                                departure={null}
                                reservation={reservation}
                                cashSession={cashSession}
                                mobileMoneyAccounts={mobileMoneyAccounts}
                                canDebitWallet={permissions.canDebitWallet}
                                processing={paymentForm.processing}
                                errors={paymentForm.errors}
                                search={search}
                                itineraryQuote={itineraryQuote ?? null}
                                onSubmit={(payload) =>
                                    recordPayment(reservation.uuid, payload)
                                }
                                onBack={() =>
                                    setPaymentStepReservationUuid(null)
                                }
                                onHoldExpired={() =>
                                    reopenSeatSelection(reservation.uuid)
                                }
                            />
                        </Suspense>
                    ) : reservation ? (
                        <Suspense fallback={<div className="sale-skeleton" />}>
                            <PassengerFinalization
                                key={reservation.uuid}
                                departure={null}
                                reservation={reservation}
                                processing={passengersForm.processing}
                                releasing={releaseForm.processing}
                                errors={passengersForm.errors}
                                search={search}
                                itineraryQuote={itineraryQuote ?? null}
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
                        <div
                            className={cn(
                                'sale-layout',
                                !hasSelection && 'is-expanded',
                            )}
                        >
                            <div className="sale-itineraries">
                                <ItineraryList
                                    itineraries={outboundItineraries}
                                    selectedLegsKey={selectedLegsKey}
                                    onSelect={onSelectOutboundItinerary}
                                    hasDate={search.date !== null}
                                    direction="outbound"
                                />
                                {search.tripType === 'round_trip' &&
                                    inboundItineraries &&
                                    inboundItineraries.length > 0 && (
                                        <ItineraryList
                                            itineraries={
                                                compatibleInboundItineraries
                                            }
                                            selectedLegsKey={
                                                selectedInbound
                                                    ? itineraryKey(
                                                          selectedInbound,
                                                      )
                                                    : null
                                            }
                                            onSelect={onSelectInboundItinerary}
                                            hasDate={search.returnDate !== null}
                                            direction="inbound"
                                            emptyReason={
                                                inboundHiddenByOutboundSelection
                                                    ? 'incompatible-with-outbound'
                                                    : undefined
                                            }
                                        />
                                    )}
                            </div>
                            {hasSelection && (
                                <SaleSummaryCard
                                    key="sale-summary"
                                    className="sale-summary-enter"
                                    departure={null}
                                    passengerCount={passengerCount}
                                    selectedSeatCodes={[]}
                                    quote={null}
                                    itineraryQuote={itineraryQuote ?? null}
                                    selectedItinerary={selectedOutbound}
                                    selectedInbound={selectedInbound}
                                    reservation={reservation}
                                    processing={
                                        quoteForm.processing ||
                                        holdForm.processing ||
                                        confirmForm.processing ||
                                        promoForm.processing
                                    }
                                    onOpenSeatPicker={handleOpenSeatPicker}
                                    companyAccounts={companyAccounts}
                                    payerType={payerType}
                                    selectedCompanyAccountUuid={
                                        selectedCompanyAccountUuid
                                    }
                                    promoCode={promoCode}
                                    onPromoCodeChange={setPromoCode}
                                    onApplyPromoCode={applyPromoCode}
                                />
                            )}
                        </div>
                    )}
                </div>
            </main>
            {showSeatPicker && (
                <SeatPicker
                    legs={seatPickerLegs}
                    legSeats={itineraryLegSeats ?? []}
                    passengerCount={passengerCount}
                    maxPassengers={groupBookingSettings.maximumPassengers}
                    processing={holdForm.processing || confirmForm.processing}
                    onPassengerCountChange={setPassengerCount}
                    onClose={() => setIsSeatPickerOpen(false)}
                    onConfirm={handleSeatPickerConfirm}
                    payerType={payerType}
                    selectedCompanyAccountUuid={selectedCompanyAccountUuid}
                    companyAccounts={companyAccounts}
                    amount={
                        (selectedOutbound?.totalAmount ?? 0) +
                        (selectedInbound?.totalAmount ?? 0)
                    }
                    currency={selectedOutbound?.currency ?? 'XOF'}
                    onPayerTypeChange={setPayerType}
                    onCompanyAccountChange={setSelectedCompanyAccountUuid}
                />
            )}
        </>
    );
}

SalePage.layout = { breadcrumbs: [{ title: 'Vente guichet', href: '/sale' }] };
