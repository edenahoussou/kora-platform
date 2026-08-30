<?php

namespace App\Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Booking\Actions\CreateSeatHoldAction;
use App\Modules\Booking\Actions\ReleaseSeatHoldAction;
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
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\BusNetwork\Services\BusCatalogService;
use App\Modules\Cash\Data\CashLedgerKind;
use App\Modules\Cash\Data\CashSessionStatus;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Departures\Data\DepartureAvailabilityData;
use App\Modules\Departures\Models\Departure;
use App\Modules\Departures\Services\DepartureSearchService;
use App\Modules\DynamicPricing\Actions\CalculateQuoteAction;
use App\Modules\DynamicPricing\Models\PriceQuote;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\Payments\Actions\RecordCounterSalePaymentAction;
use App\Modules\Payments\Models\AgencyPaymentAccount;
use App\Modules\SeatInventory\Actions\BuildCounterSaleSeatAvailabilityAction;
use App\Modules\SeatInventory\Data\CounterSaleSeatAvailabilityData;
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
     * @phpstan-type SaleContext array{departure_uuid: string|null, origin_stop_uuid: string|null, destination_stop_uuid: string|null, quote_uuid: string|null, reservation_uuid: string|null}
     */
    public function __construct(
        private DepartureSearchService $departures,
        private BusCatalogService $catalog,
        private BuildCounterSaleSeatAvailabilityAction $counterSaleSeatAvailability,
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
        $selectedDeparture = $this->selectedDeparture($context, $filters);

        return Inertia::render('sale', [
            'cities' => $this->serializeCities($this->catalog->cities()),
            'search' => [
                'date' => $filters['date'],
                'from' => $filters['from'],
                'to' => $filters['to'],
            ],
            'departures' => $this->serializeDepartures($this->searchDepartures($filters)),
            'selectedDeparture' => $selectedDeparture ? $this->serializeDeparture($selectedDeparture) : null,
            'seats' => $this->serializeSeats($this->selectedSeats($selectedDeparture, $context)),
            'quote' => $this->serializeQuote($this->selectedQuote($context, $selectedDeparture)),
            'reservation' => $this->serializeReservation($this->selectedReservation($context)),
            'cashSession' => $this->serializeCashSession(),
            'mobileMoneyAccounts' => $this->serializeMobileMoneyAccounts(),
            'permissions' => [
                'canCreate' => Gate::allows('createCounterSale', Reservation::class),
            ],
        ]);
    }

    public function quote(StoreCounterSaleQuoteRequest $request, CalculateQuoteAction $calculateQuote): RedirectResponse
    {
        $data = $request->quoteData();
        $quote = $calculateQuote->handle(
            departure: Departure::query()->where('uuid', $data['departure_uuid'])->firstOrFail(),
            originStop: Stop::query()->where('uuid', $data['origin_stop_uuid'])->firstOrFail(),
            destinationStop: Stop::query()->where('uuid', $data['destination_stop_uuid'])->firstOrFail(),
            passengerCount: $data['passenger_count'],
        );

        return to_route('sale', $request->saleQuery($quote->uuid));
    }

    public function hold(HoldCounterSaleSeatsRequest $request, CreateSeatHoldAction $createSeatHold): RedirectResponse
    {
        $data = $request->holdData();
        try {
            $reservation = $createSeatHold->handle(
                user: $request->user(),
                departure: Departure::query()->where('uuid', $data['departure_uuid'])->firstOrFail(),
                originStop: Stop::query()->where('uuid', $data['origin_stop_uuid'])->firstOrFail(),
                destinationStop: Stop::query()->where('uuid', $data['destination_stop_uuid'])->firstOrFail(),
                priceQuote: PriceQuote::query()->where('uuid', $data['price_quote_uuid'])->firstOrFail(),
                seatUuids: $data['seat_uuids'],
                channel: ReservationChannel::Counter,
            );
        } catch (SeatUnavailableException $exception) {
            throw ValidationException::withMessages([
                'seats' => $exception->getMessage(),
            ]);
        }

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

            return to_route('sale', $request->saleQuery($reservation->uuid))
                ->withInput();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Paiement confirmé. Les billets ont été émis.'),
        ]);

        $confirmedReservation = $result['reservation']->load([
            'departure.line',
            'departure.bus',
            'segments.originStop',
            'segments.destinationStop',
        ]);
        $segment = $confirmedReservation->segments->first();

        return to_route('sale', array_filter([
            'date' => $confirmedReservation->departure?->departure_date?->toDateString(),
            'from' => $segment?->originStop?->city,
            'to' => $segment?->destinationStop?->city,
            'departure_uuid' => $confirmedReservation->departure?->uuid,
            'origin_stop_uuid' => $segment?->originStop?->uuid,
            'destination_stop_uuid' => $segment?->destinationStop?->uuid,
            'reservation_uuid' => $confirmedReservation->uuid,
        ], static fn (mixed $value): bool => $value !== null && $value !== ''));
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
            ->where('user_id', auth()->id())
            ->with([
                'departure.line',
                'segments.seat',
                'segments.originStop',
                'segments.destinationStop',
                'passengers.segments.seat',
                'payments',
                'payments.agencyPaymentAccount',
                'payments.verifier.agency',
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
            ->where('user_id', $user->getKey())
            ->where('channel', ReservationChannel::Counter->value)
            ->where('status', ReservationStatus::Held->value)
            ->where('expires_at', '>', now())
            ->with([
                'departure',
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
            'date' => $reservation->departure?->departure_date?->toDateString(),
            'from' => $segment?->originStop?->city,
            'to' => $segment?->destinationStop?->city,
            'departure_uuid' => $reservation->departure?->uuid,
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

        return [
            'uuid' => $quote->uuid,
            'baseAmount' => (int) $quote->base_amount,
            'coefficient' => (float) $quote->coefficient,
            'finalAmount' => (int) $quote->final_amount,
            'currency' => $quote->currency,
            'passengerCount' => (int) ($snapshot['passenger_count'] ?? 1),
            'totalAmount' => ((int) $quote->final_amount * (int) ($snapshot['passenger_count'] ?? 1)),
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
            ->with('reservationPassenger.segments.seat')
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
            'departureUuid' => $reservation->departure?->uuid,
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
                'status' => $fiscalInvoice->status?->value,
                'koraReference' => $fiscalInvoice->kora_reference,
                'mecefUid' => $fiscalInvoice->mecef_uid,
            ] : null,
            'tickets' => $tickets->map(fn (Ticket $ticket): array => [
                'uuid' => $ticket->uuid,
                'shortCode' => $ticket->short_code,
                'ticketNumber' => $ticket->uuid,
                'qrCode' => $ticket->qr_code,
                'seatCode' => $ticket->reservationPassenger?->segments->first()?->seat?->code,
                'passengerUuid' => $ticket->reservationPassenger?->uuid,
            ])->values()->all(),
            'segments' => $reservation->segments->map(fn ($segment): array => [
                'uuid' => $segment->uuid,
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
                'seatUuid' => $passenger->segments->first()?->seat?->uuid,
                'seatCode' => $passenger->segments->first()?->seat?->code,
            ])->values()->all(),
            'draftPassengers' => $reservation->segments->map(function ($segment) use ($draftPassengers): array {
                $seatUuid = $segment->seat?->uuid;
                $draft = $seatUuid !== null && is_array($draftPassengers[$seatUuid] ?? null)
                    ? $draftPassengers[$seatUuid]
                    : [];

                return [
                    'firstName' => $draft['first_name'] ?? '',
                    'lastName' => $draft['last_name'] ?? '',
                    'phone' => $draft['phone'] ?? '',
                    'email' => $draft['email'] ?? '',
                    'documentType' => $draft['document_type'] ?? 'national_id',
                    'documentNumber' => $draft['document_number'] ?? '',
                    'nationality' => $draft['nationality'] ?? '',
                    'birthDate' => $draft['birth_date'] ?? '',
                    'seatUuid' => $seatUuid,
                    'seatCode' => $segment->seat?->code,
                ];
            })->values()->all(),
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
