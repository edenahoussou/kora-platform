<?php

namespace App\Modules\Booking\Http\Requests;

use App\Models\User;
use App\Modules\Booking\Models\Reservation;

class StoreCounterSaleDraftRequest extends StorePassengersRequest
{
    public function authorize(): bool
    {
        $reservation = $this->route('reservation');
        $user = $this->user();

        return $reservation instanceof Reservation
            && $user instanceof User
            && $reservation->user_id === $user->getKey()
            && $user->can('createCounterSale', Reservation::class);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'passengers' => ['required', 'array', 'min:1'],
            'passengers.*.first_name' => ['nullable', 'string', 'max:120'],
            'passengers.*.last_name' => ['nullable', 'string', 'max:120'],
            'passengers.*.phone' => ['nullable', 'string', 'max:32'],
            'passengers.*.email' => ['nullable', 'email', 'max:255'],
            'passengers.*.document_type' => ['nullable', 'string', 'in:national_id,biometric_card,passport,consular_card,cip'],
            'passengers.*.document_number' => ['nullable', 'string', 'max:64'],
            'passengers.*.nationality' => ['nullable', 'string', 'size:2'],
            'passengers.*.birth_date' => ['nullable', 'date_format:Y-m-d'],
            'passengers.*.seat_uuid' => ['required', 'uuid'],
        ];
    }
}
