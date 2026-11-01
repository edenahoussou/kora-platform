import { Head } from '@inertiajs/react';
import { Coins, ShieldAlert, Store } from 'lucide-react';
import { lazy, Suspense, useCallback, useMemo, useState } from 'react';
import type { SyntheticEvent } from 'react';
import { toast } from 'sonner';
import { handleAgentNavigate } from '@/components/kora-points/common/agent-nav';
import { KoraPointShell } from '@/components/kora-points/common/kora-point-shell';
import type { KoraPointSaleProps } from '@/components/kora-points/sale/use-kora-point-sale';
import { useKoraPointSale } from '@/components/kora-points/sale/use-kora-point-sale';
import { ItineraryList } from '@/components/sale/itinerary-list';
import { RouteSearch } from '@/components/sale/route-search';
import { SalePageHeading } from '@/components/sale/sale-page-heading';
import { SaleProgress } from '@/components/sale/sale-progress';
import { SaleSummaryCard } from '@/components/sale/sale-summary-card';
import type {
    SaleItinerary,
    SaleItineraryLeg,
    SalePassengerForm,
    SaleSearch,
} from '@/components/sale/sale-types';
import { SeatPicker } from '@/components/sale/seat-picker';
import { cn } from '@/lib/utils';

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

export default function AgentSalePage(props: KoraPointSaleProps) {
    const {
        point,
        health,
        commissionEarned,
        cities,
        search: initialSearch,
        outboundItineraries,
        inboundItineraries,
        itineraryQuote,
        itineraryLegSeats,
        reservation,
        cashSession,
        mobileMoneyAccounts,
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
    const isSaleCompleted = reservation !== null && reservation.payment !== null;

    const {
        searchForm,
        quoteForm,
        holdForm,
        confirmForm,
        passengersForm,
        releaseForm,
        paymentForm,
        search: submitSearch,
        fetchSeats,
        confirmSale,
        savePassengers,
        releaseHold,
        recordPayment,
        retryTicketIssuance,
        startNewSale,
    } = useKoraPointSale({ ...props, search });

    const stage = reservation
        ? isSaleCompleted
            ? 'tickets'
            : isPaymentStepOpen
                ? 'payment'
                : 'passengers'
        : selectedOutbound
            ? 'seats'
            : 'search';

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

    const compatibleInboundItineraries = useMemo(() => {
        if (!inboundItineraries) {
            return [];
        }

        if (!selectedOutbound) {
            return inboundItineraries;
        }

        const lastOutboundLeg = selectedOutbound.legs.at(-1);
        const outboundArrivalAt = lastOutboundLeg?.arrivalAt;

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

        const estimatedTotal =
            ((selectedOutbound?.totalAmount ?? 0) +
                (selectedInbound?.totalAmount ?? 0)) *
            passengerCount;

        if (!health.sales_allowed) {
            toast.error(
                health.block_reason ??
                    'Les ventes sont actuellement suspendues pour ce point de vente.',
            );

            return;
        }

        if (estimatedTotal > health.available_balance) {
            toast.error(
                `Le montant estimé (${estimatedTotal.toLocaleString('fr-FR')} FCFA) dépasse votre solde disponible (${health.available_balance.toLocaleString('fr-FR')} FCFA). Effectuez un versement avant d'émettre cette vente.`,
            );

            return;
        }

        fetchSeats(selectedOutbound, selectedInbound, () => {
            setIsSeatPickerOpen(true);
        });
    };

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
        const estimatedTotal =
            ((selectedOutbound?.totalAmount ?? 0) +
                (selectedInbound?.totalAmount ?? 0)) *
            passengerCount;

        if (estimatedTotal > health.available_balance) {
            toast.error(
                `Le montant de la vente (${estimatedTotal.toLocaleString('fr-FR')} FCFA) dépasse votre solde disponible (${health.available_balance.toLocaleString('fr-FR')} FCFA).`,
            );

            return;
        }

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

        confirmSale(
            selectedOutbound!,
            selectedInbound,
            passengerCount,
            seatAssignments,
            () => setIsSeatPickerOpen(false),
        );
    };

    const reopenSeatSelection = useCallback(
        (reservationUuid: string) => {
            setPassengerCount(1);
            setIsSeatPickerOpen(false);

            releaseHold(reservationUuid, () => {
                toast.warning(
                    'Votre blocage a expiré. Sélectionnez à nouveau vos places.',
                );
                setIsSeatPickerOpen(true);
            });
        },
        [releaseHold],
    );

    const selectedLegsKey = selectedOutbound
        ? itineraryKey(selectedOutbound)
        : null;
    const hasSelection = Boolean(selectedOutbound);
    const showSeatPicker = isSeatPickerOpen && seatPickerLegs.length > 0;

    return (
        <KoraPointShell
            point={point}
            health={health}
            activePage="sale"
            onNavigate={handleAgentNavigate}
        >
            <Head title="Vente de billets — KORA POINT" />
            <main className="w-full max-w-full overflow-x-hidden">
                <SalePageHeading
                    eyebrow="KORA POINT / Espace Agent / Vente de billets"
                    title="Vente de billets au guichet"
                    description="Émettez des billets passagers pour les lignes KORA. Vos commissions partenaires sont créditées en temps réel."
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

                {/* KORA POINT Context & Available Balance Bar */}
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gold/25 bg-gold/5 px-4 py-3">
                    <div className="flex items-center gap-2.5">
                        <Store className="size-4 text-gold" />
                        <span className="text-sm font-extrabold text-foreground">
                            {point.name} ({point.code})
                        </span>
                        <span
                            className={cn(
                                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[0.6875rem] font-bold',
                                health.sales_allowed
                                    ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                    : 'bg-rose-500/10 text-rose-600 dark:text-rose-400',
                            )}
                        >
                            {health.sales_allowed
                                ? 'Ventes autorisées'
                                : 'Ventes suspendues'}
                        </span>
                    </div>
                    <div className="flex flex-wrap items-center gap-4 text-xs font-bold text-muted-foreground">
                        <span>
                            Solde disponible :{' '}
                            <strong
                                className={cn(
                                    'font-mono text-sm font-black',
                                    health.available_balance > 0
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : 'text-rose-600',
                                )}
                            >
                                {health.available_balance.toLocaleString('fr-FR')} FCFA
                            </strong>
                        </span>
                        <span>
                            Plafond :{' '}
                            <strong className="font-mono text-foreground">
                                {health.credit_limit.toLocaleString('fr-FR')} FCFA
                            </strong>
                        </span>
                        <span>
                            Commission :{' '}
                            <strong className="text-gold">
                                {point.terms.commission_rate_bps / 100} %
                            </strong>
                        </span>
                    </div>
                </div>

                {!health.sales_allowed && (
                    <div className="mb-5 flex items-center gap-2.5 rounded-[0.9rem] border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-xs font-bold text-rose-700 dark:text-rose-300">
                        <ShieldAlert className="size-4 shrink-0 text-rose-600" />
                        <span>
                            {health.block_reason ??
                                'Les ventes sont actuellement suspendues pour ce point de vente en raison d’un dépassement de plafond ou d’un retard de reversement.'}
                        </span>
                    </div>
                )}

                <SaleProgress stage={stage} channelLabel="Guichet Partenaire" />

                <div className={isSaleCompleted ? undefined : 'grid gap-5 mt-5'}>
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
                        <div className="mt-4">
                            {/* Agent-only commission notification banner (strictly hidden on print/PDF) */}
                            {commissionEarned && (
                                <div className="no-print print:hidden mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4">
                                    <div className="flex items-center gap-3">
                                        <span className="flex size-9 items-center justify-center rounded-xl bg-emerald-500/20 text-emerald-600 dark:text-emerald-400">
                                            <Coins className="size-5" />
                                        </span>
                                        <div>
                                            <strong className="block text-sm font-black text-foreground">
                                                Vente guichet enregistrée avec succès !
                                            </strong>
                                            <span className="text-xs text-muted-foreground">
                                                Votre commission partenaire sur cette vente s'élève à{' '}
                                                <strong className="font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                                    +{commissionEarned.commission_amount.toLocaleString('fr-FR')} FCFA
                                                </strong>.
                                            </span>
                                        </div>
                                    </div>
                                    <div className="text-right text-xs">
                                        <span className="block text-muted-foreground">
                                            Montant net imputé sur l'encours :
                                        </span>
                                        <strong className="font-mono text-sm font-bold text-foreground">
                                            {commissionEarned.net_amount.toLocaleString('fr-FR')} FCFA
                                        </strong>
                                    </div>
                                </div>
                            )}

                            <Suspense fallback={<div className="sale-skeleton" />}>
                                <CounterSaleSuccess
                                    departure={null}
                                    reservation={reservation}
                                    onNewSale={startNewSale}
                                    releasing={releaseForm.processing}
                                    onRetryTicketIssuance={() =>
                                        retryTicketIssuance(reservation.uuid)
                                    }
                                />
                            </Suspense>
                        </div>
                    ) : reservation && isPaymentStepOpen ? (
                        <Suspense fallback={<div className="sale-skeleton" />}>
                            <PaymentStep
                                departure={null}
                                reservation={reservation}
                                cashSession={cashSession}
                                mobileMoneyAccounts={mobileMoneyAccounts}
                                processing={paymentForm.processing}
                                errors={paymentForm.errors}
                                search={search}
                                itineraryQuote={itineraryQuote ?? null}
                                isPartner={true}
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
                                onDraftChange={() => {}}
                                draftSaving={false}
                                draftSaved={false}
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
                                        confirmForm.processing
                                    }
                                    onOpenSeatPicker={handleOpenSeatPicker}
                                    companyAccounts={[]}
                                    payerType="personal"
                                    selectedCompanyAccountUuid={null}
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
                    payerType="personal"
                    selectedCompanyAccountUuid={null}
                    companyAccounts={[]}
                    amount={
                        (selectedOutbound?.totalAmount ?? 0) +
                        (selectedInbound?.totalAmount ?? 0)
                    }
                    currency={selectedOutbound?.currency ?? 'XOF'}
                    onPayerTypeChange={() => {}}
                    onCompanyAccountChange={() => {}}
                />
            )}
        </KoraPointShell>
    );
}
