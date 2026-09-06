<?php

namespace App\Modules\Booking\Http\Requests;

use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;

class ReleaseCounterSaleHoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $reservation = $this->route('reservation');

        return $user instanceof User
            && $user->can('createCounterSale', Reservation::class)
            && $reservation instanceof Reservation
            && $reservation->created_by_user_id === $user->getKey();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'from' => ['nullable', 'string', 'max:120'],
            'to' => ['nullable', 'string', 'max:120'],
            'itinerary_quote_uuid' => ['nullable', 'uuid', 'exists:itinerary_quotes,uuid'],
            'reservation_uuid' => ['nullable', 'uuid', 'exists:reservations,uuid'],
            'clear_quote' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function saleQuery(): array
    {
        return array_filter([
            'date' => $this->string('date')->value(),
            'from' => $this->string('from')->trim()->value() ?: null,
            'to' => $this->string('to')->trim()->value() ?: null,
            'itinerary_quote_uuid' => $this->string('itinerary_quote_uuid')->value() ?: null,
        ], static fn (?string $value): bool => $value !== null && $value !== '');
    }
}
