<?php

namespace App\Modules\Booking\Actions;

use App\Models\User;
use App\Modules\Booking\Data\PassengerDocumentType;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\Booking\Models\ReservationSegment;
use Illuminate\Auth\Access\AuthorizationException;
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
        if ($user && $reservation->user_id && $reservation->user_id !== $user->id) {
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
        if (count($passengersData) !== $segments->count()) {
            throw ValidationException::withMessages([
                'passengers' => 'Le nombre de passagers ('.count($passengersData).') ne correspond pas au nombre de sièges réservés ('.$segments->count().').',
            ]);
        }

        $isInternational = false;
        $countriesCrossed = $reservation->primaryDeparture()?->line?->countries_crossed;
        if (is_array($countriesCrossed) && count($countriesCrossed) > 1) {
            $isInternational = true;
        }

        if ($isInternational) {
            foreach ($passengersData as $index => $p) {
                if (empty($p['document_type']) || empty($p['document_number']) || empty($p['nationality'])) {
                    throw ValidationException::withMessages([
                        "passengers.{$index}.document_number" => 'Le type de document, numéro et nationalité sont obligatoires pour les trajets internationaux.',
                    ]);
                }
            }
        }

        return DB::transaction(function () use ($reservation, $segments, $passengersData): Reservation {
            // Unlink existing segments
            $reservation->segments()->update(['reservation_passenger_id' => null]);
            $reservation->passengers()->delete();
            $reservation->update(['draft_data' => null]);

            $segmentsBySeatUuid = $segments->keyBy(fn (ReservationSegment $s) => $s->seat->uuid ?? '');
            $unassignedSegments = $segments->values();

            foreach ($passengersData as $index => $pData) {
                $docType = ! empty($pData['document_type'])
                    ? PassengerDocumentType::tryFrom($pData['document_type'])
                    : null;

                $passenger = ReservationPassenger::create([
                    'reservation_id' => $reservation->id,
                    'first_name' => $pData['first_name'],
                    'last_name' => $pData['last_name'],
                    'phone' => $pData['phone'] ?? null,
                    'email' => $pData['email'] ?? null,
                    'document_type' => $docType,
                    'document_number' => $pData['document_number'] ?? null,
                    'nationality' => ! empty($pData['nationality']) ? strtoupper($pData['nationality']) : null,
                    'birth_date' => $pData['birth_date'] ?? null,
                ]);

                // Match with seat_uuid if specified, otherwise by index
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

            return $reservation->load([
                'segments.departure',
                'segments.seat',
                'segments.originStop',
                'segments.destinationStop',
                'passengers.segments.seat',
            ]);
        });
    }
}
