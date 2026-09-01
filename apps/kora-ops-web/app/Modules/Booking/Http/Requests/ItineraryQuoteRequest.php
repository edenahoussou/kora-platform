<?php

namespace App\Modules\Booking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ItineraryQuoteRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'passenger_count' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'legs' => ['required', 'array', 'min:1'],
            'legs.*.departure_uuid' => ['required', 'uuid', 'exists:departures,uuid'],
            'legs.*.origin_stop_uuid' => ['required', 'uuid', 'exists:stops,uuid'],
            'legs.*.destination_stop_uuid' => ['required', 'uuid', 'exists:stops,uuid'],
        ];
    }
}
