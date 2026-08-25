<?php

namespace App\Modules\Booking\Actions;

use App\Models\User;
use App\Modules\Booking\Data\PassengerDocumentType;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\Booking\Models\ReservationSegment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateReservationPassengersAction
{
    /**
     * @param  list<array{
     *     first_name: string,
     *     last_name: string,
     *     phone?: string|null,
     *     email?: string|null,
     *     document_type?: string|null,
     *     document_number?: string|null,
     *     nationality?: string|null,
     *     birth_date?: string|null,
     *     seat_uuid?: string|null
     * }>  $passengersData
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(Reservation $reservation, ?User $user, array $passengersData): Reservation
    {
        return DB::transaction(function () use ($reservation, $user, $passengersData): Reservation {
            $lockedReservation = Reservation::query()
                ->whereKey($reservation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($user && $lockedReservation->user_id && $lockedReservation->user_id !== $user->id) {
                throw new AuthorizationException('Vous n\'êtes pas autorisé à modifier cette réservation.');
            }

            if ($lockedReservation->status !== ReservationStatus::Held && $lockedReservation->status !== ReservationStatus::Confirmed) {
                throw ValidationException::withMessages([
                    'reservation' => 'La réservation n\'est plus dans un état modifiable.',
                ]);
            }

            if ($lockedReservation->expires_at && $lockedReservation->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'reservation' => 'La réservation a expiré.',
                ]);
            }

            $lockedReservation->load(['departure.line', 'segments.seat']);

            /** @var Collection<int, ReservationSegment> $segments */
            $segments = $lockedReservation->segments;
            if (count($passengersData) !== $segments->count()) {
                throw ValidationException::withMessages([
                    'passengers' => 'Le nombre de passagers ('.count($passengersData).') ne correspond pas au nombre de sièges réservés ('.$segments->count().').',
                ]);
            }

            $countriesCrossed = $lockedReservation->departure->line?->countries_crossed;
            $isInternational = is_array($countriesCrossed) && count($countriesCrossed) > 1;

            if ($isInternational) {
                foreach ($passengersData as $index => $passengerData) {
                    if (empty($passengerData['document_type']) || empty($passengerData['document_number']) || empty($passengerData['nationality'])) {
                        throw ValidationException::withMessages([
                            "passengers.{$index}.document_number" => 'Le type de document, numéro et nationalité sont obligatoires pour les trajets internationaux.',
                        ]);
                    }
                }
            }

            $segmentsBySeatUuid = $segments->keyBy(
                fn (ReservationSegment $segment): string => (string) $segment->seat?->uuid,
            );
            $assignedSegmentIds = [];
            $passengerSegments = [];

            foreach ($passengersData as $index => $passengerData) {
                $seatUuid = $passengerData['seat_uuid'] ?? null;
                $matchedSegment = $seatUuid !== null && $seatUuid !== ''
                    ? $segmentsBySeatUuid->get($seatUuid)
                    : $segments->first(
                        fn (ReservationSegment $segment): bool => ! in_array($segment->id, $assignedSegmentIds, true),
                    );

                if (! $matchedSegment instanceof ReservationSegment) {
                    throw ValidationException::withMessages([
                        "passengers.{$index}.seat_uuid" => 'Le siège indiqué ne fait pas partie de cette réservation.',
                    ]);
                }

                if (in_array($matchedSegment->id, $assignedSegmentIds, true)) {
                    throw ValidationException::withMessages([
                        "passengers.{$index}.seat_uuid" => 'Chaque siège de la réservation ne peut être associé qu\'à un seul passager.',
                    ]);
                }

                $assignedSegmentIds[] = $matchedSegment->id;
                $passengerSegments[$index] = $matchedSegment;
            }

            $lockedReservation->segments()->update(['reservation_passenger_id' => null]);
            $lockedReservation->passengers()->delete();

            foreach ($passengersData as $index => $passengerData) {
                $documentType = ! empty($passengerData['document_type'])
                    ? PassengerDocumentType::tryFrom($passengerData['document_type'])
                    : null;

                $passenger = ReservationPassenger::create([
                    'reservation_id' => $lockedReservation->id,
                    'first_name' => $passengerData['first_name'],
                    'last_name' => $passengerData['last_name'],
                    'phone' => $passengerData['phone'] ?? null,
                    'email' => $passengerData['email'] ?? null,
                    'document_type' => $documentType,
                    'document_number' => $passengerData['document_number'] ?? null,
                    'nationality' => ! empty($passengerData['nationality']) ? strtoupper($passengerData['nationality']) : null,
                    'birth_date' => $passengerData['birth_date'] ?? null,
                ]);

                $passengerSegments[$index]->update(['reservation_passenger_id' => $passenger->id]);
            }

            return $lockedReservation->load([
                'departure.line',
                'priceQuote',
                'segments.seat',
                'segments.originStop',
                'segments.destinationStop',
                'passengers.segments.seat',
            ]);
        });
    }
}
