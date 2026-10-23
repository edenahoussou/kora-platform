<?php

namespace App\Modules\CompanyAccounts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Booking\Actions\CalculateItineraryQuoteAction;
use App\Modules\Booking\Actions\CreateItineraryHoldAction;
use App\Modules\Booking\Actions\DescribeReservationDiscountAction;
use App\Modules\Booking\Actions\ItineraryLegQuoteRequest;
use App\Modules\Booking\Actions\ReleaseSeatHoldAction;
use App\Modules\Booking\Actions\UpdateReservationPassengersAction;
use App\Modules\Booking\Data\PayerContext;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Exceptions\SeatUnavailableException;
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
use App\Modules\CompanyAccounts\Data\CompanyTravelerStatus;
use App\Modules\CompanyAccounts\Http\Requests\CompanyPortalHoldRequest;
use App\Modules\CompanyAccounts\Http\Requests\CompanyPortalPassengersRequest;
use App\Modules\CompanyAccounts\Http\Requests\CompanyPortalPaymentRequest;
use App\Modules\CompanyAccounts\Http\Requests\CompanyPortalQuoteRequest;
use App\Modules\CompanyAccounts\Http\Requests\CompanyPortalSaleSearchRequest;
use App\Modules\CompanyAccounts\Http\Requests\CompanyPortalSeatsRequest;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\CompanyAccounts\Models\CompanyTraveler;
use App\Modules\CompanyAccounts\Services\CompanyCreditService;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\ServiceClass;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\Payments\Actions\RecordCounterSalePaymentAction;
use App\Modules\SeatInventory\Actions\BuildCounterSaleSeatAvailabilityAction;
use App\Modules\SeatInventory\Data\CounterSaleSeatAvailabilityData;
use App\Modules\Ticketing\Data\TicketIssuanceStatus;
use App\Modules\Ticketing\Jobs\IssueReservationTicketsJob;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CompanyPortalSaleController extends Controller
{
    public function __construct(
        private readonly BusCatalogService $catalog,
        private readonly BuildCounterSaleSeatAvailabilityAction $seatAvailability,
        private readonly CompanyCreditService $companyCredit,
        private readonly DepartureStopTimeResolver $stopTimeResolver,
        private readonly ItinerarySearchService $itinerarySearch,
    ) {}

    public function index(CompanyPortalSaleSearchRequest $request): Response
    {
        $account = $this->account($request);
        $filters = $request->departureFilters();
        $tripType = $request->string('trip_type')->value() ?: 'one_way';
        $returnDate = $tripType === 'round_trip'
            ? ($request->string('return_date')->value() ?: null)
            : null;
        $results = $this->itinerarySearch->searchForCounterSale([
            ...$filters,
            'trip_type' => $tripType,
            'return_date' => $returnDate,
        ]);
        $context = $request->selectedContext();
        $itineraryQuoteUuid = $context['itinerary_quote_uuid'] ?? null;
        $quote = $itineraryQuoteUuid !== null
            ? $this->quoteForAccount($itineraryQuoteUuid, $account, $request->user())
            : null;
        $reservation = $this->reservationForAccount($context['reservation_uuid'] ?? null, $account);

        if ($quote === null && $reservation !== null && $reservation->itineraryQuote !== null) {
            $quote = $this->quoteForAccount(
                $reservation->itineraryQuote->uuid,
                $account,
                $request->user(),
            );
        }

        $periodKey = now()->format('Y-m');
        $groupSettings = GroupBookingSetting::current();

        return Inertia::render('company-portal/sale', [
            'account' => [
                'uuid' => $account->uuid,
                'name' => $account->name,
                'reference' => $account->reference,
            ],
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
            'itineraryQuote' => $this->serializeQuote($quote),
            'itineraryLegSeats' => $this->serializeQuoteSeats($quote),
            'reservation' => $this->serializeReservation($reservation),
            'roster' => $account->travelers()
                ->where('status', CompanyTravelerStatus::Active->value)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
                ->map(fn (CompanyTraveler $traveler): array => $this->serializeTraveler($traveler))
                ->values()
                ->all(),
            'credit' => [
                'available' => $this->companyCredit->availableCredit($account, $periodKey),
                'monthlyLimit' => (int) $account->monthly_credit_limit,
                'periodKey' => $periodKey,
            ],
            'groupBookingSettings' => [
                'minimumPassengers' => (int) $groupSettings->minimum_passengers,
                'maximumPassengers' => (int) $groupSettings->maximum_passengers,
            ],
        ]);
    }

    public function quote(
        CompanyPortalQuoteRequest $request,
        CalculateItineraryQuoteAction $calculateItineraryQuote,
    ): RedirectResponse {
        $account = $this->account($request);
        $data = $request->quoteData();
        $legs = [];
        foreach ([
            'outbound' => $data['outbound_legs'],
            'inbound' => $data['inbound_legs'],
        ] as $direction => $directionLegs) {
            foreach ($directionLegs as $leg) {
                $legs[] = new ItineraryLegQuoteRequest(
                    departure: Departure::query()->where('uuid', $leg['departure_uuid'])->firstOrFail(),
                    originStop: Stop::query()->where('uuid', $leg['origin_stop_uuid'])->firstOrFail(),
                    destinationStop: Stop::query()->where('uuid', $leg['destination_stop_uuid'])->firstOrFail(),
                    direction: $direction,
                );
            }
        }

        $result = $calculateItineraryQuote->handle(
            legs: $legs,
            passengerCount: $data['passenger_count'],
            user: $request->user(),
            channel: ReservationChannel::CompanyPortal,
            companyAccount: $account,
        );

        return to_route('company-portal.sale.index', $request->saleQuery($result->itineraryQuote->uuid));
    }

    public function seats(CompanyPortalSeatsRequest $request): RedirectResponse
    {
        $account = $this->account($request);
        $quote = $this->quoteForAccount(
            $request->string('itinerary_quote_uuid')->toString(),
            $account,
            $request->user(),
        );

        return to_route('company-portal.sale.index', $request->saleQuery($quote->uuid));
    }

    public function hold(
        CompanyPortalHoldRequest $request,
        CreateItineraryHoldAction $createHold,
    ): RedirectResponse {
        $account = $this->account($request);
        $quote = $this->quoteForAccount(
            $request->string('itinerary_quote_uuid')->toString(),
            $account,
            $request->user(),
        );
        $user = $request->user();
        $payer = PayerContext::company($account, [
            'type' => 'company',
            'display_name' => $account->name,
            'account_uuid' => $account->uuid,
            'company_reference' => $account->reference,
            'referent_uuid' => $user->uuid,
            'referent_name' => $user->name,
            'captured_at' => now()->toIso8601String(),
        ]);

        try {
            $reservation = $createHold->handle(
                user: $user,
                itineraryQuote: $quote,
                seatAssignments: $request->seatAssignments(),
                channel: ReservationChannel::CompanyPortal,
                idempotencyKey: $request->string('idempotency_key')->toString(),
                payer: $payer,
            );
        } catch (SeatUnavailableException $exception) {
            throw ValidationException::withMessages(['seats' => $exception->getMessage()]);
        }

        $this->ensureReservationOwnership($reservation, $account);

        return to_route('company-portal.sale.index', $request->saleQuery($reservation->uuid));
    }

    public function release(
        Request $request,
        Reservation $reservation,
        ReleaseSeatHoldAction $releaseHold,
    ): RedirectResponse {
        $this->ensureReservationOwnership($reservation, $this->account($request));
        $releaseHold->handle($reservation, $request->user());

        return to_route('company-portal.sale.index');
    }

    public function storePassengers(
        CompanyPortalPassengersRequest $request,
        Reservation $reservation,
        UpdateReservationPassengersAction $updatePassengers,
    ): RedirectResponse {
        $account = $this->account($request);
        $this->ensureReservationOwnership($reservation, $account);
        $submittedPassengers = $request->passengersData();
        $travelerUuids = array_column($submittedPassengers, 'traveler_uuid');
        $travelers = $account->travelers()
            ->where('status', CompanyTravelerStatus::Active->value)
            ->whereIn('uuid', $travelerUuids)
            ->get()
            ->keyBy('uuid');

        if ($travelers->count() !== count($submittedPassengers)) {
            throw ValidationException::withMessages([
                'passengers' => 'Choisissez uniquement des voyageurs actifs de votre roster.',
            ]);
        }

        $passengers = array_map(function (array $data) use ($travelers): array {
            /** @var CompanyTraveler $traveler */
            $traveler = $travelers->get($data['traveler_uuid']);

            return [
                'passenger_index' => (int) $data['passenger_index'],
                'first_name' => $traveler->first_name,
                'last_name' => $traveler->last_name,
                'phone' => $traveler->phone,
                'email' => $traveler->email,
                'document_type' => $traveler->document_type->value ?? $data['document_type'] ?? null,
                'document_number' => $traveler->document_number ?? $data['document_number'] ?? null,
                'nationality' => $traveler->nationality ?? $data['nationality'] ?? null,
                'birth_date' => $traveler->birth_date?->toDateString() ?? $data['birth_date'] ?? null,
                'yellow_fever_vaccinated' => $traveler->yellow_fever_vaccinated
                    ?? $data['yellow_fever_vaccinated']
                    ?? null,
            ];
        }, $submittedPassengers);

        $updated = $updatePassengers->handle($reservation, $request->user(), $passengers);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Voyageurs associés aux sièges.']);

        return to_route('company-portal.sale.index', ['reservation_uuid' => $updated->uuid]);
    }

    public function storePayment(
        CompanyPortalPaymentRequest $request,
        Reservation $reservation,
        RecordCounterSalePaymentAction $recordPayment,
    ): RedirectResponse {
        $this->ensureReservationOwnership($reservation, $this->account($request));
        $result = $recordPayment->execute($reservation, $request->user(), [
            'method' => 'company_credit',
            'amount' => (int) $reservation->total_amount,
            'idempotency_key' => $request->string('idempotency_key')->toString(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Paiement confirmé sur le crédit entreprise. Les billets sont en cours d’émission.',
        ]);

        try {
            IssueReservationTicketsJob::dispatchSync($result['reservation']->getKey());
        } catch (\Throwable $exception) {
            report($exception);
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => 'Le crédit a été débité, mais l’émission des billets a échoué. Vous pouvez la relancer.',
            ]);
        }

        return to_route('company-portal.sale.index', [
            'reservation_uuid' => $result['reservation']->uuid,
        ]);
    }

    public function retryTicketIssuance(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->ensureReservationOwnership($reservation, $this->account($request));
        abort_unless($reservation->status === ReservationStatus::Paid, 422);

        $reservation->update([
            'ticket_issuance_status' => TicketIssuanceStatus::Pending,
            'ticket_issuance_error' => null,
        ]);
        IssueReservationTicketsJob::dispatch($reservation->getKey());

        return to_route('company-portal.sale.index', ['reservation_uuid' => $reservation->uuid]);
    }

    /** @param list<ItineraryData> $itineraries
     * @return list<array<string, mixed>> */
    private function serializeItineraries(array $itineraries, string $direction = 'outbound'): array
    {
        return array_map(fn (ItineraryData $itinerary): array => [
            'isDirect' => $itinerary->isDirect(),
            'connectionsCount' => $itinerary->connectionsCount,
            'totalAmount' => $itinerary->totalAmount,
            'currency' => $itinerary->currency,
            'departureAt' => $itinerary->departureAt->toISOString(),
            'arrivalAt' => $itinerary->arrivalAt->toISOString(),
            'connectionMarginMinutes' => $itinerary->connectionMarginMinutes,
            'legs' => array_map(
                fn (ItineraryLegData $leg): array => $this->serializeItineraryLeg($leg, $direction),
                $itinerary->legs,
            ),
        ], $itineraries);
    }

    /** @return array<string, mixed> */
    private function serializeItineraryLeg(ItineraryLegData $leg, string $direction): array
    {
        $departure = $leg->departure;
        $bus = $leg->bus ?? $departure->bus;

        return [
            'direction' => $direction,
            'legOrder' => $leg->legOrder,
            'departureUuid' => $departure->uuid,
            'departureDate' => $departure->departure_date?->toDateString(),
            'departureTime' => $departure->departure_time,
            'line' => [
                'uuid' => $departure->line->uuid,
                'code' => $departure->line->code,
                'name' => $departure->line->name,
                'estimatedDurationMinutes' => $leg->estimatedDurationMinutes,
                'isInternational' => is_array($departure->line->countries_crossed)
                    && count($departure->line->countries_crossed) > 1,
            ],
            'bus' => [
                'uuid' => $bus->uuid,
                'registrationNumber' => $bus->registration_number,
                'brand' => $bus->brand,
                'model' => $bus->model,
                'seatCount' => $bus->seat_count,
                'amenities' => $bus->amenities->map(fn ($amenity): array => [
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

    /** @param Collection<int, array{city: string, country: string, physical_stops: array<int, array{uuid: string, name: string}>}> $cities
     * @return list<array{city: string, country: string, stopsCount: int}> */
    private function serializeCities(Collection $cities): array
    {
        return array_values($cities->map(fn (array $city): array => [
            'city' => $city['city'],
            'country' => $city['country'],
            'stopsCount' => count($city['physical_stops']),
        ])->all());
    }

    /** @return list<array{uuid: string, code: string, name: string}> */
    private function serializeServiceClasses(): array
    {
        return array_values(ServiceClass::ordered()->get()->map(fn (ServiceClass $class): array => [
            'uuid' => $class->uuid,
            'code' => $class->code,
            'name' => $class->name,
        ])->all());
    }

    private function account(Request $request): CompanyAccount
    {
        /** @var CompanyAccount $account */
        $account = $request->attributes->get('companyAccount');

        return $account;
    }

    private function ensureReservationOwnership(Reservation $reservation, CompanyAccount $account): void
    {
        abort_unless(
            $reservation->company_account_id === $account->getKey()
                && $reservation->channel === ReservationChannel::CompanyPortal,
            404,
        );
    }

    private function quoteForAccount(string $uuid, CompanyAccount $account, ?User $user): ItineraryQuote
    {
        return ItineraryQuote::query()
            ->where('uuid', $uuid)
            ->where('user_id', $user?->getKey())
            ->where('snapshot->company_account_uuid', $account->uuid)
            ->with([
                'legs.departure.line',
                'legs.departure.bus.amenities',
                'legs.departure.bus.composition',
                'legs.originStop',
                'legs.destinationStop',
                'legs.priceQuote',
            ])
            ->firstOrFail();
    }

    private function reservationForAccount(?string $uuid, CompanyAccount $account): ?Reservation
    {
        if ($uuid === null) {
            return null;
        }

        $reservation = Reservation::query()
            ->where('uuid', $uuid)
            ->whereBelongsTo($account, 'companyAccount')
            ->where('channel', ReservationChannel::CompanyPortal->value)
            ->with([
                'itineraryQuote',
                'segments.departure.line',
                'segments.departure.bus',
                'segments.seat',
                'segments.originStop',
                'segments.destinationStop',
                'passengers.segments.seat',
                'passengers.segments.originStop',
                'passengers.segments.destinationStop',
                'payments.verifier.agency',
                'companyAccount',
            ])
            ->firstOrFail();

        return $reservation;
    }

    /** @return array<string, mixed>|null */
    private function serializeQuote(?ItineraryQuote $quote): ?array
    {
        if ($quote === null) {
            return null;
        }

        $snapshot = $quote->snapshot;

        return [
            'uuid' => $quote->uuid,
            'passengerCount' => (int) $quote->passenger_count,
            'subtotalAmount' => (int) $quote->subtotal_amount,
            'dynamicAdjustmentAmount' => (int) $quote->dynamic_adjustment_amount,
            'groupDiscountAmount' => (int) $quote->group_discount_amount,
            'promotionDiscountAmount' => (int) $quote->promotion_discount_amount,
            'promotion' => isset($snapshot['promotion']) ? [
                'uuid' => $snapshot['promotion']['uuid'] ?? null,
                'code' => $snapshot['promotion']['code'] ?? null,
                'name' => $snapshot['promotion']['name'] ?? null,
                'discountAmount' => (int) ($snapshot['promotion']['discount_amount'] ?? 0),
            ] : null,
            'companyDiscount' => $snapshot['company_discount'] ?? null,
            'totalAmount' => (int) $quote->total_amount,
            'currency' => $quote->currency,
            'groupDiscount' => $snapshot['group_discount'] ?? null,
            'isGroup' => (bool) ($snapshot['group'] ?? false),
            'passengerAllocations' => $snapshot['passenger_allocations'] ?? [],
            'legs' => $quote->legs
                ->sortBy('leg_order')
                ->map(fn (ItineraryQuoteLeg $leg): array => $this->serializeQuoteLeg($leg, $quote->currency))
                ->values()
                ->all(),
            'expiresAt' => $quote->expires_at->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeQuoteLeg(ItineraryQuoteLeg $leg, string $currency): array
    {
        $departure = $leg->departure;
        $priceQuote = $leg->priceQuote;
        $baseAmount = $priceQuote !== null ? (int) $priceQuote->base_amount : (int) $leg->amount;
        $finalAmount = $priceQuote !== null ? (int) $priceQuote->final_amount : (int) $leg->amount;
        $priceSnapshot = $priceQuote->snapshot ?? [];

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
                'amenities' => $departure->bus->amenities->map(fn ($amenity): array => [
                    'code' => $amenity->code,
                    'label' => $amenity->label,
                    'icon' => $amenity->icon,
                ])->values()->all(),
            ],
            'originStop' => $this->serializeStop($leg->originStop),
            'destinationStop' => $this->serializeStop($leg->destinationStop),
            'amount' => $finalAmount,
            'baseAmount' => $baseAmount,
            'coefficient' => $priceQuote !== null ? (float) $priceQuote->coefficient : 1.0,
            'finalAmount' => $finalAmount,
            'dynamicAdjustmentAmount' => $finalAmount - $baseAmount,
            'pricingRuleUuid' => $priceSnapshot['pricing_rule_uuid'] ?? null,
            'fillRate' => $priceSnapshot['fill_rate_at_quote'] ?? null,
            'hoursBeforeDeparture' => $priceSnapshot['hours_before_departure_at_quote'] ?? null,
            'currency' => $currency,
            'availableSeatsCount' => 0,
            'serviceClassCode' => null,
            'serviceClassName' => null,
            'departureAt' => $this->stopTimeResolver->departureAt($departure, $leg->originStop)?->toISOString(),
            'arrivalAt' => $this->stopTimeResolver->arrivalAt($departure, $leg->destinationStop)?->toISOString(),
        ];
    }

    /** @return list<array{legOrder: int, direction: string, departureUuid: string, grid: array{rows: int, cols: int, cells: list<array<string, mixed>>}|null, seats: list<array<string, mixed>>}> */
    private function serializeQuoteSeats(?ItineraryQuote $quote): array
    {
        if ($quote === null) {
            return [];
        }

        return array_values($quote->legs->map(fn (ItineraryQuoteLeg $leg): array => [
            'legOrder' => (int) $leg->leg_order,
            'direction' => $leg->direction,
            'departureUuid' => $leg->departure->uuid,
            'grid' => $leg->departure->bus?->composition?->seatGrid()->toArray(),
            'seats' => $this->serializeSeats(
                $this->seatAvailability->handle($leg->departure, $leg->originStop, $leg->destinationStop),
            ),
        ])->all());
    }

    /** @param Collection<int, CounterSaleSeatAvailabilityData> $seats
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

    /** @return array<string, mixed> */
    private function serializeTraveler(CompanyTraveler $traveler): array
    {
        return [
            'uuid' => $traveler->uuid,
            'firstName' => $traveler->first_name,
            'lastName' => $traveler->last_name,
            'phone' => $traveler->phone,
            'email' => $traveler->email,
            'documentType' => $traveler->document_type?->value,
            'documentTypeLabel' => $traveler->document_type !== null
                ? __("enums.passenger_document_type.{$traveler->document_type->value}")
                : null,
            'documentNumber' => $traveler->document_number,
            'nationality' => $traveler->nationality,
            'birthDate' => $traveler->birth_date?->toDateString(),
            'yellowFeverVaccinated' => $traveler->yellow_fever_vaccinated,
        ];
    }

    /** @return array<string, mixed>|null */
    private function serializeReservation(?Reservation $reservation): ?array
    {
        if ($reservation === null) {
            return null;
        }

        $payment = $reservation->payments->sortByDesc('id')->first();
        $invoice = FiscalInvoice::query()->where('reservation_id', $reservation->getKey())->first();
        $tickets = Ticket::query()
            ->where('reservation_id', $reservation->getKey())
            ->with([
                'reservationPassenger.segments.seat',
                'reservationPassenger.segments.originStop',
                'reservationPassenger.segments.destinationStop',
            ])
            ->orderBy('id')
            ->get();

        return [
            'uuid' => $reservation->uuid,
            'status' => $reservation->getRawOriginal('status'),
            'expiresAt' => $reservation->expires_at?->toISOString(),
            'heldAt' => $reservation->created_at?->toISOString(),
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
            'discounts' => $this->serializeDiscounts($reservation),
            'passengerAllocations' => $reservation->total_snapshot['passenger_allocations'] ?? [],
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
                'agencyAccount' => null,
                'verifiedAt' => $payment->verified_at?->toISOString(),
                'confirmedAt' => $payment->confirmed_at?->toISOString(),
                'verifiedByName' => $payment->verifier?->name,
                'verifiedByUuid' => $payment->verifier?->uuid,
                'agencyName' => $payment->verifier?->agency?->name,
                'agencyCode' => $payment->verifier?->agency?->code,
            ] : null,
            'qrPayload' => null,
            'fiscalization' => $invoice ? [
                'status' => $invoice->getRawOriginal('status'),
                'koraReference' => $invoice->kora_reference,
                'mecefUid' => $invoice->mecef_uid,
            ] : null,
            'refunds' => [],
            'tickets' => $tickets
                ->map(fn (Ticket $ticket): array => $this->serializeTicket($ticket, $reservation, $invoice))
                ->values()
                ->all(),
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
                'status' => $segment->getRawOriginal('status'),
            ])->values()->all(),
            'passengers' => $reservation->passengers->map(
                fn (ReservationPassenger $passenger): array => $this->serializePassenger($passenger),
            )->values()->all(),
            'draftPassengers' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function serializePassenger(ReservationPassenger $passenger): array
    {
        $firstSegment = $passenger->segments->first();

        return [
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
            'seatUuid' => $firstSegment?->seat?->uuid,
            'seatCode' => $firstSegment?->seat?->code,
        ];
    }

    /**
     * @return array{subtotalAmount: int, totalDiscount: int, label: string|null, promotionCode: string|null, companyDiscountAmount: int, groupDiscountAmount: int, promotionDiscountAmount: int}|null
     */
    private function serializeDiscounts(Reservation $reservation): ?array
    {
        $snapshot = is_array($reservation->total_snapshot) ? $reservation->total_snapshot : [];
        $promotionDiscount = (int) $reservation->promotion_discount_amount;
        $groupDiscount = (int) ($snapshot['group_discount']['amount'] ?? 0);
        $companyDiscount = (int) ($snapshot['company_discount']['amount'] ?? 0);
        $totalDiscount = $promotionDiscount + $groupDiscount + $companyDiscount;

        if ($totalDiscount <= 0) {
            return null;
        }

        $discounts = app(DescribeReservationDiscountAction::class);

        return [
            'subtotalAmount' => $discounts->originalTotal($reservation),
            'totalDiscount' => $totalDiscount,
            'label' => $discounts->execute($reservation),
            'promotionCode' => $snapshot['promotion']['code'] ?? null,
            'promotionDiscountAmount' => $promotionDiscount,
            'groupDiscountAmount' => $groupDiscount,
            'companyDiscountAmount' => $companyDiscount,
        ];
    }

    /** @return array<string, mixed> */
    private function serializeTicket(Ticket $ticket, Reservation $reservation, ?FiscalInvoice $invoice): array
    {
        $passenger = $ticket->reservationPassenger;
        $segments = $passenger->segments ?? collect();
        $passengerIndex = $passenger?->passenger_index;
        $allocations = $reservation->total_snapshot['passenger_allocations'] ?? [];

        return [
            'uuid' => $ticket->uuid,
            'shortCode' => $ticket->short_code,
            'ticketNumber' => $ticket->uuid,
            'qrCode' => $ticket->qr_code,
            'seatCode' => $segments->first()?->seat?->code,
            'passengerUuid' => $passenger?->uuid,
            'passengerIndex' => $passengerIndex,
            'passengerName' => $passenger !== null
                ? trim($passenger->first_name.' '.$passenger->last_name)
                : null,
            'allocationAmount' => $passengerIndex !== null ? (int) ($allocations[$passengerIndex] ?? 0) : 0,
            'transactionReference' => $invoice->kora_reference ?? $reservation->uuid,
            'status' => $ticket->status->value,
            'legs' => $segments->sortBy('leg_order')->map(fn ($segment): array => [
                'legOrder' => $segment->leg_order,
                'originStop' => $segment->originStop?->name,
                'destinationStop' => $segment->destinationStop?->name,
                'seatCode' => $segment->seat?->code,
            ])->values()->all(),
        ];
    }
}
