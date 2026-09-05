<?php

namespace App\Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Booking\Actions\CalculateItineraryQuoteAction;
use App\Modules\Booking\Actions\CalculateJourneyQuoteAction;
use App\Modules\Booking\Actions\CreateItineraryHoldAction;
use App\Modules\Booking\Actions\CreateJourneyHoldAction;
use App\Modules\Booking\Actions\CreateSeatHoldAction;
use App\Modules\Booking\Actions\ItineraryLegQuoteRequest;
use App\Modules\Booking\Actions\JourneyLegQuoteRequest;
use App\Modules\Booking\Actions\ReleaseSeatHoldAction;
use App\Modules\Booking\Actions\ResolvePayerContextAction;
use App\Modules\Booking\Actions\SaveCounterSaleDraftAction;
use App\Modules\Booking\Actions\UpdateReservationPassengersAction;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Exceptions\SeatUnavailableException;
use App\Modules\Booking\Http\Requests\CounterSaleSearchRequest;
use App\Modules\Booking\Http\Requests\HoldCounterSaleSeatsRequest;
use App\Modules\Booking\Http\Requests\ReleaseCounterSaleHoldRequest;
use App\Modules\Booking\Http\Requests\StoreCounterSaleDraftRequest;
use App\Modules\Booking\Http\Requests\StoreCounterSalePassengersRequest;
use App\Modules\Booking\Http\Requests\StoreCounterSalePaymentRequest;
use App\Modules\Booking\Http\Requests\StoreCounterSaleQuoteRequest;
use App\Modules\Booking\Models\GroupBookingSetting;
use App\Modules\Booking\Models\ItineraryQuote;
use App\Modules\Booking\Models\JourneyQuote;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\Booking\Services\ItineraryData;
use App\Modules\Booking\Services\ItineraryLegData;
use App\Modules\Booking\Services\ItinerarySearchService;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\BusNetwork\Services\BusCatalogService;
use App\Modules\Cash\Data\CashLedgerKind;
use App\Modules\Cash\Data\CashSessionStatus;
use App\Modules\Cash\Models\CashSession;
use App\Modules\CompanyAccounts\Services\CompanyAccountCatalogService;
use App\Modules\Departures\Data\DepartureAvailabilityData;
use App\Modules\Departures\Models\Departure;
use App\Modules\Departures\Services\DepartureSearchService;
use App\Modules\DynamicPricing\Actions\CalculateQuoteAction;
use App\Modules\DynamicPricing\Models\PriceQuote;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\Payments\Actions\RecordCounterSalePaymentAction;
use App\Modules\Payments\Models\AgencyPaymentAccount;
use App\Modules\Payments\Models\PaymentRefund;
use App\Modules\SeatInventory\Actions\BuildCounterSaleSeatAvailabilityAction;
use App\Modules\SeatInventory\Data\CounterSaleSeatAvailabilityData;
use App\Modules\Ticketing\Data\TicketIssuanceStatus;
use App\Modules\Ticketing\Jobs\IssueReservationTicketsJob;
use App\Modules\Ticketing\Models\Ticket;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CounterSaleController extends Controller
{
    /**
     * @phpstan-type SaleContext array{departure_uuid: string|null, origin_stop_uuid: string|null, destination_stop_uuid: string|null, quote_uuid: string|null, reservation_uuid: string|null}
     */
    public function __construct(
        private DepartureSearchService $departures,
        private BusCatalogService $catalog,
        private BuildCounterSaleSeatAvailabilityAction $counterSaleSeatAvailability,
        private CompanyAccountCatalogService $companyAccounts,
        private ItinerarySearchService $itinerarySearch,
    ) {}

    public function index(CounterSaleSearchRequest $request): Response|RedirectResponse
    {
        Gate::authorize('viewAny', Departure::class);

        if ($this->isV2()) {
            return $this->indexV2($request);
        }

        if (! $request->hasExplicitSaleContext()) {
            $activeReservation = $this->latestActiveCounterSale();

            if ($activeReservation !== null) {
                return to_route('sale', $this->saleQueryFromReservation($activeReservation));
            }
        }

        $filters = $request->departureFilters();
        $context = $request->selectedContext();
        $selectedDeparture = $this->selectedDeparture($context, $filters);

        return Inertia::render('sale', [
            'cities' => $this->serializeCities($this->catalog->cities()),
            'search' => [
                'date' => $filters['date'],
                'from' => $filters['from'],
                'to' => $filters['to'],
            ],
            'departures' => $this->serializeDepartures($this->searchDepartures($filters)),
            'itineraries' => $this->serializeItineraries($this->searchItineraries($filters)),
            'selectedDeparture' => $selectedDeparture ? $this->serializeDeparture($selectedDeparture) : null,
            'seats' => $this->serializeSeats($this->selectedSeats($selectedDeparture, $context)),
            'quote' => $this->serializeQuote($this->selectedQuote($context, $selectedDeparture)),
            'itineraryQuote' => $this->serializeItineraryQuote($this->selectedItineraryQuote($context)),
            'reservation' => $this->serializeReservation($this->selectedReservation($context)),
            'cashSession' => $this->serializeCashSession(),
            'mobileMoneyAccounts' => $this->serializeMobileMoneyAccounts(),
            'companyAccounts' => $this->serializeCompanyAccounts(),
            'groupBookingSettings' => $this->serializeGroupBookingSettings(),
            'permissions' => [
                'canCreate' => Gate::allows('createCounterSale', Reservation::class),
            ],
        ]);
    }

    public function quote(StoreCounterSaleQuoteRequest $request, CalculateQuoteAction $calculateQuote, CalculateItineraryQuoteAction $calculateItineraryQuote, CalculateJourneyQuoteAction $calculateJourneyQuote): RedirectResponse
    {
        $data = $request->quoteData();
        if ($this->isV2()) {
            $buildLegs = function (array $legs, string $direction): array {
                return array_map(
                    fn (array $leg, int $index): JourneyLegQuoteRequest => new JourneyLegQuoteRequest(
                        direction: $direction,
                        departure: Departure::query()->where('uuid', $leg['departure_uuid'])->firstOrFail(),
                        originStop: Stop::query()->where('uuid', $leg['origin_stop_uuid'])->firstOrFail(),
                        destinationStop: Stop::query()->where('uuid', $leg['destination_stop_uuid'])->firstOrFail(),
                        legOrder: $index + 1,
                    ),
                    $legs,
                    array_keys($legs),
                );
            };
            $result = $calculateJourneyQuote->handle(
                tripType: $data['trip_type'],
                outbound: $buildLegs($data['outbound_legs'], 'outbound'),
                inbound: $buildLegs($data['inbound_legs'], 'inbound'),
                passengerCount: $data['passenger_count'],
                user: $request->user(),
            );

            return to_route('sale-v2', $this->v2Query($request, [
                'origin_physical_stop_uuid' => $data['origin_physical_stop_uuid'],
                'destination_physical_stop_uuid' => $data['destination_physical_stop_uuid'],
                'return_date' => $data['return_date'],
                'selected_outbound_uuid' => $data['selected_outbound_uuid'],
                'selected_inbound_uuid' => $data['selected_inbound_uuid'],
                'journey_quote_uuid' => $result->journeyQuote->uuid,
            ]));
        }
        if (is_array($data['legs'] ?? null) && $data['legs'] !== []) {
            $legs = [];
            foreach ($data['legs'] as $leg) {
                $legs[] = new ItineraryLegQuoteRequest(
                    Departure::query()->where('uuid', $leg['departure_uuid'])->firstOrFail(),
                    Stop::query()->where('uuid', $leg['origin_stop_uuid'])->firstOrFail(),
                    Stop::query()->where('uuid', $leg['destination_stop_uuid'])->firstOrFail(),
                );
            }
            $result = $calculateItineraryQuote->handle($legs, $data['passenger_count'], $request->user());

            return to_route('sale', $request->saleQuery(null, $result->itineraryQuote->uuid));
        }

        $quote = $calculateQuote->handle(
            departure: Departure::query()->where('uuid', $data['departure_uuid'])->firstOrFail(),
            originStop: Stop::query()->where('uuid', $data['origin_stop_uuid'])->firstOrFail(),
            destinationStop: Stop::query()->where('uuid', $data['destination_stop_uuid'])->firstOrFail(),
            passengerCount: $data['passenger_count'],
        );

        return to_route('sale', $request->saleQuery($quote->uuid));
    }

    public function hold(HoldCounterSaleSeatsRequest $request, CreateSeatHoldAction $createSeatHold, CreateItineraryHoldAction $createItineraryHold, CreateJourneyHoldAction $createJourneyHold, ResolvePayerContextAction $resolvePayer): RedirectResponse
    {
        $data = $request->holdData();
        $payer = $data['payer'] !== null
            ? $resolvePayer->handle($request->user(), $data['payer'])
            : null;
        try {
            if ($this->isV2() && $data['journey_quote_uuid'] !== null) {
                $reservation = $createJourneyHold->handle(
                    user: $request->user(),
                    journeyQuote: JourneyQuote::query()->where('uuid', $data['journey_quote_uuid'])->firstOrFail(),
                    seatAssignments: $request->journeySeatAssignments(),
                    channel: ReservationChannel::Counter,
                    idempotencyKey: $data['idempotency_key'],
                    payer: $payer,
                );
            } elseif ($data['itinerary_quote_uuid'] !== null) {
                $reservation = $createItineraryHold->handle(
                    user: $request->user(),
                    itineraryQuote: ItineraryQuote::query()->where('uuid', $data['itinerary_quote_uuid'])->firstOrFail(),
                    seatAssignments: $request->itinerarySeatAssignments(),
                    channel: ReservationChannel::Counter,
                    idempotencyKey: $data['idempotency_key'],
                    payer: $payer,
                );
            } else {
                $reservation = $createSeatHold->handle(
                    user: $request->user(),
                    departure: Departure::query()->where('uuid', $data['departure_uuid'])->firstOrFail(),
                    originStop: Stop::query()->where('uuid', $data['origin_stop_uuid'])->firstOrFail(),
                    destinationStop: Stop::query()->where('uuid', $data['destination_stop_uuid'])->firstOrFail(),
                    priceQuote: PriceQuote::query()->where('uuid', $data['price_quote_uuid'])->firstOrFail(),
                    seatUuids: $data['seat_uuids'],
                    channel: ReservationChannel::Counter,
                    payer: $payer,
                    idempotencyKey: $data['idempotency_key'],
                );
            }
        } catch (SeatUnavailableException $exception) {
            throw ValidationException::withMessages([
                'seats' => $exception->getMessage(),
            ]);
        }

        return to_route($this->saleRouteName(), $this->isV2()
            ? $this->v2Query($request, ['reservation_uuid' => $reservation->uuid])
            : $request->saleQuery($reservation->uuid));
    }

    public function storePassengers(
        StoreCounterSalePassengersRequest $request,
        Reservation $reservation,
        UpdateReservationPassengersAction $updatePassengers,
    ): RedirectResponse {
        $updatedReservation = $updatePassengers->handle(
            reservation: $reservation,
            user: $request->user(),
            passengersData: $request->passengersData(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Informations passagers enregistrées.'),
        ]);

        return to_route($this->saleRouteName(), $this->isV2()
            ? $this->v2Query($request, ['reservation_uuid' => $updatedReservation->uuid])
            : $request->saleQuery($updatedReservation->uuid));
    }

    public function storePayment(
        StoreCounterSalePaymentRequest $request,
        Reservation $reservation,
        RecordCounterSalePaymentAction $recordPayment,
    ): RedirectResponse {
        try {
            $result = $recordPayment->execute(
                reservation: $reservation,
                agent: $request->user(),
                attributes: $request->payload(),
            );
        } catch (ValidationException $exception) {
            logger()->warning('Counter sale payment validation failed', [
                'reservation_uuid' => $reservation->uuid,
                'errors' => $exception->errors(),
                'input' => $request->except(['evidence']),
            ]);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => collect($exception->errors())->flatten()->first() ?? __('Le paiement n’a pas pu être enregistré.'),
            ]);

            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Une erreur technique est survenue lors de l’encaissement. Réessayez ou contactez le support.'),
            ]);

            return to_route($this->saleRouteName(), $this->isV2()
                ? $this->v2Query($request, ['reservation_uuid' => $reservation->uuid])
                : $request->saleQuery($reservation->uuid))
                ->withInput();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Paiement confirmé. L’émission des billets est en cours.'),
        ]);

        $confirmedReservation = $result['reservation']->load([
            'segments.departure.line',
            'segments.departure.bus',
            'segments.originStop',
            'segments.destinationStop',
        ]);
        $segment = $confirmedReservation->segments->first();

        return to_route($this->saleRouteName(), array_filter([
            'date' => $segment?->departure?->departure_date?->toDateString(),
            'from' => $segment?->originStop?->city,
            'to' => $segment?->destinationStop?->city,
            'departure_uuid' => $segment?->departure?->uuid,
            'origin_stop_uuid' => $segment?->originStop?->uuid,
            'destination_stop_uuid' => $segment?->destinationStop?->uuid,
            'reservation_uuid' => $confirmedReservation->uuid,
        ], static fn (mixed $value): bool => $value !== null && $value !== ''));
    }

    public function retryTicketIssuance(Reservation $reservation): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $reservation->created_by_user_id === $user->getKey(), 403);
        abort_unless($reservation->status === ReservationStatus::Paid, 422);

        $reservation->update([
            'ticket_issuance_status' => TicketIssuanceStatus::Pending,
            'ticket_issuance_error' => null,
        ]);
        IssueReservationTicketsJob::dispatch($reservation->getKey());

        return back();
    }

    public function releaseHold(
        ReleaseCounterSaleHoldRequest $request,
        Reservation $reservation,
        ReleaseSeatHoldAction $releaseSeatHold,
    ): RedirectResponse {
        $releaseSeatHold->handle($reservation, $request->user());

        return to_route($this->saleRouteName(), $this->isV2()
            ? $this->v2Query($request)
            : $request->saleQuery());
    }

    public function saveDraft(
        StoreCounterSaleDraftRequest $request,
        Reservation $reservation,
        SaveCounterSaleDraftAction $saveDraft,
    ): RedirectResponse {
        $saveDraft->handle(
            reservation: $reservation,
            user: $request->user(),
            passengersData: $request->passengersData(),
        );

        return back();
    }

    private function isV2(): bool
    {
        return request()->routeIs('sale-v2*');
    }

    private function saleRouteName(): string
    {
        return $this->isV2() ? 'sale-v2' : 'sale';
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function v2Query(Request $request, array $extra = []): array
    {
        return array_filter([
            'trip_type' => $request->input('trip_type', 'one_way'),
            'origin_physical_stop_uuid' => $request->input('origin_physical_stop_uuid'),
            'destination_physical_stop_uuid' => $request->input('destination_physical_stop_uuid'),
            'departure_date' => $request->input('departure_date', $request->input('date')),
            'return_date' => $request->input('return_date'),
            'passenger_count' => $request->input('passenger_count', 1),
            'selected_outbound_uuid' => $request->input('selected_outbound_uuid'),
            'selected_inbound_uuid' => $request->input('selected_inbound_uuid'),
            ...$extra,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function indexV2(CounterSaleSearchRequest $request): Response|RedirectResponse
    {
        if (! $request->hasExplicitSaleContext()) {
            $activeReservation = $this->latestActiveCounterSale();
            if ($activeReservation !== null) {
                return to_route('sale-v2', $this->v2Query($request, ['reservation_uuid' => $activeReservation->uuid]));
            }
        }

        $tripType = $request->string('trip_type')->value() ?: 'one_way';
        $originUuid = $request->string('origin_physical_stop_uuid')->trim()->value() ?: null;
        $destinationUuid = $request->string('destination_physical_stop_uuid')->trim()->value() ?: null;
        $departureDate = $request->string('departure_date')->value() ?: $request->string('date')->value() ?: now()->toDateString();
        $returnDate = $request->string('return_date')->value() ?: null;
        $passengerCount = max(1, min(10, $request->integer('passenger_count') ?: 1));
        $outbound = $this->searchV2Journeys($originUuid, $destinationUuid, $departureDate, $passengerCount);
        $inbound = $tripType === 'round_trip' && $returnDate !== null
            ? $this->searchV2Journeys($destinationUuid, $originUuid, $returnDate, $passengerCount, 'inbound')
            : [];
        $selectedOutboundUuid = $request->string('selected_outbound_uuid')->value() ?: null;
        $selectedInboundUuid = $request->string('selected_inbound_uuid')->value() ?: null;
        $selected = [];
        foreach ([$this->findJourneyByUuid($outbound, $selectedOutboundUuid), $this->findJourneyByUuid($inbound, $selectedInboundUuid)] as $journey) {
            if ($journey !== null) {
                $selected[] = $journey;
            }
        }

        return Inertia::render('sale-v2', [
            'physicalStops' => PhysicalStop::query()->where('status', 'active')->orderBy('city')->orderBy('name')->get()->map(fn (PhysicalStop $stop): array => [
                'uuid' => $stop->uuid,
                'name' => $stop->name,
                'city' => $stop->city,
                'country' => $stop->country,
            ])->values()->all(),
            'search' => [
                'tripType' => $tripType,
                'originPhysicalStopUuid' => $originUuid,
                'destinationPhysicalStopUuid' => $destinationUuid,
                'departureDate' => $departureDate,
                'returnDate' => $returnDate,
                'passengerCount' => $passengerCount,
            ],
            'journeys' => [
                'outbound' => array_map(fn (array $journey): array => $journey['serialized'], $outbound),
                'inbound' => array_map(fn (array $journey): array => $journey['serialized'], $inbound),
            ],
            'selectedJourneys' => [
                'outbound' => $this->findJourneyByUuid($outbound, $selectedOutboundUuid)['serialized'] ?? null,
                'inbound' => $this->findJourneyByUuid($inbound, $selectedInboundUuid)['serialized'] ?? null,
            ],
            'journeySeats' => $this->serializeJourneySeats($selected),
            'journeyQuote' => $this->serializeJourneyQuote($this->selectedJourneyQuote($request)),
            'reservation' => $this->serializeReservation($this->selectedReservation($request->selectedContext())),
            'cashSession' => $this->serializeCashSession(),
            'mobileMoneyAccounts' => $this->serializeMobileMoneyAccounts(),
            'companyAccounts' => $this->serializeCompanyAccounts(),
            'groupBookingSettings' => $this->serializeGroupBookingSettings(),
            'permissions' => ['canCreate' => Gate::allows('createCounterSale', Reservation::class)],
        ]);
    }

    /** @return list<array{serialized: array<string, mixed>, data: ItineraryData}> */
    private function searchV2Journeys(?string $originUuid, ?string $destinationUuid, string $date, int $passengerCount, string $direction = 'outbound'): array
    {
        if ($originUuid === null || $destinationUuid === null || $originUuid === $destinationUuid) {
            return [];
        }
        $journeys = $this->itinerarySearch->search(
            originPhysicalStopUuid: $originUuid,
            destinationPhysicalStopUuid: $destinationUuid,
            date: CarbonImmutable::parse($date),
            passengerCount: $passengerCount,
        );

        return array_map(fn (ItineraryData $journey): array => [
            'serialized' => $this->serializeV2Journey($journey, $direction),
            'data' => $journey,
        ], $journeys);
    }

    /**
     * @param  list<array{serialized: array<string, mixed>, data: ItineraryData}>  $journeys
     * @return array{serialized: array<string, mixed>, data: ItineraryData}|null
     */
    private function findJourneyByUuid(array $journeys, ?string $uuid): ?array
    {
        if ($uuid === null) {
            return null;
        }
        foreach ($journeys as $journey) {
            if ($journey['serialized']['uuid'] === $uuid) {
                return $journey;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function serializeV2Journey(ItineraryData $journey, string $direction): array
    {
        $key = $direction.'|'.implode('|', array_map(fn (ItineraryLegData $leg): string => $leg->departure->uuid.':'.$leg->originStop->uuid.':'.$leg->destinationStop->uuid, $journey->legs));
        $uuid = substr(hash('sha256', $key), 0, 8).'-'.substr(hash('sha256', $key), 8, 4).'-4'.substr(hash('sha256', $key), 13, 3).'-8'.substr(hash('sha256', $key), 17, 3).'-'.substr(hash('sha256', $key), 20, 12);

        return [
            'uuid' => $uuid,
            'direction' => $direction,
            'isDirect' => $journey->isDirect(),
            'connectionsCount' => $journey->connectionsCount,
            'departureAt' => $journey->departureAt->toISOString(),
            'arrivalAt' => $journey->arrivalAt->toISOString(),
            'totalAmount' => $journey->totalAmount,
            'currency' => $journey->currency,
            'connectionMarginMinutes' => $journey->connectionMarginMinutes,
            'legs' => array_map(fn (ItineraryLegData $leg): array => $this->serializeItineraryLeg($leg), $journey->legs),
        ];
    }

    /**
     * @param  list<array{serialized: array<string, mixed>, data: ItineraryData}>  $selected
     * @return list<array{direction: string, legOrder: int, departureUuid: string, seats: list<array<string, mixed>>}>
     */
    private function serializeJourneySeats(array $selected): array
    {
        $result = [];
        foreach ($selected as $journey) {
            foreach ($journey['data']->legs as $leg) {
                $result[] = [
                    'direction' => $journey['serialized']['direction'],
                    'legOrder' => $leg->legOrder,
                    'departureUuid' => $leg->departure->uuid,
                    'seats' => $this->serializeSeats($this->counterSaleSeatAvailability->handle($leg->departure, $leg->originStop, $leg->destinationStop)),
                ];
            }
        }

        return $result;
    }

    /**
     * @param  array{date: string|null, from: string|null, to: string|null}  $filters
     * @return Collection<int, DepartureAvailabilityData>
     */
    private function searchDepartures(array $filters): Collection
    {
        return $this->departures->searchSellable([
            'date' => $filters['date'],
            'from' => $filters['from'],
            'to' => $filters['to'],
        ], sellableOnly: true);
    }

    /**
     * Search for multi-leg itineraries (direct + connections) when both
     * origin and destination cities are provided.
     *
     * @param  array{date: string|null, from: string|null, to: string|null}  $filters
     * @return list<ItineraryData>
     */
    private function searchItineraries(array $filters): array
    {
        if ($filters['from'] === null || $filters['to'] === null || $filters['from'] === $filters['to']) {
            return [];
        }

        $originPhysical = PhysicalStop::query()
            ->where('city', $filters['from'])
            ->where('status', 'active')
            ->first();

        $destinationPhysical = PhysicalStop::query()
            ->where('city', $filters['to'])
            ->where('status', 'active')
            ->first();

        if ($originPhysical === null || $destinationPhysical === null) {
            return [];
        }

        $date = $filters['date'] !== null
            ? CarbonImmutable::parse($filters['date'])
            : CarbonImmutable::today();

        return $this->itinerarySearch->search(
            originPhysicalStopUuid: $originPhysical->uuid,
            destinationPhysicalStopUuid: $destinationPhysical->uuid,
            date: $date,
            passengerCount: 1,
        );
    }

    /**
     * @param  list<ItineraryData>  $itineraries
     * @return list<array<string, mixed>>
     */
    private function serializeItineraries(array $itineraries): array
    {
        return array_map(fn (ItineraryData $itinerary): array => $this->serializeItinerary($itinerary), $itineraries);
    }

    /** @return array<string, mixed> */
    private function serializeItinerary(ItineraryData $itinerary): array
    {
        return [
            'isDirect' => $itinerary->isDirect(),
            'connectionsCount' => $itinerary->connectionsCount,
            'totalAmount' => $itinerary->totalAmount,
            'currency' => $itinerary->currency,
            'departureAt' => $itinerary->departureAt->toISOString(),
            'arrivalAt' => $itinerary->arrivalAt->toISOString(),
            'connectionMarginMinutes' => $itinerary->connectionMarginMinutes,
            'legs' => array_map(fn (ItineraryLegData $leg): array => $this->serializeItineraryLeg($leg), $itinerary->legs),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeItineraryLeg(ItineraryLegData $leg): array
    {
        return [
            'legOrder' => $leg->legOrder,
            'departureUuid' => $leg->departure->uuid,
            'departureDate' => $leg->departure->departure_date?->toDateString(),
            'departureTime' => $leg->departure->departure_time,
            'line' => [
                'uuid' => $leg->departure->line->uuid,
                'code' => $leg->departure->line->code,
                'name' => $leg->departure->line->name,
                'isInternational' => is_array($leg->departure->line->countries_crossed)
                    && count($leg->departure->line->countries_crossed) > 1,
            ],
            'bus' => [
                'uuid' => $leg->departure->bus->uuid,
                'registrationNumber' => $leg->departure->bus->registration_number,
            ],
            'originStop' => $this->serializeStop($leg->originStop),
            'destinationStop' => $this->serializeStop($leg->destinationStop),
            'amount' => $leg->amount,
            'departureAt' => $leg->departureAt->toISOString(),
            'arrivalAt' => $leg->arrivalAt->toISOString(),
        ];
    }

    /**
     * @param  array{departure_uuid: string|null, origin_stop_uuid: string|null, destination_stop_uuid: string|null, quote_uuid: string|null, reservation_uuid: string|null}  $context
     * @param  array{date: string|null, from: string|null, to: string|null}  $filters
     */
    private function selectedDeparture(array $context, array $filters): ?DepartureAvailabilityData
    {
        if ($context['departure_uuid'] === null) {
            return null;
        }

        $departure = Departure::query()->where('uuid', $context['departure_uuid'])->firstOrFail();

        return $this->departures->show($departure, [
            'from' => $filters['from'],
            'to' => $filters['to'],
        ]);
    }

    /**
     * @param  array{departure_uuid: string|null, origin_stop_uuid: string|null, destination_stop_uuid: string|null, quote_uuid: string|null, reservation_uuid: string|null}  $context
     * @return Collection<int, CounterSaleSeatAvailabilityData>
     */
    private function selectedSeats(?DepartureAvailabilityData $selectedDeparture, array $context): Collection
    {
        if ($selectedDeparture === null || $context['origin_stop_uuid'] === null || $context['destination_stop_uuid'] === null) {
            return collect();
        }

        $segment = $this->departures->resolveSegment(
            $selectedDeparture->departure,
            $context['origin_stop_uuid'],
            $context['destination_stop_uuid'],
        );

        return $this->counterSaleSeatAvailability->handle(
            $selectedDeparture->departure,
            $segment['origin'],
            $segment['destination'],
        );
    }

    /**
     * @param  array{departure_uuid: string|null, origin_stop_uuid: string|null, destination_stop_uuid: string|null, quote_uuid: string|null, reservation_uuid: string|null}  $context
     */
    private function selectedQuote(array $context, ?DepartureAvailabilityData $selectedDeparture): ?PriceQuote
    {
        if ($context['quote_uuid'] === null || $selectedDeparture === null) {
            return null;
        }

        $quote = PriceQuote::query()->where('uuid', $context['quote_uuid'])->firstOrFail();

        abort_unless(
            $quote->departure_id === $selectedDeparture->departure->id
                && $quote->origin_stop_id === $selectedDeparture->originStop->id
                && $quote->destination_stop_id === $selectedDeparture->destinationStop->id,
            404,
        );

        return $quote;
    }

    /** @param array{itinerary_quote_uuid?: string|null} $context */
    private function selectedItineraryQuote(array $context): ?ItineraryQuote
    {
        if (($context['itinerary_quote_uuid'] ?? null) === null) {
            return null;
        }

        return ItineraryQuote::query()
            ->where('uuid', $context['itinerary_quote_uuid'])
            ->where('user_id', auth()->id())
            ->with('legs')
            ->firstOrFail();
    }

    private function selectedJourneyQuote(CounterSaleSearchRequest $request): ?JourneyQuote
    {
        $uuid = $request->string('journey_quote_uuid')->trim()->value() ?: null;
        if ($uuid === null) {
            return null;
        }

        return JourneyQuote::query()
            ->where('uuid', $uuid)
            ->where('user_id', auth()->id())
            ->with('legs')
            ->firstOrFail();
    }

    /** @return array<string, mixed>|null */
    private function serializeJourneyQuote(?JourneyQuote $quote): ?array
    {
        if ($quote === null) {
            return null;
        }

        return [
            'uuid' => $quote->uuid,
            'tripType' => $quote->trip_type,
            'passengerCount' => (int) $quote->passenger_count,
            'subtotalAmount' => (int) $quote->subtotal_amount,
            'dynamicAdjustmentAmount' => (int) $quote->dynamic_adjustment_amount,
            'groupDiscountAmount' => (int) $quote->group_discount_amount,
            'totalAmount' => (int) $quote->total_amount,
            'currency' => $quote->currency,
            'groupDiscount' => $quote->snapshot['group_discount'] ?? null,
            'passengerAllocations' => $quote->snapshot['passenger_allocations'] ?? [],
            'legs' => $quote->snapshot['legs'] ?? [],
            'expiresAt' => $quote->expires_at->toISOString(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function serializeItineraryQuote(?ItineraryQuote $quote): ?array
    {
        if ($quote === null) {
            return null;
        }

        $snapshot = $quote->snapshot ?? [];

        return [
            'uuid' => $quote->uuid,
            'passengerCount' => (int) $quote->passenger_count,
            'subtotalAmount' => (int) $quote->subtotal_amount,
            'dynamicAdjustmentAmount' => (int) $quote->dynamic_adjustment_amount,
            'groupDiscountAmount' => (int) $quote->group_discount_amount,
            'totalAmount' => (int) $quote->total_amount,
            'currency' => $quote->currency,
            'groupDiscount' => $snapshot['group_discount'] ?? null,
            'passengerAllocations' => $snapshot['passenger_allocations'] ?? [],
            'legs' => $snapshot['legs'] ?? [],
            'expiresAt' => $quote->expires_at->toISOString(),
        ];
    }

    /**
     * @param  array{departure_uuid: string|null, origin_stop_uuid: string|null, destination_stop_uuid: string|null, quote_uuid: string|null, reservation_uuid: string|null}  $context
     */
    private function selectedReservation(array $context): ?Reservation
    {
        if ($context['reservation_uuid'] === null) {
            return null;
        }

        return Reservation::query()
            ->where('uuid', $context['reservation_uuid'])
            ->where('created_by_user_id', auth()->id())
            ->with([
                'segments.departure.line',
                'segments.seat',
                'segments.originStop',
                'segments.destinationStop',
                'passengers.segments.seat',
                'payments',
                'payments.agencyPaymentAccount',
                'payments.verifier.agency',
                'companyAccount',
            ])
            ->firstOrFail();
    }

    private function latestActiveCounterSale(): ?Reservation
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        return Reservation::query()
            ->where('created_by_user_id', $user->getKey())
            ->where('channel', ReservationChannel::Counter->value)
            ->where('status', ReservationStatus::Held->value)
            ->where('expires_at', '>', now())
            ->with([
                'segments.departure',
                'segments.originStop',
                'segments.destinationStop',
            ])
            ->latest('updated_at')
            ->latest('id')
            ->first();
    }

    /** @return array<string, string> */
    private function saleQueryFromReservation(Reservation $reservation): array
    {
        $segment = $reservation->segments->first();

        return array_filter([
            'date' => $segment?->departure?->departure_date?->toDateString(),
            'from' => $segment?->originStop?->city,
            'to' => $segment?->destinationStop?->city,
            'departure_uuid' => $segment?->departure?->uuid,
            'origin_stop_uuid' => $segment?->originStop?->uuid,
            'destination_stop_uuid' => $segment?->destinationStop?->uuid,
            'reservation_uuid' => $reservation->uuid,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  Collection<int, array{city: string, country: string, stops_count: int}>  $cities
     * @return list<array{city: string, country: string, stopsCount: int}>
     */
    private function serializeCities(Collection $cities): array
    {
        return array_values($cities->map(fn (array $city): array => [
            'city' => $city['city'],
            'country' => $city['country'],
            'stopsCount' => $city['stops_count'],
        ])->all());
    }

    /**
     * @param  Collection<int, DepartureAvailabilityData>  $departures
     * @return list<array<string, mixed>>
     */
    private function serializeDepartures(Collection $departures): array
    {
        return array_values($departures->map(fn (DepartureAvailabilityData $departure): array => $this->serializeDeparture($departure))->all());
    }

    /** @return array<string, mixed> */
    private function serializeDeparture(DepartureAvailabilityData $data): array
    {
        return [
            'uuid' => $data->departure->uuid,
            'departureDate' => $data->departure->departure_date?->toDateString(),
            'departureTime' => $data->departure->departure_time,
            'estimatedArrivalAt' => $data->departure->estimated_arrival_at?->toISOString(),
            'status' => (string) $data->departure->getRawOriginal('status'),
            'line' => [
                'uuid' => $data->departure->line->uuid,
                'code' => $data->departure->line->code,
                'name' => $data->departure->line->name,
                'estimatedDurationMinutes' => $data->departure->line->estimated_duration_minutes,
                'isInternational' => is_array($data->departure->line->countries_crossed)
                    && count($data->departure->line->countries_crossed) > 1,
            ],
            'bus' => [
                'uuid' => $data->departure->bus->uuid,
                'registrationNumber' => $data->departure->bus->registration_number,
                'brand' => $data->departure->bus->brand,
                'model' => $data->departure->bus->model,
                'seatCount' => $data->departure->bus->seat_count,
            ],
            'originStop' => $this->serializeStop($data->originStop),
            'destinationStop' => $this->serializeStop($data->destinationStop),
            'availableSeatsCount' => $data->availableSeatsCount,
            'farePreview' => $data->farePreview,
            'sellability' => [
                'isSellable' => $data->sellability->isSellable,
                'reason' => $data->sellability->reason->value,
                'saleClosesAt' => $data->sellability->saleClosesAt->toISOString(),
            ],
        ];
    }

    /** @return array{uuid: string, name: string, city: string, country: string, stopOrder: int} */
    private function serializeStop(Stop $stop): array
    {
        return [
            'uuid' => $stop->uuid,
            'name' => $stop->name,
            'city' => $stop->city,
            'country' => $stop->country,
            'stopOrder' => $stop->stop_order,
        ];
    }

    /**
     * @param  Collection<int, CounterSaleSeatAvailabilityData>  $seats
     * @return list<array<string, mixed>>
     */
    private function serializeSeats(Collection $seats): array
    {
        return array_values($seats->map(fn (CounterSaleSeatAvailabilityData $seat): array => [
            'uuid' => $seat->seat->uuid,
            'code' => $seat->seat->code,
            'type' => (string) $seat->seat->getRawOriginal('type'),
            'sellable' => $seat->seat->sellable,
            'availabilityStatus' => $seat->availabilityStatus->value,
            'expiresAt' => $seat->expiresAt?->toISOString(),
        ])->all());
    }

    /** @return array<string, mixed>|null */
    private function serializeQuote(?PriceQuote $quote): ?array
    {
        if ($quote === null) {
            return null;
        }

        $snapshot = json_decode((string) $quote->getRawOriginal('snapshot'), true);
        $snapshot = is_array($snapshot) ? $snapshot : [];
        $expiresAt = $quote->getRawOriginal('expires_at');
        $passengerCount = (int) ($snapshot['passenger_count'] ?? 1);
        $groupDiscount = is_array($snapshot['group_discount'] ?? null) ? $snapshot['group_discount'] : [];
        $passengerAllocations = is_array($snapshot['passenger_allocations'] ?? null) ? $snapshot['passenger_allocations'] : [];
        $breakdown = is_array($snapshot['pricing_breakdown'] ?? null) ? $snapshot['pricing_breakdown'] : [];

        return [
            'uuid' => $quote->uuid,
            'baseAmount' => (int) ($breakdown['base_fare_amount'] ?? ((int) $quote->base_amount * $passengerCount)),
            'coefficient' => (float) $quote->coefficient,
            'finalAmount' => (int) $quote->final_amount,
            'currency' => $quote->currency,
            'passengerCount' => $passengerCount,
            'subtotalAmount' => (int) ($snapshot['subtotal_amount'] ?? ((int) $quote->final_amount * $passengerCount)),
            'adjustedSubtotalAmount' => (int) ($breakdown['adjusted_subtotal_amount'] ?? ($snapshot['subtotal_amount'] ?? 0)),
            'dynamicAdjustmentAmount' => (int) ($snapshot['dynamic_adjustment_amount'] ?? 0),
            'groupDiscount' => [
                'applied' => (bool) ($groupDiscount['applied'] ?? false),
                'threshold' => $groupDiscount['threshold'] ?? null,
                'percentage' => (float) ($groupDiscount['percentage'] ?? 0),
                'amount' => (int) ($groupDiscount['amount'] ?? 0),
            ],
            'isGroup' => (bool) ($snapshot['group'] ?? false),
            'promotions' => $breakdown['promotions'] ?? [],
            'promotionDiscountAmount' => (int) ($breakdown['promotion_discount_amount'] ?? 0),
            'passengerAllocations' => $passengerAllocations,
            'totalAmount' => (int) ($snapshot['total_amount'] ?? ((int) $quote->final_amount * $passengerCount)),
            'expiresAt' => $expiresAt ? CarbonImmutable::parse((string) $expiresAt)->toISOString() : null,
        ];
    }

    /** @return array<string, mixed>|null */
    private function serializeReservation(?Reservation $reservation): ?array
    {
        if ($reservation === null) {
            return null;
        }

        $expiresAt = $reservation->getRawOriginal('expires_at');

        $payment = $reservation->payments
            ->sortByDesc('id')
            ->first();
        $agent = $payment?->verifier;
        $agency = $agent?->agency ?? auth()->user()?->agency;
        $confirmedAt = $payment?->confirmed_at ?? $payment?->verified_at;
        $reservationCode = Str::upper(Str::substr(str_replace('-', '', $reservation->uuid), 0, 8));
        $fiscalInvoice = FiscalInvoice::query()
            ->where('reservation_id', $reservation->getKey())
            ->first();
        $tickets = Ticket::query()
            ->where('reservation_id', $reservation->getKey())
            ->with([
                'reservationPassenger.segments.seat',
                'reservationPassenger.segments.originStop',
                'reservationPassenger.segments.destinationStop',
            ])
            ->orderBy('id')
            ->get();

        $draftPassengers = is_array($reservation->draft_data['passengers'] ?? null)
            ? $reservation->draft_data['passengers']
            : [];

        return [
            'uuid' => $reservation->uuid,
            'status' => (string) $reservation->getRawOriginal('status'),
            'expiresAt' => $expiresAt ? CarbonImmutable::parse((string) $expiresAt)->toISOString() : null,
            'totalAmount' => (int) $reservation->total_amount,
            'currency' => $reservation->currency,
            'channel' => $reservation->getRawOriginal('channel'),
            'purchaseType' => $reservation->getRawOriginal('purchase_type'),
            'groupSize' => $reservation->group_size,
            'payerType' => $reservation->getRawOriginal('payer_type'),
            'payerSnapshot' => $reservation->payer_snapshot,
            'ticketIssuance' => [
                'status' => $reservation->ticket_issuance_status->value,
                'error' => $reservation->ticket_issuance_error,
                'startedAt' => $reservation->ticket_issuance_started_at?->toISOString(),
                'issuedAt' => $reservation->ticket_issued_at?->toISOString(),
            ],
            'companyAccount' => $reservation->companyAccount ? [
                'uuid' => $reservation->companyAccount->uuid,
                'name' => $reservation->companyAccount->name,
                'type' => $reservation->companyAccount->getRawOriginal('type'),
                'reference' => $reservation->companyAccount->reference,
            ] : null,
            'passengerAllocations' => is_array($reservation->total_snapshot['passenger_allocations'] ?? null)
                ? $reservation->total_snapshot['passenger_allocations']
                : [],
            'departureUuid' => $reservation->segments->first()?->departure?->uuid,
            'payment' => $payment ? [
                'uuid' => $payment->uuid,
                'method' => $payment->method?->value,
                'network' => $payment->getRawOriginal('mobile_money_network'),
                'providerReference' => $payment->provider_reference,
                'payerPhone' => $payment->payer_phone,
                'payerName' => $payment->payer_name,
                'amount' => (int) $payment->amount,
                'cashReceivedAmount' => $payment->cash_received_amount,
                'changeAmount' => $payment->change_amount,
                'agencyAccount' => $payment->agencyPaymentAccount ? [
                    'uuid' => $payment->agencyPaymentAccount->uuid,
                    'label' => $payment->agencyPaymentAccount->label,
                    'accountIdentifier' => $this->maskAccountIdentifier($payment->agencyPaymentAccount->account_identifier),
                ] : null,
                'verifiedAt' => $payment->verified_at?->toISOString(),
                'confirmedAt' => $confirmedAt?->toISOString(),
                'verifiedByName' => $agent?->name,
                'verifiedByUuid' => $agent?->uuid,
                'agencyName' => $agency?->name,
                'agencyCode' => $agency?->code,
            ] : null,
            'qrPayload' => $payment ? [
                'version' => 1,
                'type' => 'reservation_confirmation',
                'reservationCode' => $reservationCode,
                'reservationUuid' => $reservation->uuid,
                'agencyCode' => $agency?->code,
                'agencyName' => $agency?->name,
                'confirmedAt' => $confirmedAt?->toISOString(),
                'agentUuid' => $agent?->uuid,
            ] : null,
            'fiscalization' => $fiscalInvoice ? [
                'status' => $fiscalInvoice->getRawOriginal('status'),
                'koraReference' => $fiscalInvoice->kora_reference,
                'mecefUid' => $fiscalInvoice->mecef_uid,
            ] : null,
            'refunds' => PaymentRefund::query()->where('reservation_id', $reservation->getKey())->orderBy('id')->get()->map(fn (PaymentRefund $refund): array => [
                'uuid' => $refund->uuid,
                'amount' => (int) $refund->amount,
                'currency' => $refund->currency,
                'destination' => $refund->getRawOriginal('destination'),
                'status' => $refund->getRawOriginal('status'),
                'reference' => $refund->reference,
            ])->values()->all(),
            'tickets' => $tickets->map(fn (Ticket $ticket): array => [
                'uuid' => $ticket->uuid,
                'shortCode' => $ticket->short_code,
                'ticketNumber' => $ticket->uuid,
                'qrCode' => $ticket->qr_code,
                'seatCode' => $ticket->reservationPassenger?->segments->first()?->seat?->code,
                'passengerUuid' => $ticket->reservationPassenger?->uuid,
                'passengerIndex' => $ticket->reservationPassenger?->passenger_index,
                'passengerName' => trim($ticket->reservationPassenger->first_name.' '.$ticket->reservationPassenger->last_name),
                'allocationAmount' => (int) (($reservation->total_snapshot['passenger_allocations'] ?? [])[(int) $ticket->reservationPassenger->passenger_index] ?? 0),
                'transactionReference' => $fiscalInvoice !== null ? $fiscalInvoice->kora_reference : $reservation->uuid,
                'status' => $ticket->status->value,
                'legs' => $ticket->reservationPassenger->segments->sortBy('leg_order')->map(fn ($segment): array => [
                    'direction' => $segment->direction,
                    'legOrder' => $segment->leg_order,
                    'originStop' => $segment->originStop?->name,
                    'destinationStop' => $segment->destinationStop?->name,
                    'seatCode' => $segment->seat?->code,
                ])->values()->all(),
            ])->values()->all(),
            'segments' => $reservation->segments->map(fn ($segment): array => [
                'uuid' => $segment->uuid,
                'direction' => $segment->direction,
                'legOrder' => $segment->leg_order,
                'passengerIndex' => $segment->passenger_index,
                'seatUuid' => $segment->seat?->uuid,
                'seatCode' => $segment->seat?->code,
                'originStop' => $segment->originStop?->name,
                'destinationStop' => $segment->destinationStop?->name,
                'status' => (string) $segment->getRawOriginal('status'),
            ])->values()->all(),
            'passengers' => $reservation->passengers->map(fn (ReservationPassenger $passenger): array => [
                'uuid' => $passenger->uuid,
                'firstName' => $passenger->first_name,
                'lastName' => $passenger->last_name,
                'phone' => $passenger->phone,
                'email' => $passenger->email,
                'documentType' => $passenger->document_type?->value,
                'documentNumber' => $passenger->document_number,
                'nationality' => $passenger->nationality,
                'birthDate' => $passenger->birth_date?->toDateString(),
                'passengerIndex' => $passenger->passenger_index,
                'yellowFeverVaccinated' => $passenger->yellow_fever_vaccinated,
                'seatUuid' => $passenger->segments->first()?->seat?->uuid,
                'seatCode' => $passenger->segments->first()?->seat?->code,
            ])->values()->all(),
            'draftPassengers' => $reservation->segments
                ->groupBy(fn ($segment): int => (int) ($segment->passenger_index ?? $segment->getKey()))
                ->map(function ($segments, int $passengerIndex) use ($draftPassengers): array {
                    $firstSegment = $segments->sortBy('leg_order')->first();
                    $firstSeatUuid = $firstSegment->seat?->uuid;
                    $draft = is_array($draftPassengers[(string) $passengerIndex] ?? null)
                        ? $draftPassengers[(string) $passengerIndex]
                    : (is_array($draftPassengers[$firstSeatUuid ?? ''] ?? null)
                            ? $draftPassengers[$firstSeatUuid ?? '']
                            : []);

                    return [
                        'passengerIndex' => $passengerIndex,
                        'firstName' => $draft['first_name'] ?? '',
                        'lastName' => $draft['last_name'] ?? '',
                        'phone' => $draft['phone'] ?? '',
                        'email' => $draft['email'] ?? '',
                        'documentType' => $draft['document_type'] ?? 'national_id',
                        'documentNumber' => $draft['document_number'] ?? '',
                        'nationality' => $draft['nationality'] ?? '',
                        'birthDate' => $draft['birth_date'] ?? '',
                        'yellowFeverVaccinated' => $draft['yellow_fever_vaccinated'] ?? null,
                        'seatUuid' => $firstSegment?->seat?->uuid,
                        'seatCode' => $segments->pluck('seat.code')->filter()->implode(' · '),
                    ];
                })->values()->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function serializeCompanyAccounts(): array
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $user->can('createCounterSale', Reservation::class)) {
            return [];
        }

        return $this->companyAccounts->activeAccounts();
    }

    /** @return array{minimumPassengers: int, maximumPassengers: int} */
    private function serializeGroupBookingSettings(): array
    {
        $settings = GroupBookingSetting::current();

        return [
            'minimumPassengers' => (int) $settings->minimum_passengers,
            'maximumPassengers' => (int) $settings->maximum_passengers,
        ];
    }

    /** @return array{isOpen: bool, canOpen: bool, uuid: string|null, businessDate: string|null, totalCash: int, totalMobileMoney: int} */
    private function serializeCashSession(): array
    {
        $user = auth()->user();
        $session = $user?->agency_id === null ? null : CashSession::query()
            ->where('agent_id', $user->getAuthIdentifier())
            ->where('agency_id', $user->agency_id)
            ->whereDate('business_date', now()->toDateString())
            ->where('status', CashSessionStatus::Open->value)
            ->with('ledgers.agencyPaymentAccount')
            ->first();

        return [
            'isOpen' => $session !== null,
            'canOpen' => Gate::allows('create', CashSession::class),
            'uuid' => $session?->uuid,
            'businessDate' => $session === null ? null : $session->business_date->toDateString(),
            'status' => $session?->status->value,
            'totalCash' => $session === null ? 0 : (int) $session->total_cash,
            'totalMobileMoney' => $session === null ? 0 : (int) $session->total_mobile_money,
            'ledgers' => $session === null ? [] : $session->ledgers->map(fn ($ledger): array => [
                'uuid' => $ledger->uuid,
                'kind' => $ledger->kind->value,
                'status' => $ledger->status->value,
                'openingBalance' => (int) $ledger->opening_balance,
                'expectedBalance' => (int) $ledger->expected_balance,
                'actualBalance' => $ledger->actual_balance,
                'variance' => $ledger->variance,
                'accountUuid' => $ledger->agencyPaymentAccount?->uuid,
                'accountLabel' => $ledger->agencyPaymentAccount?->label,
                'accountIdentifier' => $ledger->agencyPaymentAccount ? $this->maskAccountIdentifier($ledger->agencyPaymentAccount->account_identifier) : null,
            ])->values()->all(),
        ];
    }

    /** @return list<array{uuid: string, network: string, label: string, accountIdentifier: string}> */
    private function serializeMobileMoneyAccounts(): array
    {
        $user = auth()->user();
        if ($user?->agency_id === null) {
            return [];
        }

        $accounts = AgencyPaymentAccount::query()
            ->where('agency_id', $user->agency_id)
            ->where('status', 'active')
            ->orderBy('network')
            ->orderBy('label')
            ->get()
            ->map(fn (AgencyPaymentAccount $account): array => [
                'uuid' => $account->uuid,
                'network' => $account->network->value,
                'label' => $account->label,
                'accountIdentifier' => $this->maskAccountIdentifier($account->account_identifier),
                'ledgerUuid' => $this->openLedgerUuid($account->uuid),
            ])->values()->all();

        return array_values($accounts);
    }

    private function openLedgerUuid(string $accountUuid): ?string
    {
        $user = auth()->user();
        if ($user?->agency_id === null) {
            return null;
        }

        $session = CashSession::query()
            ->where('agent_id', $user->getAuthIdentifier())
            ->where('agency_id', $user->agency_id)
            ->whereDate('business_date', now()->toDateString())
            ->where('status', CashSessionStatus::Open->value)
            ->with('ledgers')
            ->first();

        return $session?->ledgers
            ->first(fn ($ledger): bool => $ledger->kind === CashLedgerKind::MobileMoney && $ledger->ledger_key === $accountUuid)
            ?->uuid;
    }

    private function maskAccountIdentifier(string $identifier): string
    {
        $length = mb_strlen($identifier);
        if ($length <= 4) {
            return $identifier;
        }

        return str_repeat('•', max(0, $length - 4)).mb_substr($identifier, -4);
    }
}
