<?php

namespace App\Modules\Booking\Actions;

use App\Models\User;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Exceptions\SeatUnavailableException;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Data\SeatStatus;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Actions\ResolveDepartureSellabilityAction;
use App\Modules\Departures\Models\Departure;
use App\Modules\SeatInventory\Actions\FindOccupiedSeatIdsAction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use function Illuminate\Support\enum_value;

/**
 * Leg data for a multi-departure hold request.
 */
final class ItineraryLegRequest
{
    public function __construct(
        public readonly Departure $departure,
        public readonly Stop $originStop,
        public readonly Stop $destinationStop,
        public readonly string $seatUuid,
    ) {}
}

/**
 * Creates an atomic "all-or-nothing" seat hold across multiple departures
 * (multi-line itinerary with connections).
 *
 * All legs are blocked in a single DB transaction. Departures are locked
 * in deterministic order (by departure_id) to prevent deadlocks between
 * concurrent holds that share legs in inverse order.
 *
 * If any single leg fails (seat unavailable, departure closed), the entire
 * transaction is rolled back — no partial hold is left behind.
 */
class CreateItineraryHoldAction
{
    public function __construct(
        private FindOccupiedSeatIdsAction $findOccupiedSeatIds,
        private ResolveDepartureSellabilityAction $resolveSellability,
    ) {}

    /**
     * @param  list<ItineraryLegRequest>  $legs
     * @param  array<string, mixed>|null  $totalSnapshot
     *
     * @throws ValidationException
     * @throws SeatUnavailableException
     */
    public function handle(
        ?User $user,
        array $legs,
        int $totalAmount,
        string $currency,
        ReservationChannel $channel = ReservationChannel::Mobile,
        ?array $totalSnapshot = null,
    ): Reservation {
        if ($legs === []) {
            throw ValidationException::withMessages([
                'legs' => 'Au moins une jambe est requise.',
            ]);
        }

        // Pre-validate all legs before entering the transaction
        $this->validateLegs($legs);

        try {
            return DB::transaction(function () use ($user, $legs, $totalAmount, $currency, $channel, $totalSnapshot): Reservation {
                // Lock all departures in deterministic order (by id) to avoid deadlocks
                $departureIds = collect($legs)
                    ->map(fn (ItineraryLegRequest $leg): int => $leg->departure->id)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                $lockedDepartures = Departure::query()
                    ->whereIn('id', $departureIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                // Verify all departures are still sellable after locking
                foreach ($legs as $leg) {
                    $lockedDeparture = $lockedDepartures->get($leg->departure->id);
                    if (! $lockedDeparture) {
                        throw ValidationException::withMessages([
                            'legs' => 'Un des départs n\'existe plus.',
                        ]);
                    }

                    $sellability = $this->resolveSellability->handle($lockedDeparture);
                    if (! $sellability->isSellable) {
                        throw ValidationException::withMessages([
                            'legs' => "La vente est fermée pour le départ {$leg->departure->uuid}.",
                        ]);
                    }
                }

                $reservation = Reservation::create([
                    'user_id' => $user?->id,
                    'status' => ReservationStatus::Held,
                    'total_amount' => $totalAmount,
                    'currency' => $currency,
                    'channel' => $channel,
                    'expires_at' => now()->addMinutes(15),
                    'total_snapshot' => $totalSnapshot,
                ]);

                foreach ($legs as $index => $leg) {
                    $lockedDeparture = $lockedDepartures->get($leg->departure->id);

                    /** @var Seat|null $seat */
                    $seat = Seat::query()
                        ->where('bus_id', $lockedDeparture->bus_id)
                        ->where('uuid', $leg->seatUuid)
                        ->lockForUpdate()
                        ->first();

                    if (! $seat) {
                        throw ValidationException::withMessages([
                            "legs.{$index}.seat_uuid" => 'Le siège demandé n\'existe pas sur ce véhicule.',
                        ]);
                    }

                    if (! $seat->sellable || enum_value($seat->status) !== SeatStatus::Active->value) {
                        throw new SeatUnavailableException(
                            message: "Le siège {$seat->code} n'est pas disponible à la vente.",
                            conflictingSeatUuid: $seat->uuid,
                            seatCode: $seat->code,
                        );
                    }

                    $occupiedSeatIds = $this->findOccupiedSeatIds->handle($lockedDeparture, $leg->originStop, $leg->destinationStop);
                    if ($occupiedSeatIds->contains($seat->id)) {
                        throw new SeatUnavailableException(
                            message: "Le siège {$seat->code} n'est plus disponible sur ce segment.",
                            conflictingSeatUuid: $seat->uuid,
                            seatCode: $seat->code,
                        );
                    }

                    ReservationSegment::create([
                        'reservation_id' => $reservation->id,
                        'leg_order' => $index + 1,
                        'departure_id' => $lockedDeparture->id,
                        'seat_id' => $seat->id,
                        'origin_stop_id' => $leg->originStop->id,
                        'destination_stop_id' => $leg->destinationStop->id,
                        'origin_stop_order' => $leg->originStop->stop_order,
                        'destination_stop_order' => $leg->destinationStop->stop_order,
                        'status' => ReservationSegmentStatus::Held,
                    ]);
                }

                return $reservation->load([
                    'segments.departure',
                    'segments.seat',
                    'segments.originStop',
                    'segments.destinationStop',
                    'passengers',
                ]);
            });
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'reservation_segments_no_overlap') || $e->getCode() === '23P01') {
                throw new SeatUnavailableException(
                    message: 'Un des sièges sélectionnés vient d\'être réservé par un autre utilisateur.',
                    previous: $e,
                );
            }

            throw $e;
        }
    }

    /**
     * @param  list<ItineraryLegRequest>  $legs
     *
     * @throws ValidationException
     */
    private function validateLegs(array $legs): void
    {
        foreach ($legs as $index => $leg) {
            if ($leg->originStop->line_id !== $leg->departure->line_id) {
                throw ValidationException::withMessages([
                    "legs.{$index}.origin_stop_uuid" => 'L\'arrêt d\'origine n\'appartient pas à la ligne de ce départ.',
                ]);
            }

            if ($leg->destinationStop->line_id !== $leg->departure->line_id) {
                throw ValidationException::withMessages([
                    "legs.{$index}.destination_stop_uuid" => 'L\'arrêt de destination n\'appartient pas à la ligne de ce départ.',
                ]);
            }

            if ($leg->originStop->stop_order >= $leg->destinationStop->stop_order) {
                throw ValidationException::withMessages([
                    "legs.{$index}.destination_stop_uuid" => 'L\'arrêt de destination doit être situé après l\'arrêt d\'origine.',
                ]);
            }
        }

        // Verify connection continuity: destination physical stop of leg N
        // must match origin physical stop of leg N+1
        for ($i = 0; $i < count($legs) - 1; $i++) {
            $currentDestination = $legs[$i]->destinationStop;
            $nextOrigin = $legs[$i + 1]->originStop;

            if ($currentDestination->physical_stop_id !== $nextOrigin->physical_stop_id) {
                $nextLegOrder = $i + 2;
                throw ValidationException::withMessages([
                    "legs.{$nextLegOrder}.origin_stop_uuid" => 'L\'arrêt d\'origine de cette jambe ne correspond pas à l\'arrêt de destination de la jambe précédente.',
                ]);
            }
        }
    }
}
