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
            'date' => ['required', 'date_format:Y-m-d'],
            'from' => ['nullable', 'string', 'max:120'],
            'to' => ['nullable', 'string', 'max:120'],
            'departure_uuid' => ['required', 'uuid', 'exists:departures,uuid'],
            'origin_stop_uuid' => ['required', 'uuid', 'exists:stops,uuid'],
            'destination_stop_uuid' => ['required', 'uuid', 'exists:stops,uuid'],
            'quote_uuid' => ['nullable', 'uuid', 'exists:price_quotes,uuid'],
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
            'departure_uuid' => $this->string('departure_uuid')->value(),
            'origin_stop_uuid' => $this->string('origin_stop_uuid')->value(),
            'destination_stop_uuid' => $this->string('destination_stop_uuid')->value(),
            'quote_uuid' => $this->string('quote_uuid')->value() ?: null,
        ], static fn (?string $value): bool => $value !== null && $value !== '');
    }
}
