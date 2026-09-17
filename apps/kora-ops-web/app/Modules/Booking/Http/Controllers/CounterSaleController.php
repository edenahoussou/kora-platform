<?php

namespace App\Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Booking\Actions\CalculateItineraryQuoteAction;
use App\Modules\Booking\Actions\CreateItineraryHoldAction;
use App\Modules\Booking\Actions\ItineraryLegQuoteRequest;
use App\Modules\Booking\Actions\ReleaseSeatHoldAction;
use App\Modules\Booking\Actions\ResolvePayerContextAction;
use App\Modules\Booking\Actions\SaveCounterSaleDraftAction;
use App\Modules\Booking\Actions\UpdateReservationPassengersAction;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Exceptions\SeatUnavailableException;
use App\Modules\Booking\Http\Requests\ConfirmCounterSaleRequest;
use App\Modules\Booking\Http\Requests\CounterSaleSearchRequest;
use App\Modules\Booking\Http\Requests\FetchCounterSaleSeatsRequest;
use App\Modules\Booking\Http\Requests\HoldCounterSaleSeatsRequest;
use App\Modules\Booking\Http\Requests\ReleaseCounterSaleHoldRequest;
use App\Modules\Booking\Http\Requests\StoreCounterSaleDraftRequest;
use App\Modules\Booking\Http\Requests\StoreCounterSalePassengersRequest;
use App\Modules\Booking\Http\Requests\StoreCounterSalePaymentRequest;
use App\Modules\Booking\Http\Requests\StoreCounterSaleQuoteRequest;
use App\Modules\Booking\Models\GroupBookingSetting;
use App\Modules\Booking\Models\ItineraryQuote;
use App\Modules\Booking\Models\ItineraryQuoteLeg;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\Booking\Services\DepartureStopTimeResolver;
use App\Modules\Booking\Services\ItineraryData;
use App\Modules\Booking\Services\ItineraryLegData;
use App\Modules\Booking\Services\ItinerarySearchService;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\BusNetwork\Services\BusCatalogService;
use App\Modules\Cash\Data\CashLedgerKind;
use App\Modules\Cash\Data\CashSessionStatus;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Models\CashSession;
use App\Modules\CompanyAccounts\Services\CompanyAccountCatalogService;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\ServiceClass;
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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CounterSaleController extends Controller
{
    /**
     * Per-request memoization cache for expensive queries that would
     * otherwise be repeated across multiple serialize* methods.
     *
     * @var array<string, mixed>
     */
    private array $requestCache = [];

    /**
     * @phpstan-type SaleContext array{itinerary_quote_uuid: string|null, reservation_uuid: string|null}
     */
    public function __construct(
        private BusCatalogService $catalog,
        private BuildCounterSaleSeatAvailabilityAction $counterSaleSeatAvailability,
        private CompanyAccountCatalogService $companyAccounts,
        private ItinerarySearchService $itinerarySearch,
        private DepartureStopTimeResolver $stopTimeResolver,
    ) {}

    public function index(CounterSaleSearchRequest $request): Response|RedirectResponse
    {
        Gate::authorize('viewAny', Departure::class);

        if (! $request->hasExplicitSaleContext()) {
            $activeReservation = $this->latestActiveCounterSale();

            if ($activeReservation !== null) {
                return to_route('sale', $this->saleQueryFromReservation($activeReservation));
            }
        }

        $filters = $request->departureFilters();
        $context = $request->selectedContext();
        $tripType = $request->string('trip_type')->value() ?: 'one_way';
        $returnDate = $tripType === 'round_trip' ? ($request->string('return_date')->value() ?: null) : null;

        $results = $this->itinerarySearch->searchForCounterSale([
            'date' => $filters['date'],
            'from' => $filters['from'],
            'to' => $filters['to'],
            'trip_type' => $tripType,
            'return_date' => $returnDate,
            'service_class' => $filters['service_class'],
        ]);

        $itineraryQuote = $this->selectedItineraryQuote($context);

        if ($itineraryQuote === null && ($context['reservation_uuid'] ?? null) !== null) {
            $reservation = $this->selectedReservation($context);

            if ($reservation !== null && $reservation->itinerary_quote_id !== null) {
                $itineraryQuote = ItineraryQuote::query()
                    ->where('id', $reservation->itinerary_quote_id)
                    ->with('legs')
                    ->first();
            }
        }

        $pendingLegs = session()->pull('counter_sale_pending_legs');
        $itineraryLegSeats = $pendingLegs !== null
            ? $this->buildSeatAvailabilityFromLegs($pendingLegs)
            : $this->serializeItineraryLegSeats($itineraryQuote);

        return Inertia::render('sale', [
            'cities' => $this->serializeCities($this->catalog->cities()),
            'serviceClasses' => $this->serializeServiceClasses(),
            'search' => [
                'date' => $filters['date'],
                'from' => $filters['from'],
                'to' => $filters['to'],
                'tripType' => $tripType,
                'returnDate' => $returnDate,
                'serviceClass' => $filters['service_class'],
                'hasDate' => $filters['date'] !== null,
            ],
            'outboundItineraries' => $this->serializeItineraries($results['outbound']),
            'inboundItineraries' => $this->serializeItineraries($results['inbound'], 'inbound'),
            'itineraryQuote' => $this->serializeItineraryQuote($itineraryQuote),
            'itineraryLegSeats' => $itineraryLegSeats,
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

    public function quote(StoreCounterSaleQuoteRequest $request, CalculateItineraryQuoteAction $calculateItineraryQuote): RedirectResponse
    {
        $data = $request->quoteData();

        $buildLegs = function (array $legs, string $direction): array {
            return array_map(
                fn (array $leg): ItineraryLegQuoteRequest => new ItineraryLegQuoteRequest(
                    departure: Departure::query()->where('uuid', $leg['departure_uuid'])->firstOrFail(),
                    originStop: Stop::query()->where('uuid', $leg['origin_stop_uuid'])->firstOrFail(),
                    destinationStop: Stop::query()->where('uuid', $leg['destination_stop_uuid'])->firstOrFail(),
                    direction: $direction,
                ),
                $legs,
            );
        };
        $legs = array_values([
            ...$buildLegs($data['outbound_legs'], 'outbound'),
            ...$buildLegs($data['inbound_legs'], 'inbound'),
        ]);
        $result = $calculateItineraryQuote->handle($legs, $data['passenger_count'], $request->user());

        return to_route('sale', $request->saleQuery(null, $result->itineraryQuote->uuid));
    }

    public function hold(HoldCounterSaleSeatsRequest $request, CreateItineraryHoldAction $createItineraryHold, ResolvePayerContextAction $resolvePayer): RedirectResponse
    {
        $data = $request->holdData();
        $payer = $data['payer'] !== null
            ? $resolvePayer->handle($request->user(), $data['payer'])
            : null;

        try {
            $reservation = $createItineraryHold->handle(
                user: $request->user(),
                itineraryQuote: ItineraryQuote::query()->where('uuid', $data['itinerary_quote_uuid'])->firstOrFail(),
                seatAssignments: $request->itinerarySeatAssignments(),
                channel: ReservationChannel::Counter,
                idempotencyKey: $data['idempotency_key'],
                payer: $payer,
            );
        } catch (SeatUnavailableException $exception) {
            throw ValidationException::withMessages([
                'seats' => $exception->getMessage(),
            ]);
        }

        return to_route('sale', $request->saleQuery($reservation->uuid));
    }

    public function seats(FetchCounterSaleSeatsRequest $request): Response
    {
        Gate::authorize('viewAny', Departure::class);

        $data = $request->legsData();

        session()->put('counter_sale_pending_legs', $data);

        $legSeats = $this->buildSeatAvailabilityFromLegs($data);

        return Inertia::render('sale', [
            'itineraryLegSeats' => $legSeats,
        ]);
    }

    public function confirm(
        ConfirmCounterSaleRequest $request,
        CalculateItineraryQuoteAction $calculateItineraryQuote,
        CreateItineraryHoldAction $createItineraryHold,
        ResolvePayerContextAction $resolvePayer,
    ): RedirectResponse {
        $data = $request->confirmData();

        $legs = $this->buildItineraryLegs($data['outbound_legs'], $data['inbound_legs']);

        $quoteResult = $calculateItineraryQuote->handle(
            $legs,
            $data['passenger_count'],
            $request->user(),
        );

        $payer = $data['payer'] !== null
            ? $resolvePayer->handle($request->user(), $data['payer'])
            : null;

        try {
            $reservation = $createItineraryHold->handle(
                user: $request->user(),
                itineraryQuote: $quoteResult->itineraryQuote,
                seatAssignments: $request->itinerarySeatAssignments(),
                channel: ReservationChannel::Counter,
                idempotencyKey: $data['idempotency_key'],
                payer: $payer,
            );
        } catch (SeatUnavailableException $exception) {
            throw ValidationException::withMessages([
                'seats' => $exception->getMessage(),
            ]);
        }

        session()->forget('counter_sale_pending_legs');

        return to_route('sale', $request->saleQuery($reservation->uuid));
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

        return to_route('sale', $request->saleQuery($updatedReservation->uuid));
    }

    public function storePayment(
        StoreCounterSalePaymentRequest $request,
        Reservation $reservation,
        RecordCounterSalePaymentAction $recordPayment,
    ): \Symfony\Component\HttpFoundation\Response {
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

            return to_route('sale', $request->saleQuery($reservation->uuid))
                ->withInput();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Paiement confirmé. L’émission des billets est en cours.'),
        ]);

        try {
            IssueReservationTicketsJob::dispatchSync($reservation->getKey());
        } catch (\Throwable $exception) {
            report($exception);
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Paiement enregistré, mais l’émission du billet a échoué. Vous pouvez réessayer depuis la liste des ventes.'),
            ]);

            $segment = $result['reservation']->segments->first();

            return to_route('sale', array_filter([
                'date' => $segment?->departure?->departure_date?->toDateString(),
                'from' => $segment?->originStop?->city,
                'to' => $segment?->destinationStop?->city,
                'reservation_uuid' => $reservation->uuid,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''));
        }

        $confirmedReservation = $result['reservation']->load([
            'segments.departure.line',
            'segments.departure.bus',
            'segments.originStop',
            'segments.destinationStop',
            'tickets',
        ]);

        $firstTicket = $confirmedReservation->tickets->first();

        if ($firstTicket !== null) {
            return Inertia::location(route('tickets.show', [
                'ticket' => $firstTicket->uuid,
                'download' => 1,
            ]));
        }

        $segment = $confirmedReservation->segments->first();

        return to_route('sale', array_filter([
            'date' => $segment?->departure?->departure_date?->toDateString(),
            'from' => $segment?->originStop?->city,
            'to' => $segment?->destinationStop?->city,
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

        return to_route('sale', $request->saleQuery());
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

    /**
     * @param  list<ItineraryData>  $itineraries
     * @return list<array<string, mixed>>
     */
    private function serializeItineraries(array $itineraries, string $direction = 'outbound'): array
    {
        return array_map(fn (ItineraryData $itinerary): array => $this->serializeItinerary($itinerary, $direction), $itineraries);
    }

    /** @return array<string, mixed> */
    private function serializeItinerary(ItineraryData $itinerary, string $direction = 'outbound'): array
    {
        return [
            'direction' => $direction,
            'isDirect' => $itinerary->isDirect(),
            'connectionsCount' => $itinerary->connectionsCount,
            'totalAmount' => $itinerary->totalAmount,
            'currency' => $itinerary->currency,
            'departureAt' => $itinerary->departureAt->toISOString(),
            'arrivalAt' => $itinerary->arrivalAt->toISOString(),
            'connectionMarginMinutes' => $itinerary->connectionMarginMinutes,
            'legs' => array_map(fn (ItineraryLegData $leg): array => $this->serializeItineraryLeg($leg, $direction), $itinerary->legs),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeItineraryLeg(ItineraryLegData $leg, string $direction = 'outbound'): array
    {
        return [
            'legOrder' => $leg->legOrder,
            'direction' => $direction,
            'departureUuid' => $leg->departure->uuid,
            'departureDate' => $leg->departure->departure_date?->toDateString(),
            'departureTime' => $leg->departure->departure_time,
            'line' => [
                'uuid' => $leg->departure->line->uuid,
                'code' => $leg->departure->line->code,
                'name' => $leg->departure->line->name,
                'estimatedDurationMinutes' => $leg->estimatedDurationMinutes,
                'isInternational' => is_array($leg->departure->line->countries_crossed)
                    && count($leg->departure->line->countries_crossed) > 1,
            ],
            'bus' => [
                'uuid' => $leg->bus->uuid ?? $leg->departure->bus->uuid,
                'registrationNumber' => $leg->bus->registration_number ?? $leg->departure->bus->registration_number,
                'brand' => $leg->bus->brand ?? $leg->departure->bus->brand,
                'model' => $leg->bus->model ?? $leg->departure->bus->model,
                'seatCount' => $leg->bus->seat_count ?? $leg->departure->bus->seat_count,
                'amenities' => $leg->bus->amenities->map(fn ($amenity) => [
                    'code' => $amenity->code,
                    'label' => $amenity->label,
                    'icon' => $amenity->icon,
                ])->values()->all(),
            ],
            'originStop' => $this->serializeStop($leg->originStop),
            'destinationStop' => $this->serializeStop($leg->destinationStop),
            'amount' => $leg->amount,
            'baseAmount' => $leg->baseAmount,
            'coefficient' => $leg->coefficient,
            'finalAmount' => $leg->finalAmount,
            'dynamicAdjustmentAmount' => $leg->dynamicAdjustmentAmount,
            'pricingRuleUuid' => $leg->pricingRuleUuid,
            'fillRate' => $leg->fillRate,
            'hoursBeforeDeparture' => $leg->hoursBeforeDeparture,
            'availableSeatsCount' => $leg->availableSeatsCount,
            'serviceClassCode' => $leg->serviceClassCode,
            'serviceClassName' => $leg->serviceClassName,
            'departureAt' => $leg->departureAt->toISOString(),
            'arrivalAt' => $leg->arrivalAt->toISOString(),
        ];
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

    /**
     * @return list<array{legOrder: int, direction: string, departureUuid: string, seats: list<array<string, mixed>>}>
     */
    private function serializeItineraryLegSeats(?ItineraryQuote $quote): array
    {
        if ($quote === null) {
            return [];
        }

        $quote->loadMissing(['legs.departure', 'legs.originStop', 'legs.destinationStop']);

        $legs = $quote->legs->sortBy('leg_order')->values();

        return array_values($legs->map(fn (ItineraryQuoteLeg $leg): array => [
            'legOrder' => $leg->leg_order,
            'direction' => $leg->direction,
            'departureUuid' => $leg->departure->uuid,
            'seats' => $this->serializeSeats($this->counterSaleSeatAvailability->handle($leg->departure, $leg->originStop, $leg->destinationStop)),
        ])->all());
    }

    /**
     * @param  array{outbound_legs: list<array{departure_uuid: string, origin_stop_uuid: string, destination_stop_uuid: string}>, inbound_legs: list<array{departure_uuid: string, origin_stop_uuid: string, destination_stop_uuid: string}>}  $legsData
     * @return list<array{legOrder: int, direction: string, departureUuid: string, seats: list<array<string, mixed>>}>
     */
    private function buildSeatAvailabilityFromLegs(array $legsData): array
    {
        $allLegs = [
            ...array_map(fn (array $leg): array => [...$leg, 'direction' => 'outbound'], $legsData['outbound_legs']),
            ...array_map(fn (array $leg): array => [...$leg, 'direction' => 'inbound'], $legsData['inbound_legs']),
        ];

        if ($allLegs === []) {
            return [];
        }

        $departures = $this->batchFetchDepartures(array_column($allLegs, 'departure_uuid'));
        $stops = $this->batchFetchStops(array_values(array_unique(array_merge(
            array_column($allLegs, 'origin_stop_uuid'),
            array_column($allLegs, 'destination_stop_uuid'),
        ))));

        $result = [];
        $legOrder = 0;

        foreach ($allLegs as $leg) {
            $legOrder++;
            $departure = $departures->get($leg['departure_uuid']);
            $originStop = $stops->get($leg['origin_stop_uuid']);
            $destinationStop = $stops->get($leg['destination_stop_uuid']);

            if ($departure === null || $originStop === null || $destinationStop === null) {
                continue;
            }

            $result[] = [
                'legOrder' => $legOrder,
                'direction' => $leg['direction'],
                'departureUuid' => $departure->uuid,
                'seats' => $this->serializeSeats(
                    $this->counterSaleSeatAvailability->handle($departure, $originStop, $destinationStop),
                ),
            ];
        }

        return $result;
    }

    /**
     * @param  list<array{departure_uuid: string, origin_stop_uuid: string, destination_stop_uuid: string}>  $outboundLegs
     * @param  list<array{departure_uuid: string, origin_stop_uuid: string, destination_stop_uuid: string}>  $inboundLegs
     * @return list<ItineraryLegQuoteRequest>
     */
    private function buildItineraryLegs(array $outboundLegs, array $inboundLegs): array
    {
        $allLegs = [
            ...array_map(fn (array $leg): array => [...$leg, 'direction' => 'outbound'], $outboundLegs),
            ...array_map(fn (array $leg): array => [...$leg, 'direction' => 'inbound'], $inboundLegs),
        ];

        $departures = $this->batchFetchDepartures(array_column($allLegs, 'departure_uuid'));
        $stops = $this->batchFetchStops(array_values(array_unique(array_merge(
            array_column($allLegs, 'origin_stop_uuid'),
            array_column($allLegs, 'destination_stop_uuid'),
        ))));

        $legs = [];
        foreach ($allLegs as $leg) {
            $departure = $departures->get($leg['departure_uuid']);
            $originStop = $stops->get($leg['origin_stop_uuid']);
            $destinationStop = $stops->get($leg['destination_stop_uuid']);

            if ($departure === null || $originStop === null || $destinationStop === null) {
                throw ValidationException::withMessages([
                    'legs' => 'Un des départs ou arrêts sélectionnés est introuvable.',
                ]);
            }

            $legs[] = new ItineraryLegQuoteRequest(
                departure: $departure,
                originStop: $originStop,
                destinationStop: $destinationStop,
                direction: $leg['direction'],
            );
        }

        return $legs;
    }

    /**
     * @param  list<string>  $uuids
     * @return Collection<string, Departure>
     */
    private function batchFetchDepartures(array $uuids): Collection
    {
        return Departure::query()
            ->whereIn('uuid', $uuids)
            ->with(['bus.seats', 'line.stops'])
            ->get()
            ->keyBy('uuid');
    }

    /**
     * @param  list<string>  $uuids
     * @return Collection<string, Stop>
     */
    private function batchFetchStops(array $uuids): Collection
    {
        return Stop::query()
            ->whereIn('uuid', $uuids)
            ->get()
            ->keyBy('uuid');
    }

    /** @return array<string, mixed>|null */
    private function serializeItineraryQuote(?ItineraryQuote $quote): ?array
    {
        if ($quote === null) {
            return null;
        }

        $snapshot = $quote->snapshot ?? [];
        $quote->loadMissing(['legs.departure.line', 'legs.departure.bus.amenities', 'legs.originStop', 'legs.destinationStop', 'legs.priceQuote']);
        $legs = $quote->legs->sortBy('leg_order')->values();

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
            'legs' => array_values($legs->map(fn (ItineraryQuoteLeg $leg): array => $this->serializeItineraryQuoteLeg($leg, $quote->currency))->all()),
            'expiresAt' => $quote->expires_at->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeItineraryQuoteLeg(ItineraryQuoteLeg $leg, string $currency): array
    {
        $departure = $leg->departure;
        $priceQuote = $leg->priceQuote;
        $baseAmount = $priceQuote !== null ? (int) $priceQuote->base_amount : (int) $leg->amount;
        $coefficient = $priceQuote !== null ? (float) $priceQuote->coefficient : 1.0;
        $finalAmount = $priceQuote !== null ? (int) $priceQuote->final_amount : (int) $leg->amount;
        $dynamicAdjustment = $finalAmount - $baseAmount;
        $priceQuoteSnapshot = $priceQuote === null ? [] : $priceQuote->snapshot;

        return [
            'legOrder' => $leg->leg_order,
            'direction' => $leg->direction,
            'departureUuid' => $departure->uuid,
            'departureDate' => $departure->departure_date?->toDateString(),
            'departureTime' => $departure->departure_time,
            'line' => [
                'uuid' => $departure->line->uuid,
                'code' => $departure->line->code,
                'name' => $departure->line->name,
                'estimatedDurationMinutes' => $departure->line->estimated_duration_minutes,
                'isInternational' => is_array($departure->line->countries_crossed)
                    && count($departure->line->countries_crossed) > 1,
            ],
            'bus' => [
                'uuid' => $departure->bus->uuid,
                'registrationNumber' => $departure->bus->registration_number,
                'brand' => $departure->bus->brand,
                'model' => $departure->bus->model,
                'seatCount' => $departure->bus->seat_count,
                'amenities' => $departure->bus->amenities
                    ->map(fn ($amenity) => [
                        'code' => $amenity->code,
                        'label' => $amenity->label,
                        'icon' => $amenity->icon,
                    ])
                    ->values()
                    ->all(),
            ],
            'originStop' => $this->serializeStop($leg->originStop),
            'destinationStop' => $this->serializeStop($leg->destinationStop),
            'amount' => $finalAmount,
            'baseAmount' => $baseAmount,
            'coefficient' => $coefficient,
            'dynamicAdjustmentAmount' => $dynamicAdjustment,
            'pricingRuleUuid' => $priceQuoteSnapshot['pricing_rule_uuid'] ?? null,
            'fillRate' => $priceQuoteSnapshot['fill_rate_at_quote'] ?? null,
            'hoursBeforeDeparture' => $priceQuoteSnapshot['hours_before_departure_at_quote'] ?? null,
            'currency' => $currency,
            'availableSeatsCount' => 0,
            'serviceClassCode' => null,
            'serviceClassName' => null,
            'departureAt' => $this->stopTimeResolver->departureAt($departure, $leg->originStop)?->toISOString(),
            'arrivalAt' => $this->stopTimeResolver->arrivalAt($departure, $leg->destinationStop)?->toISOString(),
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
                'segments.departure.bus',
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
            'reservation_uuid' => $reservation->uuid,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  Collection<int, array{city: string, country: string, physical_stops: array<int, array{uuid: string, name: string}>}>  $cities
     * @return list<array{city: string, country: string, physicalStops: array<int, array{uuid: string, name: string}>}>
     */
    private function serializeCities(Collection $cities): array
    {
        return array_values($cities->map(fn (array $city): array => [
            'city' => $city['city'],
            'country' => $city['country'],
            'physicalStops' => array_map(fn (array $stop): array => [
                'uuid' => $stop['uuid'],
                'name' => $stop['name'],
            ], $city['physical_stops']),
        ])->all());
    }

    /**
     * @return array<int, array{uuid: string, code: string, name: string}>
     */
    private function serializeServiceClasses(): array
    {
        return ServiceClass::ordered()->get()->map(fn (ServiceClass $class): array => [
            'uuid' => $class->uuid,
            'code' => $class->code,
            'name' => $class->name,
        ])->values()->all();
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

            // ✅ MODIFICATION ICI : On appelle la nouvelle méthode privée pour éviter le "unresolvable type"
            'tickets' => $tickets->map(fn (Ticket $ticket): array => $this->serializeTicket($ticket, $reservation, $fiscalInvoice))->values()->all(),

            'segments' => $reservation->segments->map(fn ($segment): array => [
                'uuid' => $segment->uuid,
                'direction' => $segment->direction,
                'legOrder' => $segment->leg_order,
                'passengerIndex' => $segment->passenger_index,
                'seatUuid' => $segment->seat?->uuid,
                'seatCode' => $segment->seat?->code,
                'originStop' => $segment->originStop?->name,
                'destinationStop' => $segment->destinationStop?->name,
                'originStopCity' => $segment->originStop?->city,
                'destinationStopCity' => $segment->destinationStop?->city,
                'departureDate' => $segment->departure?->departure_date?->toDateString(),
                'lineCode' => $segment->departure?->line?->code,
                'lineName' => $segment->departure?->line?->name,
                'busRegistration' => $segment->departure?->bus?->registration_number,
                'departureAt' => $segment->departure && $segment->originStop
                    ? $this->stopTimeResolver->departureAt($segment->departure, $segment->originStop)?->toISOString()
                    : null,
                'arrivalAt' => $segment->departure && $segment->destinationStop
                    ? $this->stopTimeResolver->arrivalAt($segment->departure, $segment->destinationStop)?->toISOString()
                    : null,
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

    /**
     * NOUVELLE MÉTHODE PRIVÉE : Extrait la logique complexe du map() des tickets
     * pour que PHPStan puisse résoudre les types sans erreur.
     *
     * @return array<string, mixed>
     */
    private function serializeTicket(Ticket $ticket, Reservation $reservation, ?FiscalInvoice $fiscalInvoice): array
    {
        $passenger = $ticket->reservationPassenger;

        /** @var list<array<string, mixed>> $legs */
        $legs = [];
        if ($passenger !== null) {
            $legs = $passenger->segments->sortBy('leg_order')->map(function ($segment): array {
                return [
                    'direction' => $segment->direction,
                    'legOrder' => $segment->leg_order,
                    'originStop' => $segment->originStop?->name,
                    'destinationStop' => $segment->destinationStop?->name,
                    'seatCode' => $segment->seat?->code,
                ];
            })->values()->all();
        }

        $passengerIndex = $passenger?->passenger_index;
        $allocationAmount = 0;
        if ($passengerIndex !== null && is_array($reservation->total_snapshot['passenger_allocations'] ?? null)) {
            $allocationAmount = (int) ($reservation->total_snapshot['passenger_allocations'][$passengerIndex] ?? 0);
        }

        return [
            'uuid' => $ticket->uuid,
            'shortCode' => $ticket->short_code,
            'ticketNumber' => $ticket->uuid,
            'qrCode' => $ticket->qr_code,
            'seatCode' => $passenger?->segments->first()?->seat?->code,
            'passengerUuid' => $passenger?->uuid,
            'passengerIndex' => $passengerIndex,
            'passengerName' => $passenger !== null ? trim($passenger->first_name.' '.$passenger->last_name) : '',
            'allocationAmount' => $allocationAmount,
            'transactionReference' => $fiscalInvoice !== null ? $fiscalInvoice->kora_reference : $reservation->uuid,
            'status' => $ticket->status->value,
            'legs' => $legs,
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

    /** @return array{isOpen: bool, canOpen: bool, uuid: string|null, businessDate: string|null, totalCash: int, totalMobileMoney: int, cashRegisters: array<int, array{uuid: string, code: string, label: string}>} */
    private function serializeCashSession(): array
    {
        $session = $this->resolveOpenCashSession();

        return [
            'isOpen' => $session !== null,
            'canOpen' => Gate::allows('create', CashSession::class),
            'uuid' => $session?->uuid,
            'businessDate' => $session === null ? null : $session->business_date->toDateString(),
            'status' => $session?->status->value,
            'totalCash' => $session === null ? 0 : (int) $session->total_cash,
            'totalMobileMoney' => $session === null ? 0 : (int) $session->total_mobile_money,
            'cashRegisters' => $this->serializeCashRegisters(),
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

    /** @return array<int, array{uuid: string, code: string, label: string}> */
    private function serializeCashRegisters(): array
    {
        $user = auth()->user();
        if ($user?->agency_id === null) {
            return [];
        }

        return CashRegister::query()
            ->where('agency_id', $user->agency_id)
            ->where('kind', 'counter')
            ->where('status', 'active')
            ->where(function ($query) use ($user): void {
                $query->whereDoesntHave('authorizedUsers')
                    ->orWhereHas('authorizedUsers', fn ($users) => $users->whereKey($user->getKey()));
            })
            ->orderBy('code')
            ->get()
            ->map(fn (CashRegister $register): array => [
                'uuid' => $register->uuid,
                'code' => $register->code,
                'label' => $register->label,
            ])
            ->values()
            ->all();
    }

    private function resolveOpenCashSession(): ?CashSession
    {
        if (array_key_exists('openCashSession', $this->requestCache)) {
            return $this->requestCache['openCashSession'];
        }

        $user = auth()->user();
        if ($user?->agency_id === null) {
            return $this->requestCache['openCashSession'] = null;
        }

        return $this->requestCache['openCashSession'] = CashSession::query()
            ->where('agent_id', $user->getAuthIdentifier())
            ->where('agency_id', $user->agency_id)
            ->whereDate('business_date', now()->toDateString())
            ->where('status', CashSessionStatus::Open->value)
            ->with('ledgers.agencyPaymentAccount')
            ->first();
    }

    /** @return list<array{uuid: string, network: string, label: string, accountIdentifier: string}> */
    private function serializeMobileMoneyAccounts(): array
    {
        $user = auth()->user();
        if ($user?->agency_id === null) {
            return [];
        }

        $session = $this->resolveOpenCashSession();

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
                'ledgerUuid' => $this->ledgerUuidForAccount($session, $account->uuid),
            ])->values()->all();

        return array_values($accounts);
    }

    private function ledgerUuidForAccount(?CashSession $session, string $accountUuid): ?string
    {
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
