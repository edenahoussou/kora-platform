<?php

namespace App\Modules\Booking\Actions;

use App\Models\User;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class SaveCounterSaleDraftAction
{
    /**
     * @param  list<array<string, mixed>>  $passengersData
     */
    public function handle(Reservation $reservation, User $user, array $passengersData): Reservation
    {
        if ($reservation->created_by_user_id !== $user->getKey()) {
            throw new AuthorizationException('Vous n\'êtes pas autorisé à modifier cette réservation.');
        }

        if ($reservation->channel !== ReservationChannel::Counter) {
            throw ValidationException::withMessages([
                'reservation' => 'Cette réservation ne provient pas du guichet.',
            ]);
        }

        if ($reservation->status !== ReservationStatus::Held) {
            throw ValidationException::withMessages([
                'reservation' => 'Cette réservation n’est plus active.',
            ]);
        }

        if ($reservation->expires_at === null || $reservation->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'reservation' => 'La réservation a expiré. Sélectionnez à nouveau les places.',
            ]);
        }

        $reservation->loadMissing('segments.seat');
        $segments = $reservation->segments;
        $passengerIndexes = $segments->pluck('passenger_index')->filter(fn (mixed $index): bool => $index !== null)->unique()->sort()->values();
        $expectedPassengerCount = $passengerIndexes->isNotEmpty() ? $passengerIndexes->count() : $segments->count();

        if (count($passengersData) !== $expectedPassengerCount) {
            throw ValidationException::withMessages([
                'passengers' => 'Le nombre de fiches doit correspondre au nombre de voyageurs bloqués.',
            ]);
        }

        $draftPassengers = [];
        foreach ($passengersData as $index => $passenger) {
            $passengerIndex = (int) (isset($passenger['passenger_index']) ? $passenger['passenger_index'] : $index);

            if ($passengerIndexes->isNotEmpty() && ! $passengerIndexes->contains($passengerIndex)) {
                throw ValidationException::withMessages([
                    "passengers.{$index}.passenger_index" => 'Le voyageur ne correspond pas à cette réservation.',
                ]);
            }

            if (array_key_exists((string) $passengerIndex, $draftPassengers)) {
                throw ValidationException::withMessages([
                    "passengers.{$index}.passenger_index" => 'Un seul brouillon est autorisé par voyageur.',
                ]);
            }

            $draft = [
                'passenger_index' => $passengerIndex,
                'first_name' => $this->nullableValue($passenger['first_name'] ?? null),
                'last_name' => $this->nullableValue($passenger['last_name'] ?? null),
                'phone' => $this->nullableValue($passenger['phone'] ?? null),
                'email' => $this->nullableValue($passenger['email'] ?? null),
                'document_type' => $this->nullableValue($passenger['document_type'] ?? null),
                'document_number' => $this->nullableValue($passenger['document_number'] ?? null),
                'nationality' => $this->nullableValue($passenger['nationality'] ?? null),
                'birth_date' => $this->nullableValue($passenger['birth_date'] ?? null),
                'yellow_fever_vaccinated' => $passenger['yellow_fever_vaccinated'] ?? null,
            ];
            $draftPassengers[(string) $passengerIndex] = $draft;
            if (! empty($passenger['seat_uuid'])) {
                $draftPassengers[(string) $passenger['seat_uuid']] = $draft + ['seat_uuid' => $passenger['seat_uuid']];
            }
        }

        $reservation->update([
            'draft_data' => ['passengers' => $draftPassengers],
        ]);

        return $reservation->fresh([
            'segments.departure',
            'segments.seat',
            'segments.originStop',
            'segments.destinationStop',
            'passengers.segments.seat',
        ]);
    }

    private function nullableValue(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value !== '' ? $value : null;
    }
}
