<?php

namespace App\Modules\Booking\Actions;

use App\Models\User;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Exceptions\SeatUnavailableException;
use App\Modules\Booking\Models\ItineraryQuote;
use App\Modules\Booking\Models\ItineraryQuoteLeg;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Booking\Services\DepartureStopTimeResolver;
use App\Modules\BusNetwork\Data\SeatStatus;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\Departures\Actions\ResolveDepartureSellabilityAction;
use App\Modules\Departures\Models\Departure;
use App\Modules\SeatInventory\Actions\FindOccupiedSeatIdsAction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use function Illuminate\Support\enum_value;

/**
 * Creates an atomic seat hold from one immutable itinerary quote.
 *
 * The client supplies only the quote UUID, an idempotency key and seat choices.
 * Amounts, currencies, departures and stops are read from the server-side quote.
 */
class CreateItineraryHoldAction
{
    public function __construct(
        private FindOccupiedSeatIdsAction $findOccupiedSeatIds,
        private ResolveDepartureSellabilityAction $resolveSellability,
        private DepartureStopTimeResolver $stopTimeResolver,
    ) {}

    /**
     * @param  list<array{passenger_index: int, seat_assignments: list<array{leg_order: int, seat_uuid: string}>}>  $seatAssignments
     *
     * @throws ValidationException
     * @throws SeatUnavailableException
     */
    public function handle(
        ?User $user,
        ItineraryQuote $itineraryQuote,
        array $seatAssignments,
        ReservationChannel $channel = ReservationChannel::Mobile,
        ?string $idempotencyKey = null,
    ): Reservation {
        if ($user !== null && $itineraryQuote->user_id !== null && $itineraryQuote->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'itinerary_quote_uuid' => 'Le devis ne vous appartient pas.',
            ]);
        }

        if ($idempotencyKey === null || trim($idempotencyKey) === '') {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Une clé d’idempotence est obligatoire pour créer un hold.',
            ]);
        }

        try {
            return DB::transaction(function () use ($user, $itineraryQuote, $seatAssignments, $channel, $idempotencyKey): Reservation {
                $quote = ItineraryQuote::query()
                    ->whereKey($itineraryQuote->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $existingReservation = Reservation::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->where('user_id', $user?->id)
                    ->lockForUpdate()
                    ->first();

                if ($existingReservation !== null) {
                    return $this->loadReservation($existingReservation);
                }

                if ($quote->expires_at->lte(now())) {
                    throw ValidationException::withMessages([
                        'itinerary_quote_uuid' => 'Le devis d’itinéraire a expiré.',
                    ]);
                }

                if ($user !== null && $quote->user_id !== null && $quote->user_id !== $user->id) {
                    throw ValidationException::withMessages([
                        'itinerary_quote_uuid' => 'Le devis ne vous appartient pas.',
                    ]);
                }

                $quote->load([
                    'legs.departure',
                    'legs.originStop',
                    'legs.destinationStop',
                ]);
                $this->validateSeatAssignments($quote, $seatAssignments);

                /** @var list<ItineraryQuoteLeg> $quoteLegs */
                $quoteLegs = $quote->legs->values()->all();
                $departureIds = collect($quoteLegs)
                    ->map(fn (ItineraryQuoteLeg $leg): int => $leg->departure_id)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                $lockedDepartures = Departure::query()
                    ->whereIn('id', $departureIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($lockedDepartures->count() !== count($departureIds)) {
                    throw ValidationException::withMessages([
                        'itinerary_quote_uuid' => 'Un des départs du devis n’existe plus.',
                    ]);
                }

                foreach ($quoteLegs as $index => $leg) {
                    $departure = $lockedDepartures->get($leg->departure_id);
                    $sellability = $this->resolveSellability->handle($departure);
                    if (! $sellability->isSellable) {
                        throw ValidationException::withMessages([
                            "legs.{$leg->leg_order}.departure_uuid" => 'La vente est fermée pour ce départ.',
                        ]);
                    }

                    $originAt = $this->stopTimeResolver->departureAt($departure, $leg->originStop);
                    $destinationAt = $this->stopTimeResolver->arrivalAt($departure, $leg->destinationStop);
                    $snapshotLeg = $quote->snapshot['legs'][$index] ?? null;
                    if ($snapshotLeg === null
                        || $originAt?->toIso8601String() !== ($snapshotLeg['departure_at'] ?? null)
                        || $destinationAt?->toIso8601String() !== ($snapshotLeg['arrival_at'] ?? null)
                        || $leg->originStop->physicalStop?->uuid !== ($snapshotLeg['origin_physical_stop_uuid'] ?? null)
                        || $leg->destinationStop->physicalStop?->uuid !== ($snapshotLeg['destination_physical_stop_uuid'] ?? null)
                        || $leg->originStop->line_id !== $departure->line_id
                        || $leg->destinationStop->line_id !== $departure->line_id
                    ) {
                        throw ValidationException::withMessages([
                            'itinerary_quote_uuid' => 'Le planning de l’itinéraire a changé. Demandez un nouveau devis.',
                        ]);
                    }
                }

                $seatUuids = collect($seatAssignments)
                    ->flatMap(fn (array $assignment): array => array_map(
                        fn (array $seat): string => $seat['seat_uuid'],
                        $assignment['seat_assignments'],
                    ))
                    ->unique()
                    ->values()
                    ->all();

                $seats = Seat::query()
                    ->whereIn('uuid', $seatUuids)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('uuid');

                if ($seats->count() !== count($seatUuids)) {
                    throw ValidationException::withMessages([
                        'passengers' => 'Un ou plusieurs sièges n’existent pas.',
                    ]);
                }

                $reservationExpiresAt = now()->addMinutes(15);
                if ($quote->expires_at->lt($reservationExpiresAt)) {
                    $reservationExpiresAt = $quote->expires_at;
                }

                $reservation = Reservation::create([
                    'user_id' => $user?->id,
                    'itinerary_quote_id' => $quote->id,
                    'idempotency_key' => $idempotencyKey,
                    'status' => ReservationStatus::Held,
                    'total_amount' => $quote->total_amount,
                    'currency' => $quote->currency,
                    'channel' => $channel,
                    'expires_at' => $reservationExpiresAt,
                    'total_snapshot' => $quote->snapshot,
                ]);

                $occupiedSeatIds = [];
                $usedSeats = [];

                foreach ($seatAssignments as $assignment) {
                    $legsByOrder = collect($assignment['seat_assignments'])->keyBy('leg_order');

                    foreach ($quoteLegs as $quoteLeg) {
                        $selectedSeatUuid = $legsByOrder->get($quoteLeg->leg_order)['seat_uuid'];
                        /** @var Seat $seat */
                        $seat = $seats->get($selectedSeatUuid);
                        $departure = $lockedDepartures->get($quoteLeg->departure_id);
                        $seatKey = $departure->id.':'.$seat->id;

                        if (isset($usedSeats[$seatKey])) {
                            throw ValidationException::withMessages([
                                'passengers' => 'Un siège ne peut être attribué à deux passagers sur le même départ.',
                            ]);
                        }
                        $usedSeats[$seatKey] = true;

                        if ($seat->bus_id !== $departure->bus_id) {
                            throw ValidationException::withMessages([
                                'passengers' => 'Un siège sélectionné n’appartient pas au bus du départ.',
                            ]);
                        }

                        if (! $seat->sellable || enum_value($seat->status) !== SeatStatus::Active->value) {
                            throw new SeatUnavailableException(
                                message: "Le siège {$seat->code} n'est pas disponible à la vente.",
                                conflictingSeatUuid: $seat->uuid,
                                seatCode: $seat->code,
                            );
                        }

                        $occupiedKey = $quoteLeg->departure_id.':'.$quoteLeg->origin_stop_id.':'.$quoteLeg->destination_stop_id;
                        $occupiedSeatIds[$occupiedKey] ??= $this->findOccupiedSeatIds->handle(
                            $departure,
                            $quoteLeg->originStop,
                            $quoteLeg->destinationStop,
                        );

                        if ($occupiedSeatIds[$occupiedKey]->contains($seat->id)) {
                            throw new SeatUnavailableException(
                                message: "Le siège {$seat->code} n'est plus disponible sur ce segment.",
                                conflictingSeatUuid: $seat->uuid,
                                seatCode: $seat->code,
                            );
                        }

                        ReservationSegment::create([
                            'reservation_id' => $reservation->id,
                            'leg_order' => $quoteLeg->leg_order,
                            'passenger_index' => $assignment['passenger_index'],
                            'departure_id' => $departure->id,
                            'seat_id' => $seat->id,
                            'origin_stop_id' => $quoteLeg->origin_stop_id,
                            'destination_stop_id' => $quoteLeg->destination_stop_id,
                            'origin_stop_order' => $quoteLeg->originStop->stop_order,
                            'destination_stop_order' => $quoteLeg->destinationStop->stop_order,
                            'status' => ReservationSegmentStatus::Held,
                        ]);
                    }
                }

                return $this->loadReservation($reservation);
            });
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'reservation_segments_no_overlap') || $exception->getCode() === '23P01') {
                throw new SeatUnavailableException(
                    message: 'Un des sièges sélectionnés vient d’être réservé par un autre utilisateur.',
                    previous: $exception,
                );
            }

            throw $exception;
        }
    }

    /**
     * @param  list<array{passenger_index: int, seat_assignments: list<array{leg_order: int, seat_uuid: string}>}>  $seatAssignments
     *
     * @throws ValidationException
     */
    private function validateSeatAssignments(ItineraryQuote $quote, array $seatAssignments): void
    {
        if (count($seatAssignments) !== $quote->passenger_count) {
            throw ValidationException::withMessages([
                'passengers' => 'Le nombre de passagers ne correspond pas au devis.',
            ]);
        }

        $expectedPassengerIndexes = range(0, $quote->passenger_count - 1);
        $passengerIndexes = collect($seatAssignments)
            ->pluck('passenger_index')
            ->map(fn (mixed $index): int => (int) $index)
            ->sort()
            ->values()
            ->all();

        if ($passengerIndexes !== $expectedPassengerIndexes) {
            throw ValidationException::withMessages([
                'passengers' => 'Les index des passagers doivent former une séquence complète.',
            ]);
        }

        $expectedLegOrders = $quote->legs->pluck('leg_order')->sort()->values()->all();
        foreach ($seatAssignments as $index => $assignment) {
            $legOrders = collect($assignment['seat_assignments'])
                ->pluck('leg_order')
                ->map(fn (mixed $order): int => (int) $order)
                ->sort()
                ->values()
                ->all();

            if ($legOrders !== $expectedLegOrders) {
                throw ValidationException::withMessages([
                    "passengers.{$index}.seat_assignments" => 'Chaque passager doit avoir un siège sur chaque jambe.',
                ]);
            }
        }
    }

    private function loadReservation(Reservation $reservation): Reservation
    {
        return $reservation->load([
            'itineraryQuote',
            'segments.departure',
            'segments.seat',
            'segments.originStop',
            'segments.destinationStop',
            'passengers',
        ]);
    }
}
