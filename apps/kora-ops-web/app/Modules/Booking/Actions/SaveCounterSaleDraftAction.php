<?php

namespace App\Modules\Booking\Actions;

use App\Models\User;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationSegment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class SaveCounterSaleDraftAction
{
    /**
     * @param  list<array{
     *     first_name?: string|null,
     *     last_name?: string|null,
     *     phone?: string|null,
     *     email?: string|null,
     *     document_type?: string|null,
     *     document_number?: string|null,
     *     nationality?: string|null,
     *     birth_date?: string|null,
     *     seat_uuid?: string|null
     * }>  $passengersData
     */
    public function handle(Reservation $reservation, User $user, array $passengersData): Reservation
    {
        if ($reservation->user_id !== $user->getKey()) {
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
        $segmentSeatUuids = $segments
            ->map(fn (ReservationSegment $segment): ?string => $segment->seat?->uuid)
            ->filter()
            ->values();

        if (count($passengersData) !== $segments->count()) {
            throw ValidationException::withMessages([
                'passengers' => 'Le nombre de passagers doit correspondre au nombre de sièges bloqués.',
            ]);
        }

        $draftPassengers = [];
        foreach ($passengersData as $index => $passenger) {
            $seatUuid = (string) ($passenger['seat_uuid'] ?? '');

            if ($seatUuid === '' || ! $segmentSeatUuids->contains($seatUuid)) {
                throw ValidationException::withMessages([
                    "passengers.{$index}.seat_uuid" => 'Le siège ne correspond pas à cette réservation.',
                ]);
            }

            if (array_key_exists($seatUuid, $draftPassengers)) {
                throw ValidationException::withMessages([
                    "passengers.{$index}.seat_uuid" => 'Un seul brouillon est autorisé par siège.',
                ]);
            }

            $draftPassengers[$seatUuid] = [
                'seat_uuid' => $seatUuid,
                'first_name' => $this->nullableValue($passenger['first_name'] ?? null),
                'last_name' => $this->nullableValue($passenger['last_name'] ?? null),
                'phone' => $this->nullableValue($passenger['phone'] ?? null),
                'email' => $this->nullableValue($passenger['email'] ?? null),
                'document_type' => $this->nullableValue($passenger['document_type'] ?? null),
                'document_number' => $this->nullableValue($passenger['document_number'] ?? null),
                'nationality' => $this->nullableValue($passenger['nationality'] ?? null),
                'birth_date' => $this->nullableValue($passenger['birth_date'] ?? null),
            ];
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
