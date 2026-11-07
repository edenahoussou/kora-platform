<?php

namespace App\Modules\KoraPoints\Http\Controllers;

use App\Models\User;
use App\Modules\Booking\Actions\CalculateItineraryQuoteAction;
use App\Modules\Booking\Actions\CreateItineraryHoldAction;
use App\Modules\Booking\Actions\ItineraryLegQuoteRequest;
use App\Modules\Booking\Actions\ReleaseSeatHoldAction;
use App\Modules\Booking\Actions\ResolvePayerContextAction;
use App\Modules\Booking\Actions\UpdateReservationPassengersAction;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Exceptions\SeatUnavailableException;
use App\Modules\Booking\Http\Controllers\CounterSaleController;
use App\Modules\Booking\Http\Requests\ConfirmCounterSaleRequest;
use App\Modules\Booking\Http\Requests\CounterSaleSearchRequest;
use App\Modules\Booking\Http\Requests\FetchCounterSaleSeatsRequest;
use App\Modules\Booking\Http\Requests\HoldCounterSaleSeatsRequest;
use App\Modules\Booking\Http\Requests\StoreCounterSalePassengersRequest;
use App\Modules\Booking\Http\Requests\StoreCounterSalePaymentRequest;
use App\Modules\Booking\Http\Requests\StoreCounterSaleQuoteRequest;
use App\Modules\Booking\Models\ItineraryQuote;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Services\DepartureStopTimeResolver;
use App\Modules\Booking\Services\ItinerarySearchService;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\BusNetwork\Services\BusCatalogService;
use App\Modules\CompanyAccounts\Services\CompanyAccountCatalogService;
use App\Modules\Departures\Models\Departure;
use App\Modules\KoraPoints\Models\KoraPointSale;
use App\Modules\KoraPoints\Services\KoraPointAgentReadService;
use App\Modules\Payments\Actions\RecordCounterSalePaymentAction;
use App\Modules\Payments\Data\PaymentMethod;
use App\Modules\SeatInventory\Actions\BuildCounterSaleSeatAvailabilityAction;
use App\Modules\Ticketing\Data\TicketIssuanceStatus;
use App\Modules\Ticketing\Jobs\IssueReservationTicketsJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class KoraPointSaleController extends CounterSaleController
{
    public function __construct(
        BusCatalogService $catalog,
        BuildCounterSaleSeatAvailabilityAction $counterSaleSeatAvailability,
        CompanyAccountCatalogService $companyAccounts,
        ItinerarySearchService $itinerarySearch,
        DepartureStopTimeResolver $stopTimeResolver,
        protected readonly KoraPointAgentReadService $agentRead,
    ) {
        parent::__construct(
            $catalog,
            $counterSaleSeatAvailability,
            $companyAccounts,
            $itinerarySearch,
            $stopTimeResolver,
        );
    }

    /**
     * Display the official KORA POINT counter sale interface with real departures and seat layouts.
     */
    public function index(CounterSaleSearchRequest $request): Response|RedirectResponse
    {
        Gate::authorize('viewAny', Departure::class);

        /** @var User $user */
        $user = $request->user();
        $point = $this->agentRead->resolvePoint($user);
        $health = $this->agentRead->health($point);

        if (! $request->hasExplicitSaleContext()) {
            $activeReservation = $this->latestActiveCounterSale();

            if ($activeReservation !== null) {
                return to_route('kora-point.sale', $this->saleQueryFromReservation($activeReservation));
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

        $reservation = $this->selectedReservation($context);

        $koraPointSale = null;
        if ($reservation !== null && $reservation->payments->isNotEmpty()) {
            $saleRecord = KoraPointSale::query()
                ->where('reservation_id', $reservation->getKey())
                ->first();

            if ($saleRecord !== null) {
                $koraPointSale = [
                    'commission_amount' => (int) $saleRecord->commission_amount,
                    'net_amount' => (int) $saleRecord->net_amount,
                    'gross_amount' => (int) $saleRecord->gross_amount,
                ];
            }
        }

        return Inertia::render('kora-points/agent/sale', [
            'point' => $this->agentRead->profile($point, $user),
            'health' => $health,
            'commissionEarned' => $koraPointSale,
            'cities' => $this->serializeCities($this->catalog->cities()),
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
            'reservation' => $this->serializeReservation($reservation),
            'permissions' => [
                'canCreate' => $health['sales_allowed'] && Gate::allows('createCounterSale', Reservation::class),
                'canDebitWallet' => $user->can('wallet.debit-counter'),
            ],
            'cashSession' => $this->serializeCashSession(),
            'mobileMoneyAccounts' => $this->serializeMobileMoneyAccounts(),
            'companyAccounts' => [], // Partner points only sell individual tickets
            'groupBookingSettings' => $this->serializeGroupBookingSettings(),
        ]);
    }

    /**
     * Compute a travel quote for the partner agent and enforce available balance threshold.
     */
    public function quote(StoreCounterSaleQuoteRequest $request, CalculateItineraryQuoteAction $calculateItineraryQuote): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $point = $this->agentRead->resolvePoint($user);
        $health = $this->agentRead->health($point);

        if (! $health['sales_allowed']) {
            throw ValidationException::withMessages([
                'point' => 'Les ventes sont actuellement suspendues pour ce point de vente.',
            ])->redirectTo(route('kora-point.sale', $request->saleQuery()));
        }

        $data = $request->quoteData();

        $buildLegs = fn (array $legs, string $direction): array => array_map(
            fn (array $leg): ItineraryLegQuoteRequest => new ItineraryLegQuoteRequest(
                departure: Departure::query()->where('uuid', $leg['departure_uuid'])->firstOrFail(),
                originStop: Stop::query()->where('uuid', $leg['origin_stop_uuid'])->firstOrFail(),
                destinationStop: Stop::query()->where('uuid', $leg['destination_stop_uuid'])->firstOrFail(),
                direction: $direction,
            ),
            $legs,
        );

        $legs = array_values([
            ...$buildLegs($data['outbound_legs'], 'outbound'),
            ...$buildLegs($data['inbound_legs'], 'inbound'),
        ]);

        try {
            $result = $calculateItineraryQuote->handle(
                $legs,
                $data['passenger_count'],
                $user,
                null, // No promo code for Kora Point
                ReservationChannel::Counter,
            );
        } catch (ValidationException $exception) {
            throw $exception->redirectTo(route('kora-point.sale', $request->saleQuery()));
        }

        if ($result->itineraryQuote->total_amount > $health['available_balance']) {
            throw ValidationException::withMessages([
                'credit' => 'Le montant de ce devis ('.number_format($result->itineraryQuote->total_amount, 0, ',', ' ').' FCFA) dépasse votre solde disponible ('.number_format($health['available_balance'], 0, ',', ' ').' FCFA). Effectuez un versement avant d’émettre cette vente.',
            ])->redirectTo(route('kora-point.sale', $request->saleQuery()));
        }

        return to_route('kora-point.sale', $request->saleQuery(null, $result->itineraryQuote->uuid));
    }

    /**
     * Set selected legs in session for seat selection.
     */
    public function seats(FetchCounterSaleSeatsRequest $request): Response
    {
        Gate::authorize('viewAny', Departure::class);

        $data = $request->legsData();

        session()->put('counter_sale_pending_legs', $data);

        $legSeats = $this->buildSeatAvailabilityFromLegs($data);

        return Inertia::render('kora-points/agent/sale', [
            'itineraryLegSeats' => $legSeats,
        ]);
    }

    /**
     * Reserve seats temporarily while verifying available balance.
     */
    public function hold(HoldCounterSaleSeatsRequest $request, CreateItineraryHoldAction $createItineraryHold, ResolvePayerContextAction $resolvePayer): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $point = $this->agentRead->resolvePoint($user);
        $health = $this->agentRead->health($point);

        if (! $health['sales_allowed']) {
            throw ValidationException::withMessages([
                'point' => 'Les ventes sont suspendues pour ce point de vente.',
            ])->redirectTo(route('kora-point.sale', $request->saleQuery()));
        }

        $data = $request->holdData();

        try {
            $payer = $data['payer'] !== null
                ? $resolvePayer->handle($user, $data['payer'])
                : null;

            $itineraryQuote = ItineraryQuote::query()
                ->where('uuid', $data['itinerary_quote_uuid'])
                ->firstOrFail();

            if ($itineraryQuote->total_amount > $health['available_balance']) {
                throw ValidationException::withMessages([
                    'credit' => 'Le montant de la vente dépasse votre solde disponible ('.number_format($health['available_balance'], 0, ',', ' ').' FCFA).',
                ]);
            }

            $reservation = $createItineraryHold->handle(
                user: $user,
                itineraryQuote: $itineraryQuote,
                seatAssignments: $request->itinerarySeatAssignments(),
                channel: ReservationChannel::Counter,
                idempotencyKey: $data['idempotency_key'],
                payer: $payer,
            );
        } catch (ValidationException $exception) {
            throw $exception->redirectTo(route('kora-point.sale', $request->saleQuery()));
        }

        return to_route('kora-point.sale', $request->saleQuery($reservation->uuid));
    }

    /**
     * Compute quote and create seat hold in a single transaction for KORA POINT.
     */
    public function confirm(
        ConfirmCounterSaleRequest $request,
        CalculateItineraryQuoteAction $calculateItineraryQuote,
        CreateItineraryHoldAction $createItineraryHold,
        ResolvePayerContextAction $resolvePayer,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $point = $this->agentRead->resolvePoint($user);
        $health = $this->agentRead->health($point);

        if (! $health['sales_allowed']) {
            throw ValidationException::withMessages([
                'point' => 'Les ventes sont actuellement suspendues pour ce point de vente.',
            ])->redirectTo(route('kora-point.sale', $request->saleQuery()));
        }

        $data = $request->confirmData();
        $legs = $this->buildItineraryLegs($data['outbound_legs'], $data['inbound_legs']);

        try {
            $payer = $data['payer'] !== null ? $resolvePayer->handle($user, $data['payer']) : null;

            $quoteResult = $calculateItineraryQuote->handle(
                $legs,
                $data['passenger_count'],
                $user,
                null, // No promo code
                ReservationChannel::Counter,
            );

            if ($quoteResult->itineraryQuote->total_amount > $health['available_balance']) {
                throw ValidationException::withMessages([
                    'credit' => 'Le montant de ce voyage ('.number_format($quoteResult->itineraryQuote->total_amount, 0, ',', ' ').' FCFA) dépasse votre solde disponible ('.number_format($health['available_balance'], 0, ',', ' ').' FCFA). Effectuez un versement avant d’émettre cette vente.',
                ]);
            }

            $reservation = $createItineraryHold->handle(
                user: $user,
                itineraryQuote: $quoteResult->itineraryQuote,
                seatAssignments: $request->itinerarySeatAssignments(),
                channel: ReservationChannel::Counter,
                idempotencyKey: $data['idempotency_key'],
                payer: $payer,
            );
        } catch (SeatUnavailableException $exception) {
            throw ValidationException::withMessages(['seats' => $exception->getMessage()])
                ->redirectTo(route('kora-point.sale', $request->saleQuery()));
        } catch (ValidationException $exception) {
            throw $exception->redirectTo(route('kora-point.sale', $request->saleQuery()));
        }

        return to_route('kora-point.sale', $request->saleQuery($reservation->uuid));
    }

    /**
     * Update passenger records for the active reservation.
     */
    public function storePassengers(
        StoreCounterSalePassengersRequest $request,
        Reservation $reservation,
        UpdateReservationPassengersAction $updatePassengers,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        $updatePassengers->handle(
            reservation: $reservation,
            user: $user,
            passengersData: $request->passengersData(),
        );

        return to_route('kora-point.sale', $request->saleQuery($reservation->uuid));
    }

    /**
     * Record cash or mobile money payment for KORA POINT sale.
     */
    public function storePayment(
        StoreCounterSalePaymentRequest $request,
        Reservation $reservation,
        RecordCounterSalePaymentAction $recordPayment,
    ): \Symfony\Component\HttpFoundation\Response {
        /** @var User $user */
        $user = $request->user();
        $point = $this->agentRead->resolvePoint($user);
        $health = $this->agentRead->health($point);

        if (! $health['sales_allowed']) {
            throw ValidationException::withMessages([
                'point' => 'Les ventes sont suspendues pour ce point de vente.',
            ]);
        }

        if ($request->input('method') === PaymentMethod::CompanyCredit->value) {
            throw ValidationException::withMessages([
                'method' => 'Le crédit entreprise n’est pas autorisé pour un point de vente partenaire.',
            ]);
        }

        if (blank($request->input('payer_phone'))) {
            throw ValidationException::withMessages([
                'payer_phone' => 'Le numéro de téléphone du payeur est obligatoire pour la vente partenaire.',
            ]);
        }

        try {
            $recordPayment->execute(
                reservation: $reservation,
                agent: $user,
                attributes: $request->payload(),
            );
        } catch (ValidationException $exception) {
            logger()->warning('Kora Point sale payment validation failed', [
                'reservation_uuid' => $reservation->uuid,
                'errors' => $exception->errors(),
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

            return to_route('kora-point.sale', $request->saleQuery($reservation->uuid))
                ->withInput();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Paiement confirmé. Billets émis avec succès.'),
        ]);

        try {
            IssueReservationTicketsJob::dispatchSync($reservation->getKey());
        } catch (\Throwable $exception) {
            report($exception);
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Paiement enregistré, mais l’émission du billet a échoué. Vous pouvez réessayer depuis l’écran de confirmation.'),
            ]);
        }

        return to_route('kora-point.sale', ['reservation_uuid' => $reservation->uuid]);
    }

    /**
     * Release temporary seat hold on reservation.
     */
    public function release(
        Request $request,
        Reservation $reservation,
        ReleaseSeatHoldAction $releaseHold,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        $releaseHold->handle($reservation, $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Réservation annulée et places libérées.'),
        ]);

        return to_route('kora-point.sale');
    }

    /**
     * Retry async ticket issuance.
     */
    public function retryTicketIssuance(Reservation $reservation): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->can('manageCounterSale', $reservation), 403);
        abort_unless($reservation->status === ReservationStatus::Paid, 422);

        $reservation->update([
            'ticket_issuance_status' => TicketIssuanceStatus::Pending,
            'ticket_issuance_error' => null,
        ]);

        try {
            IssueReservationTicketsJob::dispatchSync($reservation->getKey());
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('Billets émis avec succès.'),
            ]);
        } catch (\Throwable $exception) {
            report($exception);
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('L’émission du billet a échoué. Réessayez ou contactez le support.'),
            ]);
        }

        return back();
    }
}
