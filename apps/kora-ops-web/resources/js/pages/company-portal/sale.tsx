import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, CreditCard, Search, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import { CreditPaymentStep } from '@/components/company-portal/credit-payment-step';
import type {
    CompanyPortalPassengerSelection,
    CompanyPortalTraveler,
} from '@/components/company-portal/roster-passenger-picker';
import { RosterPassengerPicker } from '@/components/company-portal/roster-passenger-picker';
import type { CompanyPortalSeatAssignments } from '@/components/company-portal/use-company-portal-sale';
import { useCompanyPortalSale } from '@/components/company-portal/use-company-portal-sale';
import { CounterSaleSuccess } from '@/components/sale/counter-sale-success';
import { ItineraryList } from '@/components/sale/itinerary-list';
import { RouteSearch } from '@/components/sale/route-search';
import { SalePageHeading } from '@/components/sale/sale-page-heading';
import { SaleProgress } from '@/components/sale/sale-progress';
import { SaleSummaryCard } from '@/components/sale/sale-summary-card';
import type {
    SaleItinerary,
    SaleItineraryLeg,
    SaleItineraryQuote,
    SaleProps,
    SaleSearch,
} from '@/components/sale/sale-types';
import { formatMoney } from '@/components/sale/sale-utils';
import { SeatPicker } from '@/components/sale/seat-picker';
import { Button } from '@/components/ui/button';
import companyPortal from '@/routes/company-portal';

type Props = Pick<
    SaleProps,
    | 'cities'
    | 'serviceClasses'
    | 'outboundItineraries'
    | 'inboundItineraries'
    | 'itineraryLegSeats'
    | 'itineraryQuote'
    | 'reservation'
    | 'groupBookingSettings'
> & {
    account: { uuid: string; name: string; reference: string };
    search: SaleSearch;
    roster: CompanyPortalTraveler[];
    credit: { available: number; monthlyLimit: number; periodKey: string };
};

function itineraryKey(itinerary: SaleItinerary): string {
    return itinerary.legs
        .map(
            (leg) =>
                `${leg.departureUuid}:${leg.originStop.uuid}:${leg.destinationStop.uuid}`,
        )
        .join('|');
}

function quoteItinerary(
    quote: SaleItineraryQuote | null,
    direction: 'outbound' | 'inbound',
): SaleItinerary | null {
    const legs = quote?.legs.filter((leg) => leg.direction === direction) ?? [];
    const firstLeg = legs[0];
    const lastLeg = legs.at(-1);

    if (!quote || !firstLeg || !lastLeg) {
        return null;
    }

    return {
        isDirect: legs.length === 1,
        connectionsCount: Math.max(0, legs.length - 1),
        totalAmount: legs.reduce((total, leg) => total + leg.amount, 0),
        currency: quote.currency,
        departureAt: firstLeg.departureAt,
        arrivalAt: lastLeg.arrivalAt,
        connectionMarginMinutes: 0,
        legs,
    };
}

function blankSelections(count: number): CompanyPortalPassengerSelection[] {
    return Array.from({ length: count }, (_, passengerIndex) => ({
        passengerIndex,
        travelerUuid: '',
        documentType: '',
        documentNumber: '',
        nationality: '',
        birthDate: '',
        yellowFeverVaccinated: null,
    }));
}

function savedSelections(
    count: number,
    roster: CompanyPortalTraveler[],
    reservation: Props['reservation'],
): CompanyPortalPassengerSelection[] {
    const selections = blankSelections(count);

    reservation?.passengers.forEach((passenger) => {
        const index = passenger.passengerIndex;

        if (index === null || index < 0 || index >= count) {
            return;
        }

        const traveler = roster.find(
            (candidate) =>
                candidate.phone === passenger.phone &&
                candidate.firstName === passenger.firstName &&
                candidate.lastName === passenger.lastName,
        );
        selections[index] = {
            passengerIndex: index,
            travelerUuid: traveler?.uuid ?? '',
            documentType: passenger.documentType ?? '',
            documentNumber: passenger.documentNumber ?? '',
            nationality: passenger.nationality ?? '',
            birthDate: passenger.birthDate ?? '',
            yellowFeverVaccinated: passenger.yellowFeverVaccinated,
        };
    });

    return selections;
}

function legPayload(legs: SaleItineraryLeg[]) {
    return legs.map((leg) => ({
        departure_uuid: leg.departureUuid,
        origin_stop_uuid: leg.originStop.uuid,
        destination_stop_uuid: leg.destinationStop.uuid,
    }));
}

export default function CompanyPortalSalePage(props: Props) {
    const {
        account,
        cities,
        serviceClasses,
        search: initialSearch,
        outboundItineraries,
        inboundItineraries = [],
        itineraryLegSeats = [],
        itineraryQuote,
        reservation,
        roster,
        credit,
        groupBookingSettings,
    } = props;
    const [search, setSearch] = useState<SaleSearch>(initialSearch);
    const [selectedOutbound, setSelectedOutbound] =
        useState<SaleItinerary | null>(null);
    const [selectedInbound, setSelectedInbound] =
        useState<SaleItinerary | null>(null);
    const [ignoreQuote, setIgnoreQuote] = useState(false);
    const [passengerCount, setPassengerCount] = useState(
        reservation?.groupSize ??
            itineraryQuote?.passengerCount ??
            Math.min(1, roster.length),
    );
    const [seatPickerOpen, setSeatPickerOpen] = useState(false);
    const [editingPassengers, setEditingPassengers] = useState(false);
    const [passengerSelections, setPassengerSelections] = useState(() =>
        savedSelections(
            reservation?.groupSize ?? itineraryQuote?.passengerCount ?? 1,
            roster,
            reservation,
        ),
    );
    const sale = useCompanyPortalSale();

    const quotedOutbound = quoteItinerary(itineraryQuote ?? null, 'outbound');
    const quotedInbound = quoteItinerary(itineraryQuote ?? null, 'inbound');
    const activeOutbound = selectedOutbound ?? quotedOutbound;
    const activeInbound = selectedInbound ?? quotedInbound;
    const quoteMatchesSelection =
        !ignoreQuote &&
        itineraryQuote != null &&
        itineraryQuote.passengerCount === passengerCount &&
        (selectedOutbound === null ||
            itineraryKey(selectedOutbound) ===
                itineraryKey(quotedOutbound ?? selectedOutbound)) &&
        (selectedInbound === null ||
            itineraryKey(selectedInbound) ===
                itineraryKey(quotedInbound ?? selectedInbound));
    const activeQuote: SaleItineraryQuote | null = quoteMatchesSelection
        ? (itineraryQuote ?? null)
        : null;
    const maxPassengers = Math.min(
        roster.length,
        groupBookingSettings.maximumPassengers,
    );
    const passengerCountForReservation =
        reservation?.groupSize ?? activeQuote?.passengerCount ?? passengerCount;
    const passengersComplete =
        reservation !== null &&
        reservation.passengers.length === passengerCountForReservation;
    const paid = reservation?.payment !== null && reservation !== null;
    const processing =
        sale.quoteForm.processing ||
        sale.seatsForm.processing ||
        sale.holdForm.processing ||
        sale.passengersForm.processing ||
        sale.paymentForm.processing ||
        sale.releaseForm.processing;
    const selectedSeatCodes =
        reservation?.segments
            .map((segment) => segment.seatCode)
            .filter((code): code is string => Boolean(code)) ?? [];
    const stage = paid
        ? 'tickets'
        : reservation
          ? passengersComplete && !editingPassengers
              ? 'payment'
              : 'passengers'
          : activeQuote
            ? 'seats'
            : 'search';

    const onSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setSelectedOutbound(null);
        setSelectedInbound(null);
        setIgnoreQuote(true);
        router.get(
            companyPortal.sale.index.url(),
            {
                date: search.date ?? undefined,
                from: search.from ?? undefined,
                to: search.to ?? undefined,
                trip_type: search.tripType,
                return_date:
                    search.tripType === 'round_trip'
                        ? (search.returnDate ?? undefined)
                        : undefined,
                service_class: search.serviceClass ?? undefined,
            },
            { preserveState: false, preserveScroll: true },
        );
    };

    const calculateQuote = () => {
        if (!activeOutbound || passengerCount < 1) {
            return;
        }

        const date = search.date ?? activeOutbound.legs[0]?.departureDate ?? '';
        setIgnoreQuote(false);
        sale.calculateQuote({
            outbound_legs: legPayload(activeOutbound.legs),
            inbound_legs: activeInbound ? legPayload(activeInbound.legs) : [],
            passenger_count: passengerCount,
            date,
            from: search.from,
            to: search.to,
            tripType: search.tripType,
            returnDate: search.returnDate,
        });
    };

    const openSeatPicker = () => {
        if (!activeQuote) {
            return;
        }

        sale.fetchSeats(
            activeQuote.uuid,
            {
                date:
                    search.date ??
                    activeOutbound?.legs[0]?.departureDate ??
                    null,
                from: search.from,
                to: search.to,
                tripType: search.tripType,
                returnDate: search.returnDate,
            },
            () => setSeatPickerOpen(true),
        );
    };

    const holdSelectedSeats = (
        assignments: Record<number, Record<number, string>>,
    ) => {
        if (!activeQuote) {
            return;
        }

        const seatAssignments: CompanyPortalSeatAssignments = Object.entries(
            assignments,
        ).map(([passengerIndex, seats]) => ({
            passenger_index: Number(passengerIndex),
            seat_assignments: activeQuote.legs.map((leg) => ({
                leg_order: leg.legOrder,
                direction: leg.direction ?? 'outbound',
                seat_uuid: seats[leg.legOrder],
            })),
        }));

        sale.holdSeats(activeQuote.uuid, seatAssignments, {
            date: search.date ?? activeOutbound?.legs[0]?.departureDate ?? null,
            from: search.from,
            to: search.to,
            tripType: search.tripType,
            returnDate: search.returnDate,
        });
        setSeatPickerOpen(false);
    };

    const savePassengers = () => {
        if (
            !reservation ||
            passengerSelections.some((passenger) => !passenger.travelerUuid)
        ) {
            toast.error('Sélectionnez un voyageur pour chaque siège.');

            return;
        }

        sale.savePassengers(reservation.uuid, passengerSelections, () =>
            setEditingPassengers(false),
        );
    };

    const releaseReservation = () => {
        if (!reservation) {
            return;
        }

        sale.releaseHold(reservation.uuid, () => {
            router.get(
                companyPortal.sale.index.url(),
                {},
                { preserveState: false },
            );
        });
    };

    const createNewSale = () => {
        if (
            reservation &&
            reservation.payment === null &&
            !window.confirm(
                'Libérer les sièges réservés et démarrer un nouvel achat ?',
            )
        ) {
            return;
        }

        if (reservation && reservation.payment === null) {
            releaseReservation();

            return;
        }

        router.get(
            companyPortal.sale.index.url(),
            {},
            { preserveState: false },
        );
    };

    return (
        <>
            <Head title="Acheter des billets — Espace entreprise" />
            <div className="cash-page">
                <SalePageHeading
                    eyebrow="KORA OPS / Espace entreprise / Achat"
                    title="Acheter des billets"
                    description="Réservez pour les voyageurs de votre entreprise. Le compte entreprise paie par crédit mensuel."
                    action={
                        <Link
                            href={companyPortal.index.url()}
                            className="btn btn-ghost"
                        >
                            <ArrowLeft className="size-4" />
                            Retour à l’espace
                        </Link>
                    }
                />
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gold/25 bg-gold/5 px-4 py-3">
                    <span className="flex items-center gap-2 text-sm font-bold text-foreground">
                        <CreditCard className="size-4 text-gold" />
                        {account.name}
                    </span>
                    <span className="flex items-center gap-2 text-xs font-bold text-muted-foreground">
                        <ShieldCheck className="size-4 text-emerald-600" />
                        Crédit disponible&nbsp;
                        <strong className="text-foreground">
                            {formatMoney(credit.available, 'XOF')}
                        </strong>
                    </span>
                </div>

                <SaleProgress stage={stage} channelLabel="Achat entreprise" />

                {paid && reservation ? (
                    <div className="mt-6">
                        <CounterSaleSuccess
                            departure={null}
                            reservation={reservation}
                            releasing={sale.releaseForm.processing}
                            companyPortal
                            onNewSale={createNewSale}
                            onRetryTicketIssuance={() =>
                                router.post(
                                    companyPortal.sale.tickets.retry.url({
                                        reservation: reservation.uuid,
                                    }),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        />
                    </div>
                ) : (
                    <div className="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
                        <main className="grid min-w-0 gap-5">
                            {!reservation && (
                                <>
                                    <RouteSearch
                                        cities={cities}
                                        serviceClasses={serviceClasses}
                                        value={search}
                                        processing={processing}
                                        onChange={setSearch}
                                        onSubmit={onSearch}
                                    />
                                    {outboundItineraries.length > 0 ? (
                                        <ItineraryList
                                            itineraries={outboundItineraries}
                                            selectedLegsKey={
                                                activeOutbound
                                                    ? itineraryKey(
                                                          activeOutbound,
                                                      )
                                                    : null
                                            }
                                            onSelect={(itinerary) => {
                                                setSelectedOutbound(itinerary);
                                                setSelectedInbound(null);
                                                setIgnoreQuote(true);
                                            }}
                                            hasDate={search.hasDate}
                                        />
                                    ) : (
                                        <div className="rounded-2xl border border-dashed border-border bg-card p-8 text-center">
                                            <Search className="mx-auto size-6 text-muted-foreground" />
                                            <p className="mt-3 text-sm font-bold text-foreground">
                                                Aucun trajet ne correspond à
                                                cette recherche.
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Modifiez les villes ou la date
                                                pour voir d’autres départs.
                                            </p>
                                        </div>
                                    )}

                                    {search.tripType === 'round_trip' && (
                                        <ItineraryList
                                            itineraries={inboundItineraries}
                                            selectedLegsKey={
                                                activeInbound
                                                    ? itineraryKey(
                                                          activeInbound,
                                                      )
                                                    : null
                                            }
                                            onSelect={(itinerary) => {
                                                setSelectedInbound(itinerary);
                                                setIgnoreQuote(true);
                                            }}
                                            hasDate={Boolean(search.returnDate)}
                                            direction="inbound"
                                        />
                                    )}

                                    {activeOutbound && roster.length > 0 && (
                                        <div className="flex flex-wrap items-end gap-4 rounded-2xl border border-border bg-card p-4">
                                            <label className="field w-40">
                                                <span>Voyageurs</span>
                                                <select
                                                    className="control"
                                                    value={passengerCount}
                                                    onChange={(event) => {
                                                        const nextCount =
                                                            Number(
                                                                event.target
                                                                    .value,
                                                            );
                                                        setPassengerCount(
                                                            nextCount,
                                                        );
                                                        setPassengerSelections(
                                                            blankSelections(
                                                                nextCount,
                                                            ),
                                                        );
                                                        setIgnoreQuote(true);
                                                    }}
                                                >
                                                    {Array.from(
                                                        {
                                                            length: maxPassengers,
                                                        },
                                                        (_, index) => index + 1,
                                                    ).map((count) => (
                                                        <option
                                                            key={count}
                                                            value={count}
                                                        >
                                                            {count}
                                                        </option>
                                                    ))}
                                                </select>
                                            </label>
                                            <Button
                                                type="button"
                                                variant="kora"
                                                size="kora"
                                                onClick={calculateQuote}
                                                disabled={
                                                    processing ||
                                                    (search.tripType ===
                                                        'round_trip' &&
                                                        !activeInbound)
                                                }
                                            >
                                                Calculer le devis
                                            </Button>
                                            {roster.length === 0 && (
                                                <p className="text-xs text-destructive">
                                                    Ajoutez d’abord des
                                                    voyageurs à votre
                                                    entreprise.
                                                </p>
                                            )}
                                        </div>
                                    )}

                                </>
                            )}

                            {reservation &&
                                stage === 'passengers' &&
                                reservation.payment === null && (
                                    <section className="grid gap-4">
                                        <div className="rounded-2xl border border-border bg-card p-5">
                                            <span className="section-kicker">
                                                Sièges réservés
                                            </span>
                                            <h2 className="mt-1 text-lg font-black text-foreground">
                                                Associer les voyageurs
                                            </h2>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                L’identité est reprise de vos
                                                voyageurs enregistrés. Les
                                                informations de pièce manquantes
                                                peuvent être complétées.
                                            </p>
                                        </div>
                                        <RosterPassengerPicker
                                            roster={roster}
                                            selections={passengerSelections}
                                            onChange={setPassengerSelections}
                                        />
                                        <div className="flex flex-wrap justify-between gap-3">
                                            <Button
                                                type="button"
                                                variant="kora-secondary"
                                                size="kora"
                                                onClick={releaseReservation}
                                                disabled={processing}
                                            >
                                                Libérer les sièges
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="kora"
                                                size="kora"
                                                onClick={savePassengers}
                                                disabled={
                                                    processing ||
                                                    passengerSelections.some(
                                                        (passenger) =>
                                                            !passenger.travelerUuid,
                                                    )
                                                }
                                            >
                                                Enregistrer les voyageurs
                                            </Button>
                                        </div>
                                    </section>
                                )}

                            {reservation &&
                                stage === 'payment' &&
                                reservation.payment === null && (
                                    <CreditPaymentStep
                                        quote={activeQuote}
                                        reservation={reservation}
                                        creditAvailable={credit.available}
                                        processing={processing}
                                        onConfirm={() =>
                                            sale.recordPayment(reservation.uuid)
                                        }
                                        onBack={() =>
                                            setEditingPassengers(true)
                                        }
                                    />
                                )}
                        </main>

                        <aside className="grid content-start gap-4 xl:sticky xl:top-24">
                            {!reservation && activeOutbound && (
                                <div className="rounded-2xl border border-border bg-card p-4">
                                    {roster.length > 0 ? (
                                        <div className="grid gap-3">
                                            <label className="field">
                                                <span>Nombre de voyageurs</span>
                                                <select
                                                    className="control"
                                                    value={passengerCount}
                                                    onChange={(event) => {
                                                        const nextCount =
                                                            Number(
                                                                event.target
                                                                    .value,
                                                            );
                                                        setPassengerCount(
                                                            nextCount,
                                                        );
                                                        setPassengerSelections(
                                                            blankSelections(
                                                                nextCount,
                                                            ),
                                                        );
                                                        setIgnoreQuote(true);
                                                    }}
                                                >
                                                    {Array.from(
                                                        {
                                                            length: maxPassengers,
                                                        },
                                                        (_, index) => index + 1,
                                                    ).map((count) => (
                                                        <option
                                                            key={count}
                                                            value={count}
                                                        >
                                                            {count}
                                                        </option>
                                                    ))}
                                                </select>
                                            </label>
                                            <Button
                                                type="button"
                                                variant="kora"
                                                size="kora"
                                                className="w-full justify-center"
                                                onClick={calculateQuote}
                                                disabled={
                                                    processing ||
                                                    (search.tripType ===
                                                        'round_trip' &&
                                                        !activeInbound)
                                                }
                                            >
                                                {activeQuote
                                                    ? 'Recalculer le devis'
                                                    : 'Calculer le devis'}
                                            </Button>
                                        </div>
                                    ) : (
                                        <p className="text-xs text-destructive">
                                            Ajoutez d’abord des voyageurs actifs
                                            à votre roster.
                                        </p>
                                    )}
                                </div>
                            )}
                            <SaleSummaryCard
                                passengerCount={passengerCountForReservation}
                                selectedSeatCodes={selectedSeatCodes}
                                quote={activeQuote}
                                itineraryQuote={activeQuote}
                                selectedItinerary={activeOutbound}
                                selectedInbound={activeInbound}
                                reservation={reservation}
                                processing={processing}
                                onOpenSeatPicker={
                                    activeQuote &&
                                    reservation === null &&
                                    credit.available >= activeQuote.totalAmount
                                        ? openSeatPicker
                                        : undefined
                                }
                                companyPortal
                                companyName={account.name}
                                creditAvailable={credit.available}
                            />
                            {!reservation &&
                                activeQuote &&
                                credit.available < activeQuote.totalAmount && (
                                    <div className="summary-callout warning">
                                        Crédit insuffisant : le devis dépasse le
                                        disponible de{' '}
                                        {formatMoney(
                                            activeQuote.totalAmount -
                                                credit.available,
                                            activeQuote.currency,
                                        )}
                                        .
                                    </div>
                                )}
                            {reservation && reservation.payment === null && (
                                <div className="summary-callout warning">
                                    Le crédit mensuel est débité uniquement
                                    après la saisie des voyageurs et votre
                                    confirmation.
                                </div>
                            )}
                        </aside>
                    </div>
                )}
            </div>

            {seatPickerOpen && activeQuote && reservation === null && (
                <SeatPicker
                    legs={activeQuote.legs}
                    legSeats={itineraryLegSeats}
                    passengerCount={passengerCount}
                    maxPassengers={passengerCount}
                    minPassengers={passengerCount}
                    processing={
                        sale.holdForm.processing || sale.seatsForm.processing
                    }
                    onPassengerCountChange={() => undefined}
                    onClose={() => setSeatPickerOpen(false)}
                    onConfirm={holdSelectedSeats}
                    amount={activeQuote.totalAmount}
                    currency={activeQuote.currency}
                />
            )}
        </>
    );
}
