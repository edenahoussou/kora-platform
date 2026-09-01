<?php

namespace App\Modules\Booking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ItineraryHoldRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'total_amount' => ['required', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'snapshot' => ['sometimes', 'array'],
            'legs' => ['required', 'array', 'min:1'],
            'legs.*.departure_uuid' => ['required', 'uuid', 'exists:departures,uuid'],
            'legs.*.origin_stop_uuid' => ['required', 'uuid', 'exists:stops,uuid'],
            'legs.*.destination_stop_uuid' => ['required', 'uuid', 'exists:stops,uuid'],
            'legs.*.seat_uuid' => ['required', 'uuid', 'exists:seats,uuid'],
        ];
    }
}
