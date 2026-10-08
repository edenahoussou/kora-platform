<?php

namespace App\Modules\Booking\Actions;

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
use App\Modules\Settings\Settings\OperationsSettings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use function Illuminate\Support\enum_value;

/**
 * Single inventory-acquisition seam shared by new and assisted reservations.
 */
class ApplyItinerarySeatHoldAction
{
    public function __construct(
        private FindOccupiedSeatIdsAction $findOccupiedSeatIds,
        private ResolveDepartureSellabilityAction $resolveSellability,
        private DepartureStopTimeResolver $stopTimeResolver,
        private OperationsSettings $operationsSettings,
    ) {}

    /**
     * @param  list<array{passenger_index: int, seat_assignments: list<array{leg_order: int, seat_uuid: string}>}>  $seatAssignments
     */
    public function handle(
        Reservation $reservation,
        ItineraryQuote $itineraryQuote,
        array $seatAssignments,
        ReservationChannel $channel,
        bool $replaceRequestedSegments = false,
    ): Reservation {
        try {
            return DB::transaction(function () use ($reservation, $itineraryQuote, $seatAssignments, $channel, $replaceRequestedSegments): Reservation {
                $lockedReservation = Reservation::query()->whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();
                $quote = ItineraryQuote::query()->whereKey($itineraryQuote->getKey())->lockForUpdate()->firstOrFail();

                if ($quote->expires_at->lte(now())) {
                    throw ValidationException::withMessages(['itinerary_quote_uuid' => 'Le devis d’itinéraire a expiré.']);
                }

                $quote->load(['legs.departure', 'legs.originStop.physicalStop', 'legs.destinationStop.physicalStop']);
                $this->validateSeatAssignments($quote, $seatAssignments);

                /** @var list<ItineraryQuoteLeg> $quoteLegs */
                $quoteLegs = $quote->legs->values()->all();
                $departureIds = collect($quoteLegs)->pluck('departure_id')->unique()->sort()->values()->all();
                $lockedDepartures = Departure::query()
                    ->whereIn('id', $departureIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($lockedDepartures->count() !== count($departureIds)) {
                    throw ValidationException::withMessages(['itinerary_quote_uuid' => 'Un des départs du devis n’existe plus.']);
                }

                foreach ($quoteLegs as $index => $leg) {
                    $departure = $lockedDepartures->get($leg->departure_id);
                    if (! $this->resolveSellability->handle($departure)->isSellable) {
                        throw ValidationException::withMessages(["legs.{$leg->leg_order}.departure_uuid" => 'La vente est fermée pour ce départ.']);
                    }

                    $snapshotLeg = $quote->snapshot['legs'][$index] ?? null;
                    $originAt = $this->stopTimeResolver->departureAt($departure, $leg->originStop);
                    $destinationAt = $this->stopTimeResolver->arrivalAt($departure, $leg->destinationStop);
                    if ($snapshotLeg === null
                        || $originAt?->toIso8601String() !== ($snapshotLeg['departure_at'] ?? null)
                        || $destinationAt?->toIso8601String() !== ($snapshotLeg['arrival_at'] ?? null)
                        || $leg->originStop->physicalStop?->uuid !== ($snapshotLeg['origin_physical_stop_uuid'] ?? null)
                        || $leg->destinationStop->physicalStop?->uuid !== ($snapshotLeg['destination_physical_stop_uuid'] ?? null)
                        || $leg->originStop->line_id !== $departure->line_id
                        || $leg->destinationStop->line_id !== $departure->line_id) {
                        throw ValidationException::withMessages(['itinerary_quote_uuid' => 'Le planning de l’itinéraire a changé. Demandez un nouveau devis.']);
                    }
                }

                $seatUuids = collect($seatAssignments)
                    ->flatMap(fn (array $assignment): array => array_column($assignment['seat_assignments'], 'seat_uuid'))
                    ->unique()
                    ->values()
                    ->all();
                $seats = Seat::query()->whereIn('uuid', $seatUuids)->orderBy('id')->lockForUpdate()->get()->keyBy('uuid');
                if ($seats->count() !== count($seatUuids)) {
                    throw ValidationException::withMessages(['passengers' => 'Un ou plusieurs sièges n’existent pas.']);
                }

                $passengerIds = $lockedReservation->passengers()->pluck('id', 'passenger_index');
                $occupiedSeatIds = [];
                $usedSeats = [];
                $segments = [];
                foreach ($seatAssignments as $assignment) {
                    $legsByOrder = collect($assignment['seat_assignments'])->keyBy('leg_order');
                    foreach ($quoteLegs as $quoteLeg) {
                        $selectedSeatUuid = $legsByOrder->get($quoteLeg->leg_order)['seat_uuid'];
                        /** @var Seat $seat */
                        $seat = $seats->get($selectedSeatUuid);
                        $departure = $lockedDepartures->get($quoteLeg->departure_id);
                        $seatKey = $departure->id.':'.$seat->id;

                        if (isset($usedSeats[$seatKey])) {
                            throw ValidationException::withMessages(['passengers' => 'Un siège ne peut être attribué à deux passagers sur le même départ.']);
                        }
                        $usedSeats[$seatKey] = true;

                        if ($seat->bus_id !== $departure->bus_id) {
                            throw ValidationException::withMessages(['passengers' => 'Un siège sélectionné n’appartient pas au bus du départ.']);
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

                        $segments[] = [
                            'reservation_id' => $lockedReservation->id,
                            'reservation_passenger_id' => $passengerIds->get($assignment['passenger_index']),
                            'leg_order' => $quoteLeg->leg_order,
                            'direction' => $quoteLeg->direction,
                            'passenger_index' => $assignment['passenger_index'],
                            'departure_id' => $departure->id,
                            'seat_id' => $seat->id,
                            'origin_stop_id' => $quoteLeg->origin_stop_id,
                            'destination_stop_id' => $quoteLeg->destination_stop_id,
                            'origin_stop_order' => $this->stopTimeResolver->circulationIndexOf($departure, $quoteLeg->originStop),
                            'destination_stop_order' => $this->stopTimeResolver->circulationIndexOf($departure, $quoteLeg->destinationStop),
                            'status' => ReservationSegmentStatus::Held,
                        ];
                    }
                }

                if ($replaceRequestedSegments) {
                    if ($lockedReservation->segments()->where('status', '!=', ReservationSegmentStatus::Requested->value)->exists()) {
                        throw ValidationException::withMessages(['reservation' => 'Les sièges de cette réservation ne peuvent plus être remplacés.']);
                    }
                    $lockedReservation->segments()->delete();
                } elseif ($lockedReservation->segments()->exists()) {
                    throw ValidationException::withMessages(['reservation' => 'Cette réservation possède déjà des sièges.']);
                }

                foreach ($segments as $segment) {
                    ReservationSegment::query()->create($segment);
                }

                $reservationExpiresAt = min(
                    now()->addMinutes($this->operationsSettings->seatHoldMinutes),
                    $quote->expires_at,
                );
                $lockedReservation->update([
                    'itinerary_quote_id' => $quote->id,
                    'promotion_id' => $quote->promotion_id,
                    'promotion_discount_amount' => (int) $quote->promotion_discount_amount,
                    'status' => ReservationStatus::Held,
                    'total_amount' => $quote->total_amount,
                    'currency' => $quote->currency,
                    'channel' => $channel,
                    'expires_at' => $reservationExpiresAt,
                    'total_snapshot' => $quote->snapshot,
                ]);

                return $lockedReservation->load([
                    'itineraryQuote', 'segments.departure', 'segments.seat',
                    'segments.originStop', 'segments.destinationStop', 'passengers',
                ]);
            });
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'reservation_segments_no_overlap') || $exception->getCode() === '23P01') {
                throw new SeatUnavailableException('Un des sièges sélectionnés vient d’être réservé par un autre utilisateur.', previous: $exception);
            }
            if (str_contains($exception->getMessage(), 'reservation_segments_valid_stop_order') || $exception->getCode() === '23514') {
                throw ValidationException::withMessages(['itinerary_quote_uuid' => 'La séquence des arrêts de ce départ est incompatible. Demandez un nouveau devis.']);
            }

            throw $exception;
        }
    }

    /**
     * @param  list<array{passenger_index: int, seat_assignments: list<array{leg_order: int, seat_uuid: string}>}>  $seatAssignments
     */
    private function validateSeatAssignments(ItineraryQuote $quote, array $seatAssignments): void
    {
        if (count($seatAssignments) !== $quote->passenger_count) {
            throw ValidationException::withMessages(['passengers' => 'Le nombre de passagers ne correspond pas au devis.']);
        }
        $expectedPassengerIndexes = range(0, $quote->passenger_count - 1);
        $passengerIndexes = collect($seatAssignments)->pluck('passenger_index')->map(fn ($index): int => (int) $index)->sort()->values()->all();
        if ($passengerIndexes !== $expectedPassengerIndexes) {
            throw ValidationException::withMessages(['passengers' => 'Les index des passagers doivent former une séquence complète.']);
        }

        $expectedLegOrders = $quote->legs->pluck('leg_order')->sort()->values()->all();
        foreach ($seatAssignments as $index => $assignment) {
            $legOrders = collect($assignment['seat_assignments'])->pluck('leg_order')->map(fn ($order): int => (int) $order)->sort()->values()->all();
            if ($legOrders !== $expectedLegOrders) {
                throw ValidationException::withMessages(["passengers.{$index}.seat_assignments" => 'Chaque passager doit avoir un siège sur chaque jambe.']);
            }
        }
    }
}
