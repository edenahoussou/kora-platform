<?php

namespace App\Modules\Booking\Actions;

use App\Models\User;
use App\Modules\Booking\Data\PassengerDocumentType;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\IdentityAccess\Contracts\PassengerIdentityResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateReservationPassengersAction
{
    public function __construct(private readonly PassengerIdentityResolver $identityResolver) {}

    /**
     * @param  list<array<string, mixed>>  $passengersData
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(Reservation $reservation, ?User $user, array $passengersData): Reservation
    {
        if ($user && $reservation->created_by_user_id && $reservation->created_by_user_id !== $user->id) {
            throw new AuthorizationException('Vous n\'êtes pas autorisé à modifier cette réservation.');
        }

        if ($reservation->status !== ReservationStatus::Held && $reservation->status !== ReservationStatus::Confirmed) {
            throw ValidationException::withMessages([
                'reservation' => 'La réservation n\'est plus dans un état modifiable.',
            ]);
        }

        if ($reservation->expires_at && $reservation->expires_at->isPast()) {
            $reservation->update(['draft_data' => null]);

            throw ValidationException::withMessages([
                'reservation' => 'La réservation a expiré.',
            ]);
        }

        $reservation->loadMissing(['segments.departure.line', 'segments.seat']);

        $segments = $reservation->segments;
        $itineraryPassengerIndexes = $segments
            ->pluck('passenger_index')
            ->filter(fn (mixed $index): bool => $index !== null)
            ->unique()
            ->sort()
            ->values()
            ->all();
        $isMultiSegmentMatrix = $itineraryPassengerIndexes !== [];
        $expectedPassengerCount = $isMultiSegmentMatrix
            ? count($itineraryPassengerIndexes)
            : $segments->count();

        if (count($passengersData) !== $expectedPassengerCount) {
            throw ValidationException::withMessages([
                'passengers' => 'Le nombre de passagers ('.count($passengersData).') ne correspond pas au nombre de voyageurs réservés ('.$expectedPassengerCount.').',
            ]);
        }

        if ($isMultiSegmentMatrix && $itineraryPassengerIndexes !== range(0, $expectedPassengerCount - 1)) {
            throw ValidationException::withMessages([
                'reservation' => 'La matrice passager/jambe de la réservation est incomplète.',
            ]);
        }

        if ($isMultiSegmentMatrix) {
            $submittedIndexes = collect($passengersData)
                ->map(fn (array $passenger, int $index): int => (int) ($passenger['passenger_index'] ?? $index))
                ->sort()
                ->values()
                ->all();

            if ($submittedIndexes !== range(0, $expectedPassengerCount - 1)) {
                throw ValidationException::withMessages([
                    'passengers' => 'Les index des passagers doivent former une séquence complète.',
                ]);
            }
        }

        $normalizedPhones = collect($passengersData)
            ->map(fn (array $passenger): ?string => $this->identityResolver->normalizePhone($passenger['phone'] ?? null))
            ->filter();
        if ($normalizedPhones->unique()->count() !== $normalizedPhones->count()) {
            throw ValidationException::withMessages([
                'passengers' => 'Un même numéro personnel ne peut identifier qu’un seul voyageur du groupe.',
            ]);
        }

        return DB::transaction(function () use ($reservation, $segments, $passengersData, $isMultiSegmentMatrix): Reservation {
            // Unlink existing segments
            $reservation->segments()->update(['reservation_passenger_id' => null]);
            $reservation->passengers()->delete();
            $reservation->update(['draft_data' => null]);

            $segmentsBySeatUuid = $segments->keyBy(fn (ReservationSegment $s) => $s->seat->uuid ?? '');
            $unassignedSegments = $segments->values();

            foreach ($passengersData as $index => $pData) {
                $normalizedPhone = $this->identityResolver->normalizePhone($pData['phone'] ?? null);
                $docType = ! empty($pData['document_type'])
                    ? PassengerDocumentType::tryFrom($pData['document_type'])
                    : null;

                $passenger = ReservationPassenger::create([
                    'reservation_id' => $reservation->id,
                    'passenger_index' => (int) ($pData['passenger_index'] ?? $index),
                    'user_id' => $normalizedPhone === null
                        ? null
                        : $this->identityResolver->resolveOrCreateByPhone(
                            $normalizedPhone,
                            trim($pData['first_name'].' '.$pData['last_name']),
                        )->getKey(),
                    'first_name' => $pData['first_name'],
                    'last_name' => $pData['last_name'],
                    'phone' => $normalizedPhone,
                    'email' => $pData['email'] ?? null,
                    'document_type' => $docType,
                    'document_number' => $pData['document_number'] ?? null,
                    'nationality' => ! empty($pData['nationality']) ? strtoupper($pData['nationality']) : null,
                    'birth_date' => $pData['birth_date'] ?? null,
                    'yellow_fever_vaccinated' => $pData['yellow_fever_vaccinated'] ?? null,
                ]);

                if ($isMultiSegmentMatrix) {
                    $passengerIndex = $pData['passenger_index'] ?? $index;
                    $segments
                        ->where('passenger_index', $passengerIndex)
                        ->each(fn (ReservationSegment $segment): bool => (bool) $segment->update(['reservation_passenger_id' => $passenger->id]));
                } else {
                    // Match with seat_uuid if specified, otherwise by index.
                    $matchedSegment = null;
                    if (! empty($pData['seat_uuid']) && $segmentsBySeatUuid->has($pData['seat_uuid'])) {
                        $matchedSegment = $segmentsBySeatUuid->get($pData['seat_uuid']);
                    } elseif (isset($unassignedSegments[$index])) {
                        $matchedSegment = $unassignedSegments[$index];
                    }

                    if ($matchedSegment) {
                        $matchedSegment->update(['reservation_passenger_id' => $passenger->id]);
                    }
                }
            }

            return $reservation->load([
                'segments.departure',
                'segments.seat',
                'segments.originStop',
                'segments.destinationStop',
                'passengers.segments.seat',
                'passengers.segments.departure',
            ]);
        });
    }
}
