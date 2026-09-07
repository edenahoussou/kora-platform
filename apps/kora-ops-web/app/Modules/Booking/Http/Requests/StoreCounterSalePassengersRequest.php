<?php

namespace App\Modules\Booking\Http\Requests;

use App\Models\User;
use App\Modules\Booking\Models\Reservation;

class StoreCounterSalePassengersRequest extends StorePassengersRequest
{
    public function authorize(): bool
    {
        $reservation = $this->route('reservation');
        $user = $this->user();

        return $reservation instanceof Reservation
            && $user instanceof User
            && $reservation->created_by_user_id === $user->getKey()
            && $user->can('createCounterSale', Reservation::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'information_confirmed' => ['required', 'accepted'],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function saleQuery(string $reservationUuid): array
    {
        return array_filter([
            'date' => $this->string('date')->value() ?: null,
            'from' => $this->string('from')->trim()->value() ?: null,
            'to' => $this->string('to')->trim()->value() ?: null,
            'departure_uuid' => $this->string('departure_uuid')->value() ?: null,
            'origin_stop_uuid' => $this->string('origin_stop_uuid')->value() ?: null,
            'destination_stop_uuid' => $this->string('destination_stop_uuid')->value() ?: null,
            'reservation_uuid' => $reservationUuid,
        ], static fn (?string $value): bool => $value !== null && $value !== '');
    }
}
