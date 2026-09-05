<?php

namespace App\Modules\Booking\Actions;

use App\Models\User;
use App\Modules\Booking\Data\PayerContext;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Exceptions\SeatUnavailableException;
use App\Modules\Booking\Models\JourneyQuote;
use App\Modules\Booking\Models\JourneyQuoteLeg;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Booking\Services\DepartureStopTimeResolver;
use App\Modules\BusNetwork\Data\SeatStatus;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\CompanyAccounts\Services\CompanyCreditService;
use App\Modules\Departures\Actions\ResolveDepartureSellabilityAction;
use App\Modules\Departures\Models\Departure;
use App\Modules\SeatInventory\Actions\FindOccupiedSeatIdsAction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use function Illuminate\Support\enum_value;

class CreateJourneyHoldAction
{
    public function __construct(
        private readonly FindOccupiedSeatIdsAction $findOccupiedSeatIds,
        private readonly ResolveDepartureSellabilityAction $resolveSellability,
        private readonly DepartureStopTimeResolver $stopTimeResolver,
        private readonly CompanyCreditService $companyCredit,
    ) {}

    /**
     * @param  list<array{passenger_index: int, seat_assignments: list<array{direction: string, leg_order: int, seat_uuid: string}>}>  $seatAssignments
     *
     * @throws ValidationException
     * @throws SeatUnavailableException
     */
    public function handle(
        ?User $user,
        JourneyQuote $journeyQuote,
        array $seatAssignments,
        ReservationChannel $channel,
        string $idempotencyKey,
        ?PayerContext $payer = null,
    ): Reservation {
        $payer ??= PayerContext::personal($user?->getKey());

        try {
            return DB::transaction(function () use ($user, $journeyQuote, $seatAssignments, $channel, $idempotencyKey, $payer): Reservation {
                $quote = JourneyQuote::query()->whereKey($journeyQuote->getKey())->lockForUpdate()->firstOrFail();
                $existing = Reservation::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->where('created_by_user_id', $user?->getKey())
                    ->lockForUpdate()
                    ->first();
                if ($existing !== null) {
                    return $this->loadReservation($existing);
                }
                if ($quote->expires_at->lte(now())) {
                    throw ValidationException::withMessages(['journey_quote_uuid' => 'Le devis de parcours a expiré.']);
                }
                if ($user !== null && $quote->user_id !== null && $quote->user_id !== $user->getKey()) {
                    throw ValidationException::withMessages(['journey_quote_uuid' => 'Le devis ne vous appartient pas.']);
                }

                $quote->load(['legs.departure', 'legs.originStop', 'legs.destinationStop']);
                $legs = $quote->legs->values();
                $this->validateAssignments($quote, $seatAssignments);
                $departureIds = $legs->pluck('departure_id')->unique()->sort()->values()->all();
                $departures = Departure::query()->whereIn('id', $departureIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                if ($departures->count() !== count($departureIds)) {
                    throw ValidationException::withMessages(['journey_quote_uuid' => 'Un départ du devis n’existe plus.']);
                }

                foreach ($legs as $leg) {
                    $departure = $departures->get($leg->departure_id);
                    if (! $this->resolveSellability->handle($departure)->isSellable) {
                        throw ValidationException::withMessages([
                            "legs.{$leg->direction}.{$leg->leg_order}" => 'La vente est fermée pour ce départ.',
                        ]);
                    }

                    $departureAt = $this->stopTimeResolver->departureAt($departure, $leg->originStop);
                    $arrivalAt = $this->stopTimeResolver->arrivalAt($departure, $leg->destinationStop);
                    if ($departureAt === null || $arrivalAt === null || $arrivalAt->lte($departureAt)) {
                        throw ValidationException::withMessages([
                            "legs.{$leg->direction}.{$leg->leg_order}" => 'Les horaires de ce tronçon sont incomplets.',
                        ]);
                    }
                }

                $seatUuids = collect($seatAssignments)->flatMap(
                    fn (array $assignment): array => array_column($assignment['seat_assignments'], 'seat_uuid'),
                )->unique()->values()->all();
                $seats = Seat::query()->whereIn('uuid', $seatUuids)->orderBy('id')->lockForUpdate()->get()->keyBy('uuid');
                if ($seats->count() !== count($seatUuids)) {
                    throw ValidationException::withMessages(['passengers' => 'Un siège sélectionné n’existe plus.']);
                }

                $expiresAt = min($quote->expires_at, now()->addMinutes(15));
                $reservation = Reservation::query()->create([
                    'created_by_user_id' => $user?->getKey(),
                    'payer_type' => $payer->type,
                    'payer_user_id' => $payer->payerUserId,
                    'company_account_id' => $payer->companyAccount?->getKey(),
                    'purchase_type' => $quote->passenger_count >= 5 ? 'group' : 'individual',
                    'group_size' => $quote->passenger_count,
                    'payer_snapshot' => $payer->snapshot,
                    'journey_quote_id' => $quote->getKey(),
                    'idempotency_key' => $idempotencyKey,
                    'status' => ReservationStatus::Held,
                    'total_amount' => $quote->total_amount,
                    'currency' => $quote->currency,
                    'channel' => $channel,
                    'expires_at' => $expiresAt,
                    'total_snapshot' => $quote->snapshot,
                ]);

                $usedSeats = [];
                $occupiedBySegment = [];
                foreach ($seatAssignments as $assignment) {
                    foreach ($legs as $leg) {
                        /** @var array{direction: string, leg_order: int, seat_uuid: string}|null $selection */
                        $selection = collect($assignment['seat_assignments'])->first(
                            fn (array $selection): bool => $selection['direction'] === $leg->direction && (int) $selection['leg_order'] === $leg->leg_order,
                        );
                        if ($selection === null) {
                            throw ValidationException::withMessages(['passengers' => 'Un siège manque sur un tronçon du parcours.']);
                        }
                        $selectedUuid = $selection['seat_uuid'];
                        $seat = $seats->get($selectedUuid);
                        $departure = $departures->get($leg->departure_id);
                        $seatKey = $departure->getKey().':'.$seat->getKey();
                        if (isset($usedSeats[$seatKey])) {
                            throw ValidationException::withMessages(['passengers' => 'Un siège ne peut être attribué à deux voyageurs sur un même départ.']);
                        }
                        $usedSeats[$seatKey] = true;
                        if ($seat->bus_id !== $departure->bus_id) {
                            throw ValidationException::withMessages(['passengers' => 'Un siège sélectionné n’appartient pas au bus du départ.']);
                        }
                        if (! $seat->sellable || enum_value($seat->status) !== SeatStatus::Active->value) {
                            throw new SeatUnavailableException("Le siège {$seat->code} n’est pas disponible à la vente.", $seat->uuid, $seat->code);
                        }
                        $occupiedKey = $leg->departure_id.':'.$leg->origin_stop_id.':'.$leg->destination_stop_id;
                        $occupiedBySegment[$occupiedKey] ??= $this->findOccupiedSeatIds->handle($departure, $leg->originStop, $leg->destinationStop);
                        if ($occupiedBySegment[$occupiedKey]->contains($seat->getKey())) {
                            throw new SeatUnavailableException("Le siège {$seat->code} n’est plus disponible sur ce segment.", $seat->uuid, $seat->code);
                        }
                        ReservationSegment::query()->create([
                            'reservation_id' => $reservation->getKey(),
                            'direction' => $leg->direction,
                            'leg_order' => $leg->leg_order,
                            'passenger_index' => $assignment['passenger_index'],
                            'departure_id' => $departure->getKey(),
                            'seat_id' => $seat->getKey(),
                            'origin_stop_id' => $leg->origin_stop_id,
                            'destination_stop_id' => $leg->destination_stop_id,
                            'origin_stop_order' => $leg->originStop->stop_order,
                            'destination_stop_order' => $leg->destinationStop->stop_order,
                            'status' => ReservationSegmentStatus::Held,
                        ]);
                    }
                }
                if ($payer->companyAccount !== null) {
                    $this->companyCredit->reserve($reservation, $payer->companyAccount);
                }

                return $this->loadReservation($reservation);
            });
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'reservation_segments_no_overlap') || $exception->getCode() === '23P01') {
                throw new SeatUnavailableException('Un des sièges sélectionnés vient d’être réservé par un autre utilisateur.', previous: $exception);
            }
            throw $exception;
        }
    }

    /** @param list<array{passenger_index: int, seat_assignments: list<array{direction: string, leg_order: int, seat_uuid: string}>}> $assignments */
    private function validateAssignments(JourneyQuote $quote, array $assignments): void
    {
        if (count($assignments) !== $quote->passenger_count) {
            throw ValidationException::withMessages(['passengers' => 'Le nombre de voyageurs ne correspond pas au devis.']);
        }
        $expected = range(0, $quote->passenger_count - 1);
        $actual = collect($assignments)->pluck('passenger_index')->map(fn (mixed $index): int => (int) $index)->sort()->values()->all();
        if ($actual !== $expected) {
            throw ValidationException::withMessages(['passengers' => 'Les voyageurs doivent être numérotés sans trou.']);
        }
        $expectedSelections = $quote->legs->map(fn (JourneyQuoteLeg $leg): string => $leg->direction.':'.$leg->leg_order)->sort()->values()->all();
        foreach ($assignments as $index => $assignment) {
            $actualSelections = collect($assignment['seat_assignments'])
                ->map(fn (array $selection): string => $selection['direction'].':'.(int) $selection['leg_order'])
                ->sort()->values()->all();
            if ($actualSelections !== $expectedSelections) {
                throw ValidationException::withMessages(["passengers.{$index}.seat_assignments" => 'Chaque voyageur doit avoir un siège sur chaque tronçon et chaque direction.']);
            }
        }
    }

    private function loadReservation(Reservation $reservation): Reservation
    {
        return $reservation->load(['journeyQuote', 'segments.departure', 'segments.seat', 'segments.originStop', 'segments.destinationStop', 'passengers']);
    }
}
